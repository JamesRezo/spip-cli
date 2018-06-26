<?php
namespace Spip\Cli\Sql;

use Spip\Cli\Loader\Sql;

/**
 * Facilitateur de requêtes SQL courantes (via PDO)
 * @api
 */
class Query {

	/** @var Sql */
	private $sql;

	/**
	 * Sql constructor.
	 * @param Sql $sql Loader SQL pour SPIP
	 */
	public function __construct(Sql $sql) {
		$this->sql = $sql;
	}

	/**
	 * Retourne une instance de Pdo chargé sur le connect du site SPIP
	 * @return \PDO
	 */
	public function getPdo() {
		return $this->sql->getPdo();
	}

	/**
	 * Retourne la valeur d’une meta (table spip_meta de spip)
	 *
	 * @param string $meta
	 * @return mixed
	 * @throws \InvalidArgumentException
	 */
	public function getMeta($meta) {
		if (!is_string($meta) or !$meta) {
			throw new \InvalidArgumentException("Argument \$meta expected to be non-empty string. Given : " . gettype($meta));
		}
		/** @var \PDO $pdo */
		$pdo = $this->getPdo();
		$table = $this->prefixerTable('spip_meta');
		$query = $pdo->prepare("SELECT valeur FROM $table WHERE nom=:nom");
		$query->bindValue(':nom', $meta, \PDO::PARAM_STR);
		$query->execute();
		$meta = $query->fetchColumn();
		return $meta;
	}

	/**
	 * Retourne la description vue par PDO de la table indiquée
	 * @param string $table
	 * @return array Une ligne par colonne
	 * @throws \InvalidArgumentException
	 */
	public function getColumnsDescription($table) {
		if (!is_string($table) or !$table) {
			throw new \InvalidArgumentException("Argument \$table expected to be non-empty string. Given : " . gettype($table));
		}

		/** Méthode pour obtenir la liste des champs même si la table est vide */
		$pdo = $this->getPdo();
		$query = $pdo->prepare('SELECT *, count(*) FROM `' . $table . '` LIMIT 1');
		$query->execute();
		$n = $query->columnCount() - 1; // ignorer le count.
		$metas = [];
		for ($i = 0; $i < $n; $i++) {
			$meta = $query->getColumnMeta($i);
			if ($meta) {
				// name en premier…
				$meta = ['name' => $meta['name']] + $meta;
				$meta['flags'] = implode(', ', $meta['flags']);
				$metas[] = $meta;
			}
		}
		return $metas;
	}

	/**
	 * Utiliser le prefixe de table SPIP déclaré
	 * @param string $table
	 * @return string
	 * @throws \Exception
	 */
	protected function prefixerTable($table) {
		if ($prefixe = $this->sql->getPrefixTable()) {
			return preg_replace('/(^|[,\s])spip_/S', '\1' . $prefixe . '_', $table);
		}
		return $table;
	}
}