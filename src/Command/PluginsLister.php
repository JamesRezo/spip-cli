<?php

namespace Spip\Cli\Command;

use Spip\Cli\Console\Command;
use Spip\Cli\Console\Style\SpipCliStyle;
use Spip\Cli\Loader\Spip;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class PluginsLister extends Command {

	protected function configure() {
		$this->setName("plugins:lister")
			->setDescription("Liste les plugins du site.")
			->addOption('dist', null, InputOption::VALUE_NONE, 'Uniquement les plugins dist')
			->addOption('no-dist', null, InputOption::VALUE_NONE, 'Exclure les plugins dist')
			->addOption('procure', null, InputOption::VALUE_NONE, 'Uniquement les plugins procurés')
			->addOption('php', null, InputOption::VALUE_NONE, 'Uniquement les extensions PHP présentes')
			->addOption('spip', null, InputOption::VALUE_NONE, 'Uniquement SPIP')
			->addOption('short', null, InputOption::VALUE_NONE, 'Affiche simplement le préfixe')

			->addOption('inactifs', null, InputOption::VALUE_NONE, 'Liste les plugins inactifs.')

			->addOption('export', 'e', InputOption::VALUE_NONE, 'Exporter la liste des plugins actifs dans un fichier')
			->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Nom du fichier d’export', 'plugins')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {

		$this->demarrerSpip();
		$this->io->title("Liste des plugins");

		$this->actualiserPlugins();

		if ($input->getOption('inactifs')) {
			$this->showInactifs($input);
		} elseif ($input->getOption('export')) {
			$this->exportActifs($input);
		} else {
			$this->showActifs($input);
		}
	}

	public function showActifs(InputInterface $input) {
		$options = [
			'dist' => null,
			'procure' => false,
			'php' => false,
			'spip' => false,
		];
		$list = [];

		if ($input->getOption('php')) {
			$options['php'] = true;
			$options['procure'] = true;
			$list[] = 'Uniquement les extensions PHP procurées';
		}

		if ($input->getOption('procure')) {
			$options['procure'] = true;
			$list[] = 'Uniquement les plugins procurés';
		}

		if ($input->getOption('dist')) {
			$options['dist'] = true;
			$list[] = 'Uniquement les plugins-dist';
		} elseif ($input->getOption('no-dist')) {
			$options['dist'] = false;
			$list[] = 'Sans les plugins-dist';
		}

		if ($input->getOption('spip')) {
			$options['spip'] = true;
			$list[] = 'Uniquement SPIP';
		}

		if ($list) {
			$this->io->listing($list);
		}

		$actifs = $this->getPluginsActifs($options);
		$this->showPlugins($actifs, $input->getOption('short'));
	}

	public function showInactifs(InputInterface $input) {
		$list = ["Liste des plugins inactifs"];
		$this->io->listing($list);
		$inactifs = $this->getPluginsInactifs();
		$this->showPlugins($inactifs);
	}

	public function getExportFile(InputInterface $input) {
		$name = $input->getOption('name') . '.txt';
		$file = _DIR_TMP . $name;
		return _DIR_TMP . $name;
	}
	
	public function exportActifs(InputInterface $input) {
		$file = $this->getExportFile($input);
		
		$actifs = $this->getPluginsActifs([
			'procure' => false,
			'php' => false,
		]);

		$list = implode(" ", array_map('strtolower', array_keys($actifs)));
		if (file_put_contents($file, $list)) {
			$this->io->check("Export dans : " . $file);
		} else {
			$this->io->fail("Export raté : " . $file);
		}
		$this->io->text($list);
		// $this->io->columns(explode(" ", $list), 6, true);
	}

	public function actualiserPlugins() {
		include_spip('inc/plugin');
		actualise_plugins_actifs();
	}

	/**
	 * Obtenir la liste des plugins actifs
	 *
	 * @param array $options {
	 *     @var bool|null $php Afficher|Exclure|Uniquement les extensions PHP
	 *     @var bool $spip Afficher le faux plugin 'SPIP'
	 *     @var bool|null $dist Afficher|Exclure|Uniquement les plugins dist
	 * }
	 * @return array
	 */
	public function getPluginsActifs(array $options = []) {
		$options += [
			'procure' => null,
			'php' => null,
			'spip' => false, // only SPIP ?
			'dist' => null,
		];
		$plugins = unserialize($GLOBALS['meta']['plugin']);
		if ($options['spip']) {
			return ['SPIP' => array_merge(['prefixe' => 'spip'], $plugins['SPIP'])];
		} else {
			unset($plugins['SPIP']);
		}

		foreach ($plugins as $k => $v) {
			$plugins[$k] = array_merge(['prefixe' => strtolower($k)], $v);
			$is = [
				'php' => ($k === 'PHP' or strpos($k, 'PHP:') === 0),
				'dist' => ($v['dir_type'] === '_DIR_PLUGINS_DIST'),
				'procure' => (strpos($v['dir'], 'procure:') !== false),
			];
			foreach ($is as $quoi => $test) {
				if (!is_null($options[$quoi]) and ($options[$quoi] xor $is[$quoi])) {
					unset($plugins[$k]);
				}
			}
		}

		return $plugins;
	}

	public function getPluginsInactifs() {
		include_spip('inc/plugin');
		// chercher dans les plugins dispo
		$get_infos = charger_fonction('get_infos','plugins');

		$dirs = ['_DIR_PLUGINS' => _DIR_PLUGINS];
		if (defined('_DIR_PLUGINS_SUPPL') and _DIR_PLUGINS_SUPPL) {
			$dirs['_DIR_PLUGINS_SUPPL'] = _DIR_PLUGINS_SUPPL;
		}

		$list = [];
		foreach($dirs as $const => $dp) {
			$plugins = liste_plugin_files($dp);
			foreach($plugins as $dir){
				$infos = $get_infos($dir, false, $dp);
				$list[] = [
					'prefixe' => strtolower($infos['prefix']),
					'etat' => $infos['etat'],
					'version' => $infos['version'],
					'dir' => $dir,
					'dir_type' => $const,
				];
			}
		}
		return $list;
	}

	public function showPlugins(array $list, $short = false) {
		ksort($list);
		if ($short) {
			$list = array_keys($list);
			$list = array_map('strtolower', $list);
			$list = array_map('ucfirst', $list);
			$this->io->columns($list, 6, true);
		} else {
			$this->io->atable($list);
		}
	}


	public function presenterListe(array $liste) {
		if (count($liste) > 4) {
			$this->io->columns($liste, 6, true);
		} else {
			$this->io->listing($liste);
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