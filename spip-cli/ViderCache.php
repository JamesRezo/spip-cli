<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CacheInhibeCommand extends Command {
    protected function configure() {
        $this
            ->setName('cache:inhibe')
            ->setDescription('Désactive le cache de spip pendant 24h.')
            ->setAliases(array(
                'ch'
            ))
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

class CacheReactiveCommand extends Command {
    protected function configure() {
        $this
            ->setName('cache:reactive')
            ->setDescription('Réactive le cache de spip.')
            ->setAliases(array(
                'cr'
            ))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        global $spip_root;
        global $spip_loaded;

        if ($spip_loaded) {

            chdir($spip_root);

            $purger = charger_fonction('purger', 'action');
            $purger('reactive_cache');

            $output->writeln('<info>Cache réactivé</info>');
        }
    }
}

class CacheViderToutCommand extends Command {
    protected function configure() {
        $this
            ->setName('cache:vider')
            ->setDescription('Vider le cache.')
            ->setAliases(array(
                'cc' // abbréviation commune pour "clean cache"
            ))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        global $spip_root;
        global $spip_loaded;

        if ($spip_loaded) {

            chdir($spip_root);

            $purger = charger_fonction('purger', 'action');
            $purger('cache');

            $output->writeln('<info>Cache vidé</info>');
        }
    }
}

class CacheViderSquelettesCommand extends Command {
    protected function configure() {
        $this
            ->setName('cache:vider_squelettes')
            ->setDescription('Vider le cache des squelettes.')
            ->setAliases(array(
                'cs'
            ))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        global $spip_root;
        global $spip_loaded;

        if ($spip_loaded) {

            chdir($spip_root);

            $purger = charger_fonction('purger', 'action');
            $purger('squelettes');

            $output->writeln('<info>Cache des squelettes vidé</info>');
        }
    }
}

class CacheViderVignettesCommand extends Command {
    protected function configure() {
        $this
            ->setName('cache:vider_vignettes')
            ->setDescription('Vider le cache des vignettes.')
            ->setAliases(array(
                'cv'
            ))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        global $spip_root;
        global $spip_loaded;

        if ($spip_loaded) {
            chdir($spip_root);

            $purger = charger_fonction('purger', 'action');
            $purger('vignettes');

            $output->writeln('<info>Cache des vignettes vidé</info>');
        }
    }
}