<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class PluginsActiver extends Command {
    protected function configure() {
        $this
            ->setName('plugins:activer')
            ->setDescription('Active un ou plusieurs plugins.')
            ->addArgument(
                'plugins',
                InputArgument::IS_ARRAY,
                'La liste de plugins à activer.'
            )
            ->addOption(
                'all',
                'a',
                InputOption::VALUE_NONE,
                "Activer tous les plugins disponibles."
            )
			->addOption(
				'yes',
				'y',
                InputOption::VALUE_NONE,
				'Activer les plugins sans poser de question'
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

            include_spip('inc/plugin');
            $disponibles = liste_plugin_files();
            $disponibles = array_map(function ($dir) {
                $get_infos = charger_fonction('get_infos', 'plugins');
                $infos = $get_infos($dir);
                return $infos['prefix'];
            }, $disponibles);

            $inactifs = array_filter($disponibles, function ($prefixe) {
                return ! array_key_exists(
                    strtoupper($prefixe),
                    unserialize($GLOBALS['meta']['plugin'])
                );
            });

            /* Si on a choisi l'option --all, on prend tous les
               plugins inactifs. */
            if ($input->getOption('all')) {
                $plugins = array_map('strtolower', $inactifs);
            } else {
                $plugins = $input->getArgument('plugins');
            }

			// Si on est en mode "All" et qu'il n'y a pas de plugin, il n'y a rien a faire
			if (!$plugins and $input->getOption('all')) {
				return;
			}

            if ( ! $plugins) {

                /* Si pas de plugin(s) spécifiés, on demande */
                $helper = $this->getHelper('question');
                $question = new Question("Quel plugin faut-il activer ?\n", 'help');
                $question->setAutoCompleterValues(array_map('strtolower', $inactifs));

                $reponse = trim($helper->ask($input, $output, $question));
                /* Si même après avoir demandé, l'utilisateur n'a pas
                   donné de plugin à activer, on affiche l'aide. */
                if ($reponse == 'help') {
                    $command = $this->getApplication()->find('help');
                    $arguments = array(
                        'command' => 'help',
                        'command_name' => 'plugins:activer',
                    );
                    $input = new ArrayInput($arguments);
                    $command->run($input, $output);
                    return;
                }

                $plugins = explode(' ', $reponse);
            }

            /* On liste le(s) plugin(s) qui seront activés et on
               demande confirmation. */
            $helper = $this->getHelper('question');

			if (!$input->getOption('yes')) {
				$confirmer = new ConfirmationQuestion("Vous allez activer les plugins suivants : " . implode(', ', $plugins) . ".\nÊtes-vous certain-e de vouloir continuer ? ", false);

				if ( ! $helper->ask($input, $output, $confirmer)) return;
			}

            /* Et enfin, on désactive le(s) plugin(s) */
            $dir_un = array();
            foreach ($plugins as $prefixe) {
                if ( ! in_array($prefixe, $disponibles)) {
                    $output->writeln("<error>Le plugin $prefixe est introuvable dans les plugins disponibles.</error>");
                } else if ( ! in_array($prefixe, $inactifs)) {
                    $output->writeln("<comment>Le plugin $prefixe est déjà activé.</comment>");
                } else {
                    include_spip('base/abstract_sql');
                    $p = sql_fetsel('src_archive, constante', 'spip_paquets', array('constante!=""', 'prefixe='.sql_quote($prefixe)));

                    $dir = constant($p['constante']) . $p['src_archive'];
                    $output->writeln("<info>Active le plugin $prefixe (repertoire $dir)</info>");

                    $dirs_un[] = $p['src_archive'];
                }
            }

            if (count($dirs_un)){
                include_spip('inc/plugin');
                ecrire_plugin_actifs($dirs_un,false,'ajoute');
                /* actualiser la liste des paquets locaux */
                include_spip('inc/svp_depoter_local');
                /*sans forcer tout le recalcul en base, mais en
                  récupérant les erreurs XML */
                $err = array();
                svp_actualiser_paquets_locaux(false, $err);
                if ($err) {
                    $output->writeln("<error>Erreur XML $err</error>");
                }
            }

            chdir($cwd);

        } else {

            $output->writeln("<comment>Vous n'êtes pas dans un installation de SPIP, il n'y a pas de plugins disponibles.</comment>");

        }
    }
}
