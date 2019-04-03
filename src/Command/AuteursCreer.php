<?php

namespace Spip\Cli\Command;

use Spip\Cli\Application;
use Spip\Cli\Console\Command;
use Spip\Cli\Loader\Spip;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class AuteursCreer extends Command
{

	/** @var Application */
	protected $app;

	protected function configure() {
		$this->setName("auteurs:creer")
			->setDescription("Créer ou modifier un auteur (identifié selon id, sinon login, sinon email)")
			->addOption('statut', null, InputOption::VALUE_REQUIRED, 'Statut à positionner')
			->addOption('nom', null, InputOption::VALUE_REQUIRED, 'Nom de l\'auteur à créer ou modifier')
			->addOption('id', null, InputOption::VALUE_REQUIRED, 'Identifiant de l\'auteur à créer ou modifier')
			->addOption('login', null, InputOption::VALUE_REQUIRED, 'Login de l\'auteur à créer ou modifier')
			->addOption('password', null, InputOption::VALUE_REQUIRED, 'Mot de passe de l\'auteur à créer ou modifier')
			->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email de l\'auteur à créer ou modifier')
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
		
		if ($input->getOption('nom')) {
				$nom = $input->getOption('nom');
		}
		if ($input->getOption('email')) {
				$email = $input->getOption('email');
		}
		if ($input->getOption('id')) {
			$id = $input->getOption('id');
		}
		if ($input->getOption('login')) {
			$login = $input->getOption('login');
		}
		if ($input->getOption('password')) {
			$password = $input->getOption('password');
		}
		
		if ($id OR $login OR $email) {
			$resultat = $this->creerOuModifierAuteur($id, $nom, $email, $login, $password, $statut, $webmestre);
			if ($resultat) {
				$this->io->table(['id_auteur', 'nom', 'email', 'statut', 'webmestre', 'login', 'password'], $resultat);
			}
		} else {
			$this->io->text("Pas assez d'informations pour créer ou modifier l'auteur.\nIl faut Id, Email ou Login pour modifier.\nIl faut Login et Mot de passe pour créer");
		}
	}

	/** Créer ou modifier un auteur */
	public function creerOuModifierAuteur($id = 0, $nom='', $email = '', $login = '', $password = '', $statut = '', $webmestre = false) {
		$criteres = array();
		if ($id>0) {
			$criteres[] = 'id_auteur=' . sql_quote($id, '', 'INT');
			$auteur = sql_allfetsel(['id_auteur', 'login', 'nom', 'email', 'statut', 'webmestre'], 'spip_auteurs', $criteres );
			if (count($auteur) > 0) {
				$resultat = $this->modifierAuteur($id, $nom, $email, $login, $password, $statut, $webmestre, $criteres);
				return $resultat;
			}
		}
		if ($login != '') {
			$criteres[] = 'login=' . sql_quote($login);
			$auteur = sql_allfetsel(['id_auteur', 'login', 'nom', 'email', 'statut', 'webmestre'], 'spip_auteurs', $criteres );
			if (count($auteur) > 0) {
				$resultat = $this->modifierAuteur($id, $nom, $email, $login, $password, $statut, $webmestre, $criteres);
				return $resultat;
			}
		}
		if ($email != '') {
			$criteres[] = 'email=' . sql_quote("$email");
			$auteur = sql_allfetsel(['id_auteur', 'login', 'nom', 'email', 'statut', 'webmestre'], 'spip_auteurs', $criteres );
			if (count($auteur) > 0) {
				$resultat = $this->modifierAuteur($id, $nom, $email, $login, $password, $statut, $webmestre, $criteres);
				return $resultat;
			}
		}
		
		// Si on arrive là, c'est qu'on n'a pas pu modifier, donc, c'est une création
		if ($login AND $password) {
			$action = array();
			// Si pas de nom, utiliser le login
			$action = array_merge($action, ['nom' => ($nom)?$nom:$login]); 
			if ($email) $action = array_merge($action, ['email' => $email]); 
			if ($login) $action = array_merge($action, ['login' => $login]); 
			if ($password) $action = array_merge($action, ['pass' =>  md5($password)]); // SPIP passera en sha256 + sel à la première connexion. 
			// Si pas de statut, mettre rédacteur par défaut
			$action = array_merge($action, ['statut' => ($statut)?$statut:'1comite']);
			if ($webmestre) $action = array_merge($action, ['webmestre' => ($webmestre)?'oui':'non']);

			sql_insertq('spip_auteurs', $action);
			
			$criteres[] = 'login=' . sql_quote($login);
			$resultat = sql_allfetsel(['id_auteur', 'nom', 'email', 'statut', 'webmestre', 'login', 'pass'], 'spip_auteurs', $criteres);
			
			$this->io->text("Création d'un auteur");
			return $resultat;
		}
		
		$this->io->text("Il manque le mot de passe");
		return false;
	}
	/** Modification d'un auteur */
	public function modifierAuteur($id = 0, $nom='', $email = '', $login = '', $password = '', $statut = '', $webmestre = false, $criteres = array()) {

		$resultat = sql_allfetsel(['id_auteur', 'nom', 'email', 'statut', 'webmestre', 'login', 'pass'], 'spip_auteurs', $criteres );

		$action = array();
		if ($nom) $action = array_merge($action, ['nom' => "'$nom'"]); 
		if ($email) $action = array_merge($action, ['email' => "'$email'"]); 
		if ($login) $action = array_merge($action, ['login' => "'$login'"]); 
		if ($password) $action = array_merge($action, ['pass' =>  "'".md5($password)."'"]); // SPIP passera en sha256 + sel à la première connexion. 
		if ($statut) $action = array_merge($action, ['statut' => "'$statut'"]);
		if ($webmestre) $action = array_merge($action, ['webmestre' => ($webmestre)?"'oui'":"'non'"]);
		
		sql_update('spip_auteurs', $action, $criteres);
		
		$resultat = array_merge($resultat, sql_allfetsel(['id_auteur', 'nom', 'email', 'statut', 'webmestre', 'login', 'pass'], 'spip_auteurs', $criteres));

		$this->io->text("Modification d'un auteur existant (avant/après)");
		return $resultat;
	}

}
