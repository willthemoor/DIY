<?php
/**
 *
 * Prep the environment (strip stupid shit like magic quotes...ooh...magic!)
 *
 * @package seed.org.cashmusic
 * @author Jesse von Doom / CASH Music
 * @link http://cashmuisc.org/
 * 
 * scans querystring values to get current page state and inititate PaypalSeed
 * objects where needed and setting a pageState variable to indicate progress
 *
 * Copyright (c) 2010, CASH Music
 * Licensed under the Affero General Public License version 3.
 * See http://www.gnu.org/licenses/agpl-3.0.html
 *
 **/

// handy little __DIR__ fix posted anonymously here: 
// http://php.net/manual/en/language.constants.predefined.php
if (!defined('__DIR__')) { 
	class __FILE_CLASS__ { 
		function  __toString() { 
			$X = debug_backtrace(); 
			return dirname($X[1]['file']); 
		} 
	} 
	define('__DIR__', new __FILE_CLASS__); 
}

$ini = parse_ini_file(__DIR__.'/../settings/seed.ini');
define('PAYPAL_ADDRESS', $ini['paypal_address']);
define('PAYPAL_KEY', $ini['paypal_key']);
define('PAYPAL_SECRET', $ini['paypal_secret']);
define('PAYPAL_MICRO_ADDRESS', $ini['paypal_micro_address']);
define('PAYPAL_MICRO_KEY', $ini['paypal_micro_key']);
define('PAYPAL_MICRO_SECRET', $ini['paypal_micro_secret']);
define('DB_HOSTNAME', $ini['hostname']);
define('DB_USERNAME', $ini['username']);
define('DB_PASSWORD', $ini['password']);
define('DB_DATABASE', $ini['database']);
define('SMALLEST_ALLOWED_TRANSACTION', $ini['smallest_allowed_transaction']);

if (get_magic_quotes_gpc()) {
    function stripslashes_from_gpc(&$value) {$value = stripslashes($value);}
    $gpc = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
    array_walk_recursive($gpc, 'stripslashes_from_gpc');
	unset($gpc);
}
?>