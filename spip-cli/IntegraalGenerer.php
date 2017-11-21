<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

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
				InputArgument::OPTIONAL,
				'Préfixe du nouveau projet'
			)
			->addArgument(
				'nom',
				InputArgument::OPTIONAL,
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
			->addOption(
				'theme',
				't',
				InputOption::VALUE_REQUIRED,
				'Version du thème à utiliser : gulp ou scssphp'
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		// On récupère les arguments
		$prefixe = $input->getArgument('prefixe');
		$nom = $input->getArgument('nom');

		// On récupère les options
		$chemin_integraal = rtrim($input->getOption('chemin_integraal'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
		$auteur = $input->getOption('auteur');
		$url = $input->getOption('url');
		$version_theme = $input->getOption('theme');

		// Si des arguments nécessaires manquent, on pose des questions
		if (!$prefixe) {
			$helper = $this->getHelper('question');
			$question_prefixe = new Question('Préfixe du projet : ');
			$prefixe = $helper->ask($input, $output, $question_prefixe);
			$output->writeln("<info>Préfixe utilisé : $prefixe</info>");
		}
		if (!$nom) {
			$helper = $this->getHelper('question');
			$question_nom = new Question('Nom humain du projet : ');
			if (!$nom = $helper->ask($input, $output, $question_nom)) {
				$nom = $prefixe;
			}
			$output->writeln("<info>Nom utilisé : $nom</info>");
		}
		if (!$version_theme) {
			$helper = $this->getHelper('question');
			$question_theme = new ChoiceQuestion(
				'Version du thème à utiliser',
				array('gulp', 'scssphp'),
				0
			);
			$version_theme = $helper->ask($input, $output, $question_theme);
			if (!in_array($version_theme, array('gulp', 'scssphp'))) {
				$version_theme = 'gulp';
			}
			$output->writeln("Version choisie : $version_theme");
		}
		if (!$auteur) {
			$helper = $this->getHelper('question');
			$question_auteur = new Question('Auteur du projet : ');
			$auteur = $helper->ask($input, $output, $question_auteur);
			$output->writeln("<info>Auteur utilisé : $auteur</info>");
		}
		if (!$url) {
			$helper = $this->getHelper('question');
			$question_url = new Question('URL de l’auteur du projet : ');
			$url = $helper->ask($input, $output, $question_url);
			$output->writeln("<info>URL de l’auteur utilisée : $url</info>");
		}


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

			// On créé un nouveau répertoire pour y accueillir les 3 plugins d'Intégraal
			if (!$dir_integraal = mkdir($prefixe, 0775)) {
				$output->writeln("<error>Le répertoire « $prefixe » n’a pas pu être créé dans le dossier des plugins.</error>");
			} else {

				// On se déplace dans le dossier du nouveau projet
				chdir($prefixe);

				// Retrouver le chemin correspondant à la version du thème choisie
				$chemins_theme = array(
					'gulp'    => 'theme/trunk',
					'scssphp' => 'theme/branches/scssphp',
				);
				$chemin_theme = $chemins_theme[$version_theme];

				// On lance la commande SVN pour télécharger les 3 plugins
				// dans le répertoire courant
				$erreur_telecharger = false;
				$sous_chemins_integraal = array(
					'squelettes',
					$chemin_theme,
					'core'
				);
				foreach($sous_chemins_integraal as $dossier){
					$chemin_sous_dossier = $chemin_integraal . $dossier;
					passthru("svn export {$chemin_sous_dossier}", $erreur);
					if ($erreur !== 0) {
						$erreur_telecharger = true;
						break;
					}
				}

				if ($erreur_telecharger) {
					$output->writeln("<error>Une erreur s’est produite durant le téléchargement.</error>");
				} else {

					// Mettre les bonnes permissions
					passthru("chmod 775 -R *");

					// On renomme le dossier du theme
					$dossier_theme = ltrim(strrchr($chemin_theme, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
					passthru("mv $dossier_theme theme", $erreur);
					if ($erreur){
						$output->writeln("<error>Le dossier du theme (${dossier_theme}) n’a pas pu être renommé en « theme »</error>");
					}

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
					// Si on demande à changer l'url
					if ($url) {
						passthru("find . -type f -exec sed -i 's|http://www\.ldd\.fr|{$url}|g' {} \;", $erreur);
					}

					$output->writeln('<info>C’est fini ! Vous pouvez maintenant personnaliser les fichiers selon vos besoins.</info>');
					$output->writeln('<info>Les plugins ont été générés dans ' . _DIR_PLUGINS . $prefixe . '.</info>');
				}
			}
		}
	}
}
