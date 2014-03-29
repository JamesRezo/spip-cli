<?php

use Symfony\Component\Console\Command\Command;

/**
 * Cette classe s'utilise comme la classe Command du composant Console
 * de Symphony, mais s'occupe aussi de :
 *
 *   - Trouver si la commande a été appelée depuis une installation SPIP
 *   - Si c'est le cas, trouver le chemin vers la racine du SPIP
 *   - Charger inc_version.php, et donc charger les fonctions
 *     principales du core de SPIP.
 *
 */
class SpipCommand extends Command {

    /* Le chemin vers la racine de l'installation SPIP dans laquelle on
       se trouve. */
    protected $spip_dir = FALSE;

    public function __construct($name = null) {

        parent::__construct($name);
        $this->spip_dir = $this->get_spip_dir();
    }

    /**
     * Inclut ecrire/inc_version.php, ce qui permet ensuite d'utiliser
     * toutes les fonctions de spip comme lors du chargement d'une
     * page.
     */
    protected function load_spip($input, $output) {

        if ( ! $this->spip_dir) {
            $output->writeln('<error>Cette commande ne peux fonctionner que dans une installation SPIP.</error>');
            return FALSE;
        }

        /* Pour que les include dans les fichiers php de SPIP
           fonctionnent correctement, il faut être à la racine du
           site. Du coup on change de répertoire courant le temps de
           charger tout ça. */
        $cwd = getcwd();
        chdir($this->spip_dir);
        include_once $this->prep_dir("$this->spip_dir/ecrire/inc_version.php");
        /* On revient dans le répertoire dans lequel la commande a été
           appellée, au cas où la commande voudrait utiliser cette
           info. */
        chdir($cwd);
        /* On part du principe que si _ECRIRE_INC_VERSION existe,
           inc_version.php a été chargé correctement. */
        if (_ECRIRE_INC_VERSION) {
            return TRUE;
        }
    }

    /**
     * Retourne le chemin absolu vers la racine du site SPIP dans
     * lequel se trouve le répertoire courant. Retourne FALSE si l'on
     * est pas dans l'arborescence d'un site SPIP.
     */
    private function get_spip_dir() {

        $cwd = getcwd();

        while ($cwd) {
            if (file_exists($this->prep_dir("$cwd/ecrire/inc_version.php"))) {
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
     * Si on est sur un OS qui n'utilise pas des / comme séparateur de
     * dossier dans les chemins, on remplace les / par le bon
     * séparateur.
     */
    private function prep_dir($path) {

        if (DIRECTORY_SEPARATOR !== '/') {
            return str_replace('/', DIRECTORY_SEPARATOR, $path);
        } else {
            return $path;
        }
    }
}