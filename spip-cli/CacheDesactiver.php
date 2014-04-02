<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CacheDesactiver extends Command {
    protected function configure() {
        $this
            ->setName('cache:desactiver')
            ->setDescription('Désactive le cache de spip pendant 24h.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        global $spip_root;
        global $spip_loaded;

        if ($spip_loaded) {

            chdir($spip_root);

            $purger = charger_fonction('purger', 'action');
            $purger('inhibe_cache');

            $output->writeln('<info>Cache désactivé</info>');
        }
    }
}
