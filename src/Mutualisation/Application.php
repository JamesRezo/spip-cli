<?php

namespace Spip\Cli\Mutualisation;

use Pimple\Container;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Input\InputInterface;

class Application extends ConsoleApplication {

	const NAME = "Spip Cli Mu";
	const VERSION = "1.0.0";
	protected $options = [];
	protected $container;

	/**
	 * Application constructor.
	 *
	 * Permet d’appliquer une commande à un ensemble de sites
	 * d’une mutualisation.
	 *
	 * @param array $options
	 */
	public function __construct(array $options = []) {
		parent::__construct(self::NAME, self::VERSION);

		$this->container = new Container([
			'debug' => false,
			'spip.directory' => null,
			'path.spip-cli' => '/usr/local/bin/spip',
		]);

		// todo: config file
		foreach ($options as $key => $value) {
			$this->container[$key] = $value;
		}
	}


	/**
	 * Gets the name of the command based on input.
	 *
	 * @param InputInterface $input The input interface
	 *
	 * @return string The command name
	 */
	protected function getCommandName(InputInterface $input)
	{
		// This should return the name of your command.
		return 'Batch';
	}

	/**
	 * Gets the default commands that should always be available.
	 *
	 * @return array An array of default Command instances
	 */
	protected function getDefaultCommands()
	{
		// Keeps the core default commands to have the HelpCommand
		// which is used when using the --help option
		$defaultCommands = parent::getDefaultCommands();
		$defaultCommands[] = new Command\Batch();
		return $defaultCommands;
	}

	/**
	 * Overridden so that the application doesn't expect the command
	 * name to be the first argument.
	 */
	public function getDefinition()
	{
		$inputDefinition = parent::getDefinition();
		// clears out the normal first argument, which is the command name
		$inputDefinition->setArguments();
		return $inputDefinition;
	}

	public function getContainer() {
		return $this->container;
	}

	public function getService($name) {
		return $this->container[$name];
	}

}