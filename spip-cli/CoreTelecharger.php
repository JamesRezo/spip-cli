<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CoreTelecharger extends Command {
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
		
		$lister_versions = $this->getApplication()->find('core:listerversions');
		$versions = $lister_versions->get_versions();
		
		// On vérifie qu'on est pas déjà dans une installation de SPIP !
		if ($GLOBALS['spip_loaded']) {
			$output->writeln('<info>Vous êtes déjà dans une installation de SPIP '.$GLOBALS['spip_version_branche'].'.</info> <comment>Téléchargement annulé.</comment>');
		}
		// On teste si on peut utiliser "passthru"
		elseif (!function_exists('passthru')){
			$output->writeln("<error>Votre installation de PHP doit pouvoir exécuter des commandes externes avec la fonction passthru().</error>");
		}
		// Sinon c'est bon on peut télécharger SPIP
		else {
			// On détermine quelle version on doit télécharger
			$url = null;
			$mode = 'release';
			
			// S'il y a une release demandée, c'est prioritaire
			if ($input->hasParameterOption(array('--release', '-r'))) {
				// S'il y a une release précise demandée
				if ($version = $demande = $input->getOption('release')) {
					// Si la version pile exacte existe
					if (isset($versions['tags'][$demande])) {
						$url = $versions['tags'][$demande];
					}
					// Sinon on cherche le plus récent approchant
					elseif ($version = $lister_versions->get_last_release($demande)) {
						$url = $versions['tags'][$version];
					}
					// Sinon le truc demandé n'existe pas
					else {
						$output->writeln(array(
							"<error>La version \"{$demande}\" n'est pas prise en charge.</error>",
							'Releases supportées : <info>'.join('</info>, <info>', array_keys($versions['tags'])).'</info>'
						));
					}
				}
				// Sinon la dernière parmi toutes les releases
				else {
					$version = $lister_versions->get_last_release();
					$url = $versions['tags'][$version];
				}
			}
			// Sinon s'il y a une branche demandée
			elseif ($input->hasParameterOption(array('--branche', '-b'))) {
				$mode = 'branche';
				
				// S'il y a une branche précise demandée
				if ($version = $demande = $input->getOption('branche')) {
					// Si la version pile exacte existe
					if (isset($versions['branches'][$demande])) {
						$url = $versions['branches'][$demande];
					}
					// Sinon on cherche le plus récent approchant
					elseif ($version = $lister_versions->get_last_branche($demande)) {
						$url = $versions['branches'][$version];
					}
					else {
						$output->writeln(array(
							"<error>La version \"{$demande}\" n'est pas prise en charge.</error>",
							'Branches supportées : <info>'.join('</info>, <info>', array_keys($versions['branches'])).'</info>'
						));
					}
				}
				// Sinon la dernière parmi toutes les branches
				else {
					$version = $lister_versions->get_last_branche();
					$url = $versions['branches'][$version];
				}
			}
			// Sinon on prend la dernière release stable
			else {
				$version = $lister_versions->get_last_release();
				$url = $versions['tags'][$version];
			}
			
			if ($url) {
				$output->writeln("<info>C'est parti pour le téléchargement de la $mode $version !</info>");
			
				// On lance la commande SVN dans le répertoire courant
				passthru("svn co $url .");
			}
		}
	}
}
