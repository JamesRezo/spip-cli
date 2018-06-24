<?php

namespace Spip\Cli\Command;

use Spip\Cli\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class TestStyle extends Command
{

	protected function configure() {
		$this->setName("test:style")
			->setDescription("Affiche les différents styles d'écriture.");
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$io = $this->io;
		$io->title("Styles");
		$io->section("Symfony simple styles");

		$this->printSimples([
			'title', 'section', 'text', 'comment',
			'success', 'warning', 'error', 'note', 'caution'
		]);


		$io->section("Symfony array styles");
		$io->text([
			'Array of text lines: $io->text([..., ...])',
			'And this is the second line',
			'And this is the third line',
			'And this is the fourth line',
		]);
		$io->listing([
			'Listing:',
			'$io->listing([..., ..., ...])',
			'Item 2',
			'Item 3',
			'Item 4',
		]);
		$io->table(
			['Table', 'Header2', 'Header3'],
			[
				['$io->table(Headers, Rows)', 'Value 2', 'Value 3' ],
				['$io->table([title1, title2, ...], [[value 1, Value 2, ...], ... ])', 'Value 2', 'Value 3' ],
				['$io->table([..., ...], [[..., ...], [..., ...]])', 'Value 2', 'Value 3' ],
			]
		);

		$io->section("Spip Cli more styles");
		$this->printSimples([
			'check', 'fail', 'care'
		]);
		$io->columns(['Columns', '$io->columns([...], 6)'] + range('a', 'z'), 6);
		$io->columns(['Columns', '$io->columns([...], 6, true)'] + range('a', 'z'), 6, true);
		$io->atable([
			['Table' => 'Symplify table for key based array', 'Header2' => '', 'Header3' => ''],
			['Table' => '$io->atable(Rows)', 'Header2' => '', 'Header3' => ''],
			['Table' => '$io->atable([ [...], [...], ... ])', 'Header2' => '', 'Header3' => ''],
		]);
	}

	public function printSimples($simples) {
		foreach ($simples as $simple) {
			$this->printSimple($simple);
		}
	}

	public function printSimple($command) {
		$this->io->$command(ucfirst($command) . ': $io->' . $command . '(...)');
	}
}