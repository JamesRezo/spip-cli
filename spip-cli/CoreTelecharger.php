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
				'3.0' // Par défaut, la dernière version stable
			)
			->setAliases(array(
				'dl' // abbréviation commune pour "download"
			))
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		// On travaille dans le dossier courant
		$dossier = getcwd();
		
		// Liste des branches acceptées
		$branches_ok = array(
			'2.1' => 'svn://trac.rezo.net/spip/branches/spip-2.1',
			'3.0' => 'svn://trac.rezo.net/spip/branches/spip-3.0',
			'trunk' => 'svn://trac.rezo.net/spip/spip',
		);
		// Branche séléctionnée
		$branche = $input->getOption('branche');
		
		// On vérifie qu'on est pas déjà dans une installation de SPIP !
		if ($GLOBALS['spip_loaded']) {
			$output->writeln('<error>Vous êtes déjà dans une installation de SPIP '.$GLOBALS['spip_version_branche'].'. Téléchargement annulé.</error>');
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
				$output->writeln("<info>C'est parti pour le téléchargement de la version $branche !</info>");
			
				// On lance la commande SVN dans le répertoire courant
				passthru('svn co '.$branches_ok[$branche].' .');
			}
		}
	}
}
