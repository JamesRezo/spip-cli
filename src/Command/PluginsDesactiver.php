<?php

namespace Spip\Cli\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class PluginsDesactiver extends PluginsLister {

	protected function configure() {
		$this->setName("plugins:desactiver")
			->setDescription("Désactive un ou plusieurs plugins")
			->addArgument('prefixes', InputArgument::OPTIONAL|InputArgument::IS_ARRAY, 'Liste des préfixes à désactiver')
			->addOption('all', 'a', InputOption::VALUE_NONE, 'Désactive tous les plugins actifs')
			->addOption('yes', 'y', InputOption::VALUE_NONE, 'Désctiver les plugins sans poser de question')
		;
	}


	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->demarrerSpip();
		$this->io->title("Désactiver des plugins");
		$this->actualiserPlugins();

		$prefixes = $input->getArgument('prefixes');

		if ($input->getOption('all')) {
			$prefixes = array_column($this->getPluginsActifs(['procure' => false, 'php' => false, 'dist' => false]), 'prefixe');
		}

		if (!$prefixes) {
			$prefixes = $this->getPrefixesFromQuestion();
			if (!$prefixes) {
				$this->getApplication()->showHelp('plugins:desactiver', $output);
				return;
			}
		}

		$this->io->text("Liste des plugins à désactiver :");
		$this->presenterListe($prefixes);

		if (
			!$input->getOption('yes')
			and !$this->io->confirm("Les plugins listés au-dessus seront désactivés. Confirmez-vous ?", false)
		) {
			$this->io->care("Action annulée");
			return;
		}

		$this->desactiverPlugins($prefixes);
	}

	/* Si pas de plugin(s) spécifiés, on demande */
	public function getPrefixesFromQuestion() {
		$inactifs = array_column($this->getPluginsActifs(['dist' => false]), 'prefixe');
		$question = new Question("Quel plugin faut-il désactiver ?\n", 'help');
		$question->setAutoCompleterValues($inactifs);
		$reponse = trim($this->io->askQuestion($question));
		if ($reponse === 'help') {
			return false;
		}
		return explode(' ', $reponse);
	}


	public function desactiverPlugins($prefixes) {
		if (!count($prefixes)) {
			$this->io->care("Aucun prefixe à désactiver");
			return true;
		}

		$actifs = array_column($this->getPluginsActifs(), 'prefixe');

		if ($deja = array_diff($prefixes, $actifs)) {
			$prefixes = array_diff($prefixes, $deja);
			if ($prefixes) {
				$this->io->text("Certains préfixes demandés sont déjà inactifs :");
				$this->presenterListe($deja);
			} else {
				$this->io->check("Tous les préfixes demandés sont déjà inactifs");
				return true;
			}
		}

		$desactiver = [];
		foreach ($this->getPluginsActifs() as $plugin) {
			$prefixe = $plugin['prefixe'];
			if (in_array($prefixe, $prefixes)) {
				$desactiver[] = $plugin['dir'];
				$prefixes = array_diff($prefixes, [$prefixe]);
			}
		}

		ecrire_plugin_actifs($desactiver, false, 'enleve');
		$actifs = $this->getPluginsActifs(['procure' => false, 'php' => false]);
		$this->io->text("Plugins actifs après action :");
		$this->showPlugins($actifs);
		$this->showPluginsErrors();
		$this->actualiserSVP();
	}
}
