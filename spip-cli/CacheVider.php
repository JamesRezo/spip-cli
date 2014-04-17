<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CacheVider extends Command {
    protected function configure() {
        $this
            ->setName('cache:vider')
            ->setDescription('Vider le cache.')
            ->addOption(
               'squelettes',
               null,
               InputOption::VALUE_NONE,
               'Si défini, on ne vide que le cache des squelettes'
            )
            ->addOption(
               'images',
               null,
               InputOption::VALUE_NONE,
               'Si défini, on ne vide que le cache des images'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        global $spip_racine;
        global $spip_loaded;

        if ($spip_loaded) {
            chdir($spip_racine);

            $purger = charger_fonction('purger', 'action');

            $squelettes = $input->getOption('squelettes');
            $images = $input->getOption('images');

            if (!($squelettes OR $images)) {
                $purger('cache');
            } else {
                if ($squelettes) {
                    $purger('squelettes');
                }
                if ($images) {
                    $purger('vignettes');
                }
            }

            $output->writeln('<info>Cache vidé</info>');
        }
    }
}
