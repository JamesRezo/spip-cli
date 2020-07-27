<?php

namespace Spip\Cli\Command;

use Spip\Cli\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressHelper;
use Symfony\Component\Console\Helper\ProgressBar;


class SqlConvertTomysql extends Command
{
	protected function configure() {
		$this->setName("sql:convert:tomysql")
			->setDescription("Convertit un site vers mysql (utile pour un site en sqlite)")
			->addOption('connect', null, InputOption::VALUE_OPTIONAL, 'Le nom du connect MySQL a utiliser', null)
			->addOption('ignore-missing', 'i', InputOption::VALUE_NONE, 'Ignorer les champs et tables manquantes dans la base MySQL');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->demarrerSpip();
		$this->io->title("Convertir en MySQL");

		$connect_mysql = $input->getOption('connect');
		if (!$connect_mysql) {
			$this->io->error("Indiquez le nom du fichier php connect pour se connecter à la base MySQL");
			exit(1);
		}

		$ignore_missing = $input->getOption('ignore-missing');


		$this->installStructure($connect_mysql);

		if (!$this->checkStructure($connect_mysql)) {
			$this->io->note("Il manque des tables ou des champs dans la base cible $connect_mysql");
			if (!$ignore_missing) {
				$this->io->error("Réactivez les plugins manquants pour la création, ou corrigez manuellement dans la base MySQL");
				exit(1);
			}
			else {
				$this->io->note("L'export va se poursuivre en ignorant ces tables ou champs manquants");
			}
		}
		else {
			$this->io->success("Structure de la base cible $connect_mysql OK");
		}

		$this->copyAllData($connect_mysql);

		$this->io->success("Fini");
	}

	protected function ignoredTables(){
		return [
			'spip_depots',
			'spip_depots_plugins',
			'spip_paquets',
			'spip_plugins',
			'spip_plugins',
			'spip_test',
		];
	}

	protected function installStructure($connect_mysql) {

		include_spip('base/create');

		$trouver_table = charger_fonction('trouver_table', 'base');
		// on vide les caches
		$trouver_table('', $connect_mysql);

		$this->io->text("Installation/mise à jour des tables sur $connect_mysql");

		foreach (lister_tables_principales() as $k => $v) {
			creer_ou_upgrader_table($k, $v, true, true, $connect_mysql);
		}

		foreach (lister_tables_auxiliaires() as $k => $v) {
			creer_ou_upgrader_table($k, $v, false, true, $connect_mysql);
		}
		$this->io->text("<info>Installation/mise à jour finie</info>");

	}

	protected function checkStructure($connect_mysql) {

		$this->io->text("Vérification de la structure des tables sur $connect_mysql");

		// recuperer la liste des tables de la base du site (sur le connect par defaut donc)
		$tables = sql_alltable();
		$this->io->text(count($tables) . " table·s");
		sort($tables);

		$trouver_table = charger_fonction('trouver_table', 'base');
		// on vide les caches
		$trouver_table('');
		$trouver_table('', $connect_mysql);

		$nb_errors = 0;

		$ignored_tables = $this->ignoredTables();
		foreach ($tables as $table){
			if (!in_array($table, $ignored_tables)) {
				$desc_source = $trouver_table($table);
				$desc_mysql = $trouver_table($table, $connect_mysql);

				$ok = "OK";
				$out = "Table $table :";

				// regarder si la table existe dans le base cible
				if (!$desc_mysql){
					$this->io->text("<error>$out  MANQUANTE</error>");
					$nb_errors++;
					$ok = '';
				} else {
					// verifier si les champs existent
					foreach ($desc_source['field'] as $champ => $sql){
						if (!isset($desc_mysql['field'][$champ])){
							if ($out) {
								$this->io->text($out);
								$out = "";
							}
							$this->io->text("<error>  MANQUANT : $champ $sql</error>");
							// on corrige a la volee les cas bien connus
							if (!$this->tryRepairField($table, $champ, $connect_mysql)){
								$nb_errors++;
								$ok = '';
							}
						}
					}
				}

				if ($ok) {
					$this->io->text("<info>$out  $ok</info>");
				}
			}
		}

		if ($nb_errors) {
			return false;
		}
		return true;
	}

	protected function tryRepairField($table, $champ, $connect_mysql) {
		$champs_connus = [
			'*' => [
				'extra' => "extra longtext default '' NOT NULL",
			],
			'spip_documents' => [
				'oembed' => "text NOT NULL DEFAULT ''",
			]
		];

		$desc = false;
		if (!empty($champs_connus[$table][$champ])) {
			$desc = $champs_connus[$table][$champ];
		}
		elseif (!empty($champs_connus['*'][$champ])) {
			$desc = $champs_connus['*'][$champ];
		}

		if ($desc) {
			sql_alter("TABLE $table ADD $champ $desc", $connect_mysql);
			$this->io->text("<comment>  Corrigé : $champ $desc</comment>");
			return true;
		}

		return false;
	}

	protected function copyAllData($connect_mysql) {

		$tables = sql_alltable();
		$this->io->text(count($tables) . " table·s à copier");
		sort($tables);

		$trouver_table = charger_fonction('trouver_table', 'base');
		$ignored_tables = $this->ignoredTables();

		foreach ($tables as $table){
			if (!in_array($table, $ignored_tables)) {
				$desc_source = $trouver_table($table);
				$desc_mysql = $trouver_table($table, $connect_mysql);

				$out = "Table $table :";

				if ($desc_mysql) {
					$this->io->text($out);
					$nb_copy = $this->copyDataTable($table, $desc_source, $desc_mysql, $connect_mysql);
				}
				else {
					$this->io->text("<comment>$out  Ignorée</comment>");
				}
			}
		}
	}

	protected function copyDataTable($table, $desc_source, $desc_mysql, $connect_mysql) {

		$fields = array_intersect(array_keys($desc_source['field']), array_keys($desc_mysql['field']));

		if (!count($fields)) {
			$this->io->error("Pas de champs en commun sur la table $table");
			exit(1);
		}
		// on vide et on remet l'auto-increment a 1 sur la base destination
		spip_query("TRUNCATE TABLE $table", $connect_mysql);
		sql_alter("TABLE $table AUTO_INCREMENT = 1", $connect_mysql);

		$res = sql_select($fields, $table);
		$nb_lignes = sql_count($res);

		if ($nb_lignes) {
			$progressBar = new ProgressBar($this->io, $nb_lignes);
			$progressBar->start();

			$nb_size_lot = 10;
			$lot = [];
			$cpt = 0;
			while ($row = sql_fetch($res)) {

				//sql_insertq($table, $row, $desc_mysql, $connect_mysql);

				$lot[] = $row;
				if (count($lot) >= $nb_size_lot) {
					$cpt += count($lot);
					sql_insertq_multi($table, $lot, $desc_mysql, $connect_mysql);
					$lot = [];
					$progressBar->setProgress($cpt);
				}
			}

			if ($lot) {
				sql_insertq_multi($table, $lot, $desc_mysql, $connect_mysql);
			}
			$progressBar->finish();
		}
		else {
			$this->io->text("<comment> table vide</comment>");
		}

		return $nb_lignes;
	}
}