<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ServerLocate extends Command {
	protected function configure() {
		$this
			->setName('server:locate')
			->setDescription('Localiser les SPIP installés sur ce serveur')
			->setAliases(array(
			))
		;
	}
	
	protected function execute(InputInterface $input, OutputInterface $output) {

		$sites = array_merge(
			$this->locate('inc_version.php'),
			$this->locate('inc_version.php3')
		);

		if (!count($sites)) {
			$output->writeln('<error>Pas de site SPIP détecté.</error>');
			exit;
		}

		foreach($sites as $site) {
			$msg = $this->analyser_site($site);
			if ($msg)
				$output->writeln($msg);
		}
	}
	
	/**
	 * Cherche les fichiers nommés xxxx
	 *
	 * @return array liste des fichiers nommés xxxx
	 */
	protected function locate($filename) {
		$_filename = escapeshellarg($filename);
		$files = `locate $_filename 2>/dev/null`
		 . `mdfind -name $_filename 2>/dev/null`;
		$files = explode("\n", trim($files));
		$files = array_filter($files, function($x) {
			return preg_match(",/inc_version\.php[3]?$,", $x);
		});
		
		return array_unique($files);
	}

	protected function analyser_site($filename) {
		$inc_version = basename($filename);
		$ecrire = basename(dirname($filename));
		$rep = dirname(dirname($filename));
		$name = basename($rep);
		// si le nom est peu informatif, remonter encore d'un cran
		if (in_array($name, ['www', 'dev', 'old', 'site', 'Web', 'public_html'])) $name = basename(dirname($rep)).'/'.$name;

		$a = @file_get_contents($filename);

		if (preg_match('/.*spip_version_branche\s*=\s*[\'"](.*)[\'"];/', $a, $r)
		OR preg_match('/.*spip_version_affichee\s*=\s*[\'"](.*)[\'"];/', $a, $r)) {
			$version_spip = $r[1];
		} else {
			$version_spip = "<error>version?</error>";
		}

		$report = [
			"Version" => $version_spip,
			"Répertoire" => $rep,
		];


		// Regarder les bases de données connectées, etc, etc.
		// TODO


		// Données à afficher
		$aff =  "<info>$name</info> ($version_spip)\n";
		foreach($report as $key => $val)
			$aff .= "  $key: $val\n";

		return $aff;
	}

}
