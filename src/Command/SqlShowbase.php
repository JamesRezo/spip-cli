<?php

namespace Spip\Cli\Command;

use Spip\Cli\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class SqlShowbase extends Command
{
	protected function configure() {
		$this->setName("sql:showbase")
			->setDescription("Liste les tables de la BDD.");
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->demarrerSpip();
		$this->showbase();
	}

	/**
	 * Liste les tables SQL
	 */
	public function showbase() {
		$this->io->title("Liste des tables");
		$this->spip_showbase();
	}

	/**
	 * Liste les tables SQL en utilisant SPIP
	 */
	public function spip_showbase() {
		$this->io->section("Description SPIP");

		$tables = sql_alltable();
		$this->io->text(count($tables) . " table·s");
		sort($tables);
		$this->io->columns($tables, 6, true);
		return true;
	}
}