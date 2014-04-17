<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CacheReactiver extends Command {
    protected function configure() {
        $this
            ->setName('cache:reactiver')
            ->setDescription('Réactive le cache de spip.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        global $spip_racine;
        global $spip_loaded;

        if ($spip_loaded) {
            chdir($spip_racine);

            $purger = charger_fonction('purger', 'action');
            $purger('reactive_cache');

            $output->writeln('<info>Cache réactivé</info>');
        }
    }
}
