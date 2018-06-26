<?php

namespace Spip\Cli\Loader;

/**
 * Analyse de connecteur SQL SPIP et connecteur à PDO.
 * @api
 */
class Sql {

	private $connect;

	private $done = false;
	private $infos = [];
	private $pdo = null;

	/**
	 * Sql constructor.
	 * @param null $connectPath Chemin du connect
	 */
	public function __construct($connectPath = null) {
		if ($connectPath) {
			$this->setConnect($connectPath);
		}
	}

	/**
	 * Définit le chemin du connect
	 * @param string $connect
	 * @throws \Exception
	 */
	public function setConnect($connect) {
		if (!is_file($connect)) {
			throw new \Exception('Connect file not found : ' . $connect);
		}
		$this->done = false;
		$this->pdo = null;
		$this->connect = $connect;
	}

	/**
	 * Retourne une instance de Pdo chargé sur le connect du site SPIP
	 * @return \PDO
	 */
	public function getPdo() {
		if (!$this->done) {
			$infos = $this->getInfo();
			$dsn = $this->getPdoDsn($infos);
			$this->pdo = new \PDO($dsn, $infos['username'], $infos['password']);
		}
		return $this->pdo;
	}

	/**
	 * Retourne les informations du connect
	 * @return array|bool
	 * @throws \Exception
	 */
	public function getInfo() {
		if (!$this->done) {
			$this->infos = $this->infoConnect($this->connect);
		}
		return $this->infos;
	}

	/**
	 * Retourne le préfixe des tables
	 * @return string
	 * @throws \Exception
	 */
	public function getPrefixTable() {
		$infos = $this->getInfo();
		return $infos['spip_prefix'];
	}

	public function getPdoDsn($infos) {
		$dsn = "";
		if (!is_array($infos) or empty($infos['driver'])) {
			throw new \Exception('Connect info needs driver name.');
		}
		switch ($infos['driver']) {
			case 'mysql':
				// $db = new PDO('mysql:host=localhost;dbname=testdb;charset=utf8mb4', 'username', 'password');
				$params = [];
				// en CLI on ne trouve pas toujours le bon socket pour localhost
				foreach (['host', 'dbname', 'charset'] as $param) {
					if (!empty($infos[$param])) {
						$params[] = $param . '=' . $infos[$param];
					}
				}
				$dsn = 'mysql:' . implode(';', $params);
			break;

			case 'sqlite':
			case 'sqlite3':
				// $db = new PDO('sqlite:chemin.sqlite');
				$dsn = 'sqlite:' . $infos['file'];
				break;
		}
		return $dsn;
	}

	/**
	 * Retourne la liste des paramètres d’un 'connect' SPIP
	 * @param string $connectFile Chemin du connect
	 * @return bool
	 */
	public function infoConnect($connect) {
		if (!$connect or !is_file($connect)) {
			throw new \Exception('Connect file not found : ' . $connect);
		}
		$content = file_get_contents($connect);
		$content = explode("\n", $content);
		$content = array_filter($content, function($line) {
			if (strpos($line, 'spip_connect_db') === 0) {
				return true;
			}
			return false;
		});
		$content = end($content);
		if (!$content) {
			throw new \Exception('Parsing connect file in error.');
		}
		$content = str_replace(['spip_connect_db', '(', ')'], '', rtrim(rtrim($content), ';'));
		$content = explode(',', $content);
		array_walk($content, function(&$value) {
			$value = trim(trim(trim($value), "'"), '"');
		});
		if (!is_array($content)) {
			throw new \Exception('Parsing connect file in error.');
		}
		if (count($content) !== 9) {
			if (count($content) == 8) {
				$content[] = 'utf8';
			} else {
				throw new \Exception('Incorrect argument count in connect file.');
			}
		}

		$keys = [
			'host',
			'port',
			'username',
			'password',
			'dbname',
			'driver',
			'spip_prefix',
			'spip_auth',
			'charset',
		];

		$infos = array_combine($keys, $content);
		if (in_array($infos['driver'], ['sqlite', 'sqlite3'])) {
			$infos['file'] = $this->findSqliteDatabaseFromConnect($infos['dbname'], $connect);
		}
		return $infos;
	}

	public function findSqliteDatabaseFromConnect($dbname, $connect) {
		$sqlite = dirname($connect) . '/bases/' . $dbname . '.sqlite';
		if (!is_file($sqlite)) {
			throw new \Exception('Sqlite database not found : ' . $sqlite);
		}
		return $sqlite;
	}
}