<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class PluginsLister extends Command {
    protected function configure() {
        $this
            ->setName('plugins:lister')
            ->setDescription('Lister les plugins activés.')
            ->addOption(
                'disponibles',
                'd',
                InputOption::VALUE_NONE,
                'Montrer les plugins disponibles'
            )
            ->addOption(
                'inactifs',
                'i',
                InputOption::VALUE_NONE,
                'Montrer les plugins inactifs'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        global $spip_loaded;
        global $spip_racine;
        global $cwd;

        if ($spip_loaded) {
            chdir($spip_racine);

            if ($input->getOption('disponibles')) {

                $plugins = lister_plugins(
                    'paquets.constante="_DIR_PLUGINS"'
                );

            } else if ($input->getOption('inactifs')) {

                $plugins = lister_plugins(array(
                    'paquets.constante="_DIR_PLUGINS"',
                    'paquets.actif!="oui"',
                ));

            } else {

                $plugins = unserialize($GLOBALS['meta']['plugin']);

            }

            include_spip('inc/filtres');
            $data = array_map(function ($plugin) {
                return array(
                    html_entity_decode(
                        extraire_multi($plugin['nom'], 'fr')),
                    $plugin['etat'],
                    joli_no_version($plugin['version']),
                );
            }, $plugins);

            $table = new Table($output);
            $table->setHeaders(array('Nom', 'État', 'Version'));
            $table->setRows($data);
            $table->render();

            chdir($cwd);

        } else {

            $output->writeln("<comment>Vous n'êtes pas dans une installation de SPIP, il n'y a pas de plugins disponibles.</comment>");

        }
    }
}

function lister_plugins ($where) {

    include_spip('base/abstract_sql');
    $plugins = sql_allfetsel('plugins.nom, paquets.etat, paquets.version',
                             'spip_plugins as plugins' .
                             ' INNER JOIN spip_paquets as paquets' .
                             ' ON plugins.prefixe=paquets.prefixe',
                             $where);

    return $plugins;
}

function joli_no_version ($moche) {

    $tab_moche = explode('.', $moche);

    $tab_joli = array_map(function ($n) {
        return ltrim($n,'0') ?: '0';
    }, $tab_moche);

    return implode('.', $tab_joli);
}
