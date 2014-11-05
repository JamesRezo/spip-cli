<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

class PluginsDesactiver extends Command {
    protected function configure() {
        $this
            ->setName('plugins:desactiver')
            ->setDescription('Désactive des plugins.')
            ->addArgument(
                'plugins',
                InputArgument::IS_ARRAY,
                'La liste de plugins à désactiver.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        global $spip_loaded;
        global $spip_racine;
        global $cwd;

        if ($spip_loaded) {
            chdir($spip_racine);

            if ( ! $plugins = $input->getArgument('plugins')) {
                $output->writeln('<comment>Vous n\'avez pas spécifié de plugin à désactiver</comment>');

                $command = $this->getApplication()->find('help');
                $arguments = array(
                    'command' => 'help',
                    'command_name' => 'plugins:desactiver',
                );
                $input = new ArrayInput($arguments);
                $command->run($input, $output);
                return;
            }

            $plugins = $input->getArgument('plugins');

            $actifs = unserialize($GLOBALS['meta']['plugin']);

            $dir_uns = array();
            foreach ($plugins as $prefixe) {
                if ( ! isset($actifs[strtoupper($prefixe)])){
                    $output->writeln("<error>Le plugin $prefixe est introuvable dans les plugins actifs.</error>");
                } else {
                    $plugin = $actifs[strtoupper($prefixe)];
                    $dir = constant($plugin['dir_type']).$plugin['dir'];
                    $output->writeln("<info>Desactive le plugin $prefixe (repertoire $dir)</info>");
                    $dirs_un[] = $plugin['dir'];
                }
            }

            if (count($dirs_un)){
                include_spip('inc/plugin');
                ecrire_plugin_actifs($dirs_un,false,'enleve');
            }

            chdir($cwd);
        } else {

            $output->writeln("<comment>Vous n'êtes pas dans un installation de SPIP, il n'y a pas de plugins disponibles.</comment>");

        }
    }
}
