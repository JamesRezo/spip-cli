<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PhpEval extends Command {
	protected function configure() {
		$this
			->setName('php:eval')
			->setDescription('Évaluer du code PHP dans un contexte SPIPien.')
			->addArgument(
				'code',
				InputArgument::REQUIRED,
				'Le code PHP à évaluer'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		global $spip_racine;
		global $spip_loaded;

		if ($spip_loaded) {
			chdir($spip_racine);

			$return = eval($input->getArgument('code'));
		}
	}
}
