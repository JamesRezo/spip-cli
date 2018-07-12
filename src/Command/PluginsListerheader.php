<?php

namespace Spip\Cli\Command;

use Spip\Cli\Console\Command;
use Spip\Cli\Console\Style\SpipCliStyle;
use Spip\Cli\Loader\Spip;
use Spip\Cli\Loader\Sql;
use Spip\Cli\Sql\Query;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class PluginsListerheader extends PluginsLister {

	protected function configure() {
		$this->setName("plugins:listerheader")
			->setDescription("Export la liste des plugins du site telle stockée en base (peut-être tronquée si trop de plugins).")
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Nom du fichier d’export', 'plugins')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->exportPlugins($input);
	}

	public function exportPlugins(InputInterface $input) {
		$file = $this->getExportFile($input);
		
		/** @var Query $sql */
		$sql = $this->getService('sql.query');
		$header = $sql->getMeta('plugin_header');
				
		$list = preg_replace('/\([^)]*\),?/',' ', $header);
		if (strpos($list, '(')) {
			$list = substr($list, 0, strpos($list, '('));
		}
		
		if (file_put_contents($file, $list)) {
			$this->io->check("Export dans : " . $file);
		} else {
			$this->io->fail("Export raté : " . $file);
		}
		$this->io->text($list);
	}

}