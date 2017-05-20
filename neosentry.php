<?php
/*
 * Main core logic to handle elevated and centralized tasks
 */

//first lets set the current working directory to this scripts
chdir(dirname(__FILE__));

include_once "lib/_functions.php";
firstRunConfig(); // creates the settings files if they need to be created.

//var_dump($argv);
//echo "PHP_SAPI = ".PHP_SAPI;

$argMap = [];
//add our command line arguments
addArg("collect", true, 10, "", "Runs the collector against a device");

addArg("add", false, false, "", "Add an element to the database");
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
if (PHP_SAPI == "cli" && isset($argv)) {
    $script = array_shift($argv);
    $fullCmdArr = $argv;

    // show help if no arguments are passed
    if (sizeof($argv) <=0 ) {
        showHelp();
        exit;
    }

    $arr = processArgs($argv);
    $path = $arr["path"];       //the full command, ie "get device ... [args]"
    $action = $arr["action"];   //the first command, ex "get"
    $subject = $arr["full_cmdline"]; //the rest of the command, ie "device ..."
    $vals = $arr["args"];       //the arguments for the path

    switch ($action) {
        case "add":
            echo "$path:\n";
            print_r($vals);
            break;

        case "collect":
            $passTo = realpath(dirname(__FILE__)."/lib/runCollection.php");
            echo "running: php $passTo $subject\n";
            system("php $passTo $subject");
            //$ret = shell_exec("php $passTo $subject");
            //echo $ret;
            break;

        default:
            echo "No logic added for command '$path'\n";
            print_r($vals);
            exit;
    }

    exit;

}


function addArg($path, $required = false, $takesInput = 0, $regexConstraint = "", $description = ""){
    //ex: addArg("add device host", true, 1, "[hostname/IP] Required. Takes 1 parameter, the devices IP or hostname");
    //Allow option chaining if there's no more sub-options. ie chaining under "add device" since host
    global $argMap;
    $argMap[trim($path)] = array("required"=> $required, "input" => $takesInput, "constraint" => $regexConstraint, "description" => $description);
}


/** processes the command line arguments based on $argMap and either:
 *      1. shows the help if theres an invalid command
 *      2. returns an array with the filled arguments and command path
 *
 *  neosentry add device 10.1.1.1
 */
function processArgs(&$argv) {
    global $argMap;
    if ($argv[0] == __FILE__) array_shift($argv);
    $fullCmdArr = $argv;
    $path = "";
    $vals = [];
    $pathComplete = false;

    //account for an empty argument path
    if (sizeof($argv) <= 0) showHelp($path);

    //loop through and get input
    while (sizeof($argv) > 0) {
        $key = array_shift($argv);
        if (!$pathComplete) $path = trim($path." ".$key);

        //see if this command path takes an argument
        if (array_key_exists($path, $argMap)){
            if ($argMap[$path]["input"] > 0) {
                //this command path takes input so lets get the data
                $val = "";
                for ($c=1; $c <= $argMap[$path]["input"]; $c++) {
                    $val = trim($val . " " . array_shift($argv));
                }
                if ($val=="") showHelp($path);

                $vals[$key] = $val;

                //the path is complete at the first command that takes input
                $pathComplete = true;
            }
        } else {
            //invalid command
            showHelp($path);
        }

    }

    //if no values were populated then the command isn't valid, show the help
    if (sizeof($vals) <=0) {
        showHelp($path, "Incomplete command");
    } else {
        //loop through the values and make sure no extra arguments are passed that we don't want
        foreach ($vals as $key => $value) {
            $subCmd = trim(substr($path, strrpos(" ".$path," ")));
            if ($subCmd != $key && !array_key_exists($path . " " . $key, $argMap)) {
                showHelp($path,"Invalid parameter '$key'");
            }
        }
    }


    //we now have the values in $vals[]. lets verify required data and constraints
    foreach ($argMap as $key => $arrParams) {
        if (substr($key,0,strlen($path)) == $path) {
            $cmd = trim(substr($key, strrpos($key," ")));

            //ensure we have the required parameters
            if ($arrParams["required"] && $arrParams["input"]) {
                if (!array_key_exists($cmd,$vals)) {
                    showHelp($path,"Required parameter is not present");
                }
            }

            //check the constraints
            if (array_key_exists($cmd,$vals) && $arrParams["constraint"] != "") {
                $leftover = preg_replace($arrParams["constraint"],"",$vals[$cmd]);
                if ($leftover != "" ) { //if we have characters that don't match the regex constraint
                    showHelp($path,"Parameter constraint for '$cmd' did not pass.");
                }
            }

        }
    }

    $arr["action"] = array_shift($fullCmdArr);
    $arr["full_cmdline"] = "";
    foreach ($fullCmdArr as $cmd) {
        $escapeChar = isWindows()?"^":'';
        $cmd = addslashes($cmd); //escape characters
        $cmd = str_replace("<",$escapeChar. "<",$cmd);
        $cmd = str_replace(">",$escapeChar. ">",$cmd);
        $arr["full_cmdline"] .= (strpos($cmd," ")>0)?'"' . $cmd . '"' : $cmd;
        $arr["full_cmdline"] .= " ";

    }
    $arr["full_cmdline"] = trim($arr["full_cmdline"]);

    $arr["path"] = $path;   //the full command path before arguments
    $arr["args"] = $vals;

    return $arr;
}

function showHelp($path = "", $error = "") {
    global $argMap;

    //define some variables, get the level of help commands we want to display.
    $validPath = trim($path);
    $longestWord = 0;
    $arrHelp = [];

    //find a valid root command
    $cnt = 0;
    while ($validPath != "" || $cnt > 50) {
        if (array_key_exists($validPath,$argMap)) {
            //valid command prefix, lets break the loop
            break;

        } else {
            //back up one level
            $validPath = trim(substr($validPath, 0, strrpos(" ".$validPath," ")));
            if ($error == "" && strpos($validPath, "help") > 0) $error = "Invalid command '$path'";
        }
        $cnt++; //just in case we get stuck in a loop, this is our way out
    }

    //we have a valid path now, get the help for each sub command
    foreach ($argMap as $key => $arrParams) {
        if (substr($key,0,strlen($validPath)) == $validPath && substr_count($key," ") == substr_count(ltrim($validPath." "," ")," ")) {
            $cmd = trim(substr($key, strrpos($key," ")));
            $req = $arrParams["required"] ? "" : "Optional ";
            if (!$arrParams["input"]) $req = ""; //if it doesn't take input then we don't need to specify a requirement

            //for the help output and formatting of the output
            $arrHelp[$cmd] = "$req{$arrParams["description"]}";
            $l = strlen($cmd);
            if ( $l > $longestWord) $longestWord = $l;
        }
    }

    //format the output
    $strHelp = "";
    foreach ($arrHelp as $key => $string) {
        $strHelp .= "  " . $key . str_repeat(" ", $longestWord - strlen($key)) . "  " . $string . "\n";
    }

    //if there's no help then there's no sub-parameters available. modify the help accordingly
    if (array_key_exists($validPath,$argMap) && $strHelp == "") {
        if ($argMap[$validPath]["input"]>0) {
            $strHelp = "  This command takes 1 or more parameters. ". $argMap[$validPath]["description"] . "\n";
            //$strHelp .="  Command Help: " . $argMap[$validPath]["description"] . "\n";
        }
    }

    //and display
    if ($strHelp == "") {
        echo "\nError: Invalid Syntax, no help found for this command path. '$validPath'\n";

    } else {
        if ($error != "") echo "ERROR: $error\n";
        $s = ($validPath != "") ? " for '$validPath'" : "";
        $out = "Available options".$s.":\n" . $strHelp;
        consolePrint($out,str_repeat(" ",$longestWord)."    ");
    }

    //always exit after showing the help
    exit;
}

function consolePrint($text,$wrapPrefixString) {
    $cols = isWindows()?0:intval(exec('tput cols'));
    if (strlen($wrapPrefixString) * 3 <= $cols) {
        $arr = explode("\n",$text);
        foreach ($arr as $line) {
            //if (strlen($line) > $cols) {
                $i = 1;
                while ($cols * $i < strlen($line)) {
                    $line = substr_replace($line, "\n$wrapPrefixString", $cols * $i, 0);
                    $i++;
                }
            //}
            echo $line . "\n";

        }
    } else {
        echo $text;
    }
}


function firstRunConfig() {
    //initialize settings and unique values for this instance
    global $gFileSettings, $gFileUsers, $gFileDevices;

    if(!file_exists($gFileSettings)) {
        echo "First Run Config: Writing Default Configuration Settings\n";
        $s = '{
    "'.SETTING_CATEGORY_SESSION.'":{
        "login_max_failed": 5,
        "login_lockout_time": 300,
        "session_length_default": 3600,
        "session_length_remember": 5184000
    },
    "'.SETTING_CATEGORY_MAIL.'":{
        "host": "localhost",
        "from": "NMS-Notifications@company.com",
        "username": "authuser",
        "password": "encrypted-pass",
        "smtpauth": true,
        "security": "tls/ssl",
        "port": 587
    },
    "'.SETTING_CATEGORY_TASKS.'":{
        "Ping": { "Enabled": true, "Interval": 300, "Description": "Pings all devices that have this collector enabled. If ping fails a traceroute will be performed to see if there was a network interruption." },
        "Traceroute": { "Enabled": true, "Interval": 86400, "Description": "Performs a typical traceroute and fails over to an intelligent tcp traceroute if the standard method fails." },
        "SNMP System": { "Enabled": true, "Interval": 86400, "Description": "Collects basic SYSTEM info via SNMP." },
        "SNMP HW": { "Enabled": true, "Interval": 900, "Description": "Collects basic Hardware info via SNMP." },
        "SNMP Network": { "Enabled": true, "Interval": 300, "Description": "Collects Network interfaces and throughput stats via SNMP." },
        "SNMP Routing": { "Enabled": true, "Interval": 3600, "Description": "Collects the Routing table via SNMP." },
        "SNMP Custom": { "Enabled": true, "Interval": 3600, "Description": "Collects vendor specific SNMP information that\'s defined in the snmpmap file located in the conf directory." },
        "Configuration": { "Enabled": true, "Interval": 86400, "Description": "Runs custom scripts to collect vendor specific configuration info." }
    },
    "'.SETTING_CATEGORY_RETENTION.'": {
        "Data History": 365,
        "Change History": 365,
        "Alert History": 365
    },
    "'.SETTING_CATEGORY_PROFILES.'": {
        "Description": "List of remote Username/Password combos to get into the system. Elevated is the 2nd level password which is called in certain scripts. (ex. Cisco Enable"
    },
    "'.SETTING_CATEGORY_SNMP.'": {
        "public": { "version":"2c", "string":"public", "username":"","password":"" }
    },
    "'.SETTING_CATEGORY_CONFIGMGMT.'":{
        "Description": "Settings for connecting to devices to collect its configuration."
    },
    "'.SETTING_CATEGORY_ALERTS.'":{
        "Description": "Alert settings, only email alerts for now",
        "Email": "send-all-alerts-to-here@company.com",
        "Alert Site Contacts": "yes/no, this will email the contact configured for the site where the device is located",
        "Ping":{
            "Enabled": true,
            "Threshold": "5, trigger an alert after this many ping fails."
        }
    },
    
    "'.SETTING_CATEGORY_SITES.'":"",
    "'.SETTING_CATEGORY_REGIONS.'":"",
    "'.SETTING_CATEGORY_CONTACTS.'":""
    
}';

        file_put_contents($gFileSettings,$s);

    }

    if(!file_exists($gFileUsers)) {
        echo "First Run Config: Adding Default User\n";
        $u["admin"]["password"] = hashString("admin"); //default password
        $u["admin"]["api_key"] = strtoupper(randomToken()); //default api key, can be used as a password for automated tasks
        $u["admin"]["name"] = "Default Admin";
        $u["admin"]["auth_type"] = "app";
        $u["admin"]["role"] = "admin";
        $u["admin"]["email"] = "";
        file_put_contents($gFileUsers,json_encode($u));
    }

    if (!file_exists($gFileDevices)) {
        $s = '{
    "localhost": {
        "added": "'.date(DATE_ATOM).'",
        "group": "Networking",
        "site": "Home",
        "type": "Server",
        "name": "NeoSentry NMS",
        "vendor": "",
        "collectors": {
            "ping": "yes",
            "snmp": "yes",
            "snmp-profile":"public",
            "configuration":"yes",
            "configuration-profile": "fw",
            "services": "yes",
            "service-ports": "22,80",
            "netflow":"no",
            "netflow-port": ""
        }
    }
    
}';

    }


}