<?php
if(strrpos($_SERVER['REQUEST_URI'],'controller.php') !== false) {
	header('Location: ./');
	exit;
}
require_once('./constants.php');

require_once(CASH_PLATFORM_PATH);
$pages_path = ADMIN_BASE_PATH . '/components/pages/';
$admin_primary_cash_request = new CASHRequest();
$request_parameters = null;

// admin-specific autoloader
function cash_admin_autoloadCore($classname) {
	$file = ADMIN_BASE_PATH . '/classes/'.$classname.'.php';
	if (file_exists($file)) {
		require_once($file);
	}
}
spl_autoload_register('cash_admin_autoloadCore');

// make an object to use throughout the pages
$cash_admin = new AdminCore($admin_primary_cash_request->sessionGet('cash_effective_user'));
$cash_admin->page_data['www_path'] = ADMIN_WWW_BASE_PATH;
$cash_admin->page_data['fullredraw'] = false;

// set AJAX or not:
$cash_admin->page_data['data_only'] = false;
if (isset($_REQUEST['data_only'])) {
	$cash_admin->page_data['data_only'] = true;
}

// grab path from .htaccess redirect
if ($_REQUEST['p'] && ($_REQUEST['p'] != realpath(ADMIN_BASE_PATH)) && ($_REQUEST['p'] != '_')) {
	$parsed_request = str_replace('/','_',trim($_REQUEST['p'],'/'));
	define('REQUESTED_ROUTE', '/' . trim($_REQUEST['p'],'/') . '/');
	$cash_admin->page_data['requested_route'] = REQUESTED_ROUTE;
	if (file_exists($pages_path . 'controllers/' . $parsed_request . '.php')) {
		define('BASE_PAGENAME', $parsed_request);
		$include_filename = BASE_PAGENAME.'.php';
	} else {
		// cascade through a "failure" to see if it is a true bad request, or a page requested
		// with parameters requested — always show the last good true filename and push the
		// remaining request portions into te request_parameters array
		if (strpos($parsed_request,'_') !== false) {
			$fails_at_level = 0;
			$last_good_level = 0;
			$successful_request = '';
			$last_request = '';
			$exploded_request = explode('_',$parsed_request);
			for($i = 0, $a = sizeof($exploded_request); $i < $a; ++$i) {
				if ($i > 0) {
					$test_request = $last_request . '_' . $exploded_request[$i];
				} else {
					$test_request = $last_request . $exploded_request[$i];
				}
				if (file_exists($pages_path . 'controllers/' . $test_request . '.php')) {
					$successful_request = $test_request;
					$last_good_level = $i;
				} else {
					if (!$fails_at_level || $fails_at_level < $last_good_level) {
						$fails_at_level = $i;
					}
					//break;
				}
				$last_request = $test_request;
			}
			if ($fails_at_level == 0) {
				define('BASE_PAGENAME', '');
				$include_filename = 'error.php';
			} else {
				// define page as successful request
				define('BASE_PAGENAME', $successful_request);
				$include_filename = BASE_PAGENAME.'.php';
				// turn the rest of the request into the parameters array
				$request_parameters = array_slice($exploded_request, 0 - (sizeof($exploded_request) - ($fails_at_level)));
			}
		} else {
			define('BASE_PAGENAME', '');
			$include_filename = 'error.php';
		}
	}
} else {
	define('BASE_PAGENAME','mainpage');
	$include_filename = 'mainpage.php';
}

$run_login_scripts = false;

if (!isset($cash_admin->page_data['requested_route'])) {
	$cash_admin->page_data['requested_route'] = '/';
}

// if a login needs doing, do it
$cash_admin->page_data['login_message'] = 'Hello. Log In';
if (isset($_POST['login'])) {
	$browseridassertion = false;
	if (isset($_POST['browseridassertion'])) {
		if ($_POST['browseridassertion'] != -1) {
			$browseridassertion = $_POST['browseridassertion'];
		}
	}
	$login_details = AdminHelper::doLogin($_POST['address'],$_POST['password'],true,$browseridassertion);
	if ($login_details !== false) {
		$admin_primary_cash_request->sessionSet('cash_actual_user',$login_details);
		$admin_primary_cash_request->sessionSet('cash_effective_user',$login_details);
		if ($browseridassertion) {
			$address = CASHSystem::getBrowserIdStatus($browseridassertion);
		} else {
			$address = $_POST['address'];
		}
		$admin_primary_cash_request->sessionSet('cash_effective_user_email',$address);
		
		$run_login_scripts = true;
		
		$cash_admin->page_data['fullredraw'] = true;
	} else {
		$admin_primary_cash_request->sessionClearAll();
		$cash_admin->page_data['login_message'] = 'Try Again';
		$cash_admin->page_data['login_error'] = true;
	}
}

if ($run_login_scripts) {
	// handle initial login chores
	$cash_admin->runAtLogin();
}

// handle the banner hiding
if (isset($_GET['hidebanner'])) {
	$current_settings = $cash_admin->getUserSettings();
	if (isset($current_settings['banners'][BASE_PAGENAME])) {
		$current_settings['banners'][BASE_PAGENAME] = false;
		$cash_admin->setUserSettings($current_settings);
	}
}

// include Mustache because you know it's time for that
include_once(dirname(CASH_PLATFORM_PATH) . '/lib/mustache.php/Mustache.php');
$cash_admin->mustache_groomer = new Mustache;

// finally, output the template and page-specific markup (checking for current login)
if ($admin_primary_cash_request->sessionGet('cash_actual_user')) {
	// start buffering output
	ob_start();
	// set basic data for the template
	$cash_admin->page_data['user_email'] = $admin_primary_cash_request->sessionGet('cash_effective_user_email');
	$cash_admin->page_data['ui_title'] = AdminHelper::getPageTitle();
	$cash_admin->page_data['ui_page_tip'] = AdminHelper::getPageTipsString();
	$cash_admin->page_data['section_menu'] = AdminHelper::buildSectionNav();
	// set empty uid/code, then set if found
	$cash_admin->page_data['status_code'] = (isset($_SESSION['cash_last_response'])) ? $_SESSION['cash_last_response']['status_code']: '';
	$cash_admin->page_data['status_uid'] = (isset($_SESSION['cash_last_response'])) ? $_SESSION['cash_last_response']['status_uid']: '';
	// figure out the section color and current section name:
	$cash_admin->page_data['specialcolor'] = '';
	$exploded_base = explode('_',BASE_PAGENAME);
	$cash_admin->page_data['section_name'] = $exploded_base[0];
	if ($exploded_base[0] == 'assets') {
		$cash_admin->page_data['specialcolor'] = ' usecolor1';
	} elseif ($exploded_base[0] == 'people') {
		$cash_admin->page_data['specialcolor'] = ' usecolor2';
	} elseif ($exploded_base[0] == 'commerce') {
		$cash_admin->page_data['specialcolor'] = ' usecolor3';
	} elseif ($exploded_base[0] == 'calendar') {
		$cash_admin->page_data['specialcolor'] = ' usecolor4';
	} elseif ($exploded_base[0] == 'elements') {
		$cash_admin->page_data['specialcolor'] = ' usecolor5';
	}
	// set true/false for each section being current
	$cash_admin->page_data['ui_current_elements'] = ($exploded_base[0] == 'elements') ? true: false;
	$cash_admin->page_data['ui_current_assets'] = ($exploded_base[0] == 'assets') ? true: false;
	$cash_admin->page_data['ui_current_people'] = ($exploded_base[0] == 'people') ? true: false;
	$cash_admin->page_data['ui_current_commerce'] = ($exploded_base[0] == 'commerce') ? true: false;
	$cash_admin->page_data['ui_current_calendar'] = ($exploded_base[0] == 'calendar') ? true: false;
	// include controller for current page
	include($pages_path . 'controllers/' . $include_filename);
	if (file_exists($pages_path . 'views/' . $include_filename)) {
		// phasing this out:
		include($pages_path . 'views/' . $include_filename);
	} else {
		// the right way:
		$cash_admin->page_data['page_content_markup'] = $cash_admin->mustache_groomer->render($cash_admin->page_content_template, $cash_admin->page_data);
		// temporary fix. we'll ditch the buffering when all pages have been converted:
		echo $cash_admin->page_data['page_content_markup'];
	}
	// push buffer contents to "content" and stop buffering
	$cash_admin->page_data['content'] = ob_get_contents();
	ob_end_clean();

	if ($cash_admin->page_data['data_only']) {
		// data_only means we're working with AJAX requests, so dump valid JSON to the browser for the script to parse
		if ($cash_admin->page_data['fullredraw']) {
			$cash_admin->page_data['fullcontent'] = $cash_admin->mustache_groomer->render(file_get_contents(ADMIN_BASE_PATH . '/ui/default/template.mustache'), $cash_admin->page_data);
		}
		echo json_encode($cash_admin->page_data);
	} else {
		// now let's get our {{mustache}} on
		echo $cash_admin->mustache_groomer->render(file_get_contents(ADMIN_BASE_PATH . '/ui/default/template.mustache'), $cash_admin->page_data);
	}
} else {
	/*********************************
	 *
	 * SHOW LOGIN PAGE
	 *
	 *********************************/
	$cash_admin->page_data['browser_id_js'] = CASHSystem::getBrowserIdJS();

	// before we get all awesome and whatnot, detect for password reset stuff. should only happen 
	// with a full page reload, not a data-only one as above
	if (isset($_POST['dopasswordresetlink'])) {
		if (filter_var($_POST['address'], FILTER_VALIDATE_EMAIL)) {
			$reset_key = $cash_admin->requestAndStore(
				array(
					'cash_request_type' => 'system', 
					'cash_action' => 'setresetflag',
					'address' => $_POST['address']
				)
			);
			$reset_key = $reset_key['payload'];
			if ($reset_key) {
				$reset_message = 'A password reset was requested for this email address. If you didn\'t request the '
							   . 'reset simply ignore this message and no change will be made. To reset your password '
							   . 'follow this link: '
							   . "\n\n"
							   . CASHSystem::getCurrentURL()
							   . '_?dopasswordreset=' . $reset_key . '&address=' . urlencode($_POST['address']) // <-- the underscore for urls ending with a / ...i dunno. probably fixable via htaccess
							   . "\n\n"
							   . 'Thank you.';
				CASHSystem::sendEmail(
					'A password reset has been requested',
					CASHSystem::getDefaultEmail(),
					$_POST['address'],
					$reset_message,
					'Reset your password?'
				);
				$cash_admin->page_data['reset_message'] = 'Thanks. Just sent an email with instructions. Check your SPAM filters if you do not see it soon.';
			} else {
				$cash_admin->page_data['reset_message'] = 'There was an error. Please check the address and try again.';
			}
		}
	}

	// this for returning password reset people:
	if (isset($_GET['dopasswordreset'])) {
		$valid_key = $cash_admin->requestAndStore(
			array(
				'cash_request_type' => 'system', 
				'cash_action' => 'validateresetflag',
				'address' => $_GET['address'],
				'key' => $_GET['dopasswordreset']
			)
		);
		if ($valid_key) {
			$cash_admin->page_data['reset_key'] = $_GET['dopasswordreset'];
			$cash_admin->page_data['reset_email'] = $_GET['address'];
			$cash_admin->page_data['reset_action'] = CASHSystem::getCurrentURL();
		}
	}

	// and this for the actual password reset after return folks submit:
	if (isset($_POST['finalizepasswordreset'])) {
		$valid_key = $cash_admin->requestAndStore(
			array(
				'cash_request_type' => 'system', 
				'cash_action' => 'validateresetflag',
				'address' => $_POST['address'],
				'key' => $_POST['key']
			)
		);
		if ($valid_key) {
			$id_response = $cash_admin->requestAndStore(
				array(
					'cash_request_type' => 'people', 
					'cash_action' => 'getuseridforaddress',
					'address' => $_POST['address']
				)
			);
			if ($id_response['payload']) {
				$change_request = new CASHRequest(
					array(
						'cash_request_type' => 'system', 
						'cash_action' => 'setlogincredentials',
						'user_id' => $id_response['payload'], 
						'address' => $_POST['address'], 
						'password' => $_POST['newpassword']
					)
				);
				if ($change_request->response['payload'] !== false) {
					$cash_admin->page_data['reset_message'] = 'Successfully changed the password. Go ahead and log in.';
				} else {
					$cash_admin->page_data['reset_message'] = 'There was an error setting your password. Please try again.';
				}
			} else {
				$cash_admin->page_data['reset_message'] = 'There was an error setting the password. Please try again.';
			}
		}
	}

	// end login stuff

	if ($cash_admin->page_data['data_only']) {
		// data_only means we're working with AJAX requests, so dump valid JSON to the browser for the script to parse
		$cash_admin->page_data['fullredraw'] = true;
		$cash_admin->page_data['fullcontent'] = $cash_admin->mustache_groomer->render(file_get_contents(ADMIN_BASE_PATH . '/ui/default/login.mustache'), $cash_admin->page_data);
		echo json_encode($cash_admin->page_data);
	} else {
		// magnum p.i. = sweet {{mustache}} > don draper
		echo $cash_admin->mustache_groomer->render(file_get_contents(ADMIN_BASE_PATH . '/ui/default/login.mustache'), $cash_admin->page_data);
	}	
}
?>