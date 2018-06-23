<?php
namespace Spip\Cli\Command;

use Spip\Cli\Console\Command;
use Spip\Cli\Sql\Query;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;


class SqlShowtable extends Command
{

	protected function configure() {
		$this->setName("sql:showtable")
			->setDescription("Décrit une table dans la base de données.")
			->addArgument('table', InputArgument::REQUIRED, 'Le nom de la table');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->demarrerSpip();

		$table = $input->getArgument('table');
		$this->showtable($table);
	}

	/**
	 * Décrit une table SQL Spip de diverses manières
	 * @param string $table
	 */
	public function showtable($table) {
		$this->io->title("Description de la table : " . $table);
		$this->pdo_showtable($table);
		$this->spip_showtable($table);
	}

	/**
	 * Décrit une table SQL en utilisant PDO
	 * @param string $table
	 */
	public function pdo_showtable($table) {
		$this->io->section("Description PDO");

		/** @var Query $query */
		$query = $this->getService('sql.query');
		$metas = $query->getColumnsDescription($table);
		$this->io->text(count($metas) . " colonne·s");
		$this->io->atable($metas);
	}


	/**
	 * Décrit une table SQL en utilisant PDO
	 * @param string $table
	 */
	public function spip_showtable($table) {
		$this->io->section("Description SPIP");

		$res = sql_showtable($table);
		if ($res) {
			if (!empty($res['field'])) {
				$rows = array_map(
					function($k, $v) {
						return ['column' => $k, 'description' => $v];
					},
					array_keys($res['field']),
					$res['field']
				);
				$this->io->text(count($rows) . " colonne·s");
				$this->io->atable($rows);
			}
			if (!empty($res['key'])) {
				$rows = array_map(
					function($k, $v) {
						return ['key name' => $k, 'columns' => $v];
					},
					array_keys($res['key']),
					$res['key']
				);
				$this->io->text(count($rows) . " clé·s");
				$this->io->atable($rows);
			}
		}
	}
}
