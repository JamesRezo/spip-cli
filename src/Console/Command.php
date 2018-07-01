<?php

namespace Spip\Cli\Console;

use Simplex\Container;
use Spip\Cli\Console\Style\SpipCliStyle;
use Spip\Cli\Loader\Spip;
use Symfony\Component\Console\Command\Command as BaseCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base class helper for Spip Cli Commands.
 * @api
 */
abstract class Command extends BaseCommand
{

	/** @var SpipCliStyle */
	protected $io;

	protected function initialize(InputInterface $input, OutputInterface $output) {
		parent::initialize($input, $output);
		$io = $this->getService('console.io');
		$this->io = $io($input, $output);
	}

	/**
	 * Retourne le service demandé
	 * @return mixed|null
	 */
	public function getService($name) {
		return $this->getApplication()->getService($name);
	}

	/**
	 * Returns the application container.
	 * @return Container
	 */
	public function getContainer() {
		return $this->getApplication()->getContainer();
	}

	/**
	 * Retourne une sortie stylée
	 * @return SpipCliStyle
	 */
	public function getIO() {
		return $this->io;
	}

	/**
	 * Démarre SPIP
	 * @param bool $chdir
	 * @return Spip
	 * @throws \Exception
	 */
	public function demarrerSpip($chdir = true) {
		$spip = $this->getService('loader.spip');
		$spip->load();
		if ($chdir) {
			$spip->chdir();
		}
		return $spip;
	}
}