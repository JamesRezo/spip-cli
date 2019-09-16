<?php

namespace Spip\Cli\Command;

use Spip\Cli\Application;
use Spip\Cli\Console\Command;
use Spip\Cli\Loader\Spip;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class SynchroInit extends Command
{

	/** @var Application */
	protected $app;

	protected function configure() {
		$this->setName("synchro:init")
			->setDescription("Initialiser le json de configuration pour la synchro")
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		/** @var Spip $spip */

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

		if ($GLOBALS['spip_loaded']) {
			chdir($GLOBALS['spip_racine']);
			$this->initialiser_json($connect);
		}
	}

	protected function initialiser_json($connect){
		if (!file_exists('synchroSPIP.json')) {
			$json = array(
				'config_ssh' => array (
					'host'     => '',
					'user'     => '',
					'port'     => '',
					'nom_cle'  => 'id_rsa',
					'hostName' => ''
				),
				'config_mysql_local' => array (
					'user'   => $connect['1'],
					'pwd'    => $connect['2'],
					'bdd'    => $connect['3'],
					'backup' => false
				),
				'config_mysql_serveur' => array (
					'user' => '',
					'pwd'  => '',
					'bdd'  => ''
				),
				'rsync' => array (
					'IMG' => ''
				),
				'metas' => array (
					"adresse_site"      => $GLOBALS['meta']['adresse_site'],
					"auto_compress_js"  => "non",
					"auto_compress_css" => "non",
					"image_process"     => "gd2"
				)
			);

			$dest = 'synchroSPIP.json';
			$fichier = json_encode($json,JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES);
			if (ecrire_fichier($dest,$fichier)) {
				$this->io->success('Création du fichier synchroBdd.json');
			} else {
				$this->io->error('Erreur de création du fichier synchroBdd.json');
			}
		} else {
			$this->io->text('le fichier synchroBdd.json existe déjà !');
		}

	}


}
