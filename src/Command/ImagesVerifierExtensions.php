<?php

namespace Spip\Cli\Command;

use Spip\Cli\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;


class ImagesVerifierExtensions extends Command {

	protected $mimes = [
		'jpg' => 'image/jpeg',
		'png' => 'image/png',
		'gif' => 'image/gif',
	];

	protected $requirements = [
		'jpg' => 'JPEG image data',
		'png' => 'PNG image data',
		'gif' => 'GIF image data',
	];

	protected $corrections_possibles_vers = [
		'renommer' => ['png', 'jpg', 'gif'], // les logos n’ont que 3 extensions
		'reecrire' => ['png', 'jpg', 'gif'], // les documents, on pourrait traiter plus de cas
	];

	protected $dataDirectory;


	protected function configure() {
		$this->setName("images:verifier:extensions")
			->setDescription("Vérifier les extensions d’images du répertoire IMG")
			->addOption('logos', null, InputOption::VALUE_NONE, 'Uniquement les logos')
			->addOption('documents', null, InputOption::VALUE_NONE, 'Uniquement les documents')
			->addOption('extension', null, InputOption::VALUE_OPTIONAL, 'Uniquement cette extension. Choix : jpg, png, gif')
			->addOption('reparer', null, InputOption::VALUE_NONE, 'Tente de réparer le format')
			->addOption('supprimer', null, InputOption::VALUE_NONE, 'Supprime les documents en erreur s’ils ne sont pas connus dans la médiathèque')
			->addUsage("")
			->addUsage("--logos --extension=jpg")
			->addUsage("--documents")
			->addUsage("--reparer")
			->addUsage("--logos --reparer")
			->addUsage("--documents --reparer")
			->addUsage("--documents --reparer --supprimer")
		;
	}

	protected function execute(InputInterface $input, OutputInterface $output) {

		$this->demarrerSpip();
		$this->io->title("Analyse des extensions d’images du répertoire IMG");
		$this->setDataDirectory(_DIR_IMG);

		$extensions = array_keys($this->requirements);
		if ($extension = $input->getOption('extension')) {
			if (!in_array($extension, $extensions)) {
				$this->io->error("Extension <info>$extension</info> inconnue. Possibles : " . implode(', ', $extensions));
				return;
			}
			$extensions = [$extension];
		}

		$logos = $input->getOption('logos');
		$documents = $input->getOption('documents');
		// tout par défaut.
		if (!$logos and !$documents) {
			$logos = $documents = true;
		}

		$reparer = $input->getOption('reparer');
		$supprimer = $input->getOption('supprimer');

		foreach ($extensions as $extension) {
			if ($logos) {
				$errors = $this->verifier_images_repertoire($extension, '', 'Logos');
				if ($errors and $reparer) {
					$this->reparer_logos($errors);
				}
			}
			if ($documents) {
				$errors = $this->verifier_images_repertoire($extension, $extension . DIRECTORY_SEPARATOR, 'Documents');
				if ($errors and $reparer) {
					$errors = $this->reparer_documents($errors);
				}
				if ($errors) {
					$errors = $this->verifier_fichiers_en_base(array_keys($errors));
					if ($errors and $supprimer) {
						$this->effacer_fichiers($errors);
					}
				}
			}
		}
	}

	function setDataDirectory(string $dir) {
		if (!is_dir($dir)) {
			throw new \Exception("Répertoire $dir inexistant.");
		}
		$this->dataDirectory = $dir;
	}

	function getDataDirectory() {
		return $this->dataDirectory;
	}

	function log($log) {
		spip_log($log, 'images.' . _LOG_INFO_IMPORTANTE);
	}

	function verifier_images_repertoire($extension, $repertoire, $titre) {
		$this->io->section("$titre : $extension");
		$base = $this->getDataDirectory();

		$files = glob($base . $repertoire . '*.' . $extension);

		$this->io->text(count($files) . " Fichiers");
		if (!count($files)) {
			return;
		}

		$this->io->progressStart(count($files));
		$errors = [];
		foreach ($files as $file) {
			list($ok, $desc) = $this->verifier_fichier($file, $extension);
			if (!$ok) {
				$name = str_replace($base, '', $file);
				$errors[ $file ] = "<comment>$name</comment> : $desc";
			}
			$this->io->progressAdvance();
		}
		$this->io->progressFinish();
		if ($errors) {
			$this->io->fail(count($errors) . ' fichiers erronés');
			$this->io->listing($errors);
		} else {
			$this->io->check('Tous les fichiers sont corrects');
		}
		return $errors;
	}

	function verifier_fichier($file, $extension) {
		// Version rapide, si ça matche
		// Sinon, on attrape plus d’infos avec `file`.
		if ($info = @getimagesize($file)) {
			$mime = image_type_to_mime_type($info[2]);
			if ($mime === $this->mimes[$extension]) {
				return [true, $mime];
			}
		}
		$infos = `file {$file}`;
		$ok = (false !== strpos($infos, $this->requirements[$extension]));
		list (, $desc) = explode(':', $infos, 2);
		return [$ok, trim($desc)];
	}

	function reparer_logos(array $errors) {
		return $this->reparer($errors, 'renommer');
	}

	function reparer_documents(array $errors) {
		return $this->reparer($errors, 'reecrire');
	}


	protected function reparer(array $errors, $mode = 'renommer') {
		$this->io->section("Réparer " . count($errors) . " fichiers");
		if (!in_array($mode, ['renommer', 'reecrire'])) {
			$this->io->error("Mode $mode inconnu");
			return false;
		}

		$echecs = [];
		$this->io->progressStart(count($errors));
		foreach ($errors as $file => $info) {
			$extension = $this->getExtensionFromInfo($info);
			if (!$extension) {
				$echecs[$file] = $info;
			} else {
				if ($mode == 'renommer') {
					$err = $this->reparer_renommer($file, $extension);
				} else {
					$err = $this->reparer_reecrire($file, $extension);
				}
				if ($err) {
					$echecs[$file] = $info . "\n<error>$err</error>";
				}
			}
			$this->io->progressAdvance();
		}
		$this->io->progressFinish();
		if ($echecs) {
			$this->io->fail(count($echecs) . " fichiers non réparés");
			$this->io->listing($echecs);
		} else {
			$this->io->check("Tous les fichiers sont réparés");
		}
		return $echecs;
	}

	protected function reparer_renommer($file, $extension) {
		if (!in_array($extension, $this->corrections_possibles_vers['renommer'])) {
			return "Renommage vers $extension non traitable";
		}
		$p = pathinfo($file);
		$fromName = $p['basename'];
		$toName = $p['filename'] . "." . $extension;
		if (!rename($file,  $p['dirname'] . DIRECTORY_SEPARATOR. $toName)) {
			return "Echec du renommage ($fromName en  $toName)";
		}
		$this->log("Image $fromName corrigée (renommage) en $toName");
		return '';
	}

	protected function reparer_reecrire($file, $extensionFrom) {
		$extension = pathinfo($file, PATHINFO_EXTENSION);
		if (!in_array($extension, $this->corrections_possibles_vers['reecrire'])) {
			return "Réécriture vers $extension non traitable";
		}
		$name = basename($file);
		$function = $this->getGdReadFunctionFromExtension($extensionFrom);
		if (!function_exists($function) or !$image = $function($file)) {
			if (!$image = imagecreatefromstring(file_get_contents($file))) {
				return "Echec lecture de l’image ($name)";
			}
		}
		$err = $this->creer_image($image, $extension, $file);
		imagedestroy($image);
		if ($err) {
			return $err;
		}
		$this->log("Image $name corrigée (reecrite) en $extension");
		return '';
	}


	protected function getExtensionFromInfo($info) {
		foreach ($this->requirements as $extension => $req) {
			if (false !== strpos($info, $req)) {
				return $extension;
			}
		}
		return false;
	}

	protected function getGdReadFunctionFromExtension($extension) {
		$term = ($extension == 'jpg') ? 'jpeg' : $extension;
		return "imagecreatefrom$term";
	}

	protected function getGdWriteFunctionFromExtension($extension) {
		$term = ($extension == 'jpg') ? 'jpeg' : $extension;
		return "image$term";
	}

	protected function creer_image($image, $extension, $fichier) {
		$function = $this->getGdWriteFunctionFromExtension($extension);
		if (!function_exists($function)) {
			return "Function $function indisponible";
		}

		$tmp = $fichier . ".tmp";
		$ret = $function($image, $tmp);
		if (!$ret) {
			if (file_exists($tmp)) {
				unlink($tmp);
			}
			return "Échec création image temporaire : " . basename($tmp);
		}

		if (!file_exists($tmp)) {
			return "Image temporaire absente après création : " . basename($tmp);
		}

		$taille_test = getimagesize($tmp);
		if ($taille_test[0] < 1) {
			return "Image temporaire taille nulle : " . basename($tmp);
		}

		list($ok, $desc) = $this->verifier_fichier($tmp, $extension);
		if (!$ok) {
			return "Image temporaire pas du type attendu : " . basename($tmp) . " ($desc)";
		}

		if (file_exists($fichier)) {
			unlink($fichier);
		}

		if (!rename($tmp, $fichier)) {
			return "Échec renommage de " . basename($tmp) . " en " . basename($fichier);
		}

		return "";
	}

	protected function verifier_fichiers_en_base($files) {
		$base = $this->getDataDirectory();
		$fichiers = array_map(function($file) use ($base) {
			return substr($file, strlen($base));
		}, $files);

		$presents = sql_allfetsel('id_document, fichier', 'spip_documents', sql_in('fichier', $fichiers));
		if ($presents) {
			$absents = array_diff($fichiers, array_column($presents, 'fichier'));
		} else {
			$absents = $fichiers;
		}

		if ($absents) {
			$this->io->text("<info>Fichiers absents dans spip_documents :</info>");
			$this->io->text("Ils peuvent peut être être supprimés du coup…");
			$this->io->listing(array_map(function($file) {
				return "<comment>$file</comment>";
			}, $absents));
		} else {
			$this->io->text("Tous les fichiers en erreur sont présents dans spip_documents.");
			$this->io->text("");
		}
		if ($presents) {
			$this->io->text("<info>Fichiers présents dans spip_documents :</info>");
			$this->io->listing(array_map(function($row) {
				return "<comment>{$row['fichier']}</comment> : n° {$row['id_document']}";
			}, $presents));
		}

		// remettre le chemin complet
		$absents = array_map(function($file) use ($base) {
			return $base . $file;
		}, $absents);

		return $absents;
	}

	function effacer_fichiers($fichiers) {
		$fichiers = array_filter($fichiers);
		$this->io->text("Effacer " . count($fichiers) . " fichiers");
		foreach ($fichiers as $fichier) {
			$name = substr($fichier, strlen($this->getDataDirectory()));
			if (strpos($fichier, $this->getDataDirectory()) === 0) {
				unlink($fichier);
				if (file_exists($fichier)) {
					$this->io->fail($name);
				} else {
					$this->io->check($name);
				}
			} else {
				$this->io->care("<comment>$name</comment> : Fichier ignoré (hors IMG)");
			}
		}
	}
}