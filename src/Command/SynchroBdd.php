<?php

namespace Spip\Cli\Command;

use Spip\Cli\Application;
use Spip\Cli\Console\Command;
use Spip\Cli\Loader\Spip;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class SynchroBdd extends Command
{

	/** @var Application */
	protected $app;

	protected function configure() {
		$this->setName("synchro:bdd")
			->setDescription("Synchroniser la BDD du SPIP depuis celle d'un autre site (avec maj des metas pour conserver les paramètres spécifiques du SPIP, cf config générée via synchro:init)")
			->addOption('rsync', 'r', InputOption::VALUE_NONE, 'Jouer rsync')
			->addOption('backup', 'b', InputOption::VALUE_NONE, 'backup bdd')
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
		 * Debut du script
		 */
		$this->io->title('Début du script');

		/*
		 * Synchro BDD
		 */
		if ($config->config_ssh and $config->config_mysql_local and $config->config_mysql_serveur) {
			if ($this->verbeux) {
				$this->io->section('Debut synchro bdd');
			}
			$this->synchroBdd($config->config_ssh, $config->config_mysql_local, $config->config_mysql_serveur, $input->getOption('backup'));
		}

		/*
		 * maj de spip_meta
		 */
		if ($config->metas) {
			if ($this->verbeux) {
				$this->io->section('Maj des metas');
			}
			$this->majMeta($config->metas);
			$this->io->success('maj des metas');
		}

		/*
		 * Rsync
		 */
		if ($input->getOption('rsync') and $config->rsync and $config->config_ssh) {
			if ($this->verbeux) {
				$this->io->section('Debut rsync');
			}
			SynchroFichiers::lancerRsync($config->rsync, $config->config_ssh, $this->verbeux, $this->io);
		}
	}


	protected function synchroBdd($ssh, $local, $serveur, $forcer_backup) {
		$passServeur = '-p';
		if ($serveur->pwd) {
			$passServeur = '--password="'.$serveur->pwd.'"';
		}
		$passLocal = '';
		if ($local->pwd) {
			$passLocal = '--password="'.$local->pwd.'"';
		}

		/*
		 * Doit on faire un backup de la bdd
		 */
		if ($forcer_backup or $local->backup) {
			$name_backup = $local->bdd.'_'.time().'.sql';
			$commande_backup ="mysqldump -u $local->user $passLocal --opt $local->bdd > $name_backup";
			if ($this->verbeux) {
				$this->io->text('commande backup :');
				$this->io->text($commande_backup);
			}
			passthru($commande_backup, $retour);
			if ($retour != "0"){
				$this->io->error('Erreur backup bdd');
			} else {
				$this->io->success('backup bdd : '.$name_backup);
			}
		}

		if ($ssh->host) {
			$SSH = $ssh->host;
		} else {
			$SSH = "$ssh->user@$ssh->hostName";
			if ($ssh->port) {
				$SSH .= " -i $ssh->chemin_cle -p $ssh->port";
			}
		}
		$commande_ssh ="ssh $SSH 'mysqldump -u $serveur->user $passServeur --opt $serveur->bdd' |mysql -u $local->user $passLocal $local->bdd";
		if ($this->verbeux) {
			$this->io->text('commande ssh :');
			$this->io->text($commande_ssh);
		}
		passthru($commande_ssh,$retour);
		if ($retour != "0"){
			return $this->io->error('Erreur de Synchro de bdd');
		} else {
			return $this->io->success('synchro bdd');
		}

	}

	protected function majMeta($metas) {
		include_spip('inc/config');
		foreach ($metas as $meta => $valeur) {
			if ($this->verbeux) {
				$this->io->text("meta $meta ==> $valeur");
			}
			ecrire_config($meta, $valeur);
		}
	}

	protected function recupConfig() {
		$config = @file_get_contents('synchroSPIP.json');
		$config = json_decode($config);
		return $config;
	}
}
