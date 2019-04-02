<?php

namespace Spip\Cli\Command;

use Spip\Cli\Application;
use Spip\Cli\Console\Command;
use Spip\Cli\Loader\Spip;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class AuteursLister extends Command
{

	/** @var Application */
	protected $app;

	protected function configure() {
		$this->setName("auteurs:lister")
			->setDescription("Liste les auteurs d'un site")
			->addOption('statut', null, InputOption::VALUE_REQUIRED, 'Statut spécifique')
			->addOption('email', null, InputOption::VALUE_REQUIRED, 'email (fait un LIKE %email%')
			->addOption('webmestres', null, InputOption::VALUE_NONE, 'Ne chercher que les webmestres')
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
		if ($input->getOption('email')) {
			$email = $input->getOption('email');
		}
		if ($input->getOption('webmestres')) {
			$statut = '0minirezo';
			$webmestres = true;
		}
		
		$auteurs = $this->listeAuteurs($statut, $email, $webmestres);
		
		$this->io->table(['id_auteur', 'login', 'nom', 'email', 'statut', 'webmestre'], $auteurs);
	}

	/** Cherche l’auteur SPIP -1 */
	public function listeAuteurs($statut = '', $email = '', $webmestres = false) {
		$criteres = array();
		if ($statut != '') {
			$criteres[] = 'statut = ' . sql_quote($statut);
		}
		if ($email != '') {
			$criteres[] = 'email LIKE ' . sql_quote("%$email%");
		}
		if ($webmestres) {
			$criteres[] = "webmestre = 'oui'";
		}

		$auteurs = sql_allfetsel(['id_auteur', 'login', 'nom', 'email', 'statut', 'webmestre'], 'spip_auteurs', $criteres );

		return $auteurs;
	}

	public function createWebmestre($force = false) {
		if ($webmestre = $this->findWebmestre()) {
			$this->io->care("Un auteur existe déjà avec cet identifiant.");
			$this->io->table(array_keys($webmestre), [$webmestre]);
			if ($force) {
				$this->deleteWebmestre();
			} else {
				$this->io->fail("Aucune action réalisée.");
				return false;
			}
		}

		$email = $this->getService('spip.webmestre.email');
		$login = $this->getService('spip.webmestre.login');
		$nom = $this->getService('spip.webmestre.nom');
		$prefix = $this->getService('spip.webmestre.login.prefixe');
		$password = bin2hex(random_bytes(16));
		if (!$login) {
			$login = $prefix . substr(bin2hex(random_bytes(8)), 0, 8);
		}
		$data = [
			'id_auteur' => -1,
			'login' => $login,
			'nom' => $nom ?: $login,
			'email' => $email,
			'pass' => md5($password), // SPIP passera en sha256 + sel à la première connexion.
			'statut' => '0minirezo',
			'webmestre' => 'oui',
			'imessage' => 'non',
			'prefs' => serialize([
				'activer_menudev' => 'oui',
			]),
		];

		$webmestre = null;
		if (sql_insertq('spip_auteurs', $data)) {
			$webmestre = $this->findWebmestre();
		}
		if ($webmestre and $webmestre['login'] == $data['login']) {
			$this->io->check("Création du webmetre.");
			$this->io->care("Login : " . $data['login']);
			$this->io->care("Password : " . $password);
		} else {
			$this->io->error("Le webmestre n’a pas pu être créé");
			return false;
		}
	}

	/**
	 * Supprime le webmestre observateur
	 * @return bool true si supprimé ou inexistant, false si échec
	 */
	public function deleteWebmestre() {
		if (!$this->findWebmestre()) {
			$this->io->check("Aucun webmestre observateur à supprimer");
			return true;
		}
		sql_delete('spip_auteurs', 'id_auteur = ' . sql_quote(-1, '', 'INT'));
		if ($this->findWebmestre()) {
			$this->io->error("Le webmestre n’a pas pu être supprimé");
			return false;
		}
		$this->io->check("Le webmestre a été supprimé");
		return true;
	}
}
