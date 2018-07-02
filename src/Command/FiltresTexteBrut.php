<?php

namespace Spip\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FiltresTexteBrut extends Command {
	protected function configure() {
		$this
			->setName('filtres:textebrut')
			->setDescription('Convertit un texte HTML en texte brut.')
			->setAliases(array(
				'textebrut'
			))		
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		global $spip_racine;
		global $spip_loaded;

		if ($spip_loaded) {
			chdir($spip_racine);
			
			$contenu = stream_get_contents(STDIN);
			include_spip('inc/filtres');
			$output->writeln(textebrut($contenu));
		}
		else{
			$output->writeln('<error>Vous n’êtes pas dans une installation de SPIP. Impossible de convertir le texte.</error>');
		}
	}
}

