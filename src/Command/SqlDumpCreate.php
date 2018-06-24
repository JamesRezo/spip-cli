<?php

namespace Spip\Cli\Command;

use Spip\Cli\Console\Command;
use Spip\Cli\Sql\DumpCommonTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class SqlDumpCreate extends Command
{
	use DumpCommonTrait;

	protected function configure() {
		$this->setName("sql:dump:create")
			->setDescription("Crée un dump SPIP.")
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Le nom du fichier de dump (sans extension)', 'dump')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Force une création même si un dump du même nom existe déjà')
			->addOption('continue', 'c', InputOption::VALUE_NONE, 'Continue un dump (s’il y en avait un en cours)')
			->addOption('no-struct', null, InputOption::VALUE_NONE, 'Ne met pas la déclaration de structure dans la table spip_meta du dump généré.')
			->addOption('declared', 'd', InputOption::VALUE_NONE, 'Exporte uniquement les tables déclarées à SPIP')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->demarrerSpip();

		include_spip('base/dump');
		include_spip('inc/dump');

		$this->io->title("Création d’une sauvegarde SPIP de la base de données");
		$name = $input->getOption('name');

		$archive = dump_repertoire() . $name . '.sqlite';
		$status_name = $this->getStatusFilename($name);
		$has_status = is_file($this->getStatusFilepath($status_name));

		if (is_file($archive)) {
			$this->io->note("Une sauvegarde du même nom existe déjà.");
			if (!$input->getOption('continue')) {
				if (!$input->getOption('force')) {
					$this->io->note("Utilisez d’option --force pour l’effacer auparavant.");
					$this->io->note("Aucune action réalisée.");
					return;
				} else {
					$this->io->note("Suppression de ce dump.");
					unlink($archive);
				}
			} else {
				if ($has_status) {
					$this->io->note("Poursuite d’un dump.");
				} else {
					$this->io->note("Pas de statut connu pour continuer une sauvegarde.");
					return;
				}
			}
		}

		if ($has_status and $input->getOption('force')) {
			$this->io->note("Suppression d’un ancien fichier de statut de sauvegarde.");
			unlink($this->getStatusFilepath($status_name));
		}

		$this->makeDump($archive, [
			'status_name' => $status_name,
			'no-struct' => $input->getOption('no-struct'),
			'declared' => $input->getOption('declared'),
		]);
	}



	/**
	 * Créer le dump.
	 *
	 * Cf. formulaires_sauvegarder_traiter_dist()
	 *
	 * @param string $archive
	 * @param array $options {
	 *     @var string $status_file Nom du fichier de statut
	 *     @var bool $no-struct true pour supprimer la structure dans le spip_meta du dump.
	 * }
	 * @param string $status Nom du fichier qui enregistre l’avancement de la sauvegarde
	 * @return bool
	 */
	public function makeDump($archive, $options = []) {
		$this->io->text("Créer le dump : " . $archive);

		// Nom du fichier qui stocke les informations d’avancement de la sauvegarde
		$status_name = (!empty($options['status_name']) ? $options['status_name'] : $this->getStatusFilename());
		$status_file = $this->getStatusFilepath($status_name);

		$exclude = lister_tables_noexport();
		list($tables, ) = base_liste_table_for_dump($exclude);
		$tables = base_lister_toutes_tables('', $tables, $exclude);
		if (!empty($options['declared'])) {
			$principales = array_keys(lister_tables_principales());
			$auxiliaires = array_keys(lister_tables_auxiliaires());
			$declarees = array_merge($principales, $auxiliaires);
			$ignorees = array_diff($tables, $declarees);
			sort($ignorees);
			$tables = array_intersect($tables, $declarees);
			$this->io->care(count($ignorees) . " table·s non déclarée·s ignorée·s");
			$this->io->listing($ignorees);
		}
		$this->startProgressBar(count($tables) + 2);


		$res = dump_init($status_name, $archive, $tables);
		if ($res !== true) {
			$this->stopProgressBar();
			/* erreur d’initialisation ? */
			$this->io->warning($res);
			return false;
		}

		$this->progress->advance();
		ini_set('max_execution_time', -1);
		$status = $this->readStatusFile($status_name);
		dump_serveur($status['connect']);
		spip_connect('dump');

		$opts = [
			'callback_progression' => [$this, 'showProgress'],
			'no_erase_dest' => lister_tables_noerase(),
			'where' => $status['where'] ?: [],
		];

		$res = base_copier_tables($status_file, $status['tables'], '', 'dump', $opts);

		if (!$res) {
			$this->stopProgressBar();
			$this->error("Une erreur a eu lieu lors du dump.");
			$content = $this->readStatusFile();
			if (!empty($content['error'])) {
				$this->io->listing($content['error']);
			}
			return false;
		}

		$this->progress->advance();
		$this->progress->setMessage("Finition");

		dump_end($status_name, 'sauvegarder');
		$this->stopProgressBar();

		if (!empty($options['no-struct'])) {
			sql_delete('spip_meta', "nom='dump_structure_temp'", 'dump');
			$this->io->note("Suppression de la structure dans le dump.");
		}

		$this->io->success("Dump effectué.");
		unlink($status_file);
		return true;
	}

}