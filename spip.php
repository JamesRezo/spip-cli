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
        if (is_subclass_of($class, $parent))
            $result[] = $class;
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
    
    return FALSE;
}

/**
 * Lance le SPIP dans lequel on se trouve
 * 
 * Inclut ecrire/inc_version.php, ce qui permet ensuite d'utiliser
 * toutes les fonctions de SPIP comme lors du chargement d'une
 * page.
 *
 * @param string $spip_root
 * 		Le chemin vers la racine du SPIP que l'on veut charger.
 * @return bool
 * 		Retourne true si on a pu charger SPIP correctement, false sinon.
 */
function spip_charger($spip_root) {
    // Pour que les include dans les fichiers php de SPIP fonctionnent correctement,
    // il faut être à la racine du site.
    // On change de répertoire courant le temps de charger tout ça.
    $cwd = getcwd();
    chdir($spip_root);
	
	// On charge la machinerie de SPIP
    include_once prep_path("$spip_root/ecrire/inc_version.php");

    // On revient dans le répertoire dans lequel la commande a été appellée
    chdir($cwd);

    // Si _ECRIRE_INC_VERSION existe, inc_version.php a été chargé correctement
    if (_ECRIRE_INC_VERSION) {
        // Charge l'API SQL, pour être sûr de l'avoir déjà
        include_spip('base/abstract_sql');
        // Il faut initialiser cette globale pour éviter les problèmes de connexion sql
        $GLOBALS['mysql_rappel_connexion'] = true;
		// Tout s'est bien passé
        return TRUE;
    } else {
    	// Mauvais chargement
        return FALSE;
    }
}

// Création de la ligne de commande
$spip = new Application('Ligne de commande pour SPIP', '0.1.0');

// Inclusion des fichiers contenant les commandes de base
foreach (glob("$dossier_cli/spip-cli/*.php") as $commande_fichier) {
	include_once $commande_fichier;
}

if (($spip_racine = spip_chercher_racine()) and spip_charger($spip_racine)) {
    $spip_loaded = TRUE;
    // TODO charger toutes les commandes qui se trouvent dans le path
    // de SPIP.
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
