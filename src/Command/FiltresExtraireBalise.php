<?php

namespace Spip\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FiltresExtraireBalise extends Command {
	protected function configure() {
		$this
			->setName('filtres:extraire_balise')
			->setDescription('Extrait la première balise du type fourni avec l\'option -b. Exemple `spip extraire_balise  -b title` pour extraire le titre de la page')
			->setAliases(array(
				'extraire_balise'
			))
			->addOption(
				'balise',
				'b',
				InputOption::VALUE_OPTIONAL,
				'Type de balise HTML à extraire',
				''
			)		
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		global $spip_racine;
		global $spip_loaded;

		if ($spip_loaded) {
			chdir($spip_racine);
			
			$type_balise = $input->getOption('balise') ;
			if($type_balise){
				$contenu = stream_get_contents(STDIN);
			
				include_spip('inc/filtres');
				$balise = extraire_balise($contenu,$type_balise);
				$output->writeln($balise);
			}else{
				$output->writeln("Préciser le type de balise HTML à extraire avec l'option -b.");
			}
		}
		else{
			$output->writeln('<error>Vous n’êtes pas dans une installation de SPIP. Impossible de convertir le texte.</error>');
		}
	}
}


