<?php

namespace Spip\Cli\Command;

use Spip\Cli\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class SqlRepair extends Command
{
	protected function configure() {
		$this->setName("sql:repair")
			->setDescription("Crée les tables et champs manquants et tente de réparer chaque table de la base de données.");
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->demarrerSpip();
		$this->io->title("Réparer la base de données");
		include_spip('base/repair');
		$html = admin_repair_tables();
		$this->presenterHTML($html);
	}

	protected function presenterHTML($html) {
		include_spip('inc/filtres');
		$html = explode("</div><div>", $html);
		foreach ($html as $ligne) {
			$table = explode("\n", $ligne, 2);
			$table = textebrut(array_shift($table));
			$table = str_replace(['(', ')'], ['<comment>(', ')</comment>'], $table);
			if (false === stripos($ligne, "'notice'")) {
				$this->io->check($table);
			} else {
				$this->io->fail($table);
			}
		}
		$this->io->text("");
	}
}