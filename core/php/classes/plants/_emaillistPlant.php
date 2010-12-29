<?php
/**
 *
 * EmailList pre-page handler script
 *
 * @package seed.org.cashmusic
 * @author CASH Music
 * @link http://cashmusic.org/
 * 
 * scans querystring values to get current page state and inititate PaypalSeed
 * objects where needed and setting a pageState variable to indicate progress
 *
 * Copyright (c) 2010, CASH Music
 * Licensed under the Affero General Public License version 3.
 * See http://www.gnu.org/licenses/agpl-3.0.html
 *
 **/

$MySQLSeed_location = __DIR__.'/../classes/MySQLSeed.php';
$EmailList_location = __DIR__.'/../classes/EmailListSeed.php';

if ($_POST['seed_emaillist'] == 'go') {
	if (isset($_POST['seed_listid'])) {
		// reset all session variables, just in case
		if (filter_var($_POST['seed_email'], FILTER_VALIDATE_EMAIL)) {
			if (isset($_POST['seed_emailcomment'])) {$initial_comment = $_POST['seed_emailcomment'];} else {$initial_comment = '';}
			if (isset($_POST['seed_verified'])) {$verified = $_POST['seed_verified'];} else {$verified = 0;}
			if (isset($_POST['seed_emailname'])) {$name = $_POST['seed_emailname'];} else {$name = 'Anonymous';}
			include_once($MySQLSeed_location);
			include_once($EmailList_location);
			$db = new MySQLSeed(DB_HOSTNAME,DB_USERNAME,DB_PASSWORD,DB_DATABASE);
			$list = new EmailListSeed($db,$_POST['seed_listid']);
			if ($list->addAddress($_POST['seed_email'],$initial_comment,$verified,$name)) {
				$_SESSION['seed_state_emaillist'] = 'completed';
			} else {
				$_SESSION['seed_state_emaillist'] = 'failed';
			}
		} else {
			$_SESSION['seed_state_emaillist'] = 'failed';
		}
	}
}
?>