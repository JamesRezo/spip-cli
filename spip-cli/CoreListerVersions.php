<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CoreListerVersions extends Command {
	private $chemin_svn_racine = 'svn://trac.rezo.net/spip';
	
	protected function configure() {
		$this
			->setName('core:listerversions')
			->setDescription('Liste les versions de SPIP')
			//~ ->addOption(
				//~ 'branche',
				//~ 'b',
				//~ InputOption::VALUE_OPTIONAL,
				//~ 'Donner explicitement la version à télécharger.',
				//~ '3.2' // Par défaut, la dernière version stable
			//~ )
			->setAliases(array(
				'versions'
			))
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		// On travaille dans le dossier courant
		$dossier = getcwd();
		
		//~ $branche = $input->getOption('branche');
		
		//~ // On vérifie qu'on est pas déjà dans une installation de SPIP !
		//~ if ($GLOBALS['spip_loaded']) {
			//~ $output->writeln('<info>Vous êtes déjà dans une installation de SPIP '.$GLOBALS['spip_version_branche'].'.</info> <comment>Téléchargement annulé.</comment>');
		//~ }
		//~ // Sinon c'est bon on peut télécharger SPIP
		//~ else {
			//~ // On vérifie que l'on connait la version
			//~ if (!in_array($branche, array_keys($branches_ok))){
				//~ $output->writeln(array(
					//~ "<error>La version \"$branche\" n'est pas prise en charge.</error>",
					//~ 'Branches supportées : <info>'.join('</info>, <info>', array_keys($branches_ok)).'</info>'
				//~ ));
			//~ }
			//~ // Si c'est bon, on teste si on peut utiliser "passthru"
			//~ else
			
		if (!function_exists('passthru')) {
			$output->writeln("<error>Votre installation de PHP doit pouvoir exécuter des commandes externes avec la fonction passthru().</error>");
		}
		// Si c'est bon on continue
		else{
			$versions = $this->lister_versions();
			
			foreach ($versions as $type => $numeros) {
				$output->writeln("<question>$type</question>");
				
				foreach ($numeros as $numero) {
					$output->writeln("$numero");
				}
			}
		}
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
			$versions['branches'] = $this->svn_to_array($liste_branches);
		}
		
		if ($type != 'tags') {
			// On cherche les tags
			ob_start();
			passthru("svn list {$this->chemin_svn_racine}/tags");
			$liste_tags = ob_get_contents();
			ob_end_clean();
			
			// On transforme en tableau et nettoie
			$versions['tags'] = $this->svn_to_array($liste_tags);
		}
		
		return $versions;
	}
	
	private function svn_to_array($svn) {
		$liste = explode("\n", $svn);
		
		foreach ($liste as $cle=>$dossier) {
			$liste[$cle] = preg_replace('|(spip-)?(.*?)(-stable)?/?|i', '$2', $dossier);
		}
		$liste = array_filter(array_unique($liste));
		
		return $liste;
	}
}
