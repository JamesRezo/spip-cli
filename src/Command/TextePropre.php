<?php

namespace Spip\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class TextePropre extends Command {
	protected function configure() {
		$this
			->setName('texte:propre')
			->setDescription('Convertit du texte au format SPIP vers du HTML via la fonction "propre"')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		global $spip_racine;
		global $spip_loaded;

		if ($spip_loaded) {
			chdir($spip_racine);

			$contenu = stream_get_contents(STDIN);

			include_spip('inc/texte');
			$output->write(trim(propre($contenu))."\n");

		}
		else{
			$output->writeln('<error>Vous n’êtes pas dans une installation de SPIP. Impossible de convertir le texte.</error>');
		}
	}
}
