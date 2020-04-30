<?php

namespace Spip\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CoreTelecharger extends Command {
	// Méthode de téléchargement, par défaut SPIP entier
	protected $methode = 'spip';
	// La source de ce qu'on veut télécharger
	protected $source = '?';
	// Le dossier où télécharger, par défaut le dossier courant
	protected $dest = '.';
	// Branche ou tag demandé
	protected $branche = '';
	// Révision
	protected $revision = '';
	// Le type de version qui sera téléchargé, par défaut une release fixe (tag)
	protected $version_type = 'release';
	// La version demandée au départ, précise ou flou suivant une branche
	protected $version_demandee = '';
	// La version précise qui sera téléchargée
	protected $version_precise = '';
	// L'URL SVN où télécharger la version
	protected $url = '';
	
	protected function configure() {
		$this
			->setName('core:telecharger')
			->setDescription('Télécharger SPIP dans un dossier (par défaut, la dernière version stable)')
			->addArgument(
				'methode',
				InputArgument::OPTIONAL,
				'Méthode de téléchargement, pouvant être "spip" pour avoir la distribution, sinon "git", "svn", ou "ftp".',
				'spip'
			)
			->addArgument(
				'source',
				InputArgument::OPTIONAL,
				'URL du dépôt à télécharger',
				'?'
			)
			->addArgument(
				'dest',
				InputArgument::OPTIONAL,
				'Répertoire où télécharger',
				'.'
			)
			->addOption(
				'branche',
				'b',
				InputOption::VALUE_OPTIONAL,
				'Donner explicitement la branche ou le tag à télécharger'
			)
			->addOption(
				'release',
				'R',
				InputOption::VALUE_OPTIONAL,
				'Donner explicitement la release à télécharger ou bien la branche où chercher la dernière release (X.Y.Z ou X.Y ou X)'
			)
			->addOption(
				'revision',
				'r',
				InputOption::VALUE_OPTIONAL,
				'Pointer sur une révision donnée'
			)
			->addOption(
				'info',
				'i',
				InputOption::VALUE_OPTIONAL,
				'Lire les informations du répertoire'
			)
			->addOption(
				'logupdate',
				'l',
				InputOption::VALUE_OPTIONAL,
				'Afficher le log des commits à mettre à jour'
			)
			->addOption(
				'options',
				'o',
				InputOption::VALUE_OPTIONAL,
				'Ajouter des options supplémentaires à la commande finale, entre quotes'
			)
			->setAliases(array(
				'dl' // abbréviation commune pour "download"
			))
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		// On teste si on peut utiliser "passthru"
		if (!function_exists('passthru')){
			$output->writeln("<error>Votre installation de PHP doit pouvoir exécuter des commandes externes avec la fonction passthru().</error>");
		}
		else {
			// Quel dossier final ? Par défaut le dossier courant .
			if ($dest = $input->getArgument('dest')) {
				$this->dest = rtrim($dest, '/');
			}
			
			// Quelle méthode ?
			if ($methode = $input->getArgument('methode') and in_array($methode, array('spip', 'git', 'svn', 'ftp'))) {
				$this->methode = $methode;
			}
			
			//La source
			$this->source = $input->getArgument('source');
			// La branche
			$this->branche = $input->getOption('branche');
			// La révision
			$this->revision = $input->getOption('revision');
			
			// Si on cherche juste à lire les infos
			if ($input->hasParameterOption(array('--info', '-i'))) {
				$this->lire_info($input, $output);
			}
			// Si on cherche juste à avoir les logs des chsoes à mettre à jour
			elseif ($input->hasParameterOption(array('--logupdate', '-l'))) {
				$this->logupdate($input, $output);
			}
			// Sinon c'est pour un téléchargmement ou une mise à jour
			else {
				$this->checkout($input, $output);
			}
			
			//~ // On détermine la demande de départ
			//~ $this->version_demandee = $input->getOption('release');
			//~ // Si pas de release prioritaire et qu'il y a une demande de branche
			//~ if (!$this->version_demandee and $input->hasParameterOption(array('--branche', '-b'))) {
				//~ $this->version_type = 'branche';
				//~ $this->version_demandee = $input->getOption('branche');
			//~ }
			
			//~ // Si on est déjà dans un SPIP, on cherche à faire une mise à jour
			//~ if ($GLOBALS['spip_loaded']) {
				//~ // On se déplace dans la racine
				//~ chdir($GLOBALS['spip_racine']);
				
				//~ // On lance la recherche de mise à jour
				//~ $this->mettre_a_jour($input, $output);
			//~ }
			//~ // Sinon on cherche à télécharger une nouvelle version
			//~ else {
				//~ $this->telecharger($input, $output);
			//~ }
		}
	}
	
	/**
	 * Lire les infos du répertoire choisi
	 */
	protected function lire_info(&$input, &$output) {
		$methodes = array('git', 'svn', 'ftp');
		
		foreach($methodes as $methode) {
			if (
				method_exists($this, $fonction_info = $methode.'_info') 
				and $info = $this->$fonction_info()
			) {
				$output->writeln(array("<info>$info</info>"));
				
				// On s'arrête
				return;
			}
		}
		
		$output->writeln(array("<error>Impossible de trouver la source du répertoire {$this->dest}</error>"));
	}
	
	/**
	 * Logs des commits plus recents disponibles pour une mise a jour
	 * 
	 * @param string $dest
	 * @param array $options
	 * @return string
	 */
	function logupdate(&$input, &$output) {
		$methodes = array('git', 'svn');
		
		foreach($methodes as $methode) {
			if (
				method_exists($this, $fonction_info = $methode.'_info')
				and $infos = $this->$fonction_info('assoc')
				and method_exists($this, $fonction_log = $methode.'_log')
			) {
				$options = [
					"from"=>$infos['revision']
				];
				
				if ($branche = $input->getOption('branche')) {
					$options['branche'] = $branche;
				}
				
				$log = $this->$fonction_log($options);
				
				if (strlen($log)) {
					$output->writeln(array(
						"<info>Mise à jour disponible pour {$infos['source']} :</info>",
						$log
					));
				}
				
				// On s'arrête
				return;
			}
		}
	}
	
	/**
	 * Lancer un checkout
	 *
	 * @param string $methode
	 * @param string $source
	 * @param string $dest
	 * @param array $options
	 * @return string
	 */
	function checkout(&$input, &$output) {
		if (!method_exists($this, $checkout = $this->methode . '_checkout')) {
			$output->writeln(array("<error>Méthode {$this->methode} inconnue pour télécharger {$this->source} vers {$this->dest}</error>"));
		}
		else {
			$this->$checkout($input, $output);
		}
	}
	
	/**
	 * Fausse méthode raccourcie pour checkout SPIP complet
	 * 
	 * @param $source
	 * @param $dest
	 * @param $options
	 */
	function spip_checkout(&$input, &$output) {
		$url_repo_base = "https://git.spip.net/spip/";
		if ($this->source and strpos($this->source, "git@git.spip.net") !== false) {
			$url_repo_base = "git@git.spip.net:spip/";
		}
		$branche = $this-> branche ? $this->branche : 'master';
		
		// Pour le noyau
		$this->methode = 'git';
		$this->source = $url_repo_base . 'spip.git';
		
		// On va chercher la liste des plugins-dist dans le fichier git-svn du dépôt (qui n'est que sur master)
		$file_externals = '.gitsvnextmodules';
		$file_externals_master = "{$this->dest}/$file_externals";
		// Si on cherche à télécharger dans le dossier courant, alors la liste des plugins doivent être sauvé dans le dossier parent
		if ($this->dest == '.') {
			$file_externals = "../$file_externals";
		}
		if (!file_exists($file_externals)) {
			if (!file_exists($file_externals_master)) {
				// on commence par checkout SPIP en master pour récuperer le file externals
				$this->branche = 'master';
				$this->checkout($input, $output);
				// Une erreur s'il est impossible de garder en mémoire la liste des plugins-dist, que ce soit dans le dossier courant ou dans le dossier parent
				// On continue quand même car si on cherchait à avoir le master, alors le fichier est bien là
				if (file_exists($file_externals_master) and  !@copy($file_externals_master, $file_externals)) {
					$output->writeln(array("<error>Impossible de garder en mémoire la liste des plugins-dist dans {$file_externals}. Vérifier les droits d’écriture.</error>"));
				}
			}
		}

		// On checkout SPIP sur la bonne branche
		$this->branche = $branche;
		$this->checkout($input, $output);
		
		// On va chercher tous les trucs externes
		if (file_exists($f = $file_externals_master) or file_exists($f = $file_externals)) {
			$dest_racine = $this->dest;
			$externals = parse_ini_file($f, true);
			
			foreach ($externals as $external) {
				$e_methode = $external['remote'];
				$e_source = $external['url'];
				$e_dest = $dest_racine . "/" . $external['path'];
				$e_branche = $branche;
				if (strncmp($e_branche, "spip-", 5) === 0) {
					$e_branche = substr($e_branche, 5);
				}

				// remplacer les sources SVN _core_ par le git.spip.net si possible
				if ($e_methode == 'svn') {
					if (strpos($e_source, "svn://zone.spip.org/spip-zone/_core_/plugins/") === 0) {
						$e_source = explode("_core_/plugins/", $e_source);
						$e_source = $url_repo_base . end($e_source) . '.git';
						$e_methode = "git";
					}
					elseif (strpos($e_source, "https://zone.spip.net/trac/spip-zone/browser/_core_/branches/spip-3.2/plugins") === 0) {
						$e_source = explode("_core_/branches/", $e_source);
						$e_source = explode('/', $e_source);
						$e_branche = array_shift($e_source);
						array_shift($e_source);
						$e_source = $url_repo_base . implode('/', $e_source) . '.git';
						$e_methode = "git";
					}
					elseif(strpos($e_source, "https://github.com/") === 0) {
						// Pour ne pas récupérer Bigup si pas master (mais il n'a plus rien à faire sur Github !)
						if (in_array($branche, ["spip-3.2", "spip-3.1", "spip-3.0"])) {
							continue;
						}
						$e_source = explode("//github.com/", $e_source);
						$e_source = explode("/", end($e_source));
						$user = array_shift($e_source);
						$repo = array_shift($e_source);
						$what = array_shift($e_source);
						switch ($what) {
							case 'branches':
								array_shift($e_source);
								$e_branche = reset($e_source);
								break;
							case 'trunk':
							default:
								$e_branche = 'master';
								break;
						}
						$e_source = "https://github.com/$user/$repo.git";
						$e_methode = "git";
					}
				}
				
				//~ $d = dirname($e_dest);
				//~ if (!is_dir($d)) {
					//~ mkdir($d);
				//~ }
				
				// On télécharge le plugin dist au bon endroit
				$output->writeln(array("<info>spip dl $e_methode -b {$e_branche} $e_source $e_dest</info>"));
				$this->methode = $e_methode;
				$this->source = $e_source;
				$this->dest = $e_dest;
				$this->branche = $e_branche;
				$this->checkout($input, $output);
			}
		}
	}
	
	/**
	 * Lire source et révision d'un répertoire SVN et reconstruire la ligne de commande
	 * @param string $format
	 * 		Format du retour, par défaut la ligne de commande mais on peut mettre "assoc" pour avoir un tableau associatif des informations
	 * @return string|array
	 * 		Retourne la ligne de commande ou un tableau des informations
	 */
	protected function svn_info($format = 'text'){
		if (!is_dir("{$this->dest}/.svn")) {
			return '';
		}

		// on veut lire ce qui est actuellement déployé
		// et reconstituer la ligne de commande pour le déployer
		exec("svn info {$this->dest}", $output);
		$output = implode("\n", $output);

		// URL
		// URL: svn://trac.rezo.net/spip/spip
		if (!preg_match(',^URL[^:\w]*:\s+(.*)$,Uims', $output, $match)) {
			return '';
		}
		$source = $match[1];

		// Revision
		// Revision: 18763
		if (!preg_match(',^R..?vision[^:\w]*:\s+(\d+)$,Uims', $output, $match)) {
			return '';
		}
		$revision = $match[1];

		if ($format == 'assoc') {
			return array(
				'source' => $source,
				'revision' => $revision,
				'dest' => $this->dest,
			);
		}

		return "spip dl svn -r $revision $source {$this->dest}";
	}
	
	/**
	 * Loger les modifs d'une source, optionnellement entre 2 revisions
	 * 
	 * @param array $options
	 *   from : revision de depart, non inclue
	 *   to : revision de fin
	 * @return string
	 */
	function svn_log($options) {
		$r = '';
		
		if (isset($options['from']) or isset($options['to'])) {
			$from = 0;
			$to = "HEAD";
			if (isset($options['from'])) {
				$from = ($options['from']+1);
			}
			if (isset($options['to'])) {
				$to = $options['to'];
			}

			$r = " -r $from:$to";
		}

		exec("svn log$r {$this->dest}", $res);

		$output = '';
		$comm = '';
		foreach ($res as $line) {
			if (strncmp($line, '---------------', 15) == 0 or !trim($line)) {

			}
			elseif (preg_match(',^r\d+,i', $line) and count(explode('|', $line)) > 3) {
				if (strlen($comm)>_MAX_LOG_LENGTH) {
					$comm = substr($comm, 0, _MAX_LOG_LENGTH) . '...';
				}

				$line = explode('|', $line);
				$date = explode('(', $line[2]);
				$date = reset($date);
				$date = strtotime($date);
				$output .=
					$comm
					. "\n"
					. $line[0]
					. "|"
					. $line[1]
					. "| "
					. date('Y-m-d H:i:s',$date)
					. " |";
				$comm = '';
			}
			else {
				$comm .= " $line";
			}
		}
		
		if (strlen($comm)>_MAX_LOG_LENGTH) {
			$comm = substr($comm, 0, _MAX_LOG_LENGTH) . '...';
		}
		$output .= $comm;

		// reclasser le commit le plus recent en premier, git-style
		$output = explode("\n", $output);
		$output = array_reverse($output);
		$output = implode("\n", $output);

		return trim($output);
	}
	
	/**
	 * Déployer un dépôt SVN depuis source et révision donnees
	 * @param string $source
	 * @param string $dest
	 * @param array $options
	 * @return string
	 */
	function svn_checkout(&$input, &$output) {
		$rempli = false;
		
		if (is_dir($this->dest)) {
			$infos = $this->svn_info('assoc');
			
			if ($rempli = count(scandir($this->dest)) and !$infos) {
				$output->writeln(array("<error>{$this->dest} n’est ni un dépôt SVN ni un répertoire vide.</error>"));
			}
			elseif ($infos['source'] !== $this->source) {
				$output->writeln(array("<error>{$this->dest} n’est est pas sur le bon dépôt SVN.</error>"));
			}
			elseif (!$revision = $this->revision or $revision != $infos['revision']) {
				$command = 'svn up ';
				
				if ($revision) {
					$command .= '-r ' . $revision . ' ';
				}
				
				if ($options = $input->getOption('options')) {
					$command .= $options . ' ';
				}

				$command .= $this->dest;
				$output->writeln(array("<info>$command</info>"));
				passthru($command);
			}
			else {
				$output->writeln(array("<info>{$this->dest} est déja sur {$this->source} avec la révision $revision</info>"));
			}
		}
		clearstatcache();

		if (!$rempli or !is_dir($this->dest)) {
			$command = "svn co ";
			
			if ($this->revision) {
				$command .= "-r {$this->revision} ";
			}
			
			if ($options = $input->getOption('options')) {
				$command .= $options . ' ';
			}
			
			$command .= "{$this->source} {$this->dest}";
			$output->writeln(array("<info>$command</info>"));
			passthru($command);
		}
	}
	
	/**
	 * Lire source et révision d'un répertoire Git et reconstruire la ligne de commande
	 * @param string $format
	 * 		Format du retour, par défaut la ligne de commande mais on peut mettre "assoc" pour avoir un tableau associatif des informations
	 * @return string|array
	 * 		Retourne la ligne de commande ou un tableau des informations
	 */
	function git_info($format = 'text'){
		if (!is_dir("{$this->dest}/.git")) {
			return '';
		}

		$curdir = getcwd();
		chdir($this->dest);

		exec('git remote -v', $output);
		$output = implode("\n", $output);

		if (!preg_match(',(\w+://.*|\w+@[\w\.-]+:.*)\s+\(fetch\)$,Uims', $output, $match)) {
			chdir($curdir);
			return '';
		}
		$source = $match[1];

		$modified = false;
		$branche = false;
		exec('git status -b -s', $output);
		if (count($output) > 1) {
			$full = implode("|\n", $output);
			if (strpos($full, "|\n M") !== false or strpos($full, "|\nM") !== false) {
				$modified = true;
			}
		}
		// ## master...origin/master
		$output = reset($output);
		if (strpos($output, '...') !== false) {
			$branche = trim(substr($output,2));
			$branche = explode('...', $branche);
			$branche = reset($branche);
		}

		// qu'on soit sur une branche ou non, on veut la revision courante
		exec('git log -1', $output);
		$hash = explode(' ', reset($output));
		$hash = end($hash);

		chdir($curdir);

		if ($format == 'assoc') {
			$res = array(
						'source' => $source,
						'dest' => $this->dest,
						'modified' => $modified,
					);
			if ($branche) {
				$res['branche'] = $branche;
			}
			if ($hash) {
				$res['revision'] = $hash;
			}
			
			return $res;
		}

		$opt = '';
		if ($hash) {
			$opt .= ' -r ' . substr($hash,0,7);
		}
		if ($branche) {
			$opt .= " -b {$branche}";
		}
		
		return "spip dl git{$opt} $source {$this->dest}";
	}
	
	/**
	 * Loger les modifs d'une source, optionnellement entre 2 revisions
	 * 
	 * @param array $options
	 *   from : revision de depart
	 *   to : revision de fin
	 * @return string
	 */
	function git_log($options){
		if (!is_dir("$this->dest/.git")) {
			return '';
		}

		$curdir = getcwd();
		chdir($this->dest);

		$r = '';
		if (isset($options['from']) or isset($options['to'])) {
			$from = '';
			$to = '';
			if (isset($options['from'])) {
				$from = $options['from'];
				$output = array();
				exec("git log -1 -c $from --pretty=tformat:'%ct'", $output);
				$t = intval(reset($output));
				if ($t) {
					//echo date('Y-m-d H:i:s',$t)."\n";
					$from = "--since=$t $from";
				}
			}
			if (isset($options['to'])) {
				$to = $options['to'];
			}

			$r = " $from..$to";
		}

		//exec("git log$r --graph --pretty=tformat:'%Cred%h%Creset -%C(yellow)%d%Creset %s %Cgreen(%an %cr)%Creset' --abbrev-commit --date=relative master",$output);
		$output = array();
		exec('git fetch --all 2>&1', $output);
		$output = array();
		$branche = '--all';
		if (isset($options['branche'])) {
			$branche = $options['branche'];
		}
		exec("git log$r --pretty=tformat:'%h | %ae | %ct | %d %s ' $branche",$output);
		
		foreach($output as $k=>$line){
			$line = explode("|",ltrim($line,"*"));
			$revision = trim(array_shift($line));
			$comiter = trim(array_shift($line));
			$date = date('Y-m-d H:i:s',trim(array_shift($line)));
			$comm = trim(implode("|",$line));
			if (strlen($comm)>_MAX_LOG_LENGTH)
				$comm = substr($comm,0,_MAX_LOG_LENGTH)."...";
			$output[$k] = "$revision | $comiter | $date | $comm";
		}
		
		$output = implode("\n", $output);

		chdir($curdir);

		return trim($output);
	}
	
	/**
	 * Deployer un repo GIT depuis source et revision donnees
	 * @param string $source
	 * @param string $dest
	 * @param array $options
	 * @return string
	 */
	function git_checkout(&$input, &$output) {
		$curdir = getcwd();
		$rempli = false;
		$branche = $this->branche ? $this->branche : 'master';
		
		// Si le dossier voulu existe déjà ET qu'il est déjà rempli
		if (is_dir($this->dest) and $rempli = (count(scandir($this->dest)) > 2)) {
			$infos = $this->git_info('assoc');
			
			if (!$infos) {
				$output->writeln(array("<error>{$this->dest} n’est ni un dépôt Git ni un répertoire vide.</error>"));
			}
			elseif ($infos['source'] !== $this->source) {
				$output->writeln(array("<error>{$this->dest} n’est est pas sur le bon dépôt Git.</error>"));
			}
			elseif (
				!$revision = $input->getOption('revision')
				or !isset($infos['revision'])
				or git_compare_revisions($revision, $infos['revision']) !== 0
			) {
				chdir($this->dest);
				
				//$command = "git checkout $branche";
				//passthru($command);
				$command = "git fetch --all";
				passthru($command);
				
				if ($revision) {
					$command = "git checkout --detach $revision";
					$output->writeln(array("<info>$command</info>"));
					passthru($command);
				}
				else {
					$command = "git pull --rebase";
					if ($infos['modified']) {
						$command = "git stash && $command && git stash pop";
					}
					if (!isset($infos['branche']) or $infos['branche'] !== $branche) {
						$command = "git checkout $branche && $command";
					}
					$output->writeln(array("<info>$command</info>"));
					passthru($command);
				}
				
				chdir($curdir);
			}
			else {
				$output->writeln(array("<info>{$this->dest} est déja sur {$this->source} avec la révision $revision</info>"));
			}
		}
		clearstatcache();

		if (!$rempli or !is_dir($this->dest)) {
			$command = "git clone ";
			$command .= "{$this->source} {$this->dest}";
			$output->writeln(array("<info>$command</info>"));
			passthru($command);
			
			if ($revision = $input->getOption('revision')){
				chdir($this->dest);
				$command = "git checkout --detach $revision";
				$output->writeln(array("<info>$command</info>"));
				passthru($command);
				chdir($curdir);
			}
			elseif ($branche !== 'master') {
				chdir($this->dest);
				$command = "git checkout $branche";
				$output->writeln(array("<info>$command</info>"));
				passthru($command);
				chdir($curdir);
			}
		}
	}
	
	/**
	 * @param string $rev1
	 * @param string $rev2
	 * @return int
	 */
	function git_compare_revisions($rev1, $rev2) {
		$len = min(strlen($rev1), strlen($rev2));
		$len = max($len, 7);

		return strncmp($rev1, $rev2, $len);
	}
	
	/**
	 * Lire source et révision d'un répertoire FTP et reconstruire la ligne de commande
	 * @param string $format
	 * 		Format du retour, par défaut la ligne de commande mais on peut mettre "assoc" pour avoir un tableau associatif des informations
	 * @return string|array
	 * 		Retourne la ligne de commande ou un tableau des informations
	 */
	function ftp_info($format = 'text') {
		if (!file_exists($f="{$this->dest}/.ftpsource")) {
			return '';
		}

		$source = file_get_contents($f);
		$source = explode("\n", $source);

		$md5 = end($source);
		$source = reset($source);

		if ($format == 'assoc') {
			return array(
				'source' => $source,
				'revision' => $md5,
				'dest' => $this->dest,
			);
		}

		return "spip dl ftp $source {$this->dest}";
	}
	
	
	
	protected function mettre_a_jour(&$input, &$output) {
		// On cherche la version SVN actuelle
		ob_start();
		passthru('svn info --show-item url');
		$url_actuel = ob_get_contents();
		ob_end_clean();
		
		if (!$url_actuel) {
			$output->writeln("<error>Cette installation de SPIP n'est pas installée avec SVN et ne peut être mise à jour avec cette commande.</error>");
		}
		else {
			// On vérifie s'il y a des modifications de fichiers
			exec('svn status --quiet --non-interactive .', $lignes, $erreur);
			if ($erreur) {
				$output->writeln(array("<error>Erreur SVN.</error>"));
			}
			else {
				$lignes = array_filter($lignes, function ($ligne) {
					return preg_match(',^M,', $ligne);
				});
				
				// S'il y a des fichiers modifiés
				if (count($lignes) > 0) {
					$output->writeln(array(
						"<error>Pas de mise à jour car des fichiers ont été modifiés localement.</error>",
						 join("\n", $lignes),
					));
				}
				// Sinon on peut se lancer dans une mise à jour
				else {
					// Par défaut ça sera un switch sur la version demandée ou la dernière en date
					$mode_maj = 'switch';
					$version_type_actuel = null;
					
					// On cherche si on reconnait une version
					if (preg_match("|^svn://trac\.rezo\.net/spip/(.+?)(/spip-((\d+\.\d+).*?))?$|i", $url_actuel, $trouve)) {
						$dossier = $trouve[1];
						$version_actuelle = $trouve[3];
						$xy = $trouve[4];
						
						// Si on est dans un tag, ce sera toujours un switch
						if ($dossier == 'tags') {
							$mode_maj = 'switch';
							$version_type_actuel = 'release';
							
							// Et on cherchera la version stable la plus récente de la même branche X.Y, pour ne rien casser
							if (!$this->version_demandee) {
								$this->version_demandee = $xy;
							}
						}
						else {
							$version_type_actuel = 'branche';
							
							// S'il on a demandé une version, ou de changer pour une release : toujours switch
							if ($this->version_demandee or $input->hasParameterOption(array('--release', '-r'))) {
								$mode_maj = 'switch';
							}
							// Sinon on reste dans la même branche, donc on update simplement
							else {
								$mode_maj = 'update';
							}
						}
					}
					
					// Si on doit faire update, facile
					if ($mode_maj == 'update') {
						$output->writeln("<info>C'est parti pour la mise à jour en restant dans la même branche $version_actuelle</info>");
						passthru('svn update');
					}
					// Si c'est un switch, on cherche version et URL
					elseif (
						$mode_maj == 'switch'
						and $version_precise = $this->chercher_version_precise($input, $output)
					) {
						// Si la version trouvée est <= pour le même type qu'actuellement
						if ($version_type_actuel == $this->version_type and version_compare($version_precise, $version_actuelle, '<=')) {
							$output->writeln("<info>Pas de version plus récente que votre version $version_actuelle</info>");
						}
						// Sinon on peut enfin faire le switch
						else {
							$output->writeln("<info>C'est parti pour un changement de version de \"$version_actuelle\" à la {$this->version_type} \"{$this->version_precise}\"</info>");
							$output->writeln("Patientez quelques minutes, le temps que SVN calcule les changements…");
							passthru("svn switch {$this->url}");
						}
					}
				}
			}
		}
	}
	
	protected function telecharger(&$input, &$output) {
		// On cherche la bonne version suivant les params
		$this->chercher_version_precise($input, $output);
		
		// Si ya bien un URL au final
		if ($this->url) {
			$output->writeln("<info>C'est parti pour le téléchargement de la {$this->version_type} \"{$this->version_precise}\" !</info>");
			
			// On lance la commande SVN dans le répertoire courant
			passthru("svn co {$this->url} .");
		}
	}
	
	protected function chercher_version_precise(&$input, &$output) {
		$lister_versions = $this->getApplication()->find('core:listerversions');
		$versions = $lister_versions->get_versions();
		$url = null;
		
		// S'il y a une release demandée, c'est prioritaire
		if ($this->version_type == 'release') {
			// S'il y a une release précise demandée
			if ($this->version_demandee) {
				// Si la version pile exacte existe
				if (isset($versions['tags'][$this->version_demandee])) {
					$this->version_precise = $this->version_demandee;
					$this->url = $versions['tags'][$this->version_demandee];
				}
				// Sinon on cherche le plus récent approchant
				elseif ($this->version_precise = $lister_versions->get_last_release($this->version_demandee)) {
					$this->url = $versions['tags'][$this->version_precise];
				}
				// Sinon la release demandée n'existe pas
				else {
					$output->writeln(array(
						"<error>La version \"{$this->version_demandee}\" n'est pas prise en charge.</error>",
						'Releases supportées : <info>'.join('</info>, <info>', array_keys($versions['tags'])).'</info>'
					));
				}
			}
			// Sinon la dernière parmi toutes les releases
			else {
				$this->version_precise = $lister_versions->get_last_release();
				$this->url = $versions['tags'][$this->version_precise];
			}
		}
		// Sinon s'il y a une branche demandée
		elseif ($this->version_type == 'branche') {
			// S'il y a une branche précise demandée
			if ($this->version_demandee) {
				// Si la version pile exacte existe
				if (isset($versions['branches'][$this->version_demandee])) {
					$this->version_precise = $this->version_demandee;
					$this->url = $versions['branches'][$this->version_demandee];
				}
				// Sinon on cherche le plus récent approchant
				elseif ($this->version_precise = $lister_versions->get_last_branche($this->version_demandee)) {
					$this->url = $versions['branches'][$this->version_precise];
				}
				// Sinon la branche demandée n'exsite pas
				else {
					$output->writeln(array(
						"<error>La version \"{$this->version_demandee}\" n'est pas prise en charge.</error>",
						'Branches supportées : <info>'.join('</info>, <info>', array_keys($versions['branches'])).'</info>'
					));
				}
			}
			// Sinon la dernière parmi toutes les branches
			else {
				$this->version_precise = $lister_versions->get_last_branche();
				$this->url = $versions['branches'][$this->version_precise];
			}
		}
		// Sinon on prend la dernière release stable
		else {
			$this->version_precise = $lister_versions->get_last_release();
			$this->url = $versions['tags'][$this->version_precise];
		}
		
		return $this->version_precise;
	}
}
