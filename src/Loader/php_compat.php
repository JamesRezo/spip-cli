<?php

/**
 * Fix demarrer un vieux SPIP en cli dans un PHP 7.x
 */
if (!function_exists('set_magic_quotes_runtime')) {
	function set_magic_quotes_runtime(){}
}
