<?php //functions.php

ini_set('display_errors', 1); 
error_reporting(E_ALL);

//date_default_timezone_set('America/New_York');

//$appname = 'NeoSentry NMS';
//$globalErrorVar = '';

//Global Constants
const SETTING_CATEGORY_SESSION = "Session Settings";
const SETTING_CATEGORY_MAIL = "Mail Settings";
const SETTING_CATEGORY_TASKS = "Task Scheduler";
const SETTING_CATEGORY_RETENTION = "Data Retention";

const SETTING_CATEGORY_PROFILES = "Account Profiles";
const SETTING_CATEGORY_SNMP = "SNMP Profiles";
const SETTING_CATEGORY_CONFIGMGMT = "Configuration Management";
const SETTING_CATEGORY_ALERTS = "Alerts";
const SETTING_CATEGORY_SITES = "Sites";
const SETTING_CATEGORY_REGIONS = "Regions";
const SETTING_CATEGORY_CONTACTS = "Contacts";

const ROLE_ADMIN = "admin"; //can write to the database and view settings
const ROLE_READONLY = "readonly"; //can only read

const ACTION_PING = 'ping';
const ACTION_TRACEROUTE = 'traceroute';
const ACTION_SNMP = 'snmp';
const ACTION_CONFIGURATION = 'configuration';
const ACTION_CONFIGMGMT = ACTION_CONFIGURATION;

const TEMPLATE_DEVICE = array("added"=>"","site"=>"","region"=>"","ip"=>"","name"=>"","type"=>"","vendor"=>"","model"=>"",
    "collectors"=>array("ping"=>[true,""],"snmp"=>[false,""],"configuration"=>[false,""]));
    //add services:[], netflow:[]





// Global File and Folder variables
$gFolderData = realpath(dirname(__FILE__)."/../") . DIRECTORY_SEPARATOR . "data";
$gFolderLogs = "$gFolderData/logs";   //stores logs for the app and system. device logs stored in db or device folder
$gFolderScanData = "$gFolderData/devices";
$gFolderBackups = "$gFolderData/backups";

$gFolderConfigs = realpath(dirname(__FILE__)."/../") . DIRECTORY_SEPARATOR . "config";
$gFolderMibs = "$gFolderConfigs/mibs";
$gFileSNMPMap = "$gFolderConfigs/snmpmap.json";


//THESE MAY NOT BE NEEDED
//$gFileDevices = "$gFolderConfigs/devices.json";
//$gFileSettings = "$gFolderConfigs/settings.json";
//$gFileSecurity = "$gFolderConfigs/security.json";
//$gFileUsers = "$gFolderConfigs/auth.json";



// Global variables for Ping and Traceroute
//$ipListFile = "$gFolderScanData/ping.iplist"; //list of ip's that are allowing Ping monitoring.
$pingOutFile = "$gFolderScanData/pingResults.json"; //stores the results of the ping scan in json format.
$tracerouteFilename = "traceroute.out"; //this is stored in the directory of each device. contains current/last successful and failed trace.

/*
$baseUrl = "http://localhost/qnms";
$image_thumb_width = 200;
$user = "";
$loggedin = false;
 */

// Cron job variables: [minute 0-59] [hour 0-23] [day 1-31] [month 1-12] [day-of-week 0-7 (0=sunday)]
/*
$phpBin = trim(`which php`); //"/usr/bin/php";
$curlBin = trim(`which curl`); //"/usr/bin/curl";
$wgetBin = trim(`which wget`); //"/usr/bin/wget";
$cron_file = "$gFolderData/crontab"; //"/etc/cron.d/neosentry";
*/
$mibLocation = $gFolderMibs; //default is /usr/share/snmp/mibs but its write protected
//$snmpCommandsFile = "$gFolderData/snmp_commands";
$pingInterval = "*/5 * * * *"; //ping every minute
$trInterval = "0 0 */1 * *"; //traceroute every day at midnight
$snmpNetInterval = "0 */10 * * *"; //get snmp network information every hour
$snmpSysInterval = "0 */10 * * *"; //get snmp hardware information every hour
$snmpAllInterval = "0 1 */1 * *"; //get all snmp information every day at 1am
$serviceInterval = "0 */1 * * *"; //get service information every hour


// Create some necessary stuff to prevent errors
if (!is_dir($gFolderScanData)) mkdir($gFolderScanData, 0777, true);


//function to get an array value without an error if it doesn't exist
function getArrVal(&$array, &$keyVal, $defaultRet = "") {
    return array_key_exists($keyVal,$array)?$array[$keyVal]:$defaultRet;
}

function sanitizeString($var) {
    $var = strip_tags(trim($var));
    $var = htmlentities($var); //prevents XSS when displaying text in HTML
    $var = stripslashes($var); //removes \ from the text, \\ becomes \
    return $var; //turns returns into \r\n, and a few other character replacements
}

function get_string_between($string, $start, $end){
    $ini = strpos($string, $start);
    if ($ini == 0) return '';
    $ini += strlen($start);
    $len = strpos($string, $end, $ini) - $ini;
    if ($len <= 0) return substr($string, $ini);
    return substr($string, $ini, $len);
}

function isWindows(){
    return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
}
function command_exist($cmd) {
    $return = shell_exec(sprintf("which %s", escapeshellarg($cmd)));
    return !empty($return);
}





// FUNCTIONS FOR SESSION AND USER LOGIN //

function isLoggedIn($requiredRole='') {
    if (array_key_exists('id',$_SESSION) && array_key_exists('role',$_SESSION)) {
        if ($requiredRole != '') return $_SESSION['role']==$requiredRole;
        else return true;
    }
    else return false;
}
function sessionProtect($redirectToLogin = true, $requiredRole='', $errorMsg = 'Unauthorized'){
    if (!isLoggedIn($requiredRole)) {
        header('HTTP/1.1 401 Unauthorized');
        if ($redirectToLogin) {
            $_SESSION['previous_location'] = $_SERVER['REQUEST_URI'];
            header("Location: /login.php");
        }
        echo '{"error":"'.$errorMsg.'"}';
        exit;
    }
}
function sessionDestroy() {
  echo "destroying session";
	/* Alternative method to check the session before resetting is time
    $_SESSION=array();
	if (session_id() != "" || isset($COOKIE[session_name()]))
		setcookie(session_name(), '', time()-2592000, '/');
    //*/

    $_SESSION=array();
    session_unset();    //remove session variables
    setcookie(session_name(),'',time()-2592000,'/');  //trigger the deletion of the cookie by expiring it
    session_destroy();  //and finally destroy the session
}
/*
 *  starts a secure php session
 */
function sessionStart($regenerate_id = false) {
    /* Forces sessions to only use cookies.
    if (ini_set('session.use_only_cookies', 1) === FALSE) {
        //header("Location: /error.php?err=Could not initiate a safe session (ini_set)");
        echo "Could not initiate a safe session (ini_set)";
        exit();
    }//*/
    /*
    $httpsOnly = $secure = (array_key_exists('HTTPS',$_SERVER))?false:true;
    $httpOnly = true;   // This stops JavaScript being able to access the session id.

    // Gets current cookies params.
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params($cookieParams["lifetime"], $cookieParams["path"], $cookieParams["domain"], $httpsOnly, $httpOnly);
    //session_name($GLOBALS['session_name']); // this is expensive and appears to be unnecessary
    //*/
    session_start();    // Start the PHP session
    if ($regenerate_id==true) session_regenerate_id(true);    // regenerated the session, delete the old one.

    //update the users last active time
    if (array_key_exists('id',$_SESSION) && $_SESSION["id"] != "") {
        $u = getUser($_SESSION["id"]);
        $u['last_active'] = time();
        writeUser($_SESSION["id"],$u);
    }

}
function sessionLogin($id, $pass, $remember_session = false) {
    /*  read from the user db
     *  email, token (aka salted/hashed/pw), last_login, last_active, last_failed, failed_count, recovery_token, recovery_time
     *  OTHER VARIABLES:    display_name, display_image, website, [all form variables], membership_start, membership_end
     */

    //exit if the id or pass is empty
    if (trim($id)=='') { return "Username cannot be blank."; }
    if (trim($pass)=='') { return "Password cannot be blank."; }

    //set the variables
    $userData = getUser($id);
    $ret = "Invalid Username or Password";   //default return value
    $maxFailed = getSettingsValue(SETTING_CATEGORY_SESSION,"login_max_failed",5); //failed attempts before lockout
    $lockoutTime = getSettingsValue(SETTING_CATEGORY_SESSION,"login_lockout_time",300); //time in seconds


    if ($userData != '') {
        //the user exists, lets check for possible brute force
        if (array_key_exists("failed_count", $userData) && $userData["failed_count"] >= $maxFailed) {
            //too many failed attempts, lets make sure 5 minutes (5*60) has passed
            if ($userData["last_failed"] > (time()-$lockoutTime)) {
                //5 minutes has NOT passed
                $ret = "Too many failed attempts, you must wait 5 minutes to try again or you can reset your password with the form below.";
                return $ret;
            }
        }

        //brute force check passed, continue with Auth
        //see if password hashes match, or password matches the api_key
        $api = array_key_exists("api_key",$userData)?$userData["api_key"]:"";
        if (password_verify($pass,$userData["password"]) || (strlen($api) > 16 && $api == $pass) ) {
            //the passwords match. set session username and create a session key/token
            //session_regenerate_id(true);
            $_SESSION["role"] = $userData["role"];
            //if ($api==$pass) $_SESSION["role"] = ROLE_READONLY; //to protect the database
            $_SESSION["id"] = $id;
            $_SESSION["name"] = $userData["name"];
            $_SESSION["email"] = $userData["email"];
            $_SESSION["created"] = $userData["created"];
            if (is_null($_SESSION['created'])) $_SESSION['created'] = 0;
            $_SESSION["last_login"] = time();
            //$_SESSION["token"] = randomToken(); //different from the token in the db

            //reset the failed count and set the login time
            $userData["failed_count"] = 0;
            $userData["last_login"] = time();

            //set session length
            $sLen = $remember_session?getSettingsValue(SETTING_CATEGORY_SESSION,"session_length_remember",604800):getSettingsValue(SETTING_CATEGORY_SESSION,"session_length_default",3600);
            ini_set('session.gc_maxlifetime', $sLen);
            session_set_cookie_params($sLen);

            $ret = true;

        } else {
            //failed login attempt, update some stats
            $userData["last_failed"] = time();
            if (!array_key_exists("failed_count", $userData)) $userData["failed_count"] = 0;
            $userData["failed_count"] = intval($userData["failed_count"])+1;
            $subMessage = (intval($userData["failed_count"])==$maxFailed)?
                "Your account is locked for ".($lockoutTime/60)." minutes.":
                "You have ".($maxFailed-intval($userData["failed_count"]))." more attempts before your account is locked.";

            $ret = "Incorrect Password. $subMessage";
        }

        //now write the changed data to the user table
        writeUser($id,$userData);

    } else {
        $ret = "Account does not exist.";
    }

    return $ret;
}



// FUNCTIONS FOR READING/WRITING DOCUMENTS/TABLES, CONFIGS AND SETTINGS //

function getDocument($docName, $section = "") {
    //$docName = 'settings' or 'devices' or 'security' or 'auth'
    global $gFolderConfigs;

    //if the document doesn't exist then lets include the main php which does a firstRun and creates the initial docs
    if (!file_exists($gFolderConfigs . "/$docName.json")) {
        writeLogFile("application.log","WARNING: $gFolderConfigs/$docName.json does not exist. Cannot retrieve document.");
        return "";
    }

    $content = json_decode(file_get_contents($gFolderConfigs . "/$docName.json"), true);
    if ($section != "") {
        $content = isset($content[$section])?$content[$section]:"";
    }
    //return an array with the content
    return $content;
}
function writeToDocument($docName, $section, $arrValues) {
    global $gFolderConfigs;

    //get the content
    $content = file_exists($gFolderConfigs . "/$docName.json")?json_decode(file_get_contents($gFolderConfigs . "/$docName.json"), true):[];

    //update the content
    if ($section == '') {
        $content = $arrValues;
    } else {
        $content[$section] = $arrValues;
    }

    //write and return
    return file_put_contents($gFolderConfigs . "/$docName.json", json_encode($content));
}
function deleteFromDocument($docName, $section, $subName = '') {
    global $gFolderConfigs;
    $content = getDocument($docName);
    if ($subName = '') {
        unset($content[$section]);
    } else {
        unset($content[$section][$subName]);
    }

    return file_put_contents($gFolderConfigs . "/$docName.json", json_encode($content));
}

// FUNCTIONS FOR DEVICES //

function getDevicesArray() {
    return getDocument('devices');
}
function getDeviceSettings($device){
    $d = getDocument('devices',$device);
    return is_array($d) ? $d : [];
}
function writeDeviceSettings($device, $arrSettings){
    //set up the template
    $tmpl = TEMPLATE_DEVICE;
    $tmpl["added"] = date(DATE_ATOM);
    $tmpl["ip"] = $device;
    $dev = array_merge($arrSettings, $tmpl);

    return writeToDocument('devices', $device, $dev);
}
function deleteDevice($device){
    return deleteFromDocument('devices', $device);
}

// FUNCTIONS FOR SETTINGS //

function getSettingsArray($section = "") {
    $s = getDocument('settings', $section);
    return (is_array($s) ? $s : []);
}
function getSettingsValue($section, $settingName, $defaultReturnValue = "") {
    $arrSettings = getDocument('settings', $section);
    if (!array_key_exists($settingName,$arrSettings)) return $defaultReturnValue;
    return $arrSettings[$settingName];
}
function writeSettingsValue($section, $settingName, $settingValue) {
    $arrSettings = getDocument('settings',$section);
    $arrSettings[$settingName] = $settingValue;
    return writeToDocument('settings', $section, $arrSettings);
}
function deleteSettingsValue($section, $settingName) {
    return deleteFromDocument('settings', $section, $settingName);
}

// FUNCTIONS FOR USERS //

function getUser($username) {
    return getDocument('auth', $username);
}
function getUsers() {
    return getDocument('auth');
}
function writeUser($username, $arrValues) {
    //exit if the user exists
    if (!empty(getUser($username))) return false;

    //add the api key and save
    $arrValues['created'] = date(DATE_ATOM);
    $arrValues["api_key"] = strtoupper(randomToken());
    return writeToDocument('auth',$username, $arrValues);
}
function updateUser($username, $arrValues) {
    //exit if the user doesn't exists
    if (empty(getUser($username))) return false;
    return writeToDocument('auth',$username, $arrValues);
}
function deleteUser($username) {
    return deleteFromDocument('auth',$username);;
}

// FUNCTIONS FOR LOGGING INFORMATION //
/**
 * @param $fileName : The logfile type (system.log, application.log, etc)
 * @param $line : The string of text to write
 * @return bool|int
 */
function writeLogFile($fileName, $line) {
    // writes log data related to the app or system
    global $gFolderLogs;
    $logFile = "$gFolderLogs/$fileName";

    // make sure we have a log file to write to
    if (!file_exists($gFolderLogs)) mkdir($gFolderLogs,0777,true);
    if (!file_exists($logFile)) touch($logFile);

    //now write the output
    $output = date(DATE_ATOM).":\t$line\n";
    echo $fileName . ": " . $output; //display to the console as well as write to the file
    return file_put_contents($logFile,$output,FILE_APPEND);

}
function getLogFileLines($fileName, $numberOfLines) {
    global $gFolderLogs;
    $logFile = "$gFolderLogs/$fileName";
    if (!file_exists($logFile)) return "";

    if($numberOfLines > 0) {
        $data = shell_exec('tail -n '.$numberOfLines.' '.$logFile);
    } else {
        $data = file_get_contents($logFile);
    }

    return $data;
}
function getLogFileSearch($fileName, $searchString) {
    global $gFolderLogs;
    $logFile = "$gFolderLogs/$fileName";
    if (!file_exists($logFile)) return "";

    //search
    $data = shell_exec('grep "'.$searchString.'" '.$logFile);

    return $data;
}


// FUNCTIONS FOR SECURITY //

function encryptString($string) {
    // for more advanced encryption. install libsodium PECL, top rated encryption package
    // https://paragonie.com/book/pecl-libsodium/read/00-intro.md#what-is-libsodium

    //load the encryption variables
    $gSecurity = securityGetKeyDoc();

    // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
    $iv = substr(hash('sha256', $gSecurity["secret_iv"]), 0, 16);

    //encrypt the string and return it
    $output = base64_encode(openssl_encrypt($string, "AES-256-CBC", hash('sha256', $gSecurity["secret_key"]), 0, $iv));
    return $output;
}
function decryptString($string){
    $gSecurity = securityGetKeyDoc();

   // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
    $iv = substr(hash('sha256', isset($gSecurity["secret_iv"])?$gSecurity["secret_iv"]:""), 0, 16);

    // decrypt the string
    if (function_exists('openssl_decrypt')) {
        $output = openssl_decrypt(base64_decode($string), "AES-256-CBC", hash('sha256', isset($gSecurity["secret_key"]) ? $gSecurity["secret_key"] : ""), 0, $iv);
    } else {
        writeLogFile('application.log',"Fatal Error: openssl_decrypt is not installed.");
        $output = $string;
    }
        return $output;
}
function hashString($string){
    /* SHA512 = crypt('rasmuslerdorf', '$6$rounds=5000$usesome16CHARsalt$') //up to 999,999,999 rounds
     * SHA256 = crypt('rasmuslerdorf', '$5$rounds=5000$usesome16CHARsalt$')
     * BlowFish = crypt('rasmuslerdorf', '$2a$07$usesomesillystringforsalt$')
     * MD5 = crypt('rasmuslerdorf', '$1$rasmusle$')
     * 
     * Hash a password with old hash to compare passwords.
     *  hash_equals($hashed_password, crypt($user_input, $hashed_password))
     * 
     * USE THESE BUILT-IN FUNCTIONS INSTEAD OF hashString(). THIS SERVES ONLY AS A PLACEHOLDER
     *  password_hash('mypass'); will hash with the best current standard
     *  password_verify('mypass', $hash); will verify a password hashes match, using the same algorithm
     * 
     */
    return password_hash($string,PASSWORD_DEFAULT);
}

function securityGetKeyDoc() {
    $gSecurity = getDocument('security');
    if (!is_array($gSecurity)) $gSecurity = array();
    if (!isset($gSecurity["secret_key"]) || !isset($gSecurity["secret_iv"])) {
        $gSecurity["secret_key"] = randomToken(48); //bin2hex(openssl_random_pseudo_bytes(48));
        $gSecurity["secret_iv"] = randomToken(16); //bin2hex(openssl_random_pseudo_bytes(16));
        writeToDocument('security',"",$gSecurity);
    }
    return $gSecurity;
}
function randomToken($length = 32){
    if(!isset($length) || intval($length) <= 8 ){ $length = 32; }
    if (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($length));
    }
    if (function_exists('random_bytes')) { //PHP 7
        return bin2hex(random_bytes($length));
    }
    throw new Exception("Could not Generate Security Token. Requires OpenSSL or PHP7");

}


