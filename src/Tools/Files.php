<?php

namespace Spip\Cli\Tools;

use Composer\Autoload\ClassLoader;

class Files {

	/**
	 * Retourne un path avec tous les \ ou / remplacés par le séparateur attendu par l’OS
	 * @param string $path
	 * @return string mixed
	 */
	public static function formatPath($path) {
		return str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $path);
	}

	/**
	 * Find the root directory of a Composer application (ie: vendor/../)
	 * @return string;
	 * @throws \RuntimeException
	 * @throws \ReflectionException
	 */
	public static function findAppRootDirectory() {
		static $root = null;
		if (is_null($root)) {
			$reflector = new \ReflectionClass(ClassLoader::class);
			$vendorPath = substr($reflector->getFileName(), 0, -strlen('/composer/ClassLoader.php'));
			if ($vendorPath && is_dir($vendorPath)) {
				return dirname($vendorPath) . '/';
			}
			throw new \RuntimeException('Unable to detect root path.');
		}
	}


	/**
	 * Find namespace of a PHP File
	 * @link https://gist.github.com/naholyr/1885879
	 * @param string $path Path of php file.
	 * @return null|string
	 */
	public static function getNamespace($path) {
		if (!file_exists($path) or !is_readable($path)) {
			return null;
		}
		$src = file_get_contents($path);
		$tokens = token_get_all($src);
		$count = count($tokens);
		$i = 0;
		$namespace = '';
		$namespace_ok = false;
		while ($i < $count) {
			$token = $tokens[$i];
			if (is_array($token) && $token[0] === T_NAMESPACE) {
				// Found namespace declaration
				while (++$i < $count) {
					if ($tokens[$i] === ';') {
						$namespace_ok = true;
						$namespace = trim($namespace);
						break;
					}
					$namespace .= is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i];
				}
				break;
			}
			$i++;
		}
		if (!$namespace_ok) {
			return null;
		} else {
			return $namespace;
		}
	}
}