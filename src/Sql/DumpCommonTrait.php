<?php

namespace Spip\Cli\Sql;

use Symfony\Component\Console\Helper\ProgressBar;

/**
 * Méthodes communes entre différentes commandes.
 */
trait DumpCommonTrait {

	/** @var ProgressBar */
	protected $progress;

	/**
	 * Retourne la description de la sauvegarde en cours
	 * @param null|string $name Nom du fichier de statut de sauvegarde
	 * @return bool|mixed|string
	 */
	public function readStatusFile($name = null) {
		$path = $this->getStatusFilepath($name);
		$content = null;
		if (is_file($path)) {
			$content = file_get_contents($path);
			$content = unserialize($content);
		}
		return $content;
	}

	/**
	 * Retourne le chemin du fichier de description de la sauvegarde en cours
	 * @param null|string $name Nom du fichier de statut de sauvegarde
	 * @return bool|mixed|string
	 */
	public function getStatusFilepath($name = null) {
		$name = $name ?: $this->getStatusFilename();
		return _DIR_TMP . $name . '.txt';
	}

	/**
	 * Retourne le nom du fichier de statut.
	 * @return string
	 */
	public function getStatusFilename($name = null) {
		return "status_dump_via_spipcli_" . ($name ?: 'defaut');
	}


	/**
	 * Démarre une barre de progression
	 * @param int $len Taille de la barre.
	 */
	public function startProgressBar($len = 0) {
		$this->progress = $this->io->createProgressBar($len);
		$this->progress->setFormatDefinition('dump', ' %bar% %message%');
		$this->progress->setFormat('dump');
		$this->progress->setMessage('Initialisation');
		$this->progress->start();
	}

	/**
	 * Stoppe la barre de progression
	 */
	public function stopProgressBar() {
		$this->progress->finish();
		$this->progress->clear();
		$this->io->newLine();
	}

	/**
	 * Afficher l'avancement de la copie
	 *
	 * @staticvar int $etape    Nombre de fois ou on est passe dans cette foncion
	 * @param string $courant   Flag pour indiquer si c'est la table sur laquelle on travaille actuellement
	 * @param int $total     Nombre total de tables
	 * @param string $table     Nom de la table
	 */
	public function showProgress($courant, $total, $table) {
		static $old = '';
		$this->progress->setMessage($table . ' ' . $courant);
		if ($old != $table) {
			$this->progress->advance();
			$old = $table;
		} else {
			$this->progress->display();
		}
	}

}