<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CoreMettreajour extends Command {
	protected function configure() {
		$this
			->setName('core:mettreajour')
			->setDescription('Mettre à jour la branche de SPIP qui est installée.')
			->addOption(
				'branche',
				'b',
				InputOption::VALUE_OPTIONAL,
				'Donner explicitement la version à télécharger.',
				'' // Par défaut, la dernière version stable
			)			
			->setAliases(array(
				'update',
				'up' // abbréviation commune pour "update"
			))
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		global $spip_racine;
		global $spip_loaded;
		
		// On ne met à jour que si SPIP est bien présent
		if ($spip_loaded) {
			// On revient à la racine
			chdir($spip_racine);
			
			// On teste si on peut utiliser "passthru"
			if (!function_exists('passthru')){
				$output->writeln("<error>Votre installation de PHP doit pouvoir exécuter des commandes externes avec la fonction passthru().</error>");
			}
			else{
				// Liste des branches acceptées
				$branches_ok = array(
					'2.1' => 'svn://trac.rezo.net/spip/branches/spip-2.1',
					'3.0' => 'svn://trac.rezo.net/spip/branches/spip-3.0',
					'3.1' => 'svn://trac.rezo.net/spip/branches/spip-3.1',
					'trunk' => 'svn://trac.rezo.net/spip/spip',
				);
				// Branche sélectionnée
				$branche = $input->getOption('branche');
				if (isset($branche) && !empty($branche)) {
					// On vérifie que l'on connait la version
					if (!in_array($branche, array_keys($branches_ok))){
						$output->writeln(array(
							"<error>La version \"$branche\" n'est pas prise en charge.</error>",
							'Branches supportées : <info>'.join('</info>, <info>', array_keys($branches_ok)).'</info>'
						));
					}
					passthru('svn switch '.$branches_ok[$branche].' .');
				} else {
					// on vérifie d'abord qu'on est sur un spip sans modifs
					exec('svn status --quiet --non-interactive .', $results, $err);
					if ($err) {
						$output->writeln(array("<error>Erreur SVN.</error>"));
					} else {
						$results = array_filter($results, function ($line) {
							return preg_match(',^M,', $line);
						});
						if (count($results) > 0) {
							$output->writeln(array(
								"<error>Pas de mise à jour automatique car des fichiers ont été modifiés localement.</error>",
								 join("\n", $results),
								 '<info>Recherche des conflits…</info>'
							));
							exec('svn status --show-updates --quiet --non-interactive .', $results, $err);
							if ($err) {
								$output->writeln(array("<error>Erreur SVN.</error>"));
							} else {
								$results = array_filter($results, function ($line) {
									return preg_match(',^M +[*],', $line);
								});

								if (count($results) == 0) {
									$output->writeln(array("<info>Pas de conflit détecté, vous pouvez probablement lancer svn update.</info>"));
								} else {
									$output->writeln(array(
									"<error>Les fichiers suivants sont peut-être en conflit;\nfaites attention si vous lancez svn update.</error>",
										join ("\n", $results)
									));
								}
							}
						}
						else {
							// On lance la commande SVN dans le répertoire courant
							passthru('svn update .');
						}
					}
				}
			}
		}
		else {
			$output->writeln('<error>Vous devez télécharger SPIP avant de pouvoir mettre à jour.</error>');
		}
	}
}
