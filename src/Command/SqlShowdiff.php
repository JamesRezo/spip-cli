<?php

namespace Spip\Cli\Command;

use Spip\Cli\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class SqlShowdiff extends Command
{
	protected function configure() {
		$this->setName("sql:show:diff")
			->setDescription("Liste les tables et champs présents mais non déclarés à SPIP, ou inversement.")
			->addOption('manquants', null, InputOption::VALUE_NONE, 'Uniquement les tables et champs déclarés mais manquants');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->demarrerSpip();
		$this->showdiff($input->getOption('manquants'));
	}

	/**
	 * Liste les tables et champs présents mais non déclarés
	 *
	 */
	public function showdiff($ignorer_excedentaires = false) {
		if ($ignorer_excedentaires) {
			$this->io->title("Liste des tables et champs déclarés mais manquants");
		} else {
			$this->io->title("Liste des tables et champs non déclarés ou déclarés mais manquants");
		}

		$tables = sql_alltable();
		$principales = lister_tables_principales();
		$auxiliaires = lister_tables_auxiliaires();
		$declarees = array_merge($principales, $auxiliaires);

		$this->io->text(count($declarees) . " table·s déclarée·s");
		$this->io->text(count($tables) . " table·s réelle·s");
		$this->io->text("");

		# Tables en trop
		if (!$ignorer_excedentaires) {
			$diff = array_diff($tables, array_keys($declarees));
			$this->printTables($diff, "table·s non déclarée·s");
		}

		# Tables manquantes
		$diff = array_diff(array_keys($declarees), $tables);
		$this->printTables($diff, "table·s déclarée·s mais absentes");


		$presentes = array_intersect_key($declarees, array_flip($tables));
		ksort($presentes);
		foreach ($presentes as $table => $desc) {
			$colonnes_declarees = $desc['field'];
			$colonnes = sql_showtable($table);
			$colonnes = $colonnes['field'];

			# Colonnes en trop
			if (!$ignorer_excedentaires) {
				$diff = array_diff_key($colonnes, $colonnes_declarees);
				$this->printColumns($diff, $table, "colonne·s non déclarée·s");
			}

			#  Colonnes manquantes
			$diff = array_diff_key($colonnes_declarees, $colonnes);
			$this->printColumns($diff, $table, "colonne·s déclarée·s mais absentes");
		}
	}

	public function printTables(array $diff, $texte) {
		if ($diff) {
			sort($diff);
			$this->io->section(count($diff) . " $texte");
			$this->io->listing($diff);
		}
	}

	public function printColumns(array $diff, $table, $texte) {
		if ($diff) {
			$this->io->section("Table " . $table . " : " . count($diff) . " $texte");
			$rows = array_map(
				function($k, $v) {
					return ['column' => $k, 'description' => $v];
				},
				array_keys($diff),
				$diff
			);
			$this->io->atable($rows);
		}
	}

}