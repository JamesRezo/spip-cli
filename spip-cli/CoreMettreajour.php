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
				// On lance la commande SVN dans le répertoire courant
				passthru('svn up .');
			}
		}
		else {
			$output->writeln('<error>Vous devez télécharger SPIP avant de pouvoir mettre à jour.</error>');
		}
	}
}
