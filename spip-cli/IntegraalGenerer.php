<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Générer un nouveau projet à partir de l'échafaudage Intégraal
 * 
 * La commande génère un nouveau projet dans le dossier des plugins, et renomme les fichiers et contenus.
 * Usage : spip integraal:generer -c /dossier/local/integraal monprojet "Mon super projet"
 **/
class IntegraalGenerer extends Command {
	protected function configure() {
		$this
			->setName('integraal:generer')
			->setDescription('Génèrer un nouveau projet à partir de l‘échafaudage Intégraal.')
			->addArgument(
				'prefixe',
				InputArgument::REQUIRED,
				'Préfixe du nouveau projet'
			)
			->addArgument(
				'nom',
				InputArgument::REQUIRED,
				'Nom humain du projet'
			)
			->addOption(
				'chemin_integraal',
				'c',
				InputOption::VALUE_REQUIRED,
				'Chemin local ou URL distante où trouver Intégraal',
				'svn://zone.spip.org/spip-zone/_squelettes_/integraal'
			)
			->addOption(
				'auteur',
				'a',
				InputOption::VALUE_REQUIRED,
				'Auteur du projet'
			)
			->addOption(
				'url',
				'u',
				InputOption::VALUE_REQUIRED,
				'URL de l’auteur du projet'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		// On récupère les arguments
		$prefixe = $input->getArgument('prefixe');
		if (!$nom = $input->getArgument('nom')) {
			$nom = $prefixe;
		}
		
		// On récupère les options
		$chemin_integraal = $input->getOption('chemin_integraal');
		$auteur = $input->getOption('auteur');
		$url = $input->getOption('url');
		
		// On se déplace dans le dossier des plugins si on est dans un SPIP
		if (
			!isset($GLOBALS['spip_loaded'])
			or !$GLOBALS['spip_loaded']
			or !chdir($GLOBALS['spip_racine'])
			or !is_dir(_DIR_PLUGINS)
		) {
			$output->writeln("<error>Vous n’êtes pas dans un SPIP ou vous n’avez pas de dossiers des plugins.</error>");
		} elseif (!function_exists('passthru')){
			$output->writeln("<error>Votre installation de PHP doit pouvoir exécuter des commandes externes avec la fonction passthru().</error>");
		} else {
			// On se déplace dans le dossier des plugins
			chdir(_DIR_PLUGINS);
			
			// On télécharge Intégraal
			$output->writeln("<info>Téléchargement de l’échafaudage IntéGraal.</info>");
			
			// On lance la commande SVN dans le répertoire courant
			passthru("svn export {$chemin_integraal} {$prefixe}", $erreur);
			
			if ($erreur !== 0) {
				$output->writeln("<error>Une erreur s’est produite durant le téléchargement.</error>");
			} else {
				passthru("chmod 775 -R {$prefixe}");
				
				// On se déplace dans le dossier du nouveau projet
				chdir($prefixe);
				
				// On renomme tous les fichiers avec "integraal"
				passthru("find . | rename -v 's/integraal/{$prefixe}/g'", $erreur);
				
				// On affiche une erreur mais on continue quand même le renommage des contenus ensuite
				if ($erreur !== 0) {
					$output->writeln("<error>Une erreur s’est produite durant le renommage des fichiers.</error>");
				}
				
				// On remplace à l'intérieur des contenus
				passthru("find . -type f -exec sed -i 's/integraal/{$prefixe}/g' {} \;", $erreur);
				passthru("find . -type f -exec sed -i 's/IntéGraal/{$nom}/g' {} \;", $erreur);
				
				// Si on demande à changer l'auteur
				if ($auteur) {
					passthru("find . -type f -exec sed -i 's/Les Développements Durables/{$auteur}/g' {} \;", $erreur);
				}
				// Si on demande à changer l'auteur
				if ($url) {
					passthru("find . -type f -exec sed -i 's|http://www\.ldd\.fr|{$url}|g' {} \;", $erreur);
				}
				
				$output->writeln('<info>C’est fini ! Vous pouvez maintenant personnaliser les fichiers selon vos besoins.</info>');
			}
		}
	}
}
