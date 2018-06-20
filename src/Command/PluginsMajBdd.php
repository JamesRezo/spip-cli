<?php

namespace Spip\Cli\Command;

use Spip\Cli\Console\Style\SpipCliStyle;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'CoreMajBdd.php';

class PluginsMajBdd extends CoreMajBdd
{
	protected function configure() {
		$this
			->setName('plugins:maj:bdd')
			->setDescription('Mettre à jour la base de données et configurations des plugins.');
	}

	// actualiser les plugins
	// avant de se relancer pour finir les maj des plugins
	protected function upgrader(SpipCliStyle $io) {
		spip_log("Mettre a jour les plugins", "maj." . _LOG_INFO_IMPORTANTE);
		$this->viderCache($io);

		include_spip('inc/texte');
		include_spip('inc/filtres');

		// on installe les plugins maintenant,
		// cela permet aux scripts d'install de faire des affichages (moches...)
		ob_start();
		plugin_installes_meta();
		$content = ob_get_clean();
		if ($content) {
			$this->presenterHTML($io, $content);
		}

		// et on finit en rechargeant les options de CK
		$this->produireCacheCouteauKiss();

		// on purge les eventuelles erreurs d'activation
		$erreurs = plugin_donne_erreurs(true);
		if ($erreurs and is_array($erreurs)) {
			$erreurs = array_map(function($e) { return "* " . $e; }, $erreurs);
			$io->error($erreurs);
		}

		if (trim($content)) {
			$io->text("Fin mise à jour des plugins");
		} else {
			$io->text("Aucune mise à jour de plugins");
		}

		spip_log("Fin de mise a jour plugins site", "maj." . _LOG_INFO_IMPORTANTE);
	}


	protected function produireCacheCouteauKiss() {
		include_spip("formulaires/configurer_ck");
		if (function_exists('formulaires_configurer_ck_charger_dist')) {
			$c = formulaires_configurer_ck_charger_dist();
			$code = ck_produire_code($c);
			ck_produire_options($code);
		}
	}
}
