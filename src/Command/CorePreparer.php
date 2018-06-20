<?php

namespace Spip\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CorePreparer extends Command {
	protected function configure() {
		$this
			->setName('core:preparer')
			->setDescription('Préparer les fichiers pour installer SPIP correctement.')
			->addOption(
				'droits',
				'd',
				InputOption::VALUE_OPTIONAL,
				'Droits des dossiers en décimales',
				'777'
			)
			->addOption(
				'rewrite-base',
				null,
				InputOption::VALUE_OPTIONAL,
				'Configuration du RewriteBase pour la réécriture des URLs',
				''
			)
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		global $spip_racine;
		global $spip_loaded;
		
		$droits = $input->getOption('droits');
		$rewrite_base = $input->getOption('rewrite-base');
		
		// On ne prépare les fichiers que si SPIP est bien présent
		if ($spip_loaded) {
			// On revient à la racine
			chdir($spip_racine);
			
			// Vérification des dossiers et leurs droits
			foreach (array('config', 'IMG', 'lib', 'local', 'plugins', 'tmp') as $dossier) {
				$sortie = '';
			
				// Si le dossier n'existe pas on l'ajoute
				if (!is_dir($dossier)) {
					mkdir($dossier);
					$sortie = "\tCréation du dossier : <info>OK</info>";
				}
			
				// Si le dossier a un mode différent de celui demandé (777 par défaut)
				if (substr(sprintf('%o', fileperms($dossier)), -3) != $droits) {
					chmod($dossier, octdec('0' . $droits));
					$sortie = "\tModification des droits : <info>$droits</info>";
				}
			
				// Si on doit afficher quelque chose
				if ($sortie) {
					$sortie = "<comment>$dossier/</comment>\n" . $sortie;
					$output->writeln($sortie);
				}
			}
		
			// .htaccess
			if (!is_file('.htaccess') and is_file('htaccess.txt')) {
				copy('htaccess.txt', '.htaccess');
				$output->writeln("<comment>Fichier .htaccess</comment>\n\tActivation du fichier depuis le modèle de SPIP : <info>OK</info>");
				// Si un RewriteBase est défini, on essaye de le changer
				if ($rewrite_base and include_spip('inc/flock') and lire_fichier('.htaccess', $htaccess)) {
					$htaccess = str_replace('RewriteBase /', 'RewriteBase '.$rewrite_base, $htaccess);
					supprimer_fichier('.htaccess');
					if (ecrire_fichier('.htaccess', $htaccess)) {
						$output->writeln("\tRewriteBase définie à <info>$rewrite_base</info>");
					}
				}
			}
		}
		else {
			$output->writeln('<error>Vous devez télécharger SPIP avant de pouvoir préparer l’installation.</error>');
		}
	}
}
