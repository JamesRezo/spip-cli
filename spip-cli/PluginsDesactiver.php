<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;

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
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                "Désactiver tous les plugins."
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        global $spip_loaded;
        global $spip_racine;
        global $cwd;

        if ($spip_loaded) {
            chdir($spip_racine);

            $actifs = unserialize($GLOBALS['meta']['plugin']);

            /* Si on a choisi l'options --all, on prends tous les
               plugins qui ne sont pas dans plugins-dist */
            if ($input->getOption('all')) {
                $plugins = array_map('strtolower',array_keys($actifs));

                include_spip('base/abstract_sql');
                $plugins = array_filter($plugins, function ($prefixe) {
                    return sql_countsel('spip_plugins as pl'.
                                        ' INNER JOIN spip_paquets as pa'.
                                        ' ON pa.prefixe=pl.prefixe',
                                        array(
                                            'pl.prefixe='.sql_quote(strtoupper($prefixe)),
                                            'pa.constante="_DIR_PLUGINS"',
                                        ));

                });

            } else {
                $plugins = $input->getArgument('plugins');
            }

            if ( ! $plugins) {

                /* Si pas de plugin(s) spécifiés, on demande */
                $helper = $this->getHelper('question');
                $question = new Question("Quel plugin faut-il désactiver ?\n", 'help');
                $question->setAutoCompleterValues(array_map('strtolower', array_keys($actifs)));

                $reponse = trim($helper->ask($input, $output, $question));
                /* Si même après avoir demandé, l'utilisateur n'a pas
                   donné de plugin à désactiver, on affiche l'aide. */
                if ($reponse == 'help') {
                    $command = $this->getApplication()->find('help');
                    $arguments = array(
                        'command' => 'help',
                        'command_name' => 'plugins:desactiver',
                    );
                    $input = new ArrayInput($arguments);
                    $command->run($input, $output);
                    return;
                }

                $plugins = explode(' ', $reponse);
            }

            /* On liste le(s) plugin(s) qui seront désactivés et on
               demande confirmation. */
            $helper = $this->getHelper('question');
            $confirmer = new ConfirmationQuestion("Vous allez désactiver les plugins suivants : " . implode(', ', $plugins) . ".\nÊtes-vous certain-e de vouloir continuer ? ", false);

            if ( ! $helper->ask($input, $output, $confirmer)) return;

            /* Et enfin, on désactive le(s) plugin(s) */
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
