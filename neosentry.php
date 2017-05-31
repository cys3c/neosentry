<?php
/*
 * Main core logic to handle elevated and centralized tasks
 */

//first lets set the current working directory to this scripts
chdir(dirname(__FILE__));

include_once "lib/_functions.php";
include_once "lib/_db_flatfiles.php";

//var_dump($argv);
//echo "PHP_SAPI = ".PHP_SAPI;

$argMap = [];
//add our command line arguments
addArg("collect", true, 10, "", "Runs the collector against a device");

//add account-profile <profile_name> username <username> password <password> password2 <2nd_password>
addArg("add", false, false, "", "Add an element to the database");
addArg("add account-profile", true, 1, "", "<profile_name> Add an account profile used to remotely log into a device to gather configs");
addArg("add account-profile username", true, 1, "", "<account_username>");
addArg("add account-profile password", true, 1, "", "<account_password>");
addArg("add account-profile password2", false, 1, "", "[2nd_account_pw] Secondary account password, used for second level auth. For example Cisco's enable password");

addArg("add app-user", true, 1, "", "<login_id> Add a user that can log into the Web UI");
addArg("add app-user name", true, 1, "", "<full_name> Full name of the user");
addArg("add app-user role", true, 1, "", "<role> Role to assign [admin, readonly, ...]");
addArg("add app-user password", true, 1, "", "<password> This will be hashed");
addArg("add app-user email", false, 1, "", "<email> Email of the user");

//addArg("add alert", true, 1, "", "Add an alert rule");

addArg("add config-rule", true, 1, "", "<rule> Add a configuration management rule. Encase rule in quotes.");

//add device <ip_or_hostname> [additional_options]
addArg("add device", true, 1, "", "<ip_or_hostname> Add a device to the database");
addArg("add device name", false, 1, "", "[friendly_name] - The devices hostname or a friendly name");
addArg("add device region", false, 1, "", "[region_name] - ex: Americas, EMEA, APAC...");
addArg("add device site", false, 1, "", "[site_name] - Site identifier, for example the address");
addArg("add device type", false, 1, "", "[device_type] - Server, Router, Firewall...");
addArg("add device vendor", false, 1, "", "[device_vendor] - Cisco, Check Point, Palo Alto...");
addArg("add device model", false, 1, "", "[device_model] - 3690, PA-5060, etc...");
addArg("add device collect-ping", false, 1, "", "[true/false] Collect ping data");
addArg("add device collect-snmp", false, 1, "", "[snmp_profile] - collect snmp data, include snmp profile name");
addArg("add device collect-config", false, 1, "", "[account_profile] - collect config data, use account profile for username/pass");

addArg("add snmp-profile", true, 1, "", "<profile_name> Add SNMP connection info");
addArg("add snmp-profile version", true, 1, "", "<snmp_version> 1, 2c, 3...");
addArg("add snmp-profile string", true, 1, "", "<snmp_community_string> used to connect");
addArg("add snmp-profile username", false, 1, "", "[username] used in version 3");
addArg("add snmp-profile password", false, 1, "", "[password] used in version 3");

addArg("delete", true, 0, "", "Deletes an element from the database");
addArg("delete account-profile", true, 1, "", "<profile_name> Deletes an Account Profile from the database");
addArg("delete app-user", true, 1, "", "<user_id> Deletes a user so they can't log in");
addArg("delete config-rule", true, 1, "", "<rule_number> Deletes configuration management rule");
addArg("delete device", true, 1, "", "<ip_or_hostname> Deletes a Device from the database");
addArg("delete snmp-profile", true, 1, "", "<profile_name> Deletes an SNMP Profile from the database");

addArg("show", true, 0, "", "Show various configuration options and stored data");
addArg("show account-profiles", true, 0, "", "Show all account profiles");
addArg("show device", true, 1, "", "<ip_or_hostname> Show details of a Device");
addArg("show devices", true, 0, "", "Show all devices");
addArg("show config-rules", true, 0, "", "Show all configuration management rules");
addArg("show snmp-profiles", true, 0, "", "Show all SNMP Profiles");
addArg("show settings", true, 0, "", "Show all Settings");
addArg("show app-users", true, 0, "", "Show all App Users");

addArg("set", true, 0, "", "Set/Update a configuration setting");
addArg("set device", true, 0, "", "<device_name> Set/Update a device configuration setting");

addArg("scan", true, 1, "", "<ip_list> Scans an IP or range of IPs for active devices and outputs the commands to add the device. Put the list of IPs in quotes separated by a space, tab, comma, or semi-colon");

addArg("search", true, 1, "", "<search_string> Searches the database for your inputted criteria. Put search string in quotes.");




// If we're running this from command line then...
if (PHP_SAPI == "cli" && isset($argv)) {
    $script = array_shift($argv);
    $fullCmdArr = $argv;

    // show help if no arguments are passed
    if (sizeof($argv) <=0 ) {
        showHelp();
        exit;
    } elseif ($argv[0] == "firstrun") {
        firstRunConfig(); // creates the settings files if they need to be created.
        exit;
    }

    $arr = processArgs($argv);
    $path = $arr["path"];       //the full command, ie "get device ... [args]"
    $action = $arr["action"];   //the first command, ex "get"
    $subject = $arr["full_cmdline"]; //the rest of the command, ie "device ..."
    $vals = $arr["args"];       //the arguments for the path

    switch ($path) {

/* ADD */

        case "add account-profile":
            $name = array_shift($vals);
            $vals["password"] = hashString($vals["password"]);
            if (isset($vals["password2"])) $vals["password2"] = hashString($vals["password2"]);

            $c = getSettingsValue(SETTING_CATEGORY_PROFILES,$name);
            if ($c != "") {
                echo "Error: Account profile with the name '$name' already exists\n";
            } else {
                $ret = writeSettingsValue(SETTING_CATEGORY_PROFILES,$name,$vals);
                echo ($ret ? "Successfully created Account profile '$name'\n" : "Error: Could not save account profile...\n");
            }
            break;

        case "add app-user":
            $name = array_shift($vals);
            $vals["password"] = hashString($vals["password"]);
            $vals["auth_type"] = "app";
            $c = getUser($name);
            if (!empty($c)) {
                echo "Error: User with the name '$name' already exists\n";
            } else {
                $ret = writeUser($name,$vals);
                echo ($ret ? "Successfully created User '$name'\n" : "Error: Could not save User...\n");
            }
            break;

        case "add config-rule":
            $rule = array_shift($vals);
            $c = getSettingsValue(SETTING_CATEGORY_CONFIGMGMT,"rules");
            if (in_array($rule,$c)) {
                echo "Error: This rule already exists\n";
            } else {
                $c[] = $rule;
                $ret = writeSettingsValue(SETTING_CATEGORY_CONFIGMGMT,"rules",$c);
                echo ($ret ? "Successfully created Configuration Rule\n" : "Error: Could not save Configuration Rule...\n");
            }
            print_r($c);
            break;

        case "add device":
            $name = array_shift($vals);
            $ping = isset($vals["collect-ping"]) ? ($vals["collect-ping"]=="true" ? true : false) : false;
            $vals["collectors"]["ping"] = [$ping,""];
            if (isset($vals["collect-snmp"])) $vals["collectors"]["snmp"] = [true,$vals["collect-snmp"]];
            if (isset($vals["collect-config"])) $vals["collectors"]["configuration"] = [true,$vals["collect-config"]];
            unset($vals["collect-ping"]);
            unset($vals["collect-snmp"]);
            unset($vals["collect-config"]);

            $d = getDeviceSettings($name);
            if (!empty($d)) {
                echo "Error: Device with the ip/hostname '$name' already exists\n";
            } else {
                $ret = writeDeviceSettings($name,$vals);
                echo ($ret ? "Successfully added the device '$name' to the database\n" : "Error: Could not save device...\n");
            }
            //print_r($vals);
            break;

        case "add snmp-profile":
            $name = array_shift($vals);
            $c = getSettingsValue(SETTING_CATEGORY_SNMP,$name);
            if (!empty($c)) {
                echo "Error: SNMP profile with the name '$name' already exists\n";
            } else {
                $ret = writeSettingsValue(SETTING_CATEGORY_SNMP,$name,$vals);
                echo ($ret ? "Successfully created SNMP profile '$test'\n" : "Error: Could not save SNMP profile...\n");
            }
            break;

/* COLLECT */

        case "collect":
            $passTo = realpath(dirname(__FILE__)."/lib/runCollection.php");
            //echo "running: php $passTo $subject\n";
            system("php $passTo $subject");
            break;

/* DELETE */

        case "delete account-profiles":
            $name = array_shift($vals);
            $read = readline("Are you sure you want to delete Account Profile '$name'? [N/y] ");
            if (strtolower($read)=="y") {
                echo (deleteSettingsValue(SETTING_CATEGORY_PROFILES,$name) ? "Account Profile '$name' removed\n" : "Error deleting Account Profile...\n");
            } else echo "Cancelled Operation\n";
            break;

        case "delete device":
            $name = array_shift($vals);
            $read = readline("Are you sure you want to delete Device '$name'? [N/y] ");
            if (strtolower($read)=="y") {
                echo (deleteDevice($name) ? "Device '$name' removed\n" : "Error deleting Device...\n");
            } else echo "Cancelled Operation\n";
            break;

        case "delete config-rule":
            $name = array_shift($vals);
            $rules = getSettingsValue(SETTING_CATEGORY_CONFIGMGMT,"rules");
            $read = readline("Are you sure you want to delete the following rule\n [$name] {$rules[$name]}\n? [N/y] ");
            if (strtolower($read)=="y") {
                unset($rules[$name]);
                echo (writeSettingsValue(SETTING_CATEGORY_CONFIGMGMT,"rules",$rules) ? "Rule [$name] removed\n" : "Error deleting rule...\n");
            } else echo "Cancelled Operation\n";
            break;

        case "delete snmp-profile":
            $name = array_shift($vals);
            $read = readline("Are you sure you want to delete SNMP Profile '$name'? [N/y] ");
            if (strtolower($read)=="y") {
                echo (deleteSettingsValue(SETTING_CATEGORY_SNMP, $name) ? "SNMP Profile '$name' removed\n" : "Error deleting SNMP Profile...\n");
            } else echo "Cancelled Operation\n";
            break;

        case "delete app-user":
            $name = array_shift($vals);
            $read = readline("Are you sure you want to delete Application User '$name'? [N/y]");
            if (strtolower($read)=="y") {
                echo (deleteUser($name) ? "User '$name' removed\n" : "Error deleting user...\n");
            } else echo "Cancelled Operation\n";
            break;

/* SHOW */

        case "show account-profiles":
            $j = json_encode(getSettingsArray(SETTING_CATEGORY_PROFILES), JSON_PRETTY_PRINT);
            echo $j;
            break;

        case "show device":
            $j = json_encode(getDeviceSettings($vals["device"]), JSON_PRETTY_PRINT);
            echo $j;
            break;

        case "show devices":
            $j = json_encode(getDevicesArray(), JSON_PRETTY_PRINT);
            echo $j;
            break;

        case "show config-rules":
            $j = json_encode(getSettingsValue(SETTING_CATEGORY_CONFIGMGMT,"rules"), JSON_PRETTY_PRINT);
            echo $j;
            break;

        case "show snmp-profiles":
            $j = json_encode(getSettingsArray(SETTING_CATEGORY_SNMP), JSON_PRETTY_PRINT);
            echo $j;
            break;

        case "show settings":
            $j = json_encode(getSettingsArray(), JSON_PRETTY_PRINT);
            echo $j;
            break;

        case "show app-users":
            $j = json_encode(getUsers(), JSON_PRETTY_PRINT);
            echo $j;
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
    $arg = "";
    $arg = substr($description,0,1) == "<" ? trim(substr($description,0,strpos($description,">")+1)) : $arg;
    $arg = substr($description,0,1) == "[" ? trim(substr($description,0,strpos($description,"]")+1)) : $arg;
    $description = trim(substr($description, strlen($arg)));

    $argMap[trim($path)] = array("required"=> $required, "input" => $takesInput, "constraint" => $regexConstraint, "description" => $description, "arg" => $arg);
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
    $usage = trim($validPath . " " . (isset($argMap[$validPath]["arg"])?$argMap[$validPath]["arg"]:"")) . " [options]";
    foreach ($argMap as $key => $arrParams) {
        if (substr($key,0,strlen($validPath)) == $validPath && substr_count($key," ") == substr_count(ltrim($validPath." "," ")," ")) {
            $cmd = trim(substr($key, strrpos($key," ")));
            $cmd .= isset($arrParams["arg"]) ? " " . $arrParams["arg"] : "";
            $req = $arrParams[ "required"] ? "" : "Optional ";
            //$usage .= " " . trim($cmd) . (isset($arrParams["arg"]) ? " " . $arrParams["arg"] : "");
            //$usage .= " " . $cmd;
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
        $out = ($validPath != "") ? "usage: " . $usage . "\n\n" : "";
        $out .= "Available options".$s.":\n" . $strHelp;
        consolePrint($out,str_repeat(" ",$longestWord)."    ");
    }

    //always exit after showing the help
    exit;
}

function consolePrint($text,$wrapPrefixString) {
    $cols = isWindows()?0:intval(exec('tput cols'));
    if (strlen($wrapPrefixString) * 1.8 <= $cols) {
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

    if(empty(getSettingsArray(SETTING_CATEGORY_SESSION))) {
        echo "First Run Config: Writing Default Configuration Settings\n";
        writeSettingsValue(SETTING_CATEGORY_SESSION,"login_max_failed",5);
        writeSettingsValue(SETTING_CATEGORY_SESSION,"login_lockout_time",300);
        writeSettingsValue(SETTING_CATEGORY_SESSION,"session_length_default",3600);
        writeSettingsValue(SETTING_CATEGORY_SESSION,"session_length_remember",5184000);

        writeSettingsValue(SETTING_CATEGORY_MAIL,"host","localhost");
        writeSettingsValue(SETTING_CATEGORY_MAIL,"from","");
        writeSettingsValue(SETTING_CATEGORY_MAIL,"username","");
        writeSettingsValue(SETTING_CATEGORY_MAIL,"password","");
        writeSettingsValue(SETTING_CATEGORY_MAIL,"smtpauth",true);
        writeSettingsValue(SETTING_CATEGORY_MAIL,"security","tls/ssl");
        writeSettingsValue(SETTING_CATEGORY_MAIL,"port",587);

        writeSettingsValue(SETTING_CATEGORY_TASKS,"ping","pull from cronjobs");
        //"Ping": { "Enabled": true, "Interval": 300, "Description": "Pings all devices that have this collector enabled. If ping fails a traceroute will be performed to see if there was a network interruption." },
        //"Traceroute": { "Enabled": true, "Interval": 86400, "Description": "Performs a typical traceroute and fails over to an intelligent tcp traceroute if the standard method fails." },
        //"SNMP System": { "Enabled": true, "Interval": 86400, "Description": "Collects basic SYSTEM info via SNMP." },
        //"SNMP HW": { "Enabled": true, "Interval": 900, "Description": "Collects basic Hardware info via SNMP." },
        //"SNMP Network": { "Enabled": true, "Interval": 300, "Description": "Collects Network interfaces and throughput stats via SNMP." },
        //"SNMP Routing": { "Enabled": true, "Interval": 3600, "Description": "Collects the Routing table via SNMP." },
        //"SNMP Custom": { "Enabled": true, "Interval": 3600, "Description": "Collects vendor specific SNMP information that\'s defined in the snmpmap file located in the conf directory." },
        //"Configuration": { "Enabled": true, "Interval": 86400, "Description": "Runs custom scripts to collect vendor specific configuration info." }

        writeSettingsValue(SETTING_CATEGORY_RETENTION,"data_history",365);
        writeSettingsValue(SETTING_CATEGORY_RETENTION,"change_history",365);
        writeSettingsValue(SETTING_CATEGORY_RETENTION,"alert_history",365);

        writeSettingsValue(SETTING_CATEGORY_PROFILES,"description","List of remote Username/Password combos to get into the system. password2 is the 2nd level password which is called in certain scripts. (ex. Cisco Enable)");

        writeSettingsValue(SETTING_CATEGORY_SNMP,"public",array("version"=>"2c","string"=>"public","username"=>"","password"=>""));

        writeSettingsValue(SETTING_CATEGORY_CONFIGMGMT,"description","Settings for connecting to devices to collect its configuration.");
        writeSettingsValue(SETTING_CATEGORY_CONFIGMGMT,"rules",
            ['vendor contains "check point" or vendor contains checkpoint run checkpoint.php',
             'vendor contains "palo alto" or vendor contains paloalto run paloalto.php']);

        writeSettingsValue(SETTING_CATEGORY_ALERTS,"description","Alert settings and rules");
        writeSettingsValue(SETTING_CATEGORY_ALERTS,"email","send-all-alerts-here@company.com");
        writeSettingsValue(SETTING_CATEGORY_ALERTS,"rules",["array of rules"]);

        writeSettingsValue(SETTING_CATEGORY_REGIONS,"Americas",array("notes"=>""));
        writeSettingsValue(SETTING_CATEGORY_REGIONS,"EMEA",array("notes"=>""));
        writeSettingsValue(SETTING_CATEGORY_REGIONS,"APAC",array("notes"=>""));
        writeSettingsValue(SETTING_CATEGORY_SITES,"Headquarters",array("address"=>"","site-id"=>"","notes"=>""));
        writeSettingsValue(SETTING_CATEGORY_CONTACTS,"Default Admin",array("name"=>"","email"=>"","notes"=>""));

    } else {
        echo "Settings are already initialized\n";
    }

    if(empty(getUsers())) {
        echo "First Run Config: Adding Default User\n";
        writeUser("admin",array("password"=>hashString("admin"), "api_key"=>strtoupper(randomToken()),
            "name"=>"Default Admin", "auth_type"=>"app", "role"=>"admin", "email"=>""));
    } else {
        echo "Users are already added\n";
    }

    if (empty(getDevicesArray())) {
        //$tmpl = TEMPLATE_DEVICE;
        echo "Created the default device 'localhost'";
        writeDeviceSettings("localhost",array("name"=>"NeoSentry NMS", "type"=>"Server","collectors"=>array("ping"=>[true,""])));
    } else {
        echo "Devices are already added\n";
    }

    echo "Done with first run config\n";

}