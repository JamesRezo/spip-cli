<?php

namespace Spip\Cli\Command;

use Spip\Cli\Console\Command;
use Spip\Cli\Sql\DumpCommonTrait;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class SqlDumpRestore extends Command
{

	use DumpCommonTrait;

	protected function configure() {
		$this->setName("sql:dump:restore")
			->setDescription("Restaure un dump SPIP.")
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Le nom du fichier de dump (sans extension)', 'dump')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Force une restauration même si un fichier de statut existe déjà')
			->addOption('keep-struct', 'k', InputOption::VALUE_NONE, 'Force à conserver la structure des tables déjà en place sur la destination')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->demarrerSpip();

		include_spip('base/dump');
		include_spip('inc/dump');

		$this->io->title("Restauration d’une sauvegarde SPIP de la base de données");
		$name = $input->getOption('name');

		$archive = dump_repertoire() . $name . '.sqlite';
		$status_name = $this->getStatusFilename($name);
		$has_status = is_file($this->getStatusFilepath($status_name));

		if (!is_file($archive)) {
			$this->io->note("Aucune sauvegarde n’existe avec ce nom.");
			return;
		}

		if ($has_status and $input->getOption('force')) {
			$this->io->note("Suppression d’un ancien fichier de statut de sauvegarde.");
			unlink($this->getStatusFilepath($status_name));
		}

		$this->restoreDump($archive, [
			'status_name' => $status_name,
			'keep-struct' => $input->getOption('keep-struct'),
		]);
	}

	public function restoreDump($archive, $options = []) {
		$this->io->text("Restaure le dump : " . $archive);

		// Nom du fichier qui stocke les informations d’avancement de la sauvegarde
		$status_name = (!empty($options['status_name']) ? $options['status_name'] : $this->getStatusFilename());
		$status_file = $this->getStatusFilepath($status_name);

		ini_set('max_execution_time', -1);
		// Fournir un faux id_auteur pour feinter le copieur.
		if (empty($GLOBALS['visiteur_session'])) {
			$GLOBALS['visiteur_session'] = [];
		}
		$GLOBALS['visiteur_session']['id_auteur'] = 0;

		// connexion à la bdd de sauvegarde
		$connect_dump = dump_connect_args($archive);
		dump_serveur($connect_dump);
		spip_connect('dump');

		$tables = $this->getDumpTables();
		$this->startProgressBar(count($tables) + 2);

		include_spip('inc/dump');
		$res = dump_init($status_name, $archive, $tables, array('spip_meta' => "impt='oui'"));
		if ($res !== true) {
			$this->stopProgressBar();
			/* erreur d’initialisation ? */
			$this->io->warning($res);
			return false;
		}


		$this->progress->advance();
		$status = $this->readStatusFile($status_name);

		$opts = array(
			'callback_progression' => [$this, 'showProgress'],
			'no_erase_dest' => lister_tables_noerase(),
			'where' => $status['where'] ?: [],
			'desc_tables_dest' => $this->getDumpTablesDesc()
		);
		if (!empty($options['keep-struct'])) {
			$opts['desc_tables_dest'] = $this->getTablesDesc();
		}

		$res = base_copier_tables($status_file, $status['tables'], 'dump', '', $opts);

		if (!$res) {
			$this->stopProgressBar();
			$this->error("Une erreur a eu lieu lors de la restauration.");
			$content = $this->readStatusFile();
			if (!empty($content['error'])) {
				$this->io->listing($content['error']);
			}
			return false;
		}

		$this->progress->advance();
		$this->progress->setMessage("Finition");

		dump_end($status_name, 'restaurer');
		$this->stopProgressBar();

		$this->io->success("Restauration effectuée.");
		unlink($status_file);
		return true;
	}

	/**
	 * Retourne la liste des tables du dump indiqué
	 * @return array
	 */
	public function getDumpTables() {
		$tables = base_lister_toutes_tables('dump');
		return $tables;
	}

	/**
	 * Retourne la description des tables sérialisées dans spip_meta du dump, si présente
	 * @return array()
	 */
	public function getDumpTablesDesc() {
		if ($desc = sql_getfetsel('valeur', 'spip_meta', "nom='dump_structure_temp'", '', '', '', '', 'dump')
			and $desc = unserialize($desc)
		) {
			return $desc;
		}
		return $desc;
	}

	/**
	 * Retourne la description des tables actuellement présente sur la destination
	 * @return array()
	 */
	public function getTablesDesc() {
		$structure = [];
		foreach (sql_alltable('%') as $t) {
			$structure[$t] = sql_showtable($t, true);
		}
		return $structure;
	}
}