<?php
/* Copyright (C) 2002-2007 Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2003      Xavier Dutoit        <doli@sydesy.com>
 * Copyright (C) 2004-2012 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2004      Sebastien Di Cintio  <sdicintio@ressource-toi.org>
 * Copyright (C) 2004      Benoit Mortier       <benoit.mortier@opensides.be>
 * Copyright (C) 2005-2012 Regis Houssin        <regis@dolibarr.fr>
 * Copyright (C) 2011      Philippe Grand       <philippe.grand@atoo-net.com>
 * Copyright (C) 2008      Matteli
 * Copyright (C) 2011-2012 Herve Prot		<herve.prot@symeos.com>
 * Copyright (C) 2011      Juanjo Menent	<jmenent@2byte.es>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file       htdocs/main.inc.php
 * 	\ingroup	core
 * 	\brief      File that defines environment for Speedealing pages only (variables not required by scripts)
 */
//@ini_set('memory_limit', '64M');	// This may be useless if memory is hard limited by your PHP
// For optionnal tuning. Enabled if environment variable DOL_TUNING is defined.
// A call first. Is the equivalent function dol_microtime_float not yet loaded.
$micro_start_time = 0;
if (!empty($_SERVER['DOL_TUNING'])) {
    list($usec, $sec) = explode(" ", microtime());
    $micro_start_time = ((float) $usec + (float) $sec);
    // Add Xdebug code coverage
    //define('XDEBUGCOVERAGE',1);
    if (defined('XDEBUGCOVERAGE')) {
        xdebug_start_code_coverage();
    }
}

// Removed magic_quotes
if (function_exists('get_magic_quotes_gpc')) { // magic_quotes_* removed in PHP6
    if (get_magic_quotes_gpc()) {

        // Forcing parameter setting magic_quotes_gpc and cleaning parameters
        // (Otherwise he would have for each position, condition
        // Reading stripslashes variable according to state get_magic_quotes_gpc).
        // Off mode recommended (just do $db->escape for insert / update).
        function stripslashes_deep($value) {
            return (is_array($value) ? array_map('stripslashes_deep', $value) : stripslashes($value));
        }

        $_GET = array_map('stripslashes_deep', $_GET);
        $_POST = array_map('stripslashes_deep', $_POST);
        $_FILES = array_map('stripslashes_deep', $_FILES);
        //$_COOKIE  = array_map('stripslashes_deep', $_COOKIE); // Useless because a cookie should never be outputed on screen nor used into sql
        @set_magic_quotes_runtime(0);
    }
}

/**
 * Security: SQL Injection and XSS Injection (scripts) protection (Filters on GET, POST, PHP_SELF).
 *
 * @param		string		$val		Value
 * @param		string		$type		1=GET, 0=POST, 2=PHP_SELF
 * @return		boolean					true if there is an injection
 */
function test_sql_and_script_inject($val, $type) {
    $sql_inj = 0;
    // For SQL Injection (only GET and POST are used to be included into bad escaped SQL requests)
    if ($type != 2) {
        $sql_inj += preg_match('/delete[\s]+from/i', $val);
        $sql_inj += preg_match('/create[\s]+table/i', $val);
        $sql_inj += preg_match('/update.+set.+=/i', $val);
        $sql_inj += preg_match('/insert[\s]+into/i', $val);
        $sql_inj += preg_match('/select.+from/i', $val);
        $sql_inj += preg_match('/union.+select/i', $val);
        $sql_inj += preg_match('/(\.\.%2f)+/i', $val);
    }
    // For XSS Injection done by adding javascript with script
    // This is all cases a browser consider text is javascript:
    // When it found '<script', 'javascript:', '<style', 'onload\s=' on body tag, '="&' on a tag size with old browsers
    // All examples on page: http://ha.ckers.org/xss.html#XSScalc
    $sql_inj += preg_match('/<script/i', $val);
    //$sql_inj += preg_match('/<style/i', $val);
    $sql_inj += preg_match('/base[\s]+href/i', $val);
    if ($type == 1) {
        $sql_inj += preg_match('/javascript:/i', $val);
        $sql_inj += preg_match('/vbscript:/i', $val);
    }
    // For XSS Injection done by adding javascript closing html tags like with onmousemove, etc... (closing a src or href tag with not cleaned param)
    if ($type == 1)
        $sql_inj += preg_match('/"/i', $val);   // We refused " in GET parameters value
    if ($type == 2)
        $sql_inj += preg_match('/[\s;"]/', $val); // PHP_SELF is an url and must match url syntax
    return $sql_inj;
}

/**
 * Security: Return true if OK, false otherwise.
 *
 * @param		string		&$var		Variable name
 * @param		string		$type		1=GET, 0=POST, 2=PHP_SELF
 * @return		boolean					true if ther is an injection
 */
function analyse_sql_and_script(&$var, $type) {
    if (is_array($var)) {
        foreach ($var as $key => $value) {
            if (analyse_sql_and_script($value, $type)) {
                $var[$key] = $value;
            } else {
                print 'Access refused by SQL/Script injection protection in main.inc.php';
                exit;
            }
        }
        return true;
    } else {
        return (test_sql_and_script_inject($var, $type) <= 0);
    }
}

// Sanity check on URL
if (!empty($_SERVER["PHP_SELF"])) {
    $morevaltochecklikepost = array($_SERVER["PHP_SELF"]);
    analyse_sql_and_script($morevaltochecklikepost, 2);
}
// Sanity check on GET parameters
if (!empty($_SERVER["QUERY_STRING"])) {
    $morevaltochecklikeget = array($_SERVER["QUERY_STRING"]);
    analyse_sql_and_script($morevaltochecklikeget, 1);
}
// Sanity check on POST
analyse_sql_and_script($_POST, 0);

// This is to make Speedealing working with Plesk
if (!empty($_SERVER['DOCUMENT_ROOT']))
    set_include_path($_SERVER['DOCUMENT_ROOT'] . '/htdocs');

// Include the conf.php and functions.lib.php
require_once("filefunc.inc.php");

// Init session. Name of session is specific to Speedealing instance.
$prefix = dol_getprefix();
$sessionname = 'DOLSESSID_' . $prefix;
$sessiontimeout = 'DOLSESSTIMEOUT_' . $prefix;
if (!empty($_COOKIE[$sessiontimeout]))
    ini_set('session.gc_maxlifetime', $_COOKIE[$sessiontimeout]);
session_name($sessionname);
session_start();
if (ini_get('register_globals')) { // To solve bug in using $_SESSION
    foreach ($_SESSION as $key => $value) {
        if (isset($GLOBALS[$key]))
            unset($GLOBALS[$key]);
    }
}

// Init the 5 global objects
// This include will set: $conf, $db, $langs, $user, $mysoc objects
require_once("master.inc.php");

// Activate end of page function
register_shutdown_function('dol_shutdown');

// Detection browser
if (isset($_SERVER["HTTP_USER_AGENT"])) {
    $tmp = getBrowserInfo();
    $conf->browser->phone = $tmp['phone'];
    $conf->browser->name = $tmp['browsername'];
    $conf->browser->os = $tmp['browseros'];
    $conf->browser->firefox = $tmp['browserfirefox'];
    $conf->browser->version = $tmp['browserversion'];
}


// Force HTTPS if required ($conf->file->main_force_https is 0/1 or https Speedealing root url)
if (!empty($conf->file->main_force_https)) {
    $newurl = '';
    if ($conf->file->main_force_https == '1') {
        if (!empty($_SERVER["SCRIPT_URI"])) { // If SCRIPT_URI supported by server
            if (preg_match('/^http:/i', $_SERVER["SCRIPT_URI"]) && !preg_match('/^https:/i', $_SERVER["SCRIPT_URI"])) { // If link is http
                $newurl = preg_replace('/^http:/i', 'https:', $_SERVER["SCRIPT_URI"]);
            }
        } else { // Check HTTPS environment variable (Apache/mod_ssl only)
            // $_SERVER["HTTPS"] is 'on' when link is https, otherwise $_SERVER["HTTPS"] is empty or 'off'
            if (empty($_SERVER["HTTPS"]) || $_SERVER["HTTPS"] != 'on') {  // If link is http
                $newurl = preg_replace('/^http:/i', 'https:', DOL_MAIN_URL_ROOT) . $_SERVER["REQUEST_URI"];
            }
        }
    } else {
        $newurl = $conf->file->main_force_https . $_SERVER["REQUEST_URI"];
    }
    // Start redirect
    if ($newurl) {
        dol_syslog("main.inc: dolibarr_main_force_https is on, we make a redirect to " . $newurl);
        header("Location: " . $newurl);
        exit;
    } else {
        dol_syslog("main.inc: dolibarr_main_force_https is on but we failed to forge new https url so no redirect is done", LOG_WARNING);
    }
}


// Chargement des includes complementaires de presentation
if (!defined('NOREQUIREMENU'))
    require_once(DOL_DOCUMENT_ROOT . "/core/class/menu.class.php");   // Need 10ko memory (11ko in 2.2)
if (!defined('NOREQUIREHTML'))
    require_once(DOL_DOCUMENT_ROOT . "/core/class/html.form.class.php");  // Need 660ko memory (800ko in 2.2)
if (!defined('NOREQUIREAJAX') && $conf->use_javascript_ajax)
    require_once(DOL_DOCUMENT_ROOT . '/core/lib/ajax.lib.php'); // Need 22ko memory








    
// If install or upgrade process not done or not completely finished, we call the install page.
if (!empty($conf->global->MAIN_NOT_INSTALLED) || !empty($conf->global->MAIN_NOT_UPGRADED)) {
    dol_syslog("main.inc: A previous install or upgrade was not complete. Redirect to install page.", LOG_WARNING);
    Header("Location: " . DOL_URL_ROOT . "/install/index.php");
    exit;
}
// If an upgrade process is required, we call the install page.
if ((!empty($conf->global->MAIN_VERSION_LAST_UPGRADE) && ($conf->global->MAIN_VERSION_LAST_UPGRADE != DOL_VERSION))
        || (empty($conf->global->MAIN_VERSION_LAST_UPGRADE) && !empty($conf->global->MAIN_VERSION_LAST_INSTALL) && ($conf->global->MAIN_VERSION_LAST_INSTALL != DOL_VERSION))) {
    $versiontocompare = empty($conf->global->MAIN_VERSION_LAST_UPGRADE) ? $conf->global->MAIN_VERSION_LAST_INSTALL : $conf->global->MAIN_VERSION_LAST_UPGRADE;
    require_once(DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php");
    $dolibarrversionlastupgrade = preg_split('/[.-]/', $versiontocompare);
    $dolibarrversionprogram = preg_split('/[.-]/', DOL_VERSION);
    $rescomp = versioncompare($dolibarrversionprogram, $dolibarrversionlastupgrade);
    if ($rescomp > 0) {   // Programs have a version higher than database. We did not add "&& $rescomp < 3" because we want upgrade process for build upgrades
        dol_syslog("main.inc: database version " . $versiontocompare . " is lower than programs version " . DOL_VERSION . ". Redirect to install page.", LOG_WARNING);
        Header("Location: " . DOL_URL_ROOT . "/install/index.php");
        exit;
    }
}

// Creation of a token against CSRF vulnerabilities
if (!defined('NOTOKENRENEWAL')) {
    $token = dol_hash(uniqid(mt_rand(), TRUE)); // Genere un hash d'un nombre aleatoire
    // roulement des jetons car cree a chaque appel
    if (isset($_SESSION['newtoken']))
        $_SESSION['token'] = $_SESSION['newtoken'];
    $_SESSION['newtoken'] = $token;
}
if (!empty($conf->global->MAIN_SECURITY_CSRF)) { // Check validity of token, only if option enabled (this option breaks some features sometimes)
    if (isset($_POST['token']) && isset($_SESSION['token'])) {
        if (($_POST['token'] != $_SESSION['token'])) {
            dol_syslog("Invalid token in " . $_SERVER['HTTP_REFERER'] . ", action=" . $_POST['action'] . ", _POST['token']=" . $_POST['token'] . ", _SESSION['token']=" . $_SESSION['token'], LOG_WARNING);
            //print 'Unset POST by CSRF protection in main.inc.php.';	// Do not output anything because this create problems when using the BACK button on browsers.
            unset($_POST);
        }
    }
}

// Disable modules (this must be after session_start and after conf has been loaded)
if (GETPOST('disablemodules'))
    $_SESSION["disablemodules"] = GETPOST('disablemodules');
if (!empty($_SESSION["disablemodules"])) {
    $disabled_modules = explode(',', $_SESSION["disablemodules"]);
    foreach ($disabled_modules as $module) {
        if ($module)
            $conf->$module->enabled = false;
    }
}


/*
 * Phase authentication / login
 */
$login = '';
if (!defined('NOLOGIN')) {
    // $authmode lists the different means of identification to be tested in order of preference.
    // Example: 'http', 'dolibarr', 'ldap', 'http,forceuser'
    // Authentication mode
    if (empty($dolibarr_main_authentication))
        $dolibarr_main_authentication = 'http,dolibarr';
    // Authentication mode: forceuser
    if ($dolibarr_main_authentication == 'forceuser' && empty($dolibarr_auto_user))
        $dolibarr_auto_user = 'auto';
    // Set authmode
    $authmode = explode(',', $dolibarr_main_authentication);

    // No authentication mode
    if (!count($authmode) && empty($conf->login_modules)) {
        $langs->load('main');
        dol_print_error('', $langs->trans("ErrorConfigParameterNotDefined", 'dolibarr_main_authentication'));
        exit;
    }

    // If requested by the login has already occurred, it is retrieved from the session
    // Call module if not realized that his request.
    // At the end of this phase, the variable $login is defined.
    $resultFetchUser = '';
    $test = true;
    $user = new User($db);
    //print $user->fetch();exit;
    if (!isset($_COOKIE["AuthSession"]) || !$user->fetch()) {
        // It is not already authenticated and it requests the login / password
        include_once(DOL_DOCUMENT_ROOT . '/core/lib/security2.lib.php');

        // If in demo mode, we check we go to home page through the public/demo/index.php page
        if ($dolibarr_main_demo && $_SERVER['PHP_SELF'] == DOL_URL_ROOT . '/index.php') {  // We ask index page
            if (!preg_match('/public/', $_SERVER['HTTP_REFERER'])) {
                dol_syslog("Call index page from another url than demo page");
                header("Location: " . DOL_URL_ROOT . '/public/demo/index.php');
                exit;
            }
        }

        // Verification security graphic code
        if (GETPOST("username", "alpha", 2) && !empty($conf->global->MAIN_SECURITY_ENABLECAPTCHA)) {
            $sessionkey = 'dol_antispam_value';
            $ok = (array_key_exists($sessionkey, $_SESSION) === TRUE && (strtolower($_SESSION[$sessionkey]) == strtolower($_POST['code'])));

            // Verifie code
            if (!$ok) {
                dol_syslog('Bad value for code, connexion refused');
                $langs->load('main');
                $langs->load('errors');

                $user->trigger_mesg = 'ErrorBadValueForCode - login=' . GETPOST("username", "alpha", 2);
                $_SESSION["dol_loginmesg"] = $langs->trans("ErrorBadValueForCode");
                $test = false;

                // Appel des triggers
                include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                $interface = new Interfaces($db);
                $result = $interface->run_triggers('USER_LOGIN_FAILED', $user, $user, $langs, $conf, GETPOST('entity'));
                if ($result < 0) {
                    $error++;
                }
                // Fin appel triggers
            }
        }

        $usertotest = (!empty($_COOKIE['login_dolibarr']) ? $_COOKIE['login_dolibarr'] : GETPOST("username", "alpha", 2));
        $passwordtotest = (!empty($_COOKIE['password_dolibarr']) ? $_COOKIE['password_dolibarr'] : $_POST["password"]);

        // Validation of login/pass/entity
        // If ok, the variable login will be returned
        // If error, we will put error message in session under the name dol_loginmesg
        $goontestloop = false;
        if (isset($_SERVER["REMOTE_USER"]) && in_array('http', $authmode))
            $goontestloop = true;
        if (GETPOST("username", "alpha", 2) || !empty($_COOKIE['login_dolibarr']) || GETPOST('openid_mode', 'alpha', 1))
            $goontestloop = true;

        if ($test && $goontestloop) {
            $login = checkLoginPassEntity($usertotest, $passwordtotest, $entitytotest, $authmode);
            if ($login) {
                $dol_authmode = $conf->authmode; // This properties is defined only when logged to say what mode was successfully used
                $dol_tz = $_POST["tz"];
                $dol_tz_string = $_POST["tz_string"];
                $dol_dst = 0;
                if (isset($_POST["dst_first"]) && isset($_POST["dst_second"])) {
                    include_once(DOL_DOCUMENT_ROOT . "/core/lib/date.lib.php");
                    $datenow = dol_now();
                    $datefirst = dol_stringtotime($_POST["dst_first"]);
                    $datesecond = dol_stringtotime($_POST["dst_second"]);
                    if ($datenow >= $datefirst && $datenow < $datesecond)
                        $dol_dst = 1;
                }
                //print $datefirst.'-'.$datesecond.'-'.$datenow; exit;
                $dol_dst_observed = $_POST["dst_observed"];
                $dol_dst_first = $_POST["dst_first"];
                $dol_dst_second = $_POST["dst_second"];
                $dol_screenwidth = $_POST["screenwidth"];
                $dol_screenheight = $_POST["screenheight"];
            }

            if (!$login) {
                dol_syslog('Bad password, connexion refused', LOG_DEBUG);
                $langs->load('main');
                $langs->load('errors');

                // Bad password. No authmode has found a good password.
                $user->trigger_mesg = $langs->trans("ErrorBadLoginPassword") . ' - login=' . GETPOST("username", "alpha", 2);
                $_SESSION["dol_loginmesg"] = $langs->trans("ErrorBadLoginPassword");

                // Appel des triggers
                include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                $interface = new Interfaces($db);
                $result = $interface->run_triggers('USER_LOGIN_FAILED', $user, $user, $langs, $conf, GETPOST("username", "alpha", 2));
                if ($result < 0) {
                    $error++;
                }
                // Fin appel triggers
            }
        }

        // End test login / passwords
        if (!$login) {
            // We show login page
            if (!is_object($langs)) { // This can occurs when calling page with NOREQUIRETRAN defined
                include_once(DOL_DOCUMENT_ROOT . "/core/class/translate.class.php");
                $langs = new Translate("", $conf);
            }
            dol_loginfunction($langs, $conf, $mysoc);
            exit;
        }

		$user = new User($db);
		$resultFetchUser = $user->fetch("org.couchdb.user:" . $login);
        /*
          if ($resultFetchUser <= 0)
          {
          dol_syslog('User not found, connexion refused');
          session_destroy();
          session_name($sessionname);
          session_start();    // Fixing the bug of register_globals here is useless since session is empty

          if ($resultFetchUser == 0)
          {
          $langs->load('main');
          $langs->load('errors');

          $user->trigger_mesg='ErrorCantLoadUserFromDolibarrDatabase - login='.$login;
          $_SESSION["dol_loginmesg"]=$langs->trans("ErrorCantLoadUserFromDolibarrDatabase",$login);
          }
          if ($resultFetchUser < 0)
          {
          $user->trigger_mesg=$user->error;
          $_SESSION["dol_loginmesg"]=$user->error;
          }

          // Call triggers
          include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
          $interface=new Interfaces($db);
          $result=$interface->run_triggers('USER_LOGIN_FAILED',$user,$user,$langs,$conf,$_POST["entity"]);
          if ($result < 0) {
          $error++;
          }
          // End call triggers */
    } else {
        // We are already into an authenticated session
        //$login = $_SESSION["dol_login"];

        //$resultFetchUser = $user->fetch("org.couchdb.user:" . $login);
        $resultFetchUser = $user->fetch();
        
        if ($resultFetchUser <= 0) {
            // Account has been removed after login
            session_destroy();
            session_name($sessionname);
            session_start(); // Fixing the bug of register_globals here is useless since session is empty
            //if ($resultFetchUser <= 0) {
            $langs->load('main');
            $langs->load('errors');


            //$_SESSION["dol_loginmesg"] = 
            //}
            //if ($resultFetchUser < 0) {
            $user->trigger_mesg = 'SessionExpire - login=' . $login;
            $_SESSION["dol_loginmesg"] = $langs->trans("ErrorCantLoadUserFromDolibarrDatabase", $login); // TODO Session Expire
            //}
            // Call triggers
            include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
            $interface = new Interfaces($db);
            $result = $interface->run_triggers('USER_LOGIN_FAILED', $user, $user, $langs, $conf, (isset($_POST["entity"]) ? $_POST["entity"] : 0));
            if ($result < 0) {
                $error++;
            }
            // End call triggers

            header('Location: ' . DOL_URL_ROOT . '/index.php');
            exit;
        } else {
            if (!empty($conf->global->MAIN_ACTIVATE_UPDATESESSIONTRIGGER)) { // We do not execute such trigger at each page load by default
                // Call triggers
                include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                $interface = new Interfaces($db);
                $result = $interface->run_triggers('USER_UPDATE_SESSION', $user, $user, $langs, $conf, $conf->entity);
                if ($result < 0) {
                    $error++;
                }
                // End call triggers
            }
        }
    }

    // Is it a new session that has started ?
    // If we are here, this means authentication was successfull.
    if (!isset($_SESSION["dol_login"])) {
        $error = 0;

        // New session for this login
        $_SESSION["dol_login"] = $user->values->name;
        $_SESSION["dol_authmode"] = isset($dol_authmode) ? $dol_authmode : '';
        $_SESSION["dol_tz"] = isset($dol_tz) ? $dol_tz : '';
        $_SESSION["dol_tz_string"] = isset($dol_tz_string) ? $dol_tz_string : '';
        $_SESSION["dol_dst"] = isset($dol_dst) ? $dol_dst : '';
        $_SESSION["dol_dst_observed"] = isset($dol_dst_observed) ? $dol_dst_observed : '';
        $_SESSION["dol_dst_first"] = isset($dol_dst_first) ? $dol_dst_first : '';
        $_SESSION["dol_dst_second"] = isset($dol_dst_second) ? $dol_dst_second : '';
        $_SESSION["dol_screenwidth"] = isset($dol_screenwidth) ? $dol_screenwidth : '';
        $_SESSION["dol_screenheight"] = isset($dol_screenheight) ? $dol_screenheight : '';
        $_SESSION["dol_company"] = $conf->global->MAIN_INFO_SOCIETE_NOM;
        $_SESSION["dol_entity"] = $conf->entity;
        dol_syslog("This is a new started user session. _SESSION['dol_login']=" . $_SESSION["dol_login"] . ' Session id=' . session_id());

        $db->begin();

        $user->update_last_login_date();

        // Call triggers
        include_once(DOL_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
        $interface = new Interfaces($db);
        $result = $interface->run_triggers('USER_LOGIN', $user, $user, $langs, $conf, $_POST["entity"]);
        if ($result < 0) {
            $error++;
        }
        // End call triggers

        if ($error) {
            $db->rollback();
            session_destroy();
            dol_print_error($db, 'Error in some triggers on action USER_LOGIN', LOG_ERR);
            exit;
        } else {
            $db->commit();
        }

        // Create entity cookie, just used for login page
        if (!empty($conf->multicompany->enabled) && !empty($conf->global->MULTICOMPANY_COOKIE_ENABLED) && isset($_POST["entity"])) {
            include_once(DOL_DOCUMENT_ROOT . "/core/class/cookie.class.php");

            $entity = $_SESSION["dol_login"] . '|' . $_POST["entity"];

            $prefix = dol_getprefix();
            $entityCookieName = 'DOLENTITYID_' . $prefix;
            // TTL : is defined in the config page multicompany
            $ttl = (!empty($conf->global->MULTICOMPANY_COOKIE_TTL) ? dol_now() + $conf->global->MULTICOMPANY_COOKIE_TTL : dol_now() + 60 * 60 * 8 );
            // Cryptkey : will be created randomly in the config page multicompany
            $cryptkey = (!empty($conf->file->cookie_cryptkey) ? $conf->file->cookie_cryptkey : '' );

            $entityCookie = new DolCookie($cryptkey);
            $entityCookie->_setCookie($entityCookieName, $entity, $ttl);
        }

        // Hooks on successfull login
        $action = '';
        include_once(DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php');
        $hookmanager = new HookManager($db);
        $hookmanager->initHooks(array('login'));
        $parameters = array('dol_authmode' => $dol_authmode);
        $reshook = $hookmanager->executeHooks('afterLogin', $parameters, $user, $action); // Note that $action and $object may have been modified by some hooks
        if ($reshook < 0)
            $error++;

        //header('Location: ' . DOL_URL_ROOT . '/index.php?idmenu=menu:home'); // TODO Add default database
        //exit;
    }


    // If user admin, we force the rights-based modules
    if ($user->admin) {
        $user->rights->user->user->lire = 1;
        $user->rights->user->user->creer = 1;
        $user->rights->user->user->password = 1;
        $user->rights->user->user->supprimer = 1;
        $user->rights->user->self->creer = 1;
        $user->rights->user->self->password = 1;
    }

    /*
     * Overwrite configs global by personal configs
     */
    // Set liste_limit
    if (isset($user->conf->MAIN_SIZE_LISTE_LIMIT)) { // Can be 0
        $conf->liste_limit = $user->conf->MAIN_SIZE_LISTE_LIMIT;
    }
    if (isset($user->conf->PRODUIT_LIMIT_SIZE)) {  // Can be 0
        $conf->product->limit_size = $user->conf->PRODUIT_LIMIT_SIZE;
    }
    // Replace conf->css by personalized value
    if (isset($user->conf->MAIN_THEME) && $user->conf->MAIN_THEME) {
        $conf->theme = $user->conf->MAIN_THEME;
        $conf->css = "/theme/" . $conf->theme . "/style.css.php";
    }

    // If theme support option like flip-hide left menu and we use a smartphone, we force it
}

// Init the 4 global objects
// This include will set: $conf, $db, $langs, $user objects
require_once("after.inc.php");

if (!defined('NOREQUIRETRAN')) {
    if (!GETPOST('lang')) { // If language was not forced on URL
        // If user has chosen its own language
        if (!empty($user->conf->MAIN_LANG_DEFAULT)) {
            // If different than current language
            //print ">>>".$langs->getDefaultLang()."-".$user->conf->MAIN_LANG_DEFAULT;
            if ($langs->getDefaultLang() != $user->conf->MAIN_LANG_DEFAULT) {
                $langs->setDefaultLang($user->conf->MAIN_LANG_DEFAULT);
            }
        }
    } else { // If language was forced on URL
        $langs->setDefaultLang(GETPOST('lang', 'alpha', 1));
    }
}

// Use php template engine
if ($conf->global->MAIN_USE_TEMPLATE_ENGINE && !defined('NOTEMPLATEENGINE')) {
    require_once(DOL_DOCUMENT_ROOT . '/includes/savant/Savant3.php');

    $tpl = new Savant3();
}

// Case forcing style from url
if (GETPOST('theme')) {
    $conf->theme = GETPOST('theme', 'alpha', 1);
    $conf->css = "/theme/" . $conf->theme . "/style.css.php";
}


if (!defined('NOLOGIN')) {
    // If the login is not recovered, it is identified with an account that does not exist.
    // Hacking attempt?
    if (!$user->values->name)
        accessforbidden();

    // Check if user is active
    if ($user->values->Status != "ENABLE") {
        // If not active, we refuse the user
        $langs->load("other");
        dol_syslog("Authentification ko as login is disabled");
        accessforbidden($langs->trans("ErrorLoginDisabled"));
        exit;
    }

    // Load permissions
    $user->getrights();
}

// Load main languages files
if (!defined('NOREQUIRETRAN')) {
    $langs->load("main");
    $langs->load("dict");
}

// Define some constants used for style of arrays
$bc = array(0 => 'class="impair"', 1 => 'class="pair"');
$bcdd = array(0 => 'class="impair drag drop"', 1 => 'class="pair drag drop"');
$bcnd = array(0 => 'class="impair nodrag nodrop"', 1 => 'class="pair nodrag nodrop"');

// Constants used to defined number of lines in textarea
if (empty($conf->browser->firefox)) {
    define('ROWS_1', 1);
    define('ROWS_2', 2);
    define('ROWS_3', 3);
    define('ROWS_4', 4);
    define('ROWS_5', 5);
    define('ROWS_6', 6);
    define('ROWS_7', 7);
    define('ROWS_8', 8);
    define('ROWS_9', 9);
} else {
    define('ROWS_1', 0);
    define('ROWS_2', 1);
    define('ROWS_3', 2);
    define('ROWS_4', 3);
    define('ROWS_5', 4);
    define('ROWS_6', 5);
    define('ROWS_7', 6);
    define('ROWS_8', 7);
    define('ROWS_9', 8);
}

$heightforframes = 52;

// Switch to another entity
if (!empty($conf->multicompany->enabled) && GETPOST('action') == 'switchentity') {
    if ($mc->switchEntity(GETPOST('entity')) >= 0) {
        Header("Location: " . DOL_URL_ROOT . '/');
        exit;
    }
}


// Functions

if (!function_exists("llxHeader")) {

    /**
     * 	Show HTML header HTML + BODY + Top menu + left menu + DIV
     *
     * @param 	string 	$head				Optionnal head lines
     * @param 	string 	$title				HTML title
     * @param	string	$help_url			Url links to help page
     * 		                            	Syntax is: For a wiki page: EN:EnglishPage|FR:FrenchPage|ES:SpanishPage
     *                                  	For other external page: http://server/url
     * @param	string	$target				Target to use on links
     * @param 	int    	$disablejs			More content into html header
     * @param 	int    	$disablehead		More content into html header
     * @param 	array  	$arrayofjs			Array of complementary js files
     * @param 	array  	$arrayofcss			Array of complementary css files
     * @param	string	$morequerystring	Query string to add to the link "print" to get same parameters (use only if autodetect fails)
     * @return	void
     */
    function llxHeader($head = '', $title = '', $help_url = '', $target = '', $disablejs = 0, $disablehead = 0, $arrayofjs = '', $arrayofcss = '', $morequerystring = '') {
        top_htmlhead($head, $title, $disablejs, $disablehead, $arrayofjs, $arrayofcss); // Show html headers
        top_menu($head, $title, $target, $disablejs, $disablehead, $arrayofjs, $arrayofcss, $morequerystring);
        main_area($title);
    }

}

/**
 *  Show HTTP header
 *
 *  @return	void
 */
function top_httphead() {
    global $conf;

    //header("Content-type: text/html; charset=UTF-8");
    header("Content-type: text/html; charset=" . $conf->file->character_set_client);

    // On the fly GZIP compression for all pages (if browser support it). Must set the bit 3 of constant to 1.
    if (isset($conf->global->MAIN_OPTIMIZE_SPEED) && ($conf->global->MAIN_OPTIMIZE_SPEED & 0x04)) {
        ob_start("ob_gzhandler");
    }
}

/**
 * Ouput html header of a page.
 * This code is also duplicated into security2.lib.php::dol_loginfunction
 *
 * @param 	string 	$head			Optionnal head lines
 * @param 	string 	$title			HTML title
 * @param 	int    	$disablejs		More content into html header
 * @param 	int    	$disablehead	More content into html header
 * @param 	array  	$arrayofjs		Array of complementary js files
 * @param 	array  	$arrayofcss		Array of complementary css files
 * @return	void
 */
function top_htmlhead($head, $title = '', $disablejs = 0, $disablehead = 0, $arrayofjs = '', $arrayofcss = '') {
    global $user, $conf, $langs, $db;

    top_httphead();

    if (empty($conf->css))
        $conf->css = '/theme/eldy/style.css.php'; // If not defined, eldy by default
    ?>
    <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
    <?php
    if (!empty($conf->global->MAIN_USE_CACHE_MANIFEST))
        print '<html manifest="cache.manifest">' . "\n";
    else
        print '<html>' . "\n";

    if (empty($disablehead)) {
        ?>
        <head>

            <meta charset="utf-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1.0" />
            <meta name="robots" content="noindex,nofollow" />
            <meta name="author" content="Speedealing Development Team" />
            <?php
            // Displays title
            $appli = 'Speedealing';
            if (!empty($conf->global->MAIN_APPLICATION_TITLE))
                $appli = $conf->global->MAIN_APPLICATION_TITLE;

            if ($title)
                print '<title>' . $appli . ' - ' . $title . '</title>';
            else
                print "<title>" . $appli . "</title>";
            print "\n";
            ?>
            <base href="<?php echo 'http://' . $_SERVER['HTTP_HOST'] . DOL_URL_ROOT . '/'; ?>" />
            <link rel="shortcut icon" type="image/x-icon" href="favicon.ico"/>
            <?php
            print '<!-- Includes for JQuery (Ajax library) -->' . "\n";
            //$jquerytheme = 'smoothness';
            //if (!empty($conf->global->MAIN_USE_JQUERY_THEME)) $jquerytheme = $conf->global->MAIN_USE_JQUERY_THEME;
            //else print '<link rel="stylesheet" type="text/css" href="'.DOL_URL_ROOT.'/includes/jquery/css/'.$jquerytheme.'/jquery-ui-latest.custom.css" />'."\n";    // JQuery
            //print '<link rel="stylesheet" type="text/css" href="'.DOL_URL_ROOT.'/includes/jquery/plugins/tiptip/tipTip.css" />'."\n";                           // Tooltip
            print '<link rel="stylesheet" type="text/css" href="includes/jquery/plugins/jnotify/jquery.jnotify-alt.min.css" />' . "\n";          // JNotify
            //print '<link rel="stylesheet" href="'.DOL_URL_ROOT.'/includes/jquery/plugins/lightbox/css/jquery.lightbox-0.5.css" media="screen" />'."\n";       // Lightbox
            // jQuery fileupload
            print '<link rel="stylesheet" type="text/css" href="includes/jquery/plugins/fileupload/css/jquery.fileupload-ui.css" />' . "\n";
            // jQuery datatables
            //print '<link rel="stylesheet" type="text/css" href="'.DOL_URL_ROOT.'/includes/jquery/plugins/datatables/media/css/jquery.dataTables.css" />'."\n";
            //print '<link rel="stylesheet" type="text/css" href="'.DOL_URL_ROOT.'/includes/jquery/plugins/datatables/media/css/jquery.dataTables_jui.css" />'."\n";
            print '<link rel="stylesheet" type="text/css" href="includes/jquery/plugins/datatables/extras/ColReorder/media/css/ColReorder.css" />' . "\n";
            //print '<link rel="stylesheet" type="text/css" href="'.DOL_URL_ROOT.'/includes/jquery/plugins/datatables/extras/ColVis/media/css/ColVis.css" />'."\n";
            //print '<link rel="stylesheet" type="text/css" href="'.DOL_URL_ROOT.'/includes/jquery/plugins/datatables/extras/ColVis/media/css/ColVisAlt.css" />'."\n";
            //print '<link rel="stylesheet" type="text/css" href="'.DOL_URL_ROOT.'/includes/jquery/plugins/datatables/extras/TableTools/media/css/TableTools.css" />'."\n";
            print '<link rel="stylesheet" type="text/css" href="includes/jquery/plugins/datatables/extras/AutoFill/media/css/AutoFill.css" />' . "\n";
            // jQuery multiselect
            //print '<link rel="stylesheet" type="text/css" href="'.DOL_URL_ROOT.'/includes/jquery/plugins/multiselect/css/ui.multiselect.css" />'."\n";
            print '<link rel="stylesheet" type="text/css" href="includes/jquery/plugins/wysiwyg/css/jquery.wysiwyg.css" />' . "\n";
            // jQuery taghandler
            print '<link rel="stylesheet" href="includes/jquery/plugins/tagHandler/css/jquery.taghandler.css" media="all" />' . "\n";

            print '<!-- Includes for Speedealing, modules or specific pages-->' . "\n";
            // Output style sheets (optioncss='print' or '')
            $themepath = dol_buildpath((empty($conf->global->MAIN_FORCETHEMEDIR) ? '' : $conf->global->MAIN_FORCETHEMEDIR) . $conf->css, 1);
            $themeparam = '?lang=' . $langs->defaultlang . '&amp;theme=' . $conf->theme . (GETPOST('optioncss') ? '&amp;optioncss=' . GETPOST('optioncss', 'alpha', 1) : '');
            if (!empty($_SESSION['dol_resetcache']))
                $themeparam.='&amp;dol_resetcache=' . $_SESSION['dol_resetcache'];
            //print 'themepath='.$themepath.' themeparam='.$themeparam;exit;
            print '<link rel="stylesheet" type="text/css" title="default" href="' . $themepath . $themeparam . '">' . "\n";
            // CSS forced by modules (relative url starting with /)
            if (is_array($conf->css_modules)) {
                foreach ($conf->css_modules as $key => $cssfile) {
                    // cssfile is an absolute path
                    print '<link rel="stylesheet" type="text/css" title="default" href="' . dol_buildpath($cssfile, 1);
                    // We add params only if page is not static, because some web server setup does not return content type text/css if url has parameters, so browser cache is not used.
                    if (!preg_match('/\.css$/i', $cssfile))
                        print $themeparam;
                    print '"><!-- Added by module ' . $key . '-->' . "\n";
                }
            }
            // CSS forced by page in top_htmlhead call (relative url starting with /)
            if (is_array($arrayofcss)) {
                foreach ($arrayofcss as $cssfile) {
                    print '<link rel="stylesheet" type="text/css" title="default" href="' . dol_buildpath($cssfile, 1);
                    // We add params only if page is not static, because some web server setup does not return content type text/css if url has parameters and browser cache is not used.
                    if (!preg_match('/\.css$/i', $cssfile))
                        print $themeparam;
                    print '"><!-- Added by page -->' . "\n";
                }
            }

            if (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER))
                print '<link rel="top" title="' . $langs->trans("Home") . '" href="' . (DOL_URL_ROOT ? DOL_URL_ROOT : '/') . '">' . "\n";
            if (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER))
                print '<link rel="copyright" title="GNU General Public License" href="http://www.gnu.org/copyleft/gpl.html#SEC1">' . "\n";
            if (empty($conf->global->MAIN_OPTIMIZEFORTEXTBROWSER))
                print '<link rel="author" title="Speedealing Development Team" href="http://www.speedealing.com">' . "\n";

            // Output standard javascript links
            $ext = '.js';
            if (isset($conf->global->MAIN_OPTIMIZE_SPEED) && ($conf->global->MAIN_OPTIMIZE_SPEED & 0x01)) {
                $ext = '.jgz';
            } // mini='_mini', ext='.gz'
            // JQuery. Must be before other includes
            print '<!-- Includes JS for JQuery -->' . "\n";
            print '<script type="text/javascript" src="includes/jquery/js/jquery-latest.min' . $ext . '"></script>' . "\n";
            print '<script type="text/javascript" src="includes/jquery/js/jquery-ui-latest.custom.min' . $ext . '"></script>' . "\n";
            print '<script type="text/javascript" src="includes/jquery/plugins/tablednd/jquery.tablednd_0_5' . $ext . '"></script>' . "\n";
            print '<script type="text/javascript" src="includes/jquery/plugins/tiptip/jquery.tipTip.min' . $ext . '"></script>' . "\n";
            print '<script type="text/javascript" src="includes/jquery/plugins/lightbox/js/jquery.lightbox-0.5.min' . $ext . '"></script>' . "\n";
            print '<script type="text/javascript" src="includes/jquery/plugins/globalize/lib/globalize.js"></script>' . "\n";
            print '<script type="text/javascript" src="includes/jquery/plugins/globalize/lib/cultures/globalize.cultures.js"></script>' . "\n";
            // jQuery Layout
            print '<script type="text/javascript" src="includes/jquery/plugins/layout/jquery.layout-latest' . $ext . '"></script>' . "\n";
            // jQuery jnotify
            print '<script type="text/javascript" src="includes/jquery/plugins/jnotify/jquery.jnotify.min.js"></script>' . "\n";
            print '<script type="text/javascript" src="core/js/jnotify.js"></script>' . "\n";

            if (!defined('NOLOGIN')) {
                // Flot
                print '<!--[if lte IE 8]><script language="javascript" type="text/javascript" src="includes/jquery/plugins/flot/excanvas.min.js"></script><![endif]-->' . "\n";
                print '<script type="text/javascript" src="includes/jquery/plugins/flot/jquery.flot.min.js"></script>' . "\n";
                print '<script type="text/javascript" src="includes/jquery/plugins/flot/jquery.flot.pie.min.js"></script>' . "\n";
                print '<script type="text/javascript" src="includes/jquery/plugins/flot/jquery.flot.stack.min.js"></script>' . "\n";
                // jQuery jeditable
                print '<script type="text/javascript" src="includes/jquery/plugins/jeditable/jquery.jeditable.min' . $ext . '"></script>' . "\n";
                print '<script type="text/javascript" src="includes/jquery/plugins/jeditable/jquery.jeditable.ui-datepicker.js"></script>' . "\n";
                print '<script type="text/javascript" src="includes/jquery/plugins/jeditable/jquery.jeditable.ui-autocomplete.js"></script>' . "\n";
                print '<script type="text/javascript" src="includes/jquery/plugins/jeditable/jquery.jeditable.wysiwyg.js"></script>' . "\n";
                print '<script type="text/javascript" src="includes/jquery/plugins/wysiwyg/jquery.wysiwyg.js"></script>' . "\n";
                print '<script type="text/javascript">' . "\n";
                //print 'var urlSaveInPlace = \'http://www.example.com/save.php\';'."\n";
                print 'var urlSaveInPlace = \'core/ajax/saveinplace.php\';' . "\n";
                print 'var urlLoadInPlace = \'core/ajax/loadinplace.php\';' . "\n";
                print 'var tooltipInPlace = \'' . $langs->transnoentities('ClickToEdit') . '\';' . "\n";
                print 'var placeholderInPlace = \'' . $langs->trans('ClickToEdit') . '\';' . "\n";
                print 'var cancelInPlace = \'' . $langs->trans('Cancel') . '\';' . "\n";
                print 'var submitInPlace = \'' . $langs->trans('Ok') . '\';' . "\n";
                print 'var indicatorInPlace = \'<img src="' . "theme/" . $conf->theme . "/img/working.gif" . '">\';' . "\n";
                print 'var ckeditorConfig = \'' . dol_buildpath('/theme/' . $conf->theme . '/ckeditor/config.js', 1) . '\';' . "\n";
                print '</script>' . "\n";
                print '<script type="text/javascript" src="core/js/editinplace.js"></script>' . "\n";
                print '<script type="text/javascript" src="includes/jquery/plugins/jeditable/jquery.jeditable.ckeditor.js"></script>' . "\n";
                // jQuery File Upload
                print '<script type="text/javascript" src="includes/jquery/plugins/template/tmpl.min.js"></script>' . "\n";
                //print '<script type="text/javascript" src="'.DOL_URL_ROOT.'/includes/jquery/plugins/fileupload/js/jquery.iframe-transport.js"></script>'."\n";
                print '<script type="text/javascript" src="includes/jquery/plugins/fileupload/js/jquery.fileupload.js"></script>' . "\n";
                print '<script type="text/javascript" src="includes/jquery/plugins/fileupload/js/jquery.fileupload-fp.js"></script>' . "\n";
                print '<script type="text/javascript" src="includes/jquery/plugins/fileupload/js/jquery.fileupload-ui.js"></script>' . "\n";
                print '<script type="text/javascript" src="includes/jquery/plugins/fileupload/js/jquery.fileupload-jui.js"></script>' . "\n";
                print '<script type="text/javascript" src="includes/lib/chosen/chosen.jquery.min.js"></script>' . "\n";
                print '<script type="text/javascript" src="includes/js/jquery.inputmask.js"></script>' . "\n";
                print '<script type="text/javascript" src="includes/js/jquery.inputmask.extentions.js"></script>' . "\n";
                print '<script type="text/javascript" src="includes/jquery/plugins/spinner/ui.spinner.min.js"></script>' . "\n";
                print '<script src="includes/jquery/plugins/tagHandler/js/jquery.taghandler.min.js"></script>' . "\n";
            }
            // jQuery DataTables
            print '<script type="text/javascript" src="includes/jquery/plugins/datatables/media/js/jquery.dataTables.min' . $ext . '"></script>' . "\n";
            //print '<script type="text/javascript" src="'.DOL_URL_ROOT.'/includes/jquery/plugins/datatables/js/dataTables.plugins.js"></script>';
            print '<script type="text/javascript" src="includes/jquery/plugins/datatables/extras/ColReorder/media/js/ColReorder.min' . $ext . '"></script>' . "\n";
            print '<script type="text/javascript" src="includes/jquery/plugins/datatables/extras/ColVis/media/js/ColVis.min' . $ext . '"></script>' . "\n";
            print '<script type="text/javascript" src="includes/jquery/plugins/datatables/extras/TableTools/media/js/TableTools.min' . $ext . '"></script>' . "\n";
            print '<script type="text/javascript" src="includes/jquery/plugins/datatables/extras/AutoFill/media/js/AutoFill.min' . $ext . '"></script>' . "\n";
            print '<script type="text/javascript" src="includes/jquery/plugins/datatables/extras/AjaxReload/media/js/fnReloadAjax.js"></script>' . "\n";
            //print '<script type="text/javascript" src="'.DOL_URL_ROOT.'/includes/jquery/plugins/datatables/js/initXHR'.$ext.'"></script>'."\n";
            //print '<script type="text/javascript" src="'.DOL_URL_ROOT.'/includes/jquery/plugins/datatables/js/searchColumns'.$ext.'"></script>'."\n";
            //print '<script type="text/javascript" src="'.DOL_URL_ROOT.'/includes/jquery/plugins/datatables/js/ZeroClipboard'.$ext.'"></script>'."\n";
            // jQuery Multiselect
            print '<script type="text/javascript" src="includes/jquery/plugins/multiselect/js/ui.multiselect.js"></script>' . "\n";

            // CKEditor
            print '<script type="text/javascript">var CKEDITOR_BASEPATH = \'' . DOL_URL_ROOT . '/includes/ckeditor/\';</script>' . "\n";
            print '<script type="text/javascript" src="includes/ckeditor/ckeditor_basic.js"></script>' . "\n";

            // BEGIN THEME
            //print '<script type="text/javascript" src="'.DOL_URL_ROOT.'/includes/lib/jQueryUI/jquery-ui-1.8.18.custom.min.js"></script>';
            print '<script type="text/javascript" src="includes/js/s_scripts.js"></script>';
            print '<script type="text/javascript" src="includes/js/jquery.ui.extend.js"></script>';
            print '<script type="text/javascript" src="includes/jquery/plugins/qtip2/jquery.qtip.min.js"></script>';
            //print '<script type="text/javascript" src="' . DOL_URL_ROOT . '/includes/lib/jQplot/jquery.jqplot.min.js"></script>';
            //print '<script type="text/javascript" src="' . DOL_URL_ROOT . '/includes/lib/jQplot/jqplot.plugins.js"></script>';
            print '<script type="text/javascript" src="includes/lib/fullcalendar/fullcalendar.min.js"></script>';
            print '<script type="text/javascript" src="includes/lib/stepy/js/jquery.stepy.min.js"></script>';
            print '<script type="text/javascript" src="includes/lib/validate/jquery.validate.min.js"></script>';
            print '<script type="text/javascript" src="includes/lib/validate/localization/messages_' . substr($langs->getDefaultLang(), 0, 2) . '.js"></script>'; //localization for validation plugin
            //print '<script type="text/javascript" src="'.DOL_URL_ROOT.'/includes/js/jquery.list.min.js"></script>';
            print '<script type="text/javascript" src="includes/js/pertho.js"></script>';
            print '<script type="text/javascript" src="includes/js/jquery.rwd-table.js"></script>';
            // END THEME

            if (!defined('NOLOGIN')) {
                // Global js function
                print '<!-- Includes JS of Speedealing -->' . "\n";
                print '<script type="text/javascript" src="core/js/lib_head.js"></script>' . "\n";
            }

            // Add datepicker default options
            //print '<script type="text/javascript" src="' . DOL_URL_ROOT . '/core/js/datepicker.js.php?lang=' . $langs->defaultlang . '"></script>' . "\n";

            print'<!-- Foundation framework -->';
            print '<link rel="stylesheet" href="theme/pertho_sample/foundation/stylesheets/foundation.css">';
            print '<!-- jquery UI -->';
            print '<link rel="stylesheet" href="theme/pertho_sample/lib/jQueryUI/css/Aristo/Aristo.css" media="all" />';
            print '<!-- jQplot (charts) -->';
            print '<link rel="stylesheet" href="theme/pertho_sample/lib/jQplot/jquery.jqplot.css" media="all" />';
            print '<!-- fancybox -->';
            print '<link rel="stylesheet" href="theme/pertho_sample/lib/fancybox/jquery.fancybox-1.3.4.css" media="all" />';
            print '<!-- fullcalendar -->';
            print '<link rel="stylesheet" href="theme/pertho_sample/lib/fullcalendar/fullcalendar.css" media="all" />';
            print '<!-- tooltips -->';
            print '<link rel="stylesheet" href="theme/pertho_sample/lib/qtip2/jquery.qtip.min.css" />';
            print '<!-- chosen (select element extended) -->';
            print '<link rel="stylesheet" href="theme/pertho_sample/lib/chosen/chosen.css" media="all" />';
            print '<!-- datatables -->';
            print '<link rel="stylesheet" href="theme/pertho_sample/lib/datatables/css/demo_table_jui.css" media="all" />';
            print '<!-- main styles -->';
            print '<link rel="stylesheet" href="theme/eldy/style.css" />';

            print '<!-- Google fonts -->';
            //print '<link href="https://fonts.googleapis.com/css?family=Open+Sans+Condensed:300" rel="stylesheet" />';
            print '<style type="text/css" media="screen, print">
		@font-face {
		font-family: "Open Sans Condensed";
		font-style: normal;
		font-weight: 300;
		src: url("theme/pertho_sample/lib/google/OpenSans-CondensedLight.woff") format("woff"),
			url("http://themes.googleusercontent.com/static/fonts/opensanscondensed/v6/gk5FxslNkTTHtojXrkp-xF1YPouZEKgzpqZW9wN-3Ek.woff") format("woff");
		}
		</style>';
            print '<style type="text/css" media="screen, print">
		@font-face {
		font-family: "Terminal Dosis";
		font-style: normal;
		font-weight: 400;
		src: url("theme/pertho_sample/lib/google/Dosis-Regular.woff") format("woff"),
			url("http://themes.googleusercontent.com/static/fonts/terminaldosis/v5/_IcvjW_kWHsIHjjUsLbdpFGxLvFzKVP0OsRYGzgS_zk.woff") format("woff");
		}
		</style>';

            print '<!-- Favicons and the like (avoid using transparent .png) -->';
            print '<link rel="apple-touch-icon-precomposed" href="icon.png" />';

            print '<!--[if lt IE 9]>';
            print '<link rel="stylesheet" href="foundation/stylesheets/ie.css">';
            print '<script src="https://html5shiv.googlecode.com/svn/trunk/html5.js"></script>';
            print '<script src="lib/jQplot/excanvas.min.js"></script>';
            print '<![endif]-->';

            // For new theme
            print '<script>
		$(document).ready(function() {
			prth_common.init();
			});
		</script>';

            // Output module javascript
            if (is_array($arrayofjs)) {
                print '<!-- Includes JS specific to page -->' . "\n";
                foreach ($arrayofjs as $jsfile) {
                    if (preg_match('/^http/i', $jsfile)) {
                        print '<script type="text/javascript" src="' . $jsfile . '"></script>' . "\n";
                    } else {
                        if (!preg_match('/^\//', $jsfile))
                            $jsfile = '/' . $jsfile; // For backward compatibility
                        print '<script type="text/javascript" src="' . dol_buildpath($jsfile, 1) . '"></script>' . "\n";
                    }
                }
            }

            if (!empty($head))
                print $head . "\n";
            if (!empty($conf->global->MAIN_HTML_HEADER))
                print $conf->global->MAIN_HTML_HEADER . "\n";
            ?>
        </head>
        <?php
    }

    $conf->headerdone = 1; // To tell header was output
}

/**
 *  Show an HTML header + a BODY + The top menu bar
 *
 *  @param      string	$head    			Lines in the HEAD
 *  @param      string	$title   			Title of web page
 *  @param      string	$target  			Target to use in menu links
 * 	@param		int		$disablejs			Do not output links to js (Ex: qd fonction utilisee par sous formulaire Ajax)
 * 	@param		int		$disablehead		Do not output head section
 * 	@param		array	$arrayofjs			Array of js files to add in header
 * 	@param		array	$arrayofcss			Array of css files to add in header
 *  @param		string	$morequerystring	Query string to add to the link "print" to get same parameters (use only if autodetect fails)
 *  @return		void
 */
function top_menu($head, $title = '', $target = '', $disablejs = 0, $disablehead = 0, $arrayofjs = '', $arrayofcss = '', $morequerystring = '') {
    global $user, $conf, $langs, $db;
    global $dolibarr_main_authentication;
    global $hookmanager;

    // Instantiate hooks of thirdparty module only if not already define
    if (!is_object($hookmanager)) {
        include_once(DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php');
        $hookmanager = new HookManager($db);
    }
    $hookmanager->initHooks(array('toprightmenu'));

    $toprightmenu = '';

    $conf->top_menu = 'auguria_backoffice.php';

    // For backward compatibility with old modules
    //if (empty($conf->headerdone)) top_htmlhead($head, $title, $disablejs, $disablehead, $arrayofjs, $arrayofcss);

    if (!defined('NOHEADER'))
        print '<body class="ptrn_a grdnt_b mhover_c fullW">'; // fullW full size width
    else
        print '<body style="background: white;">'; // fullW full size width

    /*
     * Top menu
     */
    $top_menu = $conf->top_menu;

    // Load the top menu manager
    // Load the top menu manager (only if not already done)
    if (!class_exists('MenuTop')) {
        $top_menu = 'auguria_backoffice.php';
        include_once(DOL_DOCUMENT_ROOT . "/core/menus/standard/" . $top_menu);
    }
    ?> <!-- Start top horizontal menu ' . $top_menu . ' -->
    <?php if (!defined('NOHEADER')) : ?>
        <header>
            <div class="container head_s_a">
                <div class="row sepH_b">
                    <div class="six columns">
                        <div class="row">
                            <div class="five phone-two columns">
                                <div id="logo"><?php
        $mysoc->logo_mini = $conf->global->MAIN_INFO_SOCIETE_LOGO_MINI;
        if (!empty($mysoc->logo_mini) && is_readable($conf->mycompany->dir_output . '/logos/thumbs/' . $mysoc->logo_mini))
            $urllogo = DOL_URL_ROOT . '/viewimage.php?cache=1&amp;modulepart=companylogo&amp;file=' . urlencode('thumbs/' . $mysoc->logo_mini);
        else
            $urllogo = 'logo.png';
        print '<a href="' . DOL_URL_ROOT . '/index.php?idmenu=menu:home"><img src="' . $urllogo . '" alt="' . $conf->global->MAIN_INFO_SOCIETE_NOM . '" title="' . $conf->global->MAIN_INFO_SOCIETE_NOM . '"/></a>';
        ?>
                                </div>
                            </div>
        <?php if (!defined('NOLOGIN')) : ?>
                                <div class = "seven phone-two columns">
                                    <form action = "search.php" id = "search_box" method = "post">
                                        <input name = "query" id = "query" type = "text" size = "40" placeholder = "Find&hellip;" autocomplete = "off" />
                                    </form>
                                </div>
                                <script>
                                    $(document).ready(function() {
                                        $('#query').sautocomplete('search/data.php', {
                                            delay		: 10,
                                            minChars	: 2,
                                            max			: 6,
                                            matchCase	: 1,
                                            width		: 212
                                        }).result(function(event, query_val) {
                                            $.fancybox({
                                                href	: 'search/search_result.php',
                                                ajax : {
                                                    type	: "POST",
                                                    data	: "search_item=" + query_val
                                                },
                                                'overlayOpacity'	: '0.2',
                                                'transitionIn'		: 'elastic',
                                                'transitionOut'		: 'fade',
                                                onComplete			: function() {
                                                    $('#query').blur();
                                                }
                                            });
                                        });
                                        $('#search_box').submit(function() {
                                            var query_val = $("#query").val();
                                            $.fancybox({
                                                href	: 'search/search_result.php',
                                                ajax : {
                                                    type	: "POST",
                                                    data	: "search_item=" + query_val
                                                },
                                                'overlayOpacity'	: '0.2',
                                                'transitionIn'		: 'elastic',
                                                'transitionOut'		: 'fade'
                                            });
                                            return false;
                                        });
                                    });
                                </script>
                            </div>
                        </div>
                        <div class = "six columns">
                            <div class = "user_box cf">
                                <div class = "user_avatar">
                                    <img src = "theme/pertho_sample/img/user_female.png" alt = "" />
                                </div>
                                <div class = "user_info user_sep">
                                    <p class = "sepH_a">
                                        <strong><?php echo $user->values->Firstname; ?> <?php echo $user->values->Lastname; ?></strong>
                                    </p>
                                    <span>
                                        <a href = "user/fiche.php?id=<?php echo $user->id; ?>" class = "sep">Settings</a>
                                        <a href = "user/logout.php">Log out</a>
                                    </span>
                                </div>
                                <div class = "ntf_bar user_sep">
                                    <a href = "#ntf_mail_panel" class = "ntf_item" style = "background-image: url(theme/pertho_sample/img/ico/icSw2/32-Mail.png)">
                                        <span class = "ntf_tip ntf_tip_red"><span>12</span></span>
                                    </a>
                                    <a href = "#ntf_tickets_panel" class = "ntf_item" style = "background-image: url(theme/pertho_sample/img/ico/icSw2/32-Day-Calendar.png)">
                                        <span class = "ntf_tip ntf_tip_red"><span>122</span></span>
                                    </a>
                                    <a href = "#ntf_comments_panel" class = "ntf_item" style = "background-image: url(theme/pertho_sample/img/ico/icSw2/32-Speech-Bubble.png)">
                                        <span class = "ntf_tip ntf_tip_blue"><span>8</span></span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>


            <?php if (!defined('NOREQUIREMENU')) : ?>
                        <div class = "row">
                            <div class = "twelve columns">

                                <?php
                                // Show menu
                                $menutop = new MenuTop($db);
                                $menutop->atarget = $target;
                                $menutop->showmenu();   // This contains a \n
                                ?>
                            </div>
                        </div>
                        <script>
                            $(document).ready(function() {
                                if(!jQuery.browser.mobile) {
                                    //* main navigatin
                                    prth_main_nav.h_nav();
                                } else {
                                    //* mobile navigatin
                                    prth_main_nav.mobile_nav();
                                }
                            });
                        </script>
                        <!--End Menu-->
            <?php endif; ?>

                </div>

                <!--notifications content-->
                <div style = "display:none">
                    <div id = "ntf_tickets_panel" style = "display:none">
                        <p class = "sticky-title">New Tickets</p>
                        <ul class = "sticky-list">
                            <li>
                                <a href = "#">Admin should not break if URL&hellip;
                                </a>
                                <p><span class = "s_color small">updated 01.04.2012</span></p>
                            </li>
                            <li>
                                <a href = "#">Displaying submenus in custom&hellip;
                                </a>
                                <p><span class = "s_color small">updated 01.04.2012</span></p>
                            </li>
                            <li>
                                <a href = "#">Featured image on post types.</a>
                                <p><span class = "s_color small">updated 24.03.2012</span></p>
                            </li>
                            <li>
                                <a href = "#">Multiple feed fixes and&hellip;
                                </a>
                                <p><span class = "s_color small">updated 22.03.2012</span></p>
                            </li>
                            <li>
                                <a href = "#">Automatic line breaks in&hellip;
                                </a>
                                <p><span class = "s_color small">updated 18.03.2012</span></p>
                            </li>
                            <li>
                                <a href = "#">Wysiwyg bug with shortcodes.</a>
                                <p><span class = "s_color small">updated 08.10.2012</span></p>
                            </li>
                        </ul>
                        <a href = "#" class = "gh_button btn-small">Show all tickets</a>
                    </div>
                    <div id = "ntf_comments_panel" style = "display:none">
                        <p class = "sticky-title">New Comments</p>
                        <ul class = "sticky-list">
                            <li>
                                <a href = "#">Lorem ipsum dolor sit amet&hellip;
                                </a>
                                <p><span class = "s_color small">John Smith on Maiden Castle, Dorset (29.10.2012)</span></p>
                            </li>
                            <li>
                                <a href = "#">Lorem ipsum dolor sit&hellip;
                                </a>
                                <p><span class = "s_color small">John Smith on Draining and development & hellip;
                                        (29.10.2012)</span></p>
                            </li>
                        </ul>
                        <a href = "#" class = "gh_button btn-small">Show all comments</a>
                    </div>
                    <div id = "ntf_mail_panel" style = "display:none">
                        <p class = "sticky-title">New Messages</p>
                        <ul class = "sticky-list">
                            <li>
                                <a href = "#">Lorem ipsum dolor sit amet&hellip;
                                </a>
                                <p><span class = "s_color small">From John Smith (29.10.2012)</span></p>
                            </li>
                            <li>
                                <a href = "#">Lorem ipsum dolor sit&hellip;
                                </a>
                                <p><span class = "s_color small">From John Smith (28.10.2012)</span></p>
                            </li>
                        </ul>
                        <a href = "#" class = "gh_button btn-small">Show all messages</a>
                    </div>
                </div>
        <?php endif; ?>
        </header>
    <?php endif; ?>
    <!-- End top horizontal menu -->
    <?php
}

/**
 *  Show left menu bar
 *
 *  @param  array	$menu_array_before 	       	Table of menu entries to show before entries of menu handler
 *  @param  string	$helppagename    	       	Name of wiki page for help ('' by default).
 * 				     		                   	Syntax is: For a wiki page: EN:EnglishPage|FR:FrenchPage|ES:SpanishPage
 * 									         		       For other external page: http://server/url
 *  @param  string	$moresearchform             Search Form Permanent Supplemental
 *  @param  array	$menu_array_after           Table of menu entries to show after entries of menu handler
 *  @param  int		$leftmenuwithoutmainarea    Must be set to 1. 0 by default for backward compatibility with old modules.
 *  @param  string	$title                      Title of web page
 *  @return	void
 */
function left_menu($menu_array_before, $helppagename = '', $moresearchform = '', $menu_array_after = '', $leftmenuwithoutmainarea = 0, $title = '') {
    global $user, $conf, $langs, $db;
    global $hookmanager;

    $searchform = '';
    $bookmarks = '';

    // Instantiate hooks of thirdparty module
    if (!is_object($hookmanager)) {
        include_once(DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php');
        $hookmanager = new HookManager($db);
    }
    $hookmanager->initHooks(array('searchform', 'leftblock'));

    print "\n";

    // Define $searchform
    if ($conf->societe->enabled && $conf->global->MAIN_SEARCHFORM_SOCIETE && $user->rights->societe->lire) {
        $langs->load("companies");
        $searchform.=printSearchForm(DOL_URL_ROOT . '/societe/societe.php', DOL_URL_ROOT . '/societe/societe.php', img_object('', 'company') . ' ' . $langs->trans("ThirdParties"), 'soc', 'socname');
    }

    if ($conf->societe->enabled && $conf->global->MAIN_SEARCHFORM_CONTACT && $user->rights->societe->lire) {
        $langs->load("companies");
        $searchform.=printSearchForm(DOL_URL_ROOT . '/contact/list.php', DOL_URL_ROOT . '/contact/list.php', img_object('', 'contact') . ' ' . $langs->trans("Contacts"), 'contact', 'contactname');
    }

    if ((($conf->product->enabled && $user->rights->produit->lire) || ($conf->service->enabled && $user->rights->service->lire))
            && $conf->global->MAIN_SEARCHFORM_PRODUITSERVICE) {
        $langs->load("products");
        $searchform.=printSearchForm(DOL_URL_ROOT . '/product/liste.php', DOL_URL_ROOT . '/product/liste.php', img_object('', 'product') . ' ' . $langs->trans("Products") . "/" . $langs->trans("Services"), 'products', 'sall');
    }

    if ($conf->adherent->enabled && $conf->global->MAIN_SEARCHFORM_ADHERENT && $user->rights->adherent->lire) {
        $langs->load("members");
        $searchform.=printSearchForm(DOL_URL_ROOT . '/adherents/liste.php', DOL_URL_ROOT . '/adherents/liste.php', img_object('', 'user') . ' ' . $langs->trans("Members"), 'member', 'sall');
    }

    // Execute hook printSearchForm
    $parameters = array();
    $searchform.=$hookmanager->executeHooks('printSearchForm', $parameters); // Note that $action and $object may have been modified by some hooks
    // Define $bookmarks
    if ($conf->bookmark->enabled && $user->rights->bookmark->lire) {
        include_once (DOL_DOCUMENT_ROOT . '/bookmarks/bookmarks.lib.php');
        $langs->load("bookmarks");

        $bookmarks = printBookmarksList($db, $langs);
    }

// Load the top menu manager (only if not already done)
    if (!class_exists('MenuLeft')) {
        $menufound = 0;
        $dirmenus = array_merge(array("/core/menus/"), $conf->menus_modules);
        foreach ($dirmenus as $dirmenu) {
            $menufound = dol_include_once($dirmenu . "standard/" . $left_menu);
            if ($menufound)
                break;
        }
        if (!$menufound) { // If failed to include, we try with standard
            $top_menu = 'eldy_backoffice.php';
            include_once(DOL_DOCUMENT_ROOT . "/core/menus/standard/" . $top_menu);
        }
    }

    /*
      // Left column
      print '<!--Begin left area - menu '.$left_menu.'-->'."\n";

      print '<div class = "row">'."\n";
      print '<div class = "three columns hide-on-phones">'."\n";

      //$menuleft=new MenuLeft($db,$menu_array_before,$menu_array_after);
      //$menuleft->showmenu(); // output menu_array and menu found in database


      // Show other forms
      if ($searchform)
      {
      print "\n";
      print "<!-- Begin SearchForm -->\n";
      print '<div id = "blockvmenusearch" class = "blockvmenusearch">'."\n";
      print $searchform;
      print '</div>'."\n";
      print "<!-- End SearchForm -->\n";
      }

      // More search form
      if ($moresearchform)
      {
      print $moresearchform;
      }

      // Bookmarks
      if ($bookmarks)
      {
      print "\n";
      print "<!-- Begin Bookmarks -->\n";
      print '<div id = "blockvmenubookmarks" class = "blockvmenubookmarks">'."\n";
      print $bookmarks;
      print '</div>'."\n";
      print "<!-- End Bookmarks -->\n";
      }

      // Link to Speedealing wiki pages
      if ($helppagename && empty($conf->global->MAIN_HELP_DISABLELINK))
      {
      $langs->load("help");

      $helpbaseurl='';
      $helppage='';
      $mode='';

      // Get helpbaseurl, helppage and mode from helppagename and langs
      $arrayres=getHelpParamFor($helppagename,$langs);
      $helpbaseurl=$arrayres['helpbaseurl'];
      $helppage=$arrayres['helppage'];
      $mode=$arrayres['mode'];

      // Link to help pages
      if ($helpbaseurl && $helppage)
      {
      print '<div id = "blockvmenuhelp" class = "blockvmenuhelp">';
      print '<a class = "help" target = "_blank" title = "'.$langs->trans($mode == 'wiki' ? 'GoToWikiHelpPage': 'GoToHelpPage');
      if ($mode == 'wiki') print ' - '.$langs->trans("PageWiki").' &quot;'.dol_escape_htmltag(strtr($helppage,'_',' ')).'&quot;';
      print '" href = "';
      print sprintf($helpbaseurl,urlencode(html_entity_decode($helppage)));
      print '">';
      print img_picto('', 'helpdoc').' ';
      print $langs->trans($mode == 'wiki' ? 'OnlineHelp': 'Help');
      //if ($mode == 'wiki') print ' ('.dol_trunc(strtr($helppage,'_',' '),8).')';
      print '</a>';
      print '</div>';
      }
      }

      // Link to bugtrack
      if (! empty($conf->global->MAIN_SHOW_BUGTRACK_LINK))
      {
      $bugbaseurl='http://savannah.nongnu.org/bugs/?';
      $bugbaseurl.='func=additem&group=dolibarr&privacy=1&';
      $bugbaseurl.="&details=";
      $bugbaseurl.=urlencode("\n\n\n\n\n-------------\n");
      $bugbaseurl.=urlencode($langs->trans("Version") . ": " . DOL_VERSION . "\n");
      $bugbaseurl.=urlencode($langs->trans("Server") . ": " . $_SERVER["SERVER_SOFTWARE"] . "\n");
      $bugbaseurl.=urlencode($langs->trans("Url") . ": " . $_SERVER["REQUEST_URI"] . "\n");
      print '<div class="help"><a class="help" target="_blank" href="' . $bugbaseurl . '">' . $langs->trans("FindBug") . '</a></div>';
      }
      print "\n";

      print "</div>\n";
      print "<!-- End left vertical menu -->\n";

      print "\n";

      // Execute hook printLeftBlock
      $parameters = array();
      $leftblock = $hookmanager->executeHooks('printLeftBlock', $parameters);	// Note that $action and $object may have been modified by some hooks
      print $leftblock;

      //print '</td>';

      print "\n";
      print '<!-- End of left area -->' . "\n";
      print "\n";
      print '<!-- Begin right area -->'."\n";
     */

    if (empty($leftmenuwithoutmainarea))
        main_area($title);
}

/**
 *  Begin main area
 *
 *  @param	string	$title		Title
 *  @return	void
 */
function main_area($title = '') {
    global $conf, $langs;

    print '<div class="container">';

    if (!empty($conf->global->MAIN_ONLY_LOGIN_ALLOWED))
        print info_admin($langs->trans("WarningYouAreInMaintenanceMode", $conf->global->MAIN_ONLY_LOGIN_ALLOWED));
}

/**
 *  Return helpbaseurl, helppage and mode
 *
 *  @param	string		$helppagename		Page name (EN:xxx,ES:eee,FR:fff...)
 *  @param  Translate	$langs				Language
 *  @return	array		Array of help urls
 */
function getHelpParamFor($helppagename, $langs) {
    if (preg_match('/^http/i', $helppagename)) {
        // If complete URL
        $helpbaseurl = '%s';
        $helppage = $helppagename;
        $mode = 'local';
    } else {
        // If WIKI URL
        if (preg_match('/^es/i', $langs->defaultlang)) {
            $helpbaseurl = 'http://wiki.dolibarr.org/index.php/%s';
            if (preg_match('/ES:([^|]+)/i', $helppagename, $reg))
                $helppage = $reg[1];
        }
        if (preg_match('/^fr/i', $langs->defaultlang)) {
            $helpbaseurl = 'http://wiki.dolibarr.org/index.php/%s';
            if (preg_match('/FR:([^|]+)/i', $helppagename, $reg))
                $helppage = $reg[1];
        }
        if (empty($helppage)) { // If help page not already found
            $helpbaseurl = 'http://wiki.dolibarr.org/index.php/%s';
            if (preg_match('/EN:([^|]+)/i', $helppagename, $reg))
                $helppage = $reg[1];
        }
        $mode = 'wiki';
    }
    return array('helpbaseurl' => $helpbaseurl, 'helppage' => $helppage, 'mode' => $mode);
}

/**
 *  Show a search area
 *
 *  @param  string	$urlaction          Url post
 *  @param  string	$urlobject          Url of the link under the search box
 *  @param  string	$title              Title search area
 *  @param  string	$htmlmodesearch     Value to set into parameter "mode_search" ('soc','contact','products','member',...)
 *  @param  string	$htmlinputname      Field Name input form
 *  @return	void
 */
function printSearchForm($urlaction, $urlobject, $title, $htmlmodesearch, $htmlinputname) {
    global $conf, $langs;

    $ret = '';
    $ret.='<div class="menu_titre">';
    $ret.='<a class="vsmenu" href="' . $urlobject . '">';
    $ret.=$title . '</a><br>';
    $ret.='</div>';
    $ret.='<form action="' . $urlaction . '" method="post">';
    $ret.='<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
    $ret.='<input type="hidden" name="mode" value="search">';
    $ret.='<input type="hidden" name="mode_search" value="' . $htmlmodesearch . '">';
    $ret.='<input type="text" class="flat" ';
    if (!empty($conf->global->MAIN_HTML5_PLACEHOLDER))
        $ret.=' placeholder="' . $langs->trans("SearchOf") . '' . strip_tags($title) . '"';
    else
        $ret.=' title="' . $langs->trans("SearchOf") . '' . strip_tags($title) . '"';
    $ret.=' name="' . $htmlinputname . '" size="10" />&nbsp;';
    $ret.='<input type="submit" class="button" value="' . $langs->trans("Go") . '">';
    $ret.="</form>\n";
    return $ret;
}

if (!function_exists("llxFooter")) {

    /**
     * Show HTML footer
     * Close div /DIV data-role=page + /DIV class=fiche + /DIV /DIV main layout + /BODY + /HTML.
     *
     * @param	string	$foot    		A text to add in HTML generated page
     * @return	void
     */
    function llxFooter($foot = '') {
        global $conf, $langs, $dolibarr_auto_user, $micro_start_time;

        // Core error message
        if (defined("MAIN_CORE_ERROR") && constant("MAIN_CORE_ERROR") == 1) {
            $title = img_warning() . ' ' . $langs->trans('CoreErrorTitle');
            print ajax_dialog($title, $langs->trans('CoreErrorMessage'));

            define("MAIN_CORE_ERROR", 0);
        }
        ?>
        </div> <!-- end div class="container" -->


        <?php if (!defined('NOHEADER')) : ?>

            <footer class="container" id="footer">
                <div class="row">
                    <div class="twelve columns">
                        Copyright &copy; 2012 speedealing.com - tzd-themes.com
                    </div>
                </div>
            </footer>
            <div class="sw_width">
                <img class="sw_full" title="switch to full width" alt="" src="theme/blank.gif" />
                <img style="display:none" class="sw_fixed" title="switch to fixed width (980px)" alt="" src="theme/blank.gif" />
            </div>
            <?php
            printCommonFooter();
            ?>
        <?php endif; ?>
        <?php
        print "</body>\n";
        print "</html>\n";
    }

}
?>
