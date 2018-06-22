<?php

namespace Spip\Cli\Loader;

use Pimple\Container;
use Spip\Cli\Tools\Files;


/**
 * Chargement de SPIP
 * @api
 */
class Spip {

	/** @var string Chemin du démarreur */
	protected $starter = 'ecrire/inc_version.php';

	/** @var string Chemin du connecteur SQL */
	protected $connect = 'config/connect.php';

	/** @var string Nom du répertoire théorique des sites, si mutualisation */
	protected $dir_sites = 'sites';

	/** @var string */
	private $directory;

	/** @var bool */
	private $loaded = false;

	/** @var bool */
	private $exists;

	/** @var Container */
	private $app;

	/**
	 * Loader constructor.
	 * @param null $directory
	 */
	public function __construct($directory = null) {
		if (is_null($directory)) {
			$directory = $this->chercher_racine_spip();
			if ($directory) {
				$this->setDirectory($directory);
				$this->trouver_host_si_mutualisation();
			} else {
				$this->setDirectory($this->getcwd());
			}
		} else {
			$this->setDirectory($directory);
		}
	}

	public function setDirectory($directory) {
		$this->directory = rtrim(Files::formatPath($directory), DIRECTORY_SEPARATOR);
	}

	/**
	 * Calcul de chemin de travail actuel,
	 *
	 *
	 * @note
	 * La fonction getcwd() résout les liens symboliques
	 * contrairement à un appel à `pwd`
	 *
	 * Par exemple si on est dans "racine_spip/sites/domaine.tld"
	 * et que le répertoire "sites" est un lien symbolique sur
	 * un répertoire en dehors de la racine de spip
	 * (tel que /mnt/sites ou /home/sites), la fonction
	 * php getcwd() résout le lien, ce qui fait
	 * que la racine de SPIP n’est plus retrouvée.
	 *
	 * On préfère retourner le chemin sans sans tenir résoudre les liens symboliques
	 *
	 * @return string
	 */
	public function getcwd() {
		if (
			'\\' !== DIRECTORY_SEPARATOR // Pas Windows…
			and $cwd = exec('pwd')
		) {
			return $cwd;
		}
		return getcwd();
	}

	/**
	 * Cherche la racine d'un site SPIP
	 *
	 * Retourne le chemin absolu vers la racine du site SPIP dans
	 * lequel se trouve le répertoire courant. Retourne FALSE si l'on
	 * est pas dans l'arborescence d'un site SPIP.
	 *
	 * @note Il faut faire attention à la résolution des liens symboliques !
	 *
	 * @return string|bool
	 * 		Retourne le chemin vers la racine du SPIP dans lequel on se trouve.
	 * 		Retourne false si on n'est pas dans l'arborescence d'une installation SPIP.
	 */
	private function chercher_racine_spip() {
		$cwd = $this->getcwd();
		while ($cwd) {
			if (file_exists(Files::formatPath($cwd . DIRECTORY_SEPARATOR . $this->starter))) {
				return $cwd;
			} else {
				/* On remonte d'un dossier dans l'arborescence */
				$cwd_array = explode(DIRECTORY_SEPARATOR, $cwd);
				array_pop($cwd_array);
				$cwd = implode(DIRECTORY_SEPARATOR, $cwd_array);
			}
		}
		return false;
	}

	/**
	 * Si le répertoire d’exécution de spip-cli est dans un site mutualisé,
	 * ie quelque part dans `sites/truc.tld/qqc`, alors, déclarer à SPIP l’adresse
	 * du site concerné (dans http_host), si on arrive à le calculer
	 * (en se connectant au sql du site via PDO)
	 *
	 * @return bool
	 */
	private function trouver_host_si_mutualisation() {
		// si le host a déjà été défini, ne rien tenter.
		if (!empty($_SERVER['HTTP_HOST'])) {
			return false;
		}
		// Si pas de répertoire 'sites', pas la peine de chercher...
		$cwd = explode(DIRECTORY_SEPARATOR, $this->getcwd());
		if (!in_array($this->dir_sites, $cwd)) {
			return false;
		}
		// On suppose que les fichiers de travail d’un site mutualisé,
		// est un répertoire enfant du répertoire 'sites' (ie: sites/domaine.tld).
		// On cherche un connecteur SQL dedans (ie: sites/domaine.tld/config/connect.php)
		// On l’utilise pour se connecter à la bdd et retrouver l’adresse du site.
		$previous = null;
		foreach (array_reverse($cwd) as $dir) {
			if ($dir === $this->dir_sites and $previous) {
				$connect_file = $this->getPathFile($this->dir_sites . DIRECTORY_SEPARATOR . $previous . DIRECTORY_SEPARATOR . $this->connect);
				if (file_exists($connect_file)) {
					try {
						$adresse = (new Sql($connect_file))->getMeta('adresse_site');
						if ($adresse) {
							$host = parse_url($adresse, PHP_URL_HOST);
							$_SERVER['HTTP_HOST'] = $host;
							echo "Host défini sur $host\n";
						}
					} catch (\Exception $e) {
						// a améliorer
						echo $e->getMessage() . "\n";
					}
				}
			}
			$previous = $dir;
		}
		return false;
	}

	/**
	 * Indique si on est à la racine d’un site SPIP
	 * @return bool;
	 */
	public function exists() {
		if (is_null($this->exists)) {
			$this->exists = is_file($this->getPathFile($this->starter));
		}
		return $this->exists;
	}

	/**
	 * Place le dossier de travail au bon endroit
	 */
	public function chdir() {
		chdir($this->getDirectory());
	}

	/**
	 * Démarre SPIP
	 */
	public function load() {
		if ($this->loaded) {
			return true;
		}

		if (!$this->exists()) {
			throw new \Exception('SPIP has not been found in ' . $this->directory);
		}

		$starter = $this->getPathFile($this->starter);
		$this->loaded = true;
		$this->runSpipWithUglyGlobals($starter);

		if (!defined('_ECRIRE_INC_VERSION')) {
			throw new \Exception('SPIP is incorrectly loaded');
		}

		// Charger l'API SQL.
		include_spip('base/abstract_sql');

		return true;
	}

	public function getPathConnect() {
		return $this->getSiteFile($this->connect);
	}

	/**
	 *  Retourne un chemin complet vers un fichier d’un site SPIP
	 *  (pour config, local, tmp, IMG) qui peut être à la racine
	 *  ou dans le répertoire sites/xxx/
	 *  @param string $path
	 *  @return string Chemin complet
	 */
	public function getSiteFile($path) {
		if (defined('_DIR_SITE')) {
			return $this->getPathFile(_DIR_SITE . $path);
		}
		return $this->getPathFile($path);
	}

	/**
	 * Retourne un chemin complet vers un fichier de SPIP
	 * @param string $path Chemin tel que 'ecrire/inc_version.php'
	 * @return string Chemin complet
	 */
	public function getPathFile($path) {
		return $this->directory . DIRECTORY_SEPARATOR . Files::formatPath($path);
	}

	/**
	 * Déclarer les globales utilisées encore par SPIP.
	 * @param string $starter Chemin du fichier de démarrage de SPIP.
	 */
	public function runSpipWithUglyGlobals($starter) {
		global
			$nombre_de_logs,
			$taille_des_logs,
			$table_prefix,
			$cookie_prefix,
			$dossier_squelettes,
			$filtrer_javascript,
			$type_urls,
			$debut_date_publication,
			$ip,
			$mysql_rappel_connexion,
			$mysql_rappel_nom_base,
			$test_i18n,
			$ignore_auth_http,
			$ignore_remote_user,
			$derniere_modif_invalide,
			$quota_cache,
			$home_server,
			$help_server,
			$url_glossaire_externe,
			$tex_server,
			$traiter_math,
			$xhtml,
			$xml_indent,
			$source_vignettes,
			$formats_logos,
			$controler_dates_rss,
			$spip_pipeline,
			$spip_matrice,
			$plugins,
			$surcharges,
			$exceptions_des_tables,
			$tables_principales,
			$table_des_tables,
			$tables_auxiliaires,
			$table_primary,
			$table_date,
			$table_titre,
			$tables_jointures,
			$liste_des_statuts,
			$liste_des_etats,
			$liste_des_authentifications,
			$spip_version_branche,
			$spip_version_code,
			$spip_version_base,
			$spip_sql_version,
			$spip_version_affichee,
			$visiteur_session,
			$auteur_session,
			$connect_statut,
			$connect_toutes_rubriques,
			$hash_recherche,
			$hash_recherche_strict,
			$ldap_present,
			$meta,
			$connect_id_rubrique,
			$puce;

		// Éviter des notices. Il faudrait utiliser HTTPFondation\Request dans SPIP.
		if (!$this->app['debug']) {
			foreach (['SERVER_NAME', 'SERVER_PORT', 'REQUEST_METHOD', 'REQUEST_URI'] as $key) {
				if (!isset($_SERVER[$key])) {
					$_SERVER[$key] = null;
				}
			}
		}

		$cwd = getcwd();
		$this->chdir();
		$this->preparerPourInstallation();
		require_once $starter;
		chdir($cwd);
	}

	/**
	 * Hack : afin que le chargement de SPIP ne termine pas par une page "minipres"
	 * indiquer, si le SPIP n’a pas encore sa BDD de configurée, que l’on est sur la
	 * page d’installation...
	 *
	 */
	protected function preparerPourInstallation() {
		// Si jamais la base n'est pas installé on anhile la redirection et on affirme qu'on est sur la page d'installation
		// Seulement si 'HTTP_HOST' n’a pas été défini (ce qui soulignerait que l’on serait sur un spip mutualisé
		// dont on a indiqué l’url — afin que le mes_options retrouve le bon dossier sites/ ensuite)
		if (empty($_SERVER['HTTP_HOST']) and !is_file($this->connect)) {
			$_GET['exec'] = 'install';
			define('_FILE_CONNECT', 'config/connect.tmp.php');
		}
		// TIMEOUT de 24h…
		define('_UPGRADE_TIME_OUT', 24*3600);
	}

	/**
	 * Chemin vers le répertoire SPIP
	 * @return string
	 */
	public function getDirectory() {
		return $this->directory;
	}


	/**
	 * Sets a pimple instance onto this application.
	 *
	 * @param Container $app
	 * @return void
	 */
	public function setContainer(Container $app) {
		$this->app = $app;
	}
}