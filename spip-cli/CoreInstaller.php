<?php

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CoreInstaller extends Command {
	protected function configure() {
		$this
			->setName('core:installer')
			->setDescription('Installer la base de données et le premier utilisateur.')
			->setAliases(array(
				'installer',
				'install',
			))
			->addOption(
				'db-server',
				'ds',
				InputOption::VALUE_OPTIONAL,
				'Type du serveur SQL',
				''
			)
			->addOption(
				'db-host',
				'dh',
				InputOption::VALUE_OPTIONAL,
				'Adresse du serveur SQL',
				'localhost'
			)
			->addOption(
				'db-login',
				'dl',
				InputOption::VALUE_OPTIONAL,
				'Identifiant au serveur SQL',
				''
			)
			->addOption(
				'db-pass',
				'dp',
				InputOption::VALUE_OPTIONAL,
				'Mot de passe pour le serveur SQL',
				''
			)
			->addOption(
				'db-database',
				'dd',
				InputOption::VALUE_OPTIONAL,
				'Nom de la base de données',
				'spip'
			)
			->addOption(
				'db-prefix',
				'dx',
				InputOption::VALUE_OPTIONAL,
				'Préfixe des tables de SPIP',
				'spip'
			)
			->addOption(
				'admin-nom',
				'an',
				InputOption::VALUE_OPTIONAL,
				'Nom du premier utilisateur',
				'Admin'
			)
			->addOption(
				'admin-login',
				'al',
				InputOption::VALUE_OPTIONAL,
				'Identifiant du premier utilisateur',
				'admin'
			)
			->addOption(
				'admin-email',
				'ae',
				InputOption::VALUE_OPTIONAL,
				'Adresse email du premier utilisateur',
				'admin@spip'
			)
			->addOption(
				'admin-pass',
				'ap',
				InputOption::VALUE_OPTIONAL,
				'Mot de passe du premier utilisateur',
				'adminadmin'
			)
		;
	}
	
	protected function execute(InputInterface $input, OutputInterface $output) {
		global $spip_racine;
        global $spip_loaded;
		
		if (!$spip_loaded) {
			$output->writeln('<error>Vous devez télécharger SPIP avant de pouvoir l’installer.</error>');
		}
		
		// Si les fichiers de SPIP sont bien là
		if ($spip_loaded) {
			// Charger les librairies nécessaires
			include_spip('inc/install');
			include_spip('inc/autoriser');
			include_spip('base/create');
			include_spip('inc/filtres');
			include_spip('inc/charsets');
			include_spip('auth/sha256.inc');
			include_spip('inc/acces');
			include_spip('inc/plugin');
			// Préciser qu'on est dans l'installation
			define('_ECRIRE_INSTALL', "1");
			define('_FILE_TMP', '_install');
			
			// On teste si c'est déjà bien installé
			$deja = (_FILE_CONNECT AND analyse_fichier_connection(_FILE_CONNECT));
			if ($deja) {
				$output->writeln('<info>La base de SPIP est déjà installée correctement.</info>');
			}
			// Sinon on essaye d'installer la base
			else {
				// Récupération des options pour la base
				$db_server = $input->getOption('db-server');
				$db_host = $input->getOption('db-host');
				$db_login = $input->getOption('db-login');
				$db_pass = $input->getOption('db-pass');
				$db_database = $input->getOption('db-database');
				$db_prefix = $input->getOption('db-prefix');
				if ($db_prefix) { set_request('tprefix', $db_prefix); }
				
				// Liste des serveurs SQL disponibles
				$serveurs = install_select_serveur();
				foreach ($serveurs as $k=>$v) {
					if (preg_match('/value=(\'|")(.*?)\1/is', $v, $serveur)) {
						$serveurs[$k] = $serveur[2];
					}
				}
				
				// Valeur par défaut du serveur SQL si aucun défini
				if (!$db_server) {
					// Si sqlite3 est disponible on le met par défaut
					if (in_array('sqlite3', $serveurs)) {
						$db_server = 'sqlite3';
					}
					// Sinon on prend le premier de la liste
					else {
						$db_server = $serveurs[0];
					}
				}
				
				// Uniquement si pas SQLite, valeur par défaut pour le login
				if (!preg_match('/sqlite.*/i', $db_server)) {
					if (!$db_login) {
						$db_login = 'root';
					}
				}
				// Unique si SQLite, on lève un drapeau
				else {
					$flag_sqlite = true;
				}
				
				// Si le type de serveur ne fait pas partie de la liste, on arrête
				if (!in_array($db_server, $serveurs)) {
					$output->writeln("<error>Les serveurs de type \"$db_server\" ne sont pas gérés.</error>");
					return;
				}
				
				// Si on n'arrive pas à se connecter à la base définie, on arrête
				if (!$db_test = spip_connect_db($db_host, 0, $db_login, $db_pass, $db_database, $db_server)) {
					$output->writeln('<error>Impossible de se connecter à la base de données.</error>');
					return;
				}
				
				// On est bien connecté, on va pouvoir installer la base !
				include_spip('install/etape_3');
				
				// Enregistrement dans les connexions de SPIP
				$GLOBALS['connexions'][$db_server] = $db_test;
				$GLOBALS['connexions'][$db_server][$GLOBALS['spip_sql_version']] = $GLOBALS['spip_' . $db_server .'_functions_' . $GLOBALS['spip_sql_version']];
				
				// On prévient qu'on démarre l'installation
				$output->writeln(array(
					"<info>Connexion à la base de données établie :</info> <comment>$db_server version ".sql_version($db_server).'</comment>',
					"\nDémarrage de l’installation de la base…",
				));
				
				// Si l'installation de la base renvoie une chaîne, c'est une erreur, on arrête
				if (
					$erreur = install_bases($db_host, $db_login, $db_pass, $db_server, $db_database, $db_database, _SPIP_CHMOD)
					and is_string($erreur)
				) {
					// On reformate la chaîne pour la ligne de commande
					$erreur = str_replace('<!--', 'ERREURDEBUT', $erreur);
					$erreur = str_replace('!>', 'ERREURFIN', $erreur);
					$erreur = textebrut($erreur);
					$erreur = str_replace('ERREURDEBUT', '<error>', $erreur);
					$erreur = str_replace('ERREURFIN', '</error>', $erreur);
					$output->writeln(array(
						'<error>Erreur lors de l’installation :</error>',
						$erreur,
						'<comment>Veuillez recommencer l’installation.</comment>',
					));
					return;
				}
				
				// Si le fichier de connexion temporaire n'est pas bien créé
				if (!file_exists(_FILE_CONNECT_TMP)) {
					$output->writeln(array(
						'<error>Le fichier de connexion n’a pas été créé correctement.</error>',
						'<comment>Veuillez recommencer l’installation.</comment>',
					));
					return;
				}
				
				// On inclue le fichier temporaire
				include(_FILE_CONNECT_TMP);
				// Si SQLite on change les droits du fichier de la base, pour être sûr !
				if ($flag_sqlite) {
					chmod("config/bases/$db_database.sqlite", _SPIP_CHMOD);
				}
				// On affirme que tout s'est bien passé
				$output->writeln("<info>La base de données a bien été installée.</info>");
			}
			
			// À ce niveau, la base est bien installée ! On va pouvoir ajouter le premier admin
			$output->writeln("\nInstallation d'un admin…");
			
			// On récupère les informations du premier admin
			$nom = $input->getOption('admin-nom');
			$login = $input->getOption('admin-login');
			$pass = $input->getOption('admin-pass');
			$email = $input->getOption('admin-email');
			
			// Si le mot de passe est trop court, on arrête
			if (strlen($pass) < _PASS_LONGUEUR_MINI) {
				$output->writeln('<error>'._T('info_passe_trop_court_car_pluriel', array('nb'=>_PASS_LONGUEUR_MINI)).'</error>');
				return;
			}
			
			// Si le login est trop court, on arrête
			if (strlen($login) < _LOGIN_TROP_COURT) {
				$output->writeln('<error>'._T('info_login_trop_court').'</error>');
				return;
			}
			
			// Si l'email n'a pas la bonne forme, on arrête
			if (!email_valide($email)) {
				$output->writeln('<error>'._T('form_email_non_valide').'</error>');
				return;
			}
			
			// On peut ajouter l'admin
			if ($id_auteur = $this->ajouter_admin($nom, $login, $pass, $email) and $id_auteur > 0){
				$output->writeln("<info>« $nom » est bien admin du site (ID $id_auteur).</info>");
			}
			else {
				$output->writeln("<error>Erreur lors de l'ajout d'un admin « $nom ».</error>");
			}
			
			// Installer les metas
			$output->write("\nInstallation des configurations par défaut… ");
			$config = charger_fonction('config', 'inc');
			$config();
			$output->writeln("<info>OK</info>");
			
			// Créer le répertoire cache, qui sert partout !
			if(!@file_exists(_DIR_CACHE)) {
				$rep = preg_replace(','._DIR_TMP.',', '', _DIR_CACHE);
				$rep = sous_repertoire(_DIR_TMP, $rep, true,true);
			}
			
			// Activer les plugins-dist
			$output->writeln("\nInstallation des plugins-dist… ");
			// Si on obtient bien une liste de plugins, on l'affiche
			if ($plugins = $this->installer_plugins($input, $output)) {
				$output->writeln('<info>Les plugins suivants sont activés :</info>');
				foreach ($plugins as $plugin){
					if (isset($plugin['nom'])) {
						$output->writeln("\t<comment>${plugin['nom']} (${plugin['version']})</comment>");
					}
				}
			}
			
			// Finitions
			$this->finir_installation();
			
			// Vérifier la securité des htaccess
			// Si elle ne fonctionne pas, prévenir
			if ($erreur = $this->verifier_htaccess($input, $output)) {
				$output->writeln('<comment>'.$erreur.'</comment>');
			}
		}
	}
	
	/**
	 * Ajouter un⋅e premier⋅e admin
	 *
	 * @return int Retourne l'identifiant de l'utilisateur créé si ça s'est bien passé
	 */
	protected function ajouter_admin($nom, $login, $pass, $email) {
		lire_metas();
		$nom = (importer_charset($nom, _DEFAULT_CHARSET));
		$login = (importer_charset($login, _DEFAULT_CHARSET));
		$email = (importer_charset($email, _DEFAULT_CHARSET));
		# pour le passwd, bizarrement il faut le convertir comme s'il avait
		# ete tape en iso-8859-1 ; car c'est en fait ce que voit md5.js
		$pass = unicode2charset(utf_8_to_unicode($pass), 'iso-8859-1');
		$htpass = generer_htpass($pass);
		$alea_actuel = creer_uniqid();
		$alea_futur = creer_uniqid();
		$shapass = _nano_sha256($alea_actuel.$pass);
		
		// prelablement, creer le champ webmestre si il n'existe pas (install neuve
		// sur une vieille base
		$t = sql_showtable("spip_auteurs", true);
		if (!isset($t['field']['webmestre'])) {
			@sql_alter("TABLE spip_auteurs ADD webmestre varchar(3)  DEFAULT 'non' NOT NULL");
		}
		
		$id_auteur = sql_getfetsel("id_auteur", "spip_auteurs", "login=" . sql_quote($login));
		if ($id_auteur !== NULL) {
			sql_updateq('spip_auteurs', array("nom"=> $nom, 'email'=> $email, 'login'=>$login, 'pass'=>$shapass, 'alea_actuel'=>$alea_actuel, 'alea_futur'=> $alea_futur, 'htpass'=>$htpass, 'statut'=>'0minirezo'), "id_auteur=$id_auteur");
		}
		else {
			$id_auteur = sql_insertq('spip_auteurs', array(
				'nom' => $nom,
				'email' => $email,
				'login' => $login,
				'pass' => $shapass,
				'htpass' => $htpass,
				'alea_actuel' => $alea_actuel,
				'alea_futur' => $alea_futur,
				'statut' =>'0minirezo'
			));
		}
		
		// le passer webmestre separrement du reste, au cas ou l'alter n'aurait pas fonctionne
		@sql_updateq('spip_auteurs', array('webmestre' => 'oui'), "id_auteur=$id_auteur");
		
		// inserer email comme email webmaster principal
		// (sauf s'il est vide: cas de la re-installation)
		if ($email) {
			ecrire_meta('email_webmaster', $email);
		}
		
		return $id_auteur;
	}
	
	/**
	 * Installation des plugins actifs (pour les plugins-dist)
	 *
	 * @return void
	 */
	protected function installer_plugins($input, $output) {
		actualise_plugins_actifs();
		chmod(_CACHE_PIPELINES, _SPIP_CHMOD);
		
		$installer_plugins = charger_fonction('installer', 'plugins');
		$meta_plug_installes = array();
		foreach (unserialize($GLOBALS['meta']['plugin']) as $prefix=>$resume) {
			if ($plug = $resume['dir']){
				$infos = $installer_plugins($plug, 'install', $resume['dir_type']);
				if ($infos){
					if (!is_array($infos) OR $infos['install_test'][0])
						$meta_plug_installes[] = $plug;
					if (is_array($infos)){
						list($ok, $trace) = $infos['install_test'];
						$output->writeln("\tInstallation du plugin ${infos['nom']}… ".($ok ? '<info>OK</info>' : '<error>Erreur</error>'));
					}
				}
			}
		}
		ecrire_meta('plugin_installes',serialize($meta_plug_installes),'non');
		
		appliquer_adresse_site('');
		return lire_config('plugin',array());
	}
	
	/**
	 * Finitions de l'installation
	 *
	 * @return void
	 */
	protected function finir_installation() {
		ecrire_acces();

		$f = str_replace( _FILE_TMP_SUFFIX, '.php', _FILE_CHMOD_TMP);
		if (file_exists(_FILE_CHMOD_TMP)) {
			if (!@rename(_FILE_CHMOD_TMP, $f)) {
				if (@copy(_FILE_CHMOD_TMP, $f))
					spip_unlink(_FILE_CHMOD_TMP);
			}
		}

		$f = str_replace( _FILE_TMP_SUFFIX, '.php', _FILE_CONNECT_TMP);
		if (file_exists(_FILE_CONNECT_TMP)) {
			spip_log("renomme $f");
			if (!@rename(_FILE_CONNECT_TMP, $f)) {
				if (@copy(_FILE_CONNECT_TMP, $f))
					@spip_unlink(_FILE_CONNECT_TMP);
			}
		}
	}
	
	/**
	 * Vérifier la securité des htaccess
	 *
	 * @return string Retourne un message d'erreur si ça ne va pas
	 */
	protected function verifier_htaccess() {
		if (
			!verifier_htaccess(_DIR_TMP, true)
			or !verifier_htaccess(_DIR_CONNECT, true)
		) {
			return _T('htaccess_inoperant');
		}
		
		return '';
	}
}
