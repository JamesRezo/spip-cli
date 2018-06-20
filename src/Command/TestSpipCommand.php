<?php

namespace Spip\Cli\Command;

use Spip\Cli\Console\Style\SpipCliStyle;
use Spip\Cli\Loader\Spip;
use Spip\Cli\Loader\Sql;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class TestSpipCommand extends Command
{

	/** @var SpipCliStyle */
	protected $io;

	protected function configure(){
		$this->setName("test:spip")
			->setDescription("Vérifie notre connexion au site SPIP.");
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$this->io = $this->getApplication()->getIO($input, $output);
		$this->io->title('Vérifier notre accès à SPIP');

		if (!$this->testSpipTrouver()) {
			return;
		}
		if (!$this->testSpipDemarrer()) {
			return;
		}
		if (!$this->testPdoCharger()) {
			return;
		}
		if (!$this->testPdoRequete()) {
			return;
		}
		if (!$this->testSpipRequete()) {
			return;
		}
	}

	protected function testSpipTrouver() {
		$io = $this->io;

		try {
			/** @var Spip $spip */
			$spip = $this->getApplication()->getService('spip.loader');
		} catch (\Exception $e) {
			$io->fail('Chargement de SPIP en erreur !');
			$io->fail($e->getMessage());
			return false;
		}
		if (!$spip->exists()) {
			$io->fail('Pas de SPIP à notre emplacement.');
			return false;
		}
		$io->check('SPIP est trouvé');
		return true;
	}

	protected function testSpipDemarrer() {
		$io = $this->io;
		try {
			/** @var Spip $spip */
			$spip = $this->getApplication()->getService('spip.loader');
			$spip->load();
		} catch (\Exception $e) {
			$io->fail('Chargement de SPIP en erreur !');
			$io->fail($e->getMessage());
			return false;
		}
		$io->check('SPIP est démarré');
		return true;
	}

	protected function testSpipRequete() {
		$io = $this->io;
		$webmestres = sql_allfetsel(
			['id_auteur AS id', 'nom', 'email'],
			'spip_auteurs',
			[
				'webmestre = ' . sql_quote('oui', '', 'text') ,
				'statut = ' . sql_quote('0minirezo', '', 'text'),
			]
		);
		if ($webmestres) {
			$io->check(count($webmestres) . ' webmestre·s sur ce site (via SPIP)');
			$this->printQueryResults($webmestres);
		} else {
			$io->care('Aucun webmestre sur ce site');
		}

		return true;
	}

	protected function testPdoCharger() {
		$io = $this->io;

		try {
			/** @var Sql $sql */
			$sql = $this->getApplication()->getService('spip.sql');
			$pdo = $sql->getPdo();
		} catch (\Exception $e) {
			$io->fail('Echec de chargement du PDO');
			$io->fail('DSN: ' . $sql->getPdoDsn($sql->getInfo()));
			$io->fail($e->getMessage());
			return false;
		}
		$io->check('PDO Accessible');
		return true;
	}

	protected function testPdoRequete() {
		$io = $this->io;

		/** @var \PDO $pdo */
		$pdo = $this->getApplication()->getService('spip.sql')->getPdo();

		$query = $pdo->prepare(
			'SELECT id_auteur AS id, nom, email FROM spip_auteurs WHERE webmestre = :webmestre AND statut = :statut'
		);
		$query->bindValue(':webmestre', 'oui', \PDO::PARAM_STR);
		$query->bindValue(':statut', '0minirezo', \PDO::PARAM_STR);
		$query->execute();
		$webmestres = $query->fetchAll(\PDO::FETCH_ASSOC);
		if ($webmestres) {
			$io->check(count($webmestres) . ' webmestre·s sur ce site (via PDO)');
			#$this->printQueryResults($webmestres);
		} else {
			$io->care('Aucun webmestre sur ce site (via PDO)');
		}
		return true;
	}

	public function printQueryResults($results) {
		$io = $this->io;
		if (!$results) {
			$io->care('Il n’y a aucun resultat.');
		} else {
			$io->table(array_keys(reset($results)), $results);
		}
	}
}
