#!/usr/bin/env php
<?php
// spip

$dossier_cli = dirname(__FILE__);

include_once "$dossier_cli/vendor/autoload.php";
use Symfony\Component\Console\Application;
use SPIP\Cli\Core\CoreInstall;

// Pouvoir trouver les sous-classes d'une classe
function getSubclassesOf($parent) {
    $result = array();
    foreach (get_declared_classes() as $class) {
        if (is_subclass_of($class, $parent))
            $result[] = $class;
    }
    return $result;
}

// Création de la ligne de commande
$spip = new Application('Ligne de commande pour SPIP', '0.1.0');

// Inclusion des commandes de base
foreach (glob("$dossier_cli/spip-cli/*.php") as $commande_fichier) {
	include_once $commande_fichier;
}

if ($commandes = getSubclassesOf('Symfony\Component\Console\Command\Command')){
	foreach ($commandes as $class){
		$spip->add(new $class);
	}
}

// Lancement de l'application
$spip->run();
