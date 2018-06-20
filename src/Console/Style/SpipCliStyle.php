<?php

namespace Spip\Cli\Console\Style;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Ajout de helpers en suppléments des Styles Symfony
 */
class SpipCliStyle extends SymfonyStyle {

	public function check($message) {
		$this->prependText($message, ' <fg=green>✔</> ');
	}

	public function fail($message) {
		return $this->prependText($message, ' <fg=red>✘</> ');
	}

	public function care($message) {
		return $this->prependText($message, ' <fg=yellow;options=bold>!</> ');
	}

	public function prependText($message, $prepend) {
		$messages = is_array($message) ? array_values($message) : array($message);
		foreach ($messages as $message) {
			$this->text($prepend . $message);
		}
	}

	/** Simplifier ->table() avec les tableaux ayant des clés */
	public function atable($rows) {
		if (count($rows)) {
			$keys = array_keys(reset($rows));
			// reordonner toujours dans le même ordre de clés chaque ligne
			foreach ($rows as $k => $row) {
				$rows[$k] = array_map(function($key) use ($row) {
					return $row[$key];
				}, $keys);
			}
			$this->table($keys, $rows);
		}
	}

	/**
	 * Affiche une liste d’éléments sur n colonnes
	 *
	 * Utile pour présenter une longue liste sur l’écran
	 *
	 *     $io->columns($liste, 6, true);
	 *
	 * @see Command::columns()
	 * @param array $list Le tableau unidimensionel
	 * @param int $columns Le nombre de colonnes souhaitées
	 * @param bool $flip Change l’ordre des éléments
	 *     Si A, B, C sont les premières entrées du tableau $array
	 *     - false : A, B, C sera en première ligne,
	 *     - true : A, B, C sera en première colonne
	 * @return array[]
	 */
	public function columns($list, $columns = 4, $flip = false) {
		if (count($list) < $columns) {
			$columns = max(1, count($list));
		}
		$tab = self::columnize($list, $columns, $flip);
		$this->table([], $tab);
	}


	/**
	 * Transforme un tableau en tableau de n colonnes
	 *
	 * Utile pour présenter une longue liste sur l’écran
	 *
	 *     $liste = SpipCliStyle::columnize($liste, 6, true);
	 *     $io->table([], $liste);
	 *
	 * @param array $list Le tableau unidimensionel
	 * @param int $columns Le nombre de colonnes souhaitées
	 * @param bool $flip Change l’ordre des éléments
	 *     Si A, B, C sont les premières entrées du tableau $array
	 *     - false : A, B, C sera en première ligne,
	 *     - true : A, B, C sera en première colonne
	 * @return array[]
	 */
	static public function columnize(array $list, $columns = 6, $flip = false) {
		$nb = count($list);
		if ($mod = $nb % $columns) {
			$list = array_pad($list, $nb + $columns - $mod, null);
		}
		$list = array_chunk($list, $columns);
		if ($flip) {
			$list = self::flip($list);
		}
		return $list;
	}


	/**
	 * Flip bidimensional array
	 * @link https://stackoverflow.com/questions/2221476/php-how-to-flip-the-rows-and-columns-of-a-2d-array
	 */
	static public function flip($arr) {

		$rows = count($arr);
		$ridx = 0;
		$cidx = 0;

		$out = array();

		foreach($arr as $rowidx => $row){
			foreach($row as $colidx => $val){
				$out[$ridx][$cidx] = $val;
				$ridx++;
				if($ridx >= $rows){
					$cidx++;
					$ridx = 0;
				}
			}
		}
		return $out;
	}
}