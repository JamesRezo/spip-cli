<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class PluginsTelecharger extends Command {
    protected function configure() {
        $this
            ->setName('plugins:telecharger')
            ->setDescription('Telecharger un plugin depuis les dépôts.')
            ->addArgument(
                'prefix',
                InputArgument::IS_ARRAY,
                'La liste des prefixes de plugins à télécharger.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        global $spip_loaded;
        global $spip_racine;
        global $cwd;

        if ($spip_loaded) {
            chdir($spip_racine);

            $plugins_prefix = $input->getArgument('prefix');

            if (empty($plugins_prefix)) {
                $output->writeln("<error>Aucun plugin demandé</error>");
                return;
            }
        }
    }
}
