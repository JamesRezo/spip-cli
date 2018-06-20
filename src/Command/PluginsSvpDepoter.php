<?php

namespace Spip\Cli\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class PluginsSvpDepoter extends Command
{
    protected function configure()
	{
        $this
            ->setName('plugins:svp:depoter')
            ->setDescription('Ajouter un depot')
            ->addArgument(
                'url',
                InputArgument::REQUIRED,
                'URL du dépot'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
	{
        global $spip_loaded;
        global $spip_racine;
        global $cwd;

        if ($spip_loaded) {
            chdir($spip_racine);

            include_spip('inc/filtres');
            include_spip('inc/svp_depoter_distant');
            $url_depot = $input->getArgument('url');

            $ajouter= svp_ajouter_depot($url_depot);

            if (! $ajouter) {
                $output->writeln("<error>Impossible d'ajouter le dépot $url</error>");
            } else {
                $output->writeln("<info>Le dépot $url a été ajouté</info>");
            }

            chdir($cwd);
        }
    }
}
