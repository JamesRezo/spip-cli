<?php

namespace Spip\Cli\Command;

use Spip\Cli\Application;
use Spip\Cli\Console\Command;
use Spip\Cli\Loader\Spip;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class AuteursStatut extends Command
{

	/** @var Application */
	protected $app;

	protected function configure() {
		$this->setName("auteurs:statut")
			->setDescription("Changer le statut d'un auteur")
			->addOption('statut', null, InputOption::VALUE_REQUIRED, 'Statut à posittionner')
			->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email de l\'auteur à modifier')
			->addOption('id', null, InputOption::VALUE_REQUIRED, 'Identifiant de l\'auteur à modifier')
			->addOption('login', null, InputOption::VALUE_REQUIRED, 'Login de l\'auteur à modifier')
			->addOption('webmestre', null, InputOption::VALUE_NONE, 'Donner le statut de webmestre')
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		/** @var Spip $spip */
		$this->demarrerSpip();

		if ($input->getOption('statut')) {
			$statut = $input->getOption('statut');
			if (!in_array($statut, array('0minirezo','1comite', '5poubelle', '6forum'))) {
				$statut = '';
			}
		}
		
		if ($input->getOption('webmestre')) {
			$statut = '0minirezo';
			$webmestre = true;
		}
		
		if ($statut) {
			if ($input->getOption('email')) {
				$email = $input->getOption('email');
			}
			if ($input->getOption('id')) {
				$id = $input->getOption('id');
			}
			if ($input->getOption('login')) {
				$login = $input->getOption('login');
			}
			
			$resultat = $this->statutAuteur($statut, $email, $id, $login, $webmestre);
			
			$this->io->table(['id_auteur', 'login', 'nom', 'email', 'statut', 'webmestre'], $resultat);
		} else {
			$this->io->text("Il faut un statut !");
		}
	}

	/** Change le statut d'un auteur */
	public function statutAuteur($statut = '', $email = '', $id = 0, $login = '', $webmestre = false) {
		$criteres = array();
		if ($email != '') {
			$criteres[] = 'email=' . sql_quote("$email");
		}
		if ($id>0) {
			$criteres[] = 'id_auteur=' . sql_quote($id, '', 'INT');
		}
		if ($login != '') {
			$criteres[] = 'login=' . sql_quote($login);
		}

		$resultat = sql_allfetsel(['id_auteur', 'login', 'nom', 'email', 'statut', 'webmestre'], 'spip_auteurs', $criteres );

		$action = array('statut' => "'$statut'", 'webmestre' => ($webmestre)?"'oui'":"'non'");
		sql_update('spip_auteurs', $action, $criteres);
		
		$resultat = array_merge($resultat, sql_allfetsel(['id_auteur', 'login', 'nom', 'email', 'statut', 'webmestre'], 'spip_auteurs', $criteres));

		return $resultat;
	}

}
