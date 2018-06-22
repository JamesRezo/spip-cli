<?php

namespace Spip\Cli\Command;

use Spip\Cli\Application;
use Spip\Cli\Console\Style\SpipCliStyle;
use Spip\Cli\Loader\Spip;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class AuteursSuperadmin extends Command
{

	/** @var SpipCliStyle */
	protected $io;
	/** @var Application */
	protected $app;

	protected function configure() {
		$this->setName("auteurs:superadmin")
			->setDescription("Ajoute / supprime un webmestre observateur (id_auteur = -1).")
			->addOption('delete', 'd', InputOption::VALUE_NONE, 'Pour supprimer le webmestre observateur.')
			->addOption('force', 'f', InputOption::VALUE_NONE, 'Supprime puis crée un webmestre observateur.')
			->setAliases(['root:me'])
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->app = $this->getApplication();
		$this->io = $this->app->getIO($input, $output);

		/** @var Spip $spip */
		$spip = $this->app->getService('spip.loader');
		$spip->load();
		$spip->chdir();

		if ($input->getOption('delete')) {
			$this->deleteWebmestre();
		} else {
			$this->createWebmestre($input->getOption('force'));
		}
		$this->io->text("");
	}

	/** Cherche l’auteur SPIP -1 */
	public function findWebmestre() {
		$webmestre = sql_fetsel(['id_auteur AS id', 'login', 'nom', 'email', 'statut', 'webmestre'], 'spip_auteurs', 'id_auteur = ' . sql_quote(-1, '', 'INT'));
		return $webmestre;
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

		$email = $this->app->getService('spip.webmestre.email');
		$login = $this->app->getService('spip.webmestre.login');
		$nom = $this->app->getService('spip.webmestre.nom');
		$prefix = $this->app->getService('spip.webmestre.login.prefixe');
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
