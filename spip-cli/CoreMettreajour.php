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
					'trunk' => 'svn://trac.rezo.net/spip/spip',
				);
				// Branche séléctionnée
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
					// On lance la commande SVN dans le répertoire courant
					passthru('svn up .');				
				}
			}
		}
		else {
			$output->writeln('<error>Vous devez télécharger SPIP avant de pouvoir mettre à jour.</error>');
		}
	}
}
