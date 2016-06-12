<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class PluginsSvpTelecharger extends Command {
    protected function configure() {
        $this
            ->setName('plugins:svp:telecharger')
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

            $output->writeln("<comment>Liste des plugins demandés : ".implode(',', $plugins_prefix)."</comment>");

            include_spip('inc/svp_decider');
    		include_spip('inc/svp_actionner');

            $decideur = new Decideur;
        	$actionneur = new Actionneur();

            foreach ($plugins_prefix as $prefix) {
                $output->writeln("<comment>Plugin en cours d'installation : ".$prefix."</comment>");
                $infos = $decideur->infos_courtes(
                        'UPPER(pl.prefixe) = LOWER("'.strtoupper($prefix).'")'
                );
                if (empty($infos['i'])) {
                    $output->writeln("<error>Le plugin ".$prefix." n'est pas référencé</error>");
                    continue;
                }
                $a_installer[key($infos['i'])] = 'geton';
	            $decideur->erreur_sur_maj_introuvable = false;
                $res = $decideur->verifier_dependances($a_installer);

            	if (!$decideur->ok) {
            		$erreurs['decideur_erreurs'] = array();
            		foreach ($decideur->err as $id => $errs) {
            			foreach ($errs as $err) {
            				$erreurs['decideur_erreurs'][] = $err;
            			}
            		}
                    $output->writeln("<error>Le plugin ".$prefix." ne peut être installé</error>");
                    $output->writeln("<error>    ".var_dump($erreurs['decideur_erreurs'],true)."</error>");
                    continue;
                }
                $actions = $decideur->presenter_actions('todo');
                $output->writeln("<comment>Pour l'installation du plugin ".$prefix." les actions suivantes sont prévues : </comment>");
                foreach($actions as $action) {
                    $output->writeln("<comment>\t".$action."</comment>");
                }
            	foreach ($decideur->todo as $_todo) {
            		$todo[$_todo['i']] = $_todo['todo'];
            	}
            	$actionneur->ajouter_actions($todo);
            	$actionneur->verrouiller();
            	$actionneur->sauver_actions();

                while ($res = $actionneur->one_action()) {
                    $output->writeln("<comment>".$res['n']." action réalisée : ".$res['todo']."</comment>");
                }

				$actionneur->deverrouiller();
				$actionneur->sauver_actions();

            	include_spip('inc/svp_depoter_local');
            	svp_actualiser_paquets_locaux();
            }
        }
    }
}
