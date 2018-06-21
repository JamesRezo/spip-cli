<?php

namespace Spip\Cli\Command;

use Spip\Cli\Loader\Spip;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class PluginsActiver extends PluginsLister
{

	protected $todo = [];

	protected function configure() {
		$this
			->setName('plugins:activer')
			->setDescription('Active un ou plusieurs plugins.')
			->addArgument('from', InputArgument::OPTIONAL | InputArgument::IS_ARRAY, 'Active les plugins listés. Détermine automatiquement l’option from-xxx.')
			->addOption('from-file', null, InputOption::VALUE_OPTIONAL, 'Chemin d’un fichier d’export')
			->addOption('from-url', null, InputOption::VALUE_OPTIONAL, 'Url d’un site SPIP')
			->addOption('from-list', null, InputOption::VALUE_OPTIONAL, 'Liste de préfixes à activer, séparés par virgule')
			->addOption('all', 'a', InputOption::VALUE_NONE, "Activer tous les plugins disponibles.")
			->addOption('yes', 'y', InputOption::VALUE_NONE, 'Activer les plugins sans poser de question');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->io = $this->getApplication()->getIO($input, $output);

		/** @var Spip $spip */
		$spip = $this->getApplication()->getService('spip.loader');
		$spip->load();
		$spip->chdir();

		if ($input->getOption('from-file')) {
			$this->addTodo($this->getPrefixesFromFile($input->getOption('from-file')));
		} elseif ($input->getOption('from-url')) {
			$this->addTodo($this->getPrefixesFromUrl($input->getOption('from-url')));
		} elseif ($input->getOption('from-list')) {
			$this->addTodo(explode(',', $input->getOption('from-list')));
		} elseif ($input->getArgument('from')) {
			$from = $input->getArgument('from');
			foreach ($from as $quoi) {
				if (preg_match(',^https?://,', $quoi)) {
					$this->addTodo($this->getPrefixesFromUrl($quoi));
				} elseif (strpbrk($quoi, '.\\/')) {
					$this->addTodo($this->getPrefixesFromFile($quoi));
				} else {
					$this->addTodo([$quoi]);
				}
			}
		} elseif ($input->getOption('all')) {
			$this->addTodo(array_column($this->getPluginsInactifs(), 'nom'));
		} else {
			$plugins = $this->getPrefixesFromQuestion();
			if (!$plugins) {
				$this->showHelp($input, $output);
				return;
			}
			$this->addTodo($plugins);
		}

		if (!$liste = $this->getTodo()) {
			$this->io->care("Aucun prefixe à activer");
			return;
		}
		$this->io->text("Liste des plugins à activer :");
		$this->presenterListe($liste);

		if (
			!$input->getOption('yes')
			and !$this->io->confirm("Les plugins listés au-dessus seront activés. Confirmez-vous ?", false)
		) {
			$this->io->care("Action annulée");
			return;
		}

		$this->actualiserPlugins();
		$this->activePlugins($liste);
	}

	/* Si pas de plugin(s) spécifiés, on demande */
	public function getPrefixesFromQuestion() {
		$io = $this->io;
		$inactifs = array_map('strtolower', array_column($this->getPluginsInactifs(), 'nom'));
		$question = new Question("Quel plugin faut-il activer ?\n", 'help');
		$question->setAutoCompleterValues($inactifs);
		$reponse = trim($io->askQuestion($question));
		if ($reponse === 'help') {
			return false;
		}
		return explode(' ', $reponse);
	}

	public function showHelp(InputInterface $input, OutputInterface $output) {
		$command = $this->getApplication()->find('help');
		$arguments = array(
			'command' => 'help',
			'command_name' => 'plugins:activer',
		);
		$input = new ArrayInput($arguments);
		$command->run($input, $output);
	}

	function confirmerActivation(InputInterface $input) {

		/* Et enfin, on désactive le(s) plugin(s) */
		$dir_un = array();
		foreach ($plugins as $prefixe) {
			if (!in_array($prefixe, $disponibles)) {
				$output->writeln("<error>Le plugin $prefixe est introuvable dans les plugins disponibles.</error>");
			} else if (!in_array($prefixe, $inactifs)) {
				$output->writeln("<comment>Le plugin $prefixe est déjà activé.</comment>");
			} else {
				include_spip('base/abstract_sql');
				$p = sql_fetsel('src_archive, constante', 'spip_paquets', array('constante!=""', 'prefixe=' . sql_quote($prefixe)));

				$dir = constant($p['constante']) . $p['src_archive'];
				$output->writeln("<info>Active le plugin $prefixe (repertoire $dir)</info>");

				$dirs_un[] = $p['src_archive'];
			}
		}

		if (count($dirs_un)) {
			include_spip('inc/plugin');
			ecrire_plugin_actifs($dirs_un, false, 'ajoute');
			/* actualiser la liste des paquets locaux */
			include_spip('inc/svp_depoter_local');
			/*sans forcer tout le recalcul en base, mais en
			  récupérant les erreurs XML */
			$err = array();
			svp_actualiser_paquets_locaux(false, $err);
			if ($err) {
				$output->writeln("<error>Erreur XML $err</error>");
			}
		}

	}

	/**
	 * Chercher un fichier qui contient la liste des préfixes à activer
	 *
	 * En mutualisation, chercher de préférence un fichier relatif au site
	 *
	 * @param string $file
	 * @return string[]
	 * @throws \Exception
	 */
	public function getPrefixesFromFile($file) {
		if (
			$file
			and defined('_DIR_SITE')
			and is_file(_DIR_SITE . $file)
		) {
			$file = _DIR_SITE . $file;
		} elseif (!is_file($file)) {
			throw new \Exception("File doesn't exists : " . $file);
		}
		$list = file_get_contents($file);
		return explode(' ', $list);
	}

	public function getPrefixesFromUrl($url) {
		// si on a un fichier local/config.txt on le prend en priorite
		exec("curl -L --silent $url/local/config.txt", $head);
		$head = implode("\n", $head) . "\n";
		if (!preg_match(",^Composed-By:(.*)\n,Uims", $head, $m)) {
			exec("curl -I -L --silent $url", $head);
			$head = implode("\n", $head);
		}
		if (preg_match(",^Composed-By:(.*)\n,Uims", $head, $m)) {
			// virer les numeros de version
			$liste = preg_replace(",\([^)]+\),", "", $m[1]);
			$liste = explode(",", $liste);
			$liste = array_map('trim', $liste);
			array_shift($liste);
		}
		return $liste;
	}

	public function addTodo(array $prefixes) {
		$prefixes = array_map('trim', $prefixes);
		$prefixes = array_map('strtolower', $prefixes);
		$this->todo = array_unique(array_merge($this->todo, $prefixes));
	}

	public function getTodo() {
		return $this->todo;
	}

	public function activePlugins($prefixes) {
		if (!count($prefixes)) {
			$this->io->care("Aucun prefixe à activer");
			return true;
		}
		$actifs = array_keys($this->getPluginsActifs());
		$actifs = array_map('strtolower', $actifs);

		if ($deja = array_intersect($actifs, $prefixes)) {
			$prefixes = array_diff($prefixes, $actifs);
			if ($prefixes) {
				$this->io->text("Certains préfixes demandés sont déjà actifs :");
				$this->presenterListe($deja);
			} else {
				$this->io->check("Tous les préfixes demandés sont déjà actifs");
				return true;
			}
		}

		$inactifs = $this->getPluginsInactifs();
		$activer = [];
		foreach ($inactifs as $plugin) {
			$prefixe = strtolower($plugin['nom']);
			if (in_array($prefixe, $prefixes)) {
				$activer[] = $plugin['dir'];
				$prefixes = array_diff($prefixes, [$prefixe]);
			}
		}

		if (count($prefixes)) {
			$this->io->fail("Certains préfixes demandés sont introuvables :");
			$this->presenterListe($prefixes);
		}

		if (count($activer)) {
			ecrire_plugin_actifs($activer, false, 'ajoute');
			$actifs = $this->getPluginsActifs(['procure' => false, 'php' => false]);
			$this->io->text("Plugins actifs après action :");
			$this->showPlugins($actifs);
			$this->actualiserSVP();
		}
	}

	public function actualiserSVP() {
		/* actualiser la liste des paquets locaux */
		include_spip('inc/svp_depoter_local');
		/* sans forcer tout le recalcul en base, mais en
		  récupérant les erreurs XML */
		$err = array();
		svp_actualiser_paquets_locaux(false, $err);
		if ($err) {
			$this->io->care("Erreurs XML présentes :");
			$this->io->care($err);
		}
	}
}
