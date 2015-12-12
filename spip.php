#!/usr/bin/env php
<?php
// spip

$dossier_cli = dirname(__FILE__);

include_once "$dossier_cli/vendor/autoload.php";
use Symfony\Component\Console\Application;

/**
 * Trouver toutes les sous-classes qui étendent une classe donnée
 *
 * @param string $parent
 * 		Nom de la classe dont on veut chercher les extensions.
 * @return array
 * 		Retourne un tableau de chaînes contenant chacune le nom d'une classe.
 */
function getSubclassesOf($parent) {
	$result = array();
	foreach (get_declared_classes() as $class) {
		if (is_subclass_of($class, $parent)) {
			$result[] = $class;
		}
	}
	return $result;
}

/**
 * Transforme un chemin avec les bons séparateurs de dossiers
 * 
 * Si on est sur un OS qui n'utilise pas des / comme séparateur de
 * dossier dans les chemins, on remplace les / par le bon
 * séparateur.
 *
 * @param string $path
 * 		Un chemin au format UNIX.
 * @return string
 * 		Retourne le même chemin au format approprié à
 * 		l'environnement dans lequel on se trouve.
 */
function prep_path($path) {
	if (DIRECTORY_SEPARATOR !== '/') {
		return str_replace('/', DIRECTORY_SEPARATOR, $path);
	} else {
		return $path;
	}
}

/**
 * Cherche la racine d'un site SPIP
 * 
 * Retourne le chemin absolu vers la racine du site SPIP dans
 * lequel se trouve le répertoire courant. Retourne FALSE si l'on
 * est pas dans l'arborescence d'un site SPIP.
 *
 * @return string|bool
 * 		Retourne le chemin vers la racine du SPIP dans lequel on se trouve.
 * 		Retourne false si on n'est pas dans l'arborescence d'une installation SPIP.
 */
function spip_chercher_racine() {
	$cwd = getcwd();

	while ($cwd) {
		if (file_exists(prep_path("$cwd/ecrire/inc_version.php"))) {
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
 * Lance le SPIP dans lequel on se trouve
 * 
 * Inclut ecrire/inc_version.php, ce qui permet ensuite d'utiliser
 * toutes les fonctions de SPIP comme lors du chargement d'une
 * page.
 *
 * @param string $spip_racine
 * 		Le chemin vers la racine du SPIP que l'on veut charger.
 * @return bool
 * 		Retourne true si on a pu charger SPIP correctement, false sinon.
 */
function spip_charger($spip_racine) {
	// On liste toutes les globales déclarées au démarrage de SPIP (55 !!)
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
		$connect_id_rubrique;

	// Pour que les include dans les fichiers php de SPIP fonctionnent correctement,
	// il faut être à la racine du site.
	// On change de répertoire courant le temps de charger tout ça.
	$cwd = getcwd();
	chdir($spip_racine);
	
	// Si jamais la base n'est pas installé on anhile la redirection et on affirme qu'on est sur la page d'installation
	if (!is_file('config/connect.php')) {
		$_GET['exec'] = 'install';
		define('_FILE_CONNECT', 'config/connect.tmp.php');
	}
	
	// TIMEOUT de 24h…
	define('_UPGRADE_TIME_OUT', 24*3600);
	
	// On charge la machinerie de SPIP
	include_once prep_path("$spip_racine/ecrire/inc_version.php");

	// On revient dans le répertoire dans lequel la commande a été appelée
	chdir($cwd);

	// Si _ECRIRE_INC_VERSION existe, inc_version.php a été chargé correctement
	if (_ECRIRE_INC_VERSION) {
		// Charge l'API SQL, pour être sûr de l'avoir déjà
		include_spip('base/abstract_sql');
		// Tout s'est bien passé
		return TRUE;
	} else {
		// Mauvais chargement
		return FALSE;
	}
}

// Création de la ligne de commande
$spip = new Application('Ligne de commande pour SPIP', '0.2.2');

// Inclusion des fichiers contenant les commandes de base
foreach (glob("$dossier_cli/spip-cli/*.php") as $commande_fichier) {
	include_once $commande_fichier;
}

if (($spip_racine = spip_chercher_racine()) and spip_charger($spip_racine)) {
    $spip_loaded = TRUE;

    // charger toutes les commandes qui se trouvent dans le path de SPIP.
    $cwd = getcwd();
    chdir($spip_racine);
    $commandes = find_all_in_path('spip-cli/', '.*[.]php$');
    foreach ($commandes as $commande_fichier) {
        include_once $commande_fichier;
    }
    chdir($cwd);
} else {
    $spip_loaded = FALSE;
}

// Ajouter automatiquement toutes les commandes trouvées (= un objet de chaque classe Command)
if ($commandes = getSubclassesOf('Symfony\Component\Console\Command\Command')){
	foreach ($commandes as $class){
        $spip->add(new $class);
	}
}

// Lancement de l'application
$spip->run();

