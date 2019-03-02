<?php

namespace Spip\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CoreListerVersions extends Command {
	private $chemin_svn_racine = 'svn://trac.rezo.net/spip';
	private $versions = array();
	private $last = '';
	
	protected function configure() {
		$this
			->setName('core:listerversions')
			->setDescription('Liste les versions de SPIP')
			->addOption(
				'type',
				't',
				InputOption::VALUE_OPTIONAL,
				'branches ou tags ?',
				'' // Par défaut, tout
			)
			->setAliases(array(
				'versions'
			))
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		// On travaille dans le dossier courant
		$dossier = getcwd();
		
		$type = $input->getOption('type');
		
		if (!function_exists('passthru')) {
			$output->writeln("<error>Votre installation de PHP doit pouvoir exécuter des commandes externes avec la fonction passthru().</error>");
		}
		// Si c'est bon on continue
		else{
			$versions = $this->get_versions();
			
			// Seulement ce type
			if (array_key_exists($type, $versions)) {
				$versions = array_intersect_key($versions, array($type=>'yes'));
			}
			
			foreach ($versions as $type => $numeros) {
				$output->writeln("<question>$type</question>");
				
				foreach ($numeros as $numero => $url) {
					$output->writeln("$numero");
				}
			}
		}
	}
	
	public function get_last_release($xy='') {
		$versions = $this->get_versions();
		$tags = array_flip($versions['tags']);
		
		if ($xy) {
			$masque = "/^$xy\.\d+$/";
		}
		else {
			$masque = '/^\d+\.\d+\.\d+$/';
		}
		
		// On ne garde que les trucs stables
		$stables = array_filter(
			$tags,
			function ($cle) use ($masque) {
				return preg_match($masque, $cle);
			}
		);
		
		// On ne renvoit que la dernière version
		natsort($stables);
		return array_pop($stables);
	}
	
	public function get_last_branche($x='') {
		$versions = $this->get_versions();
		$branches = array_flip($versions['branches']);
		
		if ($x) {
			$masque = "/^$x.\d+$/";
		}
		else {
			$masque = '/^\d+\.\d+$/';
		}
		
		// On ne garde que les trucs stables
		$stables = array_filter(
			$branches,
			function ($cle) use ($masque) {
				return preg_match($masque, $cle);
			}
		);
		
		// On ne renvoit que la dernière version
		natsort($stables);
		return array_pop($stables);
	}
	
	public function get_versions() {
		if (!$this->versions) {
			$this->versions = $this->lister_versions();
		}
		
		return $this->versions;
	}
	
	public function lister_versions($type='') {
		$versions = array();
		
		if ($type != 'tags') {
			// On cherche les branches
			ob_start();
			passthru("svn list {$this->chemin_svn_racine}/branches");
			$liste_branches = ob_get_contents();
			ob_end_clean();
			
			// On transforme en tableau et nettoie
			$versions['branches'] = $this->svn_to_array($liste_branches, 'branches');
			$versions['branches']['trunk'] = 'svn://trac.rezo.net/spip/spip';
		}
		
		if ($type != 'tags') {
			// On cherche les tags
			ob_start();
			passthru("svn list {$this->chemin_svn_racine}/tags");
			$liste_tags = ob_get_contents();
			ob_end_clean();
			
			// On transforme en tableau et nettoie
			$versions['tags'] = $this->svn_to_array($liste_tags, 'tags');
		}
		
		return $versions;
	}
	
	private function svn_to_array($svn, $type) {
		$liste = array();
		$temp = explode(PHP_EOL, $svn);
		
		foreach ($temp as $dossier) {
			if ($cle = preg_replace('|(spip-)?(.*?)/?|i', '$2', $dossier)) {
				$liste[$cle] = "{$this->chemin_svn_racine}/$type/$dossier";
			}
		}
		$liste = array_filter(array_unique($liste));
		
		return $liste;
	}
}
