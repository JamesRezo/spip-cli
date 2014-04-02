#!/usr/bin/env php
<?php
// spip

$dossier_cli = dirname(__FILE__);

include_once "$dossier_cli/vendor/autoload.php";
use Symfony\Component\Console\Application;

// Pouvoir trouver les sous-classes d'une classe
function getSubclassesOf($parent) {
    $result = array();
    foreach (get_declared_classes() as $class) {
        if (is_subclass_of($class, $parent))
            $result[] = $class;
    }
    return $result;
}

/**
 * Si on est sur un OS qui n'utilise pas des / comme séparateur de
 * dossier dans les chemins, on remplace les / par le bon
 * séparateur.
 *
 * @param string $path : un chemin au format UNIX
 *
 * @return string : le même chemin au format approprié à
 *                  l'environnement dans lequel on se trouve.
 */
function prep_path($path) {

    if (DIRECTORY_SEPARATOR !== '/') {
        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    } else {
        return $path;
    }
}

/**
 * Retourne le chemin absolu vers la racine du site SPIP dans
 * lequel se trouve le répertoire courant. Retourne FALSE si l'on
 * est pas dans l'arborescence d'un site SPIP.
 *
 * @return mixed : le chemin vers la racine du SPIP dans lequel on se
 *                 trouve. Retourne false si on n'est pas dans
 *                 l'arborescence d'une installation SPIP.
 */
function get_spip_root() {

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
 * Inclut ecrire/inc_version.php, ce qui permet ensuite d'utiliser
 * toutes les fonctions de spip comme lors du chargement d'une
 * page.
 *
 * @param string $spip_root : Le chemin vers la racine du SPIP que
 *                            l'on veut charger.
 *
 * @return bool : Retourne true si on a pu charger SPIP correctement,
 *                false sinon
 */
function charger_spip($spip_root) {

    /* Pour que les include dans les fichiers php de SPIP
       fonctionnent correctement, il faut être à la racine du
       site. Du coup on change de répertoire courant le temps de
       charger tout ça. */
    $cwd = getcwd();
    chdir($spip_root);

    include_once prep_path("$spip_root/ecrire/inc_version.php");

    /* On revient dans le répertoire dans lequel la commande a été
       appellée, au cas où la commande voudrait utiliser cette
       info. */
    chdir($cwd);

    /* On part du principe que si _ECRIRE_INC_VERSION existe,
       inc_version.php a été chargé correctement. */
    if (_ECRIRE_INC_VERSION) {
        /* charger inc_version.php ne charge pas ce fichier, et
           certaines fonctions du core l'utilisent sans
           l'importer, il faut donc le charger ici. */
        include_spip('base/abstract_sql');
        /* Il faut initialiser cette globale pour éviter les
           problèmes de connexion sql. */
        $GLOBALS['mysql_rappel_connexion'] = true;

        return TRUE;
    } else {
        return FALSE;
    }
}

// Création de la ligne de commande
$spip = new Application('Ligne de commande pour SPIP', '0.1.0');

// Inclusion des commandes de base
foreach (glob("$dossier_cli/spip-cli/*.php") as $commande_fichier) {
	include_once $commande_fichier;
}

$spip_root = get_spip_root();

if (($spip_root) AND charger_spip($spip_root)) {
    $spip_loaded = TRUE;
    // TODO charger toutes les commandes qui se trouvent dans le path
    // de SPIP.
} else {
    $spip_loaded = FALSE;
}

if ($commandes = getSubclassesOf('Symfony\Component\Console\Command\Command')){
	foreach ($commandes as $class){
        $spip->add(new $class);
	}
}

// Lancement de l'application
$spip->run();
