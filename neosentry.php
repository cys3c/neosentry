<?php
/*
 * Main core logic to handle elevated and centralized tasks
 */

include "lib/_functions.php";
firstRunConfig(); // creates the settings files if they need to be created.

//var_dump($argv);
//echo "PHP_SAPI = ".PHP_SAPI;

$argMap = [];
//add our command line arguments
addArg("add", true, 1, "", "Add an element to the database");
addArg("add account-profile", true, 1, "", "Add an account profile used to remotely log into a device to gather configs");
addArg("add device", true, 1, "", "[hostname/IP] - The devices IP or hostname");
addArg("add snmp-profile", true, 1, "", "Add SNMP connection info");
addArg("add app-user", true, 1, "", "Add a user that can log into the Web UI");
addArg("add alert", true, 1, "", "Add an alert rule");


addArg("delete", true, 0, "", "Deletes an element from the database");
addArg("show", true, 0, "", "Show various configuration options and stored data");
addArg("set", true, 0, "", "Set/Update a configuration setting");
addArg("scan", true, 0, "", "Scans an IP or range of IPs for active devices and outputs the commands to add the device");
addArg("search", true, 0, "", "Searches the database for your inputted criteria");




// If we're running this from command line then...
if (PHP_SAPI == "cli") {
    $script = array_shift($argv);

    // show help if no arguments are passed
    if (sizeof($argv) <=0 ) {
        showHelp();
        exit;
    }

    //process the arguments
    $cmd = strtolower(array_shift($argv));
    switch ($cmd) {
        case "add":
            processAdd($argv);
            break;

        case "delete":
            processDelete($argv);
            break;

        case "show":
            processShow($argv);
            break;

        case "set":
            processSet($argv);
            break;

        default:
            showHelp();
            exit;
    }

}

function processAdd(&$argv) {
    $help = '
    account-profile  Add an account profile thats used for certain connection operations, like gathering device configs
    alert            Add an alert rule
    app-user         Add a user that can log into the Web UI
    device           Add a device
    snmp-community   Add SNMP connection info
    ';
    // show help if no arguments are passed
    if (sizeof($argv) <=0 ) {
        echo $help;
        exit;
    }

    $descriptions["add"] = "Add something";
    $command["add"]["device"] = "Add something";




    //process the arguments
    $cmd = strtolower(array_shift($argv));
    switch ($cmd) {
        case "device":
            $device = array_shift($argv);
            $map = argsToMap($argv, ["name", "type", "site", "group", "vendor",]);
            processAdd($argv);
            break;

        case "app-user":
            processDelete($argv);
            break;


        default:
            echo $help;
            exit;
    }
}
function processDelete(&$argv) {

}
function processShow(&$argv) {

}
function processSet(&$argv) {

}


/**
 * @param $argv = the argument array
 * @param $map = an array of values to collect
 */
function argsToMap(&$argv, $map) {
    //for ()

    return $arr;
}

function addArg($path, $required = false, $numValues = 0, $regexConstraint = "", $description = ""){
    //ex: addArg("add device host", true, 1, "[hostname/IP] Required. Takes 1 parameter, the devices IP or hostname");
    //Allow option chaining if there's no more sub-options. ie chaining under "add device" since host
    global $argMap;
    $argMap[trim($path)] = array("required"=> $required, "values" => $numValues, "constraint" => $regexConstraint, "description" => $description);
}
function showHelp($path = "") {
    global $argMap;

    //define some variables, get the level of help commands we want to display.
    $level = substr_count(ltrim($path." ")," "); //accommodates root and sub levels
    $longestWord = 0;
    $arrHelp = [];

    //get an array of the help lines
    foreach ($argMap as $key => $arrParams) {
        if (substr($key,0,strlen($path)) == $path && substr_count($key," ") == $level) {
            $cmd = trim(substr($key, strrpos($key," ")));
            $req = $arrParams["required"] ? "Required" : "Optional";
            $arrHelp[$cmd] = $cmd . "\t" . "$req. Takes {$arrParams["values"]} parameters: {$arrParams["description"]}";
        }
    }

    //format the output
    $strHelp = "";
    foreach ($arrHelp as $key => $string) {
        $strHelp .= $key . str_repeat(" ", $longestWord - strlen($key)) . "  " . $string . "\n";
    }

    //and display
    if ($strHelp == "") {
        echo "\nError: Invalid Syntax, no help found for this command path.\n";
        return false;
    }
    echo "\nAvailable Commands:\n\n" . $strHelp;
}




function firstRunConfig() {
    //initialize settings and unique values for this instance
    global $gFileSettings, $gFileUsers;

    if(!file_exists($gFileSettings)) {
        $s = '{
    "Mail Settings":{
        "Host": "localhost",
        "From": "NMS-Notifications@company.com",
        "Username": "authuser",
        "Password": "encrypted-pass",
        "SMTPAuth": true,
        "Security": "tls/ssl",
        "Port": 587
    },
    "Task Scheduler":{
        "Ping": { "Enabled": true, "Interval": 300, "Description": "Pings all devices that have this collector enabled. If ping fails a traceroute will be performed to see if there was a network interruption." },
        "Traceroute": { "Enabled": true, "Interval": 86400, "Description": "Performs a typical traceroute and fails over to an intelligent tcp traceroute if the standard method fails." },
        "SNMP System": { "Enabled": true, "Interval": 86400, "Description": "Collects basic SYSTEM info via SNMP." },
        "SNMP HW": { "Enabled": true, "Interval": 900, "Description": "Collects basic Hardware info via SNMP." },
        "SNMP Network": { "Enabled": true, "Interval": 300, "Description": "Collects Network interfaces and throughput stats via SNMP." },
        "SNMP Routing": { "Enabled": true, "Interval": 3600, "Description": "Collects the Routing table via SNMP." },
        "SNMP Custom": { "Enabled": true, "Interval": 3600, "Description": "Collects vendor specific SNMP information that\'s defined in the snmpmap file located in the conf directory." },
        "Configuration": { "Enabled": true, "Interval": 86400, "Description": "Runs custom scripts to collect vendor specific configuration info." }
    },
    "Data Retention": {
        "Data History": 365,
        "Change History": 365,
        "Alert History": 365
    },
    "Account Profiles": {
        "Description": "List of remote Username/Password combos to get into the system. Elevated is the 2nd level password which is called in certain scripts. (ex. Cisco Enable",
    },
    "SNMP Communities": {
        "public": { "version":"2c", "string":"public", "username":"","password":"" },
    },
    "Configuration Management":{
        "Description": "Settings for connecting to devices to collect its configuration."
    },
    "Alerts":{
        "Description": "Alert settings, only email alerts for now",
        "Email": "send-all-alerts-to-here@company.com",
        "Alert Site Contacts": "yes/no, this will email the contact configured for the site where the device is located",
        "Ping":{
            "Enabled": true,
            "Threshold": "5, trigger an alert after this many ping fails."
        },
    },
    
    "Site Information":"",
    "Region Information":"",
    "Contacts":""
    
}';

        file_put_contents($gFileSettings,$s);

    }

    if(!file_exists($gFileUsers)) {
        $u["admin"]["password"] = hashString("admin"); //default password
        $u["admin"]["name"] = "Default Admin";
        $u["admin"]["auth_type"] = "app";
        $u["admin"]["role"] = "admin";
        $u["admin"]["email"] = "";
        file_put_contents($gFileUsers,json_encode($u));
    }


}