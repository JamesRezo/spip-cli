<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CoreTelecharger extends Command {
	// Le type de version qui sera téléchargé, par défaut une release fixe (tag)
	protected $version_type = 'release';
	// La version demandée au départ, précise ou flou suivant une branche
	protected $version_demandee = '';
	// La version précise qui sera téléchargée
	protected $version_precise = '';
	// L'URL SVN où télécharger la version
	protected $url = '';
	
	protected function configure() {
		$this
			->setName('core:telecharger')
			->setDescription('Télécharger SPIP dans un dossier (par défaut, la dernière version stable)')
			->addOption(
				'branche',
				'b',
				InputOption::VALUE_OPTIONAL,
				'Donner explicitement la branche de maintenance à télécharger ou le dernier numéro majeur (X.Y ou X).'
			)
			->addOption(
				'release',
				'r',
				InputOption::VALUE_OPTIONAL,
				'Donner explicitement la release (tag) à télécharger ou la branche où prendre la dernière release (X.Y.Z ou X.Y).'
			)
			->setAliases(array(
				'dl' // abbréviation commune pour "download"
			))
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		// On travaille dans le dossier courant
		$dossier = getcwd();
		
		// On teste si on peut utiliser "passthru"
		if (!function_exists('passthru')){
			$output->writeln("<error>Votre installation de PHP doit pouvoir exécuter des commandes externes avec la fonction passthru().</error>");
		}
		else {
			// On détermine la demande de départ
			$this->version_demandee = $input->getOption('release');
			// Si pas de release prioritaire et qu'il y a une demande de branche
			if (!$this->version_demandee and $input->hasParameterOption(array('--branche', '-b'))) {
				$this->version_type = 'branche';
				$this->version_demandee = $input->getOption('branche');
			}
			
			// Si on est déjà dans un SPIP, on cherche à faire une mise à jour
			if ($GLOBALS['spip_loaded']) {
				// On se déplace dans la racine
				chdir($GLOBALS['spip_racine']);
				
				// On lance la recherche de mise à jour
				$this->mettre_a_jour($input, $output);
			}
			// Sinon on cherche à télécharger une nouvelle version
			else {
				$this->telecharger($input, $output);
			}
		}
	}
	
	protected function mettre_a_jour(&$input, &$output) {
		// On cherche la version SVN actuelle
		ob_start();
		passthru('svn info --show-item url');
		$url_actuel = ob_get_contents();
		ob_end_clean();
		
		if (!$url_actuel) {
			$output->writeln("<error>Cette installation de SPIP n'est pas installée avec SVN et ne peut être mise à jour avec cette commande.</error>");
		}
		else {
			// On vérifie s'il y a des modifications de fichiers
			exec('svn status --quiet --non-interactive .', $lignes, $erreur);
			if ($erreur) {
				$output->writeln(array("<error>Erreur SVN.</error>"));
			}
			else {
				$lignes = array_filter($lignes, function ($ligne) {
					return preg_match(',^M,', $ligne);
				});
				
				// S'il y a des fichiers modifiés
				if (count($lignes) > 0) {
					$output->writeln(array(
						"<error>Pas de mise à jour car des fichiers ont été modifiés localement.</error>",
						 join("\n", $lignes),
					));
				}
				// Sinon on peut se lancer dans une mise à jour
				else {
					// Par défaut ça sera un switch sur la version demandée ou la dernière en date
					$mode_maj = 'switch';
					$version_type_actuel = null;
					
					// On cherche si on reconnait une version
					if (preg_match("|^svn://trac\.rezo\.net/spip/(.+?)(/spip-((\d+\.\d+).*?))?$|i", $url_actuel, $trouve)) {
						$dossier = $trouve[1];
						$version_actuelle = $trouve[3];
						$xy = $trouve[4];
						
						// Si on est dans un tag, ce sera toujours un switch
						if ($dossier == 'tags') {
							$mode_maj = 'switch';
							$version_type_actuel = 'release';
							
							// Et on cherchera la version stable la plus récente de la même branche X.Y, pour ne rien casser
							if (!$this->version_demandee) {
								$this->version_demandee = $xy;
							}
						}
						else {
							$version_type_actuel = 'branche';
							
							// S'il on a demandé une version, ou de changer pour une release : toujours switch
							if ($this->version_demandee or $input->hasParameterOption(array('--release', '-r'))) {
								$mode_maj = 'switch';
							}
							// Sinon on reste dans la même branche, donc on update simplement
							else {
								$mode_maj = 'update';
							}
						}
					}
					
					// Si on doit faire update, facile
					if ($mode_maj == 'update') {
						$output->writeln("<info>C'est parti pour la mise à jour en restant dans la même branche $version_actuelle</info>");
						passthru('svn update');
					}
					// Si c'est un switch, on cherche version et URL
					elseif (
						$mode_maj == 'switch'
						and $version_precise = $this->chercher_version_precise($input, $output)
					) {
						// Si la version trouvée est <= pour le même type qu'actuellement
						if ($version_type_actuel == $this->version_type and version_compare($version_precise, $version_actuelle, '<=')) {
							$output->writeln("<info>Pas de version plus récente que votre version $version_actuelle</info>");
						}
						// Sinon on peut enfin faire le switch
						else {
							$output->writeln("<info>C'est parti pour un changement de version de \"$version_actuelle\" à la {$this->version_type} \"{$this->version_precise}\"</info>");
							$output->writeln("Patientez quelques minutes, le temps que SVN calcule les changements…");
							passthru("svn switch {$this->url}");
						}
					}
				}
			}
		}
	}
	
	protected function telecharger(&$input, &$output) {
		// On cherche la bonne version suivant les params
		$this->chercher_version_precise($input, $output);
		
		// Si ya bien un URL au final
		if ($this->url) {
			$output->writeln("<info>C'est parti pour le téléchargement de la {$this->version_type} \"{$this->version_precise}\" !</info>");
			
			// On lance la commande SVN dans le répertoire courant
			passthru("svn co {$this->url} .");
		}
	}
	
	protected function chercher_version_precise(&$input, &$output) {
		$lister_versions = $this->getApplication()->find('core:listerversions');
		$versions = $lister_versions->get_versions();
		$url = null;
		
		// S'il y a une release demandée, c'est prioritaire
		if ($this->version_type == 'release') {
			// S'il y a une release précise demandée
			if ($this->version_demandee) {
				// Si la version pile exacte existe
				if (isset($versions['tags'][$this->version_demandee])) {
					$this->version_precise = $this->version_demandee;
					$this->url = $versions['tags'][$this->version_demandee];
				}
				// Sinon on cherche le plus récent approchant
				elseif ($this->version_precise = $lister_versions->get_last_release($this->version_demandee)) {
					$this->url = $versions['tags'][$this->version_precise];
				}
				// Sinon la release demandée n'existe pas
				else {
					$output->writeln(array(
						"<error>La version \"{$this->version_demandee}\" n'est pas prise en charge.</error>",
						'Releases supportées : <info>'.join('</info>, <info>', array_keys($versions['tags'])).'</info>'
					));
				}
			}
			// Sinon la dernière parmi toutes les releases
			else {
				$this->version_precise = $lister_versions->get_last_release();
				$this->url = $versions['tags'][$this->version_precise];
			}
		}
		// Sinon s'il y a une branche demandée
		elseif ($this->version_type == 'branche') {
			// S'il y a une branche précise demandée
			if ($this->version_demandee) {
				// Si la version pile exacte existe
				if (isset($versions['branches'][$this->version_demandee])) {
					$this->version_precise = $this->version_demandee;
					$this->url = $versions['branches'][$this->version_demandee];
				}
				// Sinon on cherche le plus récent approchant
				elseif ($this->version_precise = $lister_versions->get_last_branche($this->version_demandee)) {
					$this->url = $versions['branches'][$this->version_precise];
				}
				// Sinon la branche demandée n'exsite pas
				else {
					$output->writeln(array(
						"<error>La version \"{$this->version_demandee}\" n'est pas prise en charge.</error>",
						'Branches supportées : <info>'.join('</info>, <info>', array_keys($versions['branches'])).'</info>'
					));
				}
			}
			// Sinon la dernière parmi toutes les branches
			else {
				$this->version_precise = $lister_versions->get_last_branche();
				$this->url = $versions['branches'][$this->version_precise];
			}
		}
		// Sinon on prend la dernière release stable
		else {
			$this->version_precise = $lister_versions->get_last_release();
			$this->url = $versions['tags'][$this->version_precise];
		}
		
		return $this->version_precise;
	}
}
