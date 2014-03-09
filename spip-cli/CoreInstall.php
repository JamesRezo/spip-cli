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
			->setDescription('Télécharger SPIP dans un dossier (par défaut, la dernier version stable)')
			-> addArgument(
				'dossier',
				InputArgument::OPTIONAL,
				'Dossier où télécharger SPIP.',
				getcwd()
			)
			->addOption(
				'branche',
				'b',
				InputOption::VALUE_OPTIONAL,
				'Donner explicitement la version à télécharger.',
				'3.0' // Par défaut, la dernière version stable
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$branches_ok = array(
			'2.1' => 'svn://trac.rezo.net/spip/branches/spip-2.1',
			'3.0' => 'svn://trac.rezo.net/spip/branches/spip-3.0',
			'trunk' => 'svn://trac.rezo.net/spip/spip',
		);
		
		$dossier = $input->getArgument('dossier');
		$branche = $input->getOption('branche');
		
		// On vérifie que l'on connait la version
		if (!in_array($branche, array_keys($branches_ok))){
			$output->writeln("<error>La version demandée ($branche) n'est pas prise en charge.</error>");
		}
		// Si c'est bon on continue
		else{
			$output->writeln("<info>C'est parti pour le téléchargement de la version $branche !</info>");
		}
	}
}
