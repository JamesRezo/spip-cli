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
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        global $spip_loaded;
        global $spip_racine;
        global $cwd;

        if ($spip_loaded) {
            chdir($spip_racine);

            $plugins = unserialize($GLOBALS['meta']['plugin']);

            include_spip('inc/filtres');
            $data = array_map(function ($plugin) {
                return array(
                    html_entity_decode(
                        extraire_multi($plugin['nom'], 'fr')),
                    $plugin['etat'],
                    $plugin['version']
                );
            }, $plugins);

            $table = new Table($output);
            $table->setHeaders(array('Nom', 'État', 'Version'));
            $table->setRows($data);
            $table->render();

            chdir($cwd);
        } else {
            $output->writeln("<comment>Vous n'êtes pas dans un installation de SPIP, il n'y a pas de plugins disponibles.</comment>");
        }
    }
}
