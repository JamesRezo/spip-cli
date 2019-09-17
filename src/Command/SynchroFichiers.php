<?php

namespace Spip\Cli\Command;

use Spip\Cli\Application;
use Spip\Cli\Console\Command;
use Spip\Cli\Loader\Spip;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class SynchroFichiers extends Command
{

	/** @var Application */
	protected $app;

	protected function configure() {
		$this->setName("synchro:fichiers")
			->setDescription("Synchroniser un site BDD / maj des metas / option : rsync d'un dossier")
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		/** @var Spip $spip */
		//$this->demarrerSpip();

		/*
		 * SPIP est t'il installe
		 */
		include_spip('inc/install');
		if (!_FILE_CONNECT) {
			$this->io->error('Il faut que le SPIP soit installé');
			return;
		}

		/*
		 * Pour l'instant, cela ne fonctionne que pour une bdd en mysql
		 */
		$connect = analyse_fichier_connection(_FILE_CONNECT);
		$type_bdd = $connect['4'];
		if ($type_bdd !== 'mysql') {
			$this->io->error('La synchro ne fonctionne qu\'avec une bdd en mysql');
			return;
		}

		/*
		 * Affichage verbeux avec option -v
		 */
		if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
			$this->verbeux = true;
		} else {
			$this->verbeux = false;
		}

		$config = $this->recupConfig();
		if(empty($config)) {
			$this->io->error('le fichier de configuration synchroSPIP.json est vide ou inexistant');
			return;
		}
		/*
		 * Debut du script Rsync
		 */
		$this->io->title('Début du script');

		if ($this->verbeux) {
			$this->io->section('Debut rsync');
		}
		$this->lancerRsync($config->rsync, $config->config_ssh, $this->verbeux, $this->io);
	}

	public function lancerRsync($Trsync, $config, $verbeux, $io) {
		foreach ($Trsync as $local => $distant) {
			if ($verbeux) {
			}
				$io->text("copie : $distant -> $local");
			if ($local and $distant) {
				$io->text('');
				$port = $config->port ? $config->port : 22;
				if ($config->host) {
					$SSH = $config->host;
				} else {
					$SSH = "$config->user@$config->hostName";
				}
				$cle_ssh= '';
				$port = '';
				if ($config->chemin_cle) {
					if ($config->port) {
						$port = "-p $config->port";
					}
					$cle_ssh = "-i $config->chemin_cle $port";
				}
				$args = $verbeux ? "-azv" : "-az";
				$commande_rsync = "rsync -e 'ssh $cle_ssh' $args --delete-after $SSH:$distant $local";
				if ($verbeux) {
					$io->text('commande rsync :');
					$io->text($commande_rsync);
					$io->text('');
				}
				passthru($commande_rsync,$retour);
				if ($retour != "0"){
					$io->error('Erreur Rsync');
				} else {
					$io->success('rsync');
				}
			}
		}
	}

	protected function recupConfig() {
		$config = @file_get_contents('synchroSPIP.json');
		$config = json_decode($config);
		return $config;
	}
}
