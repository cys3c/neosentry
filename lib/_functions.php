<?php //functions.php

ini_set('display_errors', 1); 
error_reporting(E_ALL);

date_default_timezone_set('America/New_York');

//$appname = 'NeoSentry NMS';
$globalErrorVar = '';


// Global variables
$gFolderData = realpath("../data");
$gFolderLogs = "$gFolderData/logs";   //stores logs for the app and system. device logs stored in db or device folder
$gFolderScanData = "$gFolderData/devices";
$gFolderBackups = "$gFolderData/backups";

$gFolderConfigs = realpath("../config");
$gFolderMibs = "$gFolderConfigs/mibs";
$gFileSNMPMap = "$gFolderConfigs/snmpmap.json";

//THESE MAY NOT BE NEEDED
$gFileDevices = "$gFolderConfigs/devices.json";
$gFileSettings = "$gFolderConfigs/settings.json";
$gFileSecurity = "$gFolderConfigs/security.json";
$gFileUsers = "$gFolderConfigs/auth.json";




// Global variables for Ping and Traceroute
$ipListFile = "$gFolderScanData/ping.iplist"; //list of ip's that are allowing Ping monitoring.
$pingOutFile = "$gFolderScanData/ping.out"; //stores the results of the ping scan in json format.
$tracerouteFilename = "traceroute.out"; //this is stored in the directory of each device. contains current/last successful and failed trace.

/*
$baseUrl = "http://localhost/qnms";
$image_thumb_width = 200;
$user = "";
$loggedin = false;
 */

// Cron job variables: [minute 0-59] [hour 0-23] [day 1-31] [month 1-12] [day-of-week 0-7 (0=sunday)]
$phpBin = trim(`which php`); //"/usr/bin/php";
$curlBin = trim(`which curl`); //"/usr/bin/curl";
$wgetBin = trim(`which wget`); //"/usr/bin/wget";
$cron_file = "$gFolderData/crontab"; //"/etc/cron.d/neosentry";
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



function sessionDestroy() {
	$_SESSION=array();
	
	if (session_id() != "" || isset($COOKIE[session_name()]))
		setcookie(session_name(), '', time()-2592000, '/');
		
	session_destroy();
}
/*
 *  starts a secure php session
 */
function sessionStart($regenerate_id = false) {
    //exit if a session exists and we aren't regenerating
    if (session_id() != "" && $regenerate_id == false) return;

    $httpsOnly = $secure = ($_SERVER['HTTPS']=='')?false:true;
    $httponly = true;   // This stops JavaScript being able to access the session id.

    // Forces sessions to only use cookies.
    if (ini_set('session.use_only_cookies', 1) === FALSE) {
        //header("Location: /error.php?err=Could not initiate a safe session (ini_set)");
        echo "Could not initiate a safe session (ini_set)";
        exit();
    }
    // Gets current cookies params.
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params($cookieParams["lifetime"], $cookieParams["path"], $cookieParams["domain"], $httpsOnly, $httponly);
    //session_name($GLOBALS['session_name']); // this is expensive and appears to be unnecessary
    session_start();            // Start the PHP session
    if ($regenerate_id==true) session_regenerate_id(true);    // regenerated the session, delete the old one.

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



// FUNCTIONS FOR READING/WRITING CONFIGS AND SETTINGS //

function getDocument($docName, $section = "") {
    //$docName = 'settings' or 'devices' or 'security' or 'auth'
    global $gFolderConfigs;
    $content = json_decode(file_get_contents($gFolderConfigs . "/$docName.json"), true);
    if ($section != "") {
        $content = array_key_exists($section,$content)?$content[$section]:"";
    }
    //return an array with the content
    return $content;
}
function writeToDocument($docName, $section, $arrValues) {
    global $gFolderConfigs;
    $content = json_decode(file_get_contents($gFolderConfigs . "/$docName.json"), true);

    //update the content
    if ($section == '') {
        $content = $arrValues;
    } else {
        $content[$section] = $arrValues;
    }

    //write and return
    return file_put_contents($gFolderConfigs . "/$docName.json", json_encode($content));
}
function deleteFromDocument($docName, $section) {
    global $gFolderConfigs;
    $content = getDocument($docName);
    unset($content[$section]);
    return file_put_contents($gFolderConfigs . "/$docName.json", json_encode($content));
}

// FUNCTIONS FOR DEVICES //

function getDevicesArray() {
    return getDocument('devices');
}
function getDeviceSettings($device){
    return getDocument('devices',$device);
}
function writeDeviceSettings($device, $arrSettings){
    return writeToDocument('devices', $device, $arrSettings);
}

// FUNCTIONS FOR SETTINGS //

function getSettingsArray($section = "") {
    return getDocument('settings', $section);
}
function getSettingsValue($section, $settingName) {
    $arrSettings = getDocument('settings', $section);
    if (!array_key_exists($settingName,$arrSettings)) return "";
    return $arrSettings[$settingName];
}
function writeSettingsValue($section, $settingName, $settingValue) {
    $arrSettings = getDocument('settings',$section);
    $arrSettings[$settingName] = $settingValue;
    return writeToDocument('settings', $section, $arrSettings);
}

// FUNCTIONS FOR USERS //

function getUser($username) {
    return getDocument('auth', $username);
}
function writeUser($username, $arrValues) {
    return writeToDocument('auth',$username, $arrValues);
}

// FUNCTIONS FOR LOGGING INFORMATION //

function writeLogFile($fileName, $line) {
    // writes log data related to the app or system
    global $gFolderLogs;
    $logFile = "$gFolderLogs/$fileName";

    // make sure we have a log file to write to
    if (!file_exists($gFolderLogs)) mkdir($gFolderLogs);
    if (!file_exists($logFile)) touch($logFile);

    //now write the output
    $output = date(DATE_ATOM).":\t$line\n";
    //echo $output;
    return file_put_contents($logFile,$output,FILE_APPEND);

}

// FUNCTIONS FOR SECURITY //

function encryptString($string) {
    // for more advanced encryption. install libsodium PECL, top rated encryption package
    // https://paragonie.com/book/pecl-libsodium/read/00-intro.md#what-is-libsodium

    //load the encryption variables
    $gSecurity = getDocument('security');
    if ($gSecurity["secret_key"] == "" || $gSecurity["secret_iv"]) {
        $gSecurity["secret_key"] = bin2hex(openssl_random_pseudo_bytes(48));
        $gSecurity["secret_iv"] = bin2hex(openssl_random_pseudo_bytes(16));
        writeToDocument('security',"",$gSecurity);
    }

    // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
    $iv = substr(hash('sha256', $gSecurity["secret_iv"]), 0, 16);

    //encrypt the string and return it
    $output = base64_encode(openssl_encrypt($string, "AES-256-CBC", hash('sha256', $gSecurity["secret_key"]), 0, $iv));
    return $output;
}
function decryptString($string){
    $gSecurity = getDocument('security');

   // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
    $iv = substr(hash('sha256', $gSecurity["secret_iv"]), 0, 16);

    // decrypt the string
    $output = openssl_decrypt(base64_decode($string), "AES-256-CBC", hash('sha256', $gSecurity["secret_key"]), 0, $iv);
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




