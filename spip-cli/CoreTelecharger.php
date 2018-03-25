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
				'Donner explicitement la version à télécharger.',
				'3.2' // Par défaut, la dernière version stable
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
		
		// Liste des branches acceptées
		$branches_ok = $versions['branches'];
		// Branche séléctionnée
		$branche = $input->getOption('branche');
		
		// On vérifie qu'on est pas déjà dans une installation de SPIP !
		if ($GLOBALS['spip_loaded']) {
			$output->writeln('<info>Vous êtes déjà dans une installation de SPIP '.$GLOBALS['spip_version_branche'].'.</info> <comment>Téléchargement annulé.</comment>');
		}
		// Sinon c'est bon on peut télécharger SPIP
		else {
			// On vérifie que l'on connait la version
			if (!in_array($branche, array_keys($branches_ok))){
				$output->writeln(array(
					"<error>La version \"$branche\" n'est pas prise en charge.</error>",
					'Branches supportées : <info>'.join('</info>, <info>', array_keys($branches_ok)).'</info>'
				));
			}
			// Si c'est bon, on teste si on peut utiliser "passthru"
			elseif (!function_exists('passthru')){
				$output->writeln("<error>Votre installation de PHP doit pouvoir exécuter des commandes externes avec la fonction passthru().</error>");
			}
			// Si c'est bon on continue
			else{
				$output->writeln("<info>C'est parti pour le téléchargement de la branche $branche !</info>");
			
				// On lance la commande SVN dans le répertoire courant
				passthru('svn co '.$branches_ok[$branche].' .');
			}
		}
	}
}
