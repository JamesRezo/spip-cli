<?php

namespace Spip\Cli\Command;

use Spip\Cli\Console\Style\SpipCliStyle;
use Spip\Cli\Loader\Spip;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CoreMajBdd extends Command
{

	protected $title = "Mise à jour de la BDD et configuration de SPIP";

	protected function configure() {
		$this
			->setName('core:maj:bdd')
			->setDescription('Mettre à jour la base de données et configurations de SPIP.');
	}


	protected function execute(InputInterface $input, OutputInterface $output) {
		/** @var SpipCliStyle $io */
		$io = $this->getApplication()->getIo($input, $output);
		$io->title($this->getDescription());

		/** @var Spip $spip */
		$spip = $this->getApplication()->getService('spip.loader');
		$spip->load();
		$spip->chdir();

		if (empty($GLOBALS['meta']['adresse_site'])) {
			$io->error("Metas inacessibles !");
			return;
		}

		$this->preparer($io);
		$this->upgrader($io);
	}

	protected function preparer(SpipCliStyle $io) {
		include_spip("inc/autoriser");
		include_spip("inc/plugin");
		include_spip("base/upgrade");

		autoriser_exception("webmestre", null, null);
		define('_TIME_OUT', time() + 3600); // on a le temps on est en cli
		lire_metas();

		$io->text("Mise à jour site : " . $GLOBALS['meta']['adresse_site']);
		spip_log("Debut mise a jour site : " . $GLOBALS['meta']['adresse_site'], "maj." . _LOG_INFO_IMPORTANTE);
	}

	protected function upgrader(SpipCliStyle $io) {
		// maj du noyau si besoin
		if (
			$GLOBALS['meta']['version_installee']
			AND $GLOBALS['spip_version_base'] != $GLOBALS['meta']['version_installee']
		) {
			$this->upgraderCoreBase($io);
			$this->upgraderCoreConfig($io);
			$io->text("Fin de mise à jour");
		} else {
			$io->text("Aucune mise à jour");
		}
	}

	protected function upgraderCoreBase(SpipCliStyle $io) {

		$io->section("Mise à jour Core");
		spip_log("Mise a jour core", "maj." . _LOG_INFO_IMPORTANTE);

		// quand on rentre par ici, c'est toujours une mise a jour de SPIP
		// lancement de l'upgrade SPIP avec retour sur l'url actuelle
		ob_start();
		$res = maj_base($GLOBALS['spip_version_base']);
		$content = ob_get_clean();
		if ($content) {
			$this->presenterHTML($io, $content);
		}
		if ($res) {
			// on arrete tout ici !
			$io->error("Erreur lors de la MAJ de la base du core");
			$io->text($res);
			exit;
		}
		spip_log("Fin de mise a jour SQL.", "maj." . _LOG_INFO_IMPORTANTE);
	}

	protected function upgraderCoreConfig(SpipCliStyle $io) {
		$io->section("Mise à jour Config Core");
		spip_log("Debut m-a-j acces et config", "maj." . _LOG_INFO_IMPORTANTE);
		$this->viderCache($io);
		$config = charger_fonction('config', 'inc');
		$config();
	}

	// supprimer quelques fichiers temporaires qui peuvent se retrouver invalides
	protected function viderCache(SpipCliStyle $io) {
		$io->text("Vider caches chemins / plugins");
		@spip_unlink(_CACHE_RUBRIQUES);
		@spip_unlink(_CACHE_PIPELINES);
		@spip_unlink(_CACHE_PLUGINS_PATH);
		@spip_unlink(_CACHE_PLUGINS_OPT);
		@spip_unlink(_CACHE_PLUGINS_FCT);
		@spip_unlink(_CACHE_CHEMIN);
		@spip_unlink(_DIR_TMP . "plugin_xml_cache.gz");
	}


	protected function presenterHTML(SpipCliStyle $io, $html) {
		$html = str_replace("&lt;", "<", $html);
		$html = str_replace(array("<br />", "<br>", "</div></div></div>", "<!--/hd-->"), "\n", $html);
		$html = explode("\n", $html);
		$html = array_map('textebrut', $html);
		$io->text($html);
	}

}
