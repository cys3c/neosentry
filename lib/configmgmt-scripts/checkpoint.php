#!/usr/bin/php
<?php

error_reporting(E_COMPILE_ERROR);

/*
 *  CHECK POINT SCRIPT

Firewall Files: R77 Mgmt
$FWDIR/database/objects.C
$FWDIR/database/rules.C

Older files: R71 Mgmt
$FWDIR/conf/objects_5_0.C
$FWDIR/conf/objects.C_41
$FWDIR/conf/objects.C
$FWDIR/conf/rulebases_5_0.fws

$FWDIR/log/
fw log -l -n -p -z -s "March 20, 2017 10:50:00" fw.log | grep "; rule: "
fw log -l -p -n -z -o -b "March 20, 2017 10:50:00" "March 23, 2017 10:52:00" fw.log

rule: [0-9]*|NAT_rulenum: [0-9]*

time: 1.5s on a 2M file, 34.395s on 1 hour of logs
egrep -o "rule: [0-9]*|NAT_rulenum: [0-9]*" | sort | uniq -c

time: 1s on a 2M file, 26.551s on 1 hour of logs
fw log -l -n -p -z -s "March 20, 2017 10:50:00"
egrep -o "rule: [0-9]*|NAT_rulenum: [0-9]*" | awk '{count[$1,$2]++} END {for (word in count) print word, count[word]}'


22Mar2017 13:42:02 accept 10.59.31.10 >Internal inzone: Internal; outzone: External; service_id: http; src: 10.59.0.58; dst: 165.225.32.40; proto: tcp; xlatesrc: 38.131.4.154; NAT_rulenum: 5; NAT_addtnl_rulenum: 1; rule: 13; product: VPN-1 & FireWall-1; service: 80; s_port: 56985; xlatesport: 12825; product_family: Network;

 */



// Required includes for ssh connection.
set_include_path('%includes%/phpseclib'); //required since these libraries include other libraries
include('Net/SSH2.php');
include('Net/SCP.php');

//this script will be copied to ~/data/devices/%device%/tmp
const LOG_FILE = "configuration.log";

// these %variable% strings will be replaced with the appropriate information before running.
$device = "%device%";           //the device IP or hostname we're connecting to
$username = "%username%";
$password = "%password%";
$password2 = "%password2%";     //this 2nd password is an optional variable only used if a 2nd level password is needed
//echo "device: '$device'\nuser:   '$username'\npass:   '$password'\npass2:  '$password2'\n";

//run the main collector logic
//outputText("Running Check Point Configuration Collection Script\n");
$configArr = runCollector($device, $username, $password, $password2);

//print the output so the parent program can collect it.
if (is_array($configArr)) echo json_encode($configArr,JSON_PRETTY_PRINT);
else echo $configArr;

exit;


/** This is the primary function that's called. output should be saved in JSON format
 * @param $device
 * @param $username = username to log into the device
 * @param $password = password to log into the device
 * @param $password2 = optional 2nd password for 2nd level auth
 */
function runCollector($device, $username, $password, $password2="") {

    $saveToFolder = dirname(__FILE__);
    $saveToFile = "configurationOutput.json";

    // Get the last time we modified the config file
    $lastRunTime = file_exists($saveToFile)?filemtime($saveToFile):0;
    $arrConfig = [];

    //Set up the SSH and SCP constructors
    outputText("connecting to $device");
    $ssh = new Net_SSH2($device);
    if (!$ssh->login($username, $password)) {
        return "Error: Login Failed to $device using username '$username'. " . $ssh->getLastError();
    }
    $scp = new Net_SCP($ssh);


    //first see if we have expert access
    $ret = $ssh->exec('whoami');
    if (strpos($ret,"whoami")>0) {
        //not in expert
        //$err =  "Error: Incorrect shell detected. To collect this devices configuration then log in as Expert and run the following command: 'chsh -s /bin/bash $username'";
        outputText("Non-elevated shell detected, logging into expert mode.");
        
        sshRunCommand($ssh, 'expert', 3, 'Enter expert password:');
        $ret = trim(sshRunCommand($ssh, $password2, 3, "[Expert@$device]# ", false));
        //echo "\n'$ret'\n" . strpos("blah","not in here");
        if (strpos($ret,'Wrong password') !== false) {
            $err = "Error: Could not log into expert mode. Alternatively you could try changing the users shell so an expert login isn't required with the command: 'chsh -s /bin/bash $username'";
            outputText($err . "\n" . $ret);
            return $err . "\n" . $ret;
        }
        //$ssh->write("expert\n");
        //$buf = $ssh->read('Enter expert password:');
        //$ssh->write($password2."\n");

    }
    //we're now successfully connected to an expert shell
    outputText("Connected. Running Check Point Collection Script");
    

    // On newer versions with the clish shell, we can get a configuration output similar to palo alto and cisco
    outputText("Attempting to get the newer version of the configuration settings... ");
    $config = sshRunCommand($ssh, '/etc/cli.sh -c "show configuration"');
    if (strlen($config) < 100) {
        $config = "";
        outputText("Failed, cli.sh not present.");
    } else {
        outputText("Success!");
        $arrConfig["Configuration"] = $config;
    }


    // Get the version of Check Point we're working with so we know the right folder
    //$ssh->enablePTY();
    $ver = trim(sshRunCommand($ssh, 'ls /opt | grep CPsuite | sort -r | head -n1'));

    // Find the latest objects file
    $ret = sshRunCommand($ssh, "cd /opt/$ver/fw1/");
    $objFiles = ["database/objects.C", "conf/objects_5_0.C", "conf/objects.C_41", "conf/objects.C"];
    $objFile = trim($objFiles[0]);
    $objMod = 0;
    foreach ($objFiles as $f) {
        // loop through each possible file and find the newest
        $ret = intval(sshRunCommand($ssh, "stat -c %Y $f"));
        if ($ret > $objMod) {
            $objMod = $ret;
            $objFile = $f;
        }
    }

    // we have the latest file, lets get convert its contents
    outputText("Collecting " . $objFile . " with last modified date of " . date("M d, Y h:m:s", $objMod));
    $ret = sshRunCommand($ssh, "tr -dc '[:print:]\\n' < $objFile"); //sshRunCommand($ssh, "cat '$objFile'");
    //$ret = $scp->get("/opt/$ver/fw1/" . $objFile);
    outputText("Saving objects file");
    file_put_contents($saveToFolder . "/configmgmt_objects.C", $ret);

    //So i have to do it this way because for some reason something in the objects.C file was closing the SSH connection
    // and when transferring the file, they don't match up, giving an unzip error.
    //$ret = sshRunCommand($ssh, "cp $objFile $objFile"."2");
    //$ret = sshRunCommand($ssh, "gzip -1 -f $objFile"."2");
    //$ret = $scp->get($objFile."2.gz", $saveToFolder . "/configmgmt_objects.C.gz");
    //shell_exec("gunzip < $saveToFolder/configmgmt_objects.C.gz > $saveToFolder/configmgmt_objects.C 2> /dev/null");

    if (!file_exists($saveToFolder."/configmgmt_objects.C")) {  //error
        outputText("Could not load the objects.C file required to convert checkpoint objects into ip addresses.");
    
    } else {    //SUCCESS, got objects file
        $ret = file_get_contents($saveToFolder."/configmgmt_objects.C");

        outputText("Converting and Importing Objects File.");
        $objJSON = parseToJson($ret);
        file_put_contents($saveToFolder . "/configmgmt_objects.json", $objJSON);
        $arrObjects = json_decode($objJSON, true);
        if (json_last_error()) {    // there was something wrong with the conversion, lets save the bad json file to look at
            outputText(" - " . json_last_error_msg());
        } else {    // successfully converted, save the json.
            outputText(" - Success");
        }
    }
    


    // Now find the latest firewall rule and nat file and get its contents
    $ret = sshRunCommand($ssh, "cd /opt/$ver/fw1/"); 
    $ruleFiles = ["database/rules.C", "conf/rulebases_5_0.fws"];
    $ruleFile = "";
    $ruleMod = 0;
    foreach ($ruleFiles as $f) {
        // loop through each possible file and find the newest
        $ret = sshRunCommand($ssh, "stat -c %Y $f");
        outputText("stat of '$f' returned '$ret'");
        if ($ret > $ruleMod) {
            $ruleMod = $ret;
            $ruleFile = $f;
        }
    }
    

    // we have the latest file, lets convert its contents
    outputText("Collecting " . $ruleFile . " with last modified date of " . date("M d, Y h:m:s", $ruleMod));
    $ret = sshRunCommand($ssh, "cat '$ruleFile'");
    file_put_contents($saveToFolder . "/configmgmt_rules.C", $ret);

    outputText("Converting and Importing Rules File.");
    $ruleJSON = parseToJson($ret);
    $arrRules = json_decode($ruleJSON, true);
    if (json_last_error()) {
        // there was something wrong with the conversion, lets save the bad json file to look at
        outputText(" - " . json_last_error_msg());
        file_put_contents($saveToFolder . "/configmgmt_rules.json", $ruleJSON);
    } else {
        // success, write the json file.
        outputText(" - Success");
        file_put_contents($saveToFolder . "/configmgmt_rules.json", json_encode($arrRules, JSON_PRETTY_PRINT));

        //Convert the Firewall Rules
        foreach ($arrRules["rules"] as $key => &$item) {
            //Note: if the $item[..]["objects"] section doesn't exist then ANY is inferred
            $id = $key;//key($item);
            $section = "Firewall Rules";

            $arrTemp = [];
            $arrTemp["ID"] = $id;
            $arrTemp["Disabled"] = $item["disabled"];
            $arrTemp["Rulenum"] = $item["unified_rulenum"];
            $arrTemp["Hits"] = 0;
            $arrTemp["Name"] = $item["name"];
            $arrTemp["Source"] = getCPObjects($item["src"], $arrObjects);
            $arrTemp["Destination"] = getCPObjects($item["dst"], $arrObjects);
            $arrTemp["VPN"] = $item["through"]["ReferenceObject"]["Name"];
            $arrTemp["Services"] = getCPObjects($item["services"], $arrObjects, "servobj");
            $arrTemp["Action"] = key($item["action"]);
            $arrTemp["Track"] = rtrim(implode(", ", getCPObjects($item["track"])), ', ');
            $arrTemp["Install On"] = rtrim(implode(", ", getCPObjects($item["install"])), ', ');
            $arrTemp["Time"] = getCPObjects($item["time"]); //key($item["time"]);
            //$arrConfig["Firewall Rules"][$id]["Comments"] = 0; //this is stored in the policy .W file

            //determine if the src, dst, or services cell is negated and add a value.
            if ($item["src"]["op"] == "not in") array_unshift($arrTemp["Source"], "!NEGATED!");
            if ($item["dst"]["op"] == "not in") array_unshift($arrTemp["Destination"], "!NEGATED!");
            if ($item["services"]["op"] == "not in") array_unshift($arrTemp["Services"], "!NEGATED!");

            //add the rule to the array
            $arrConfig[$section][] = $arrTemp;
        }

        //Convert the NAT Rules
        foreach ($arrRules["rules-adtr"] as $key => $item) {
            $id = $key; //key($item);
            $section = "NAT Rules";

            $arrTemp = [];
            $arrTemp["ID"] = $id;
            $arrTemp["Disabled"] = $item["disabled"];
            $arrTemp["Hits"] = 0;
            $arrTemp["Name"] = $item["name"];
            $arrTemp["Original Source"] = getCPObjects($item["src_adtr"], $arrObjects);
            $arrTemp["Original Destination"] = getCPObjects($item["dst_adtr"], $arrObjects);
            $arrTemp["Original Service"] = getCPObjects($item["services_adtr"], $arrObjects, "servobj");
            $arrTemp["Translated Source"] = getCPObjects($item["src_adtr_translated"], $arrObjects);
            //$arrTemp["src_adtr_method"] = $item["src_adtr_translated"]["adtr_method"];
            $arrTemp["Translated Destination"] = getCPObjects($item["dst_adtr_translated"], $arrObjects);
            //$arrTemp["dst_adtr_method"] = $item["dst_adtr_translated"]["adtr_method"];
            $arrTemp["Translated Services"] = getCPObjects($item["services_adtr_translated"], $arrObjects, "servobj");
            //$arrTemp["services_adtr_method"] = $item["services_adtr_translated"]["adtr_method"];
            $arrTemp["Install On"] = rtrim(implode(", ", getCPObjects($item["install"])), ', ');

            //get the NAT method and add it to the objects array
            array_unshift($arrTemp["Translated Source"], "!" . str_replace("adtr_", "", getElement($item["src_adtr_translated"]["adtr_method"])) . "!");
            array_unshift($arrTemp["Translated Destination"], "!" . str_replace("adtr_", "", getElement($item["dst_adtr_translated"]["adtr_method"])) . "!");
            array_unshift($arrTemp["Translated Services"], "!" . str_replace("adtr_", "", getElement($item["services_adtr_translated"]["adtr_method"])) . "!");

            //add the rule to the array
            $arrConfig[$section][] = $arrTemp;
        }

    }




    // Save the consolidated configuration
    file_put_contents($saveToFile, json_encode($arrConfig));
    outputText("Saved Consolidated configuration to $saveToFile");



    /*
     * Once parsing is done and we have the rules table we can parse the logs to get a rule hitcount.
     *  this is resource intensive, especially on older devices so ideally this should be collected off hours.
     * Collect logs since the last time we ran this. make sure the start date/time is greater than the last modified
     *  date on the rules file so the rule numbers match up.

    time: 1s on a 2M file, 26.551s on 1 hour of logs
    fw log -l -n -p -z -b "March 20, 2017 10:50:00" "March 20, 2017 11:50:00" | egrep -o "rule: [0-9]*|NAT_rulenum: [0-9]*" | awk '{count[$1,$2]++} END {for (word in count) print word, count[word]}'

    */

    //get the start and end times
    $stime = ($ruleMod > $lastRunTime) ? $ruleMod : $lastRunTime;
    $etime = file_exists($saveToFile) ? filemtime($saveToFile) : 0;
    $dayAgo = $etime - 86400;
    if ($stime < $dayAgo || $stime >= $etime - 60) $stime = $dayAgo; //we only want to collect a max of 24 hours of logs due to resource utilization
    $strStart = date("F d, Y h:m:s", $stime);
    $strEnd = date("F d, Y h:m:s", $etime);
    $cmd = 'fw log -l -n -p -z -b "' . $strStart . '" "' . $strEnd . '" | egrep -o "rule: [0-9]*|NAT_rulenum: [0-9]*" | awk \'{count[$1,$2]++} END {for (word in count) print word, count[word]}\'';
    //$cmd2 = '/bin/cpfw_start log -l -n -p -z -b "' . $strStart . '" "' . $strEnd . '" | egrep -o "rule: [0-9]*|NAT_rulenum: [0-9]*" | awk \'{count[$1,$2]++} END {for (word in count) print word, count[word]}\'';
    $cmd2 = '/opt/' . $ver . '/fw1/bin/fw log -l -n -p -z -b "' . $strStart . '" "' . $strEnd . '" | egrep -o "rule: [0-9]*|NAT_rulenum: [0-9]*" | awk \'{count[$1,$2]++} END {for (word in count) print word, count[word]}\'';

    outputText("Collecting hit count from $strStart to $strEnd");

    //$ssh->enablePTY();
    //$ssh->setTimeout(5);
    //$readTo = "===[ Script Completed ]===";
    //$ssh->read();
    //if ($ssh->isTimeout()) $readTo = substr($ret, strrpos($ret,"\n"));
    //$ssh->write($cmd . " ; echo \"$readTo\"\n");
    //$ssh->setTimeout(300); //reading the rules could take time so lets set the timeout to 30 minutes (1800 seconds)
    //$ret = $ssh->read("\n".$readTo); //$ssh->read('_.*@.*[$#>]_', NET_SSH2_READ_REGEX);
    $ret = sshRunCommand($ssh, $cmd, 1200); //20 minute timeout to collect
    outputText("Text read from hit-count collection:\n$ret");

    //$ret = sshRunCommand($ssh, $cmd);
    //if (strpos($ret,"command not found") > 0) $ret = sshRunCommand($ssh, $cmd2);
    $collected = false;
    foreach (explode("\n", $ret) as $line) {
        //cleanup the line for easy processing
        $line = str_replace(":"," ",$line);
        $line = preg_replace("/[^A-Za-z0-9 ]/", '', $line);

        //make sure we have the right input, parse it,
        if (substr_count($line," ") == 2) {
            list($name, $num, $cnt) = explode(" ", $line);
            $collected = true;
            if($name == "rule" && isset($arrConfig["Firewall Rules"][$num-1])) {
                $arrConfig["Firewall Rules"][$num-1]["Hits"] = intval($cnt);
            } elseif ($name == "NAT_rulenum" && isset($arrConfig["NAT Rules"][$num-1])) {
                $arrConfig["NAT Rules"][$num-1]["Hits"] = intval($cnt);
            }
        }
    }

    if ($collected) {
        //save the config again
        file_put_contents($saveToFile, json_encode($arrConfig));
        outputText("Collection complete!");
    } else {
        //nothing collected
        outputText("Could not collect hit count.\n  cmd: " . $cmd);
        outputText("Hit count command returned: $ret");
    }

    // Exit
    //return "Successfully collected this devices configuration and saved to " . $saveToFile;
    return $arrConfig;
}



/* ===================================================
 * ADDITIONAL FUNCTIONS
 * ===================================================
 */

/** returns an array of objects. defaults to the checkpoint standard of Any */
function getCPObjects(&$arr, &$objArray = null, $section="netobj") {
    $ret = isset($arr["objects"]) ? $arr["objects"] : ["Any"];

    if (!is_null($objArray)) {
        //we'll look up the object references and return the actual values
        $ret = convertObjects($objArray,$section, $ret);
    }

    return $ret;
}
function getElement(&$arr, $default="") {
    return isset($arr) ? $arr : $default;
}

/** Converts an object list into a consolidated IP list. returns an array of IP/Cidr  */
function convertObjects(&$objArray, $objType, $objList) {
    //objType = "netobj", "servobj", "timeobj"
    $arrRet = []; // the array we'll be returning

    //handle the Any oject entry
    if (sizeof($objList) == 1 && strtolower($objList[0]) == "any")
        return $objList;


    foreach ($objList AS $item) {
        // First take care of any groups
        if (array_key_exists("objects", $objArray[$objType][$item])) {
            //if this key exists then its a group
            $arrRet = array_merge($arrRet, convertObjects($objArray, $objType, $objArray[$objType][$item]["objects"]));

        } else {
            // Its an object, not a group so lets resolve and add it to the list.
            switch ($objType) {
                case "netobj": //host and network object lookup bogus_ip
                    if (array_key_exists("bogus_ip", $objArray[$objType][$item])) { //Checkpoint specific object
                        $arrRet[] = $item;

                    } elseif (array_key_exists("ipaddr", $objArray[$objType][$item])) { //IPv4 host or network object
                        $v = getElement($objArray[$objType][$item]["addr_type_indication"], "IPv4"); //IPv4, IPv6
                        $nm = getElement($objArray[$objType][$item]["netmask"], ($v == "IPv6" ? 64 : 32));
                        $arrRet[] = $objArray[$objType][$item]["ipaddr"] . "/" . netmask2cidr($nm);

                    } elseif (array_key_exists("ipv6_address", $objArray[$objType][$item])) { //IPv6 Address
                        $nm = getElement($objArray[$objType][$item]["ipv6_prefix"], 64);
                        $arrRet[] = $objArray[$objType][$item]["ipv6_address"] . "/" . $nm;

                    } elseif (array_key_exists("ipaddr_first", $objArray[$objType][$item])) { //range object
                        $arrRet[] = $objArray[$objType][$item]["ipaddr_first"] . "-" . $objArray[$objType][$item]["ipaddr_last"];
                    } else { //unknown
                        $arrRet[] = $item;
                    }
                    break;

                case "servobj": //services lookup
                    if (array_key_exists("port", $objArray[$objType][$item])) {
                        $arrRet[] = strtoupper($objArray[$objType][$item]["type"]) . "/" . $objArray[$objType][$item]["port"];
                    } elseif (array_key_exists("type", $objArray[$objType][$item])) {
                        $arrRet[] = strtoupper($objArray[$objType][$item]["type"]) . "/" . $item;
                    } else {
                        $arrRet[] = $item;
                    }
                    break;

            }
        }
    }

    //now sort and clean up the array before handing it back
    sort($arrRet);
    $arrRet = array_unique($arrRet);

    return $arrRet;
}

function netmask2cidr($netmask) {
    if (!strpos($netmask,".")) return $netmask; //this is not a valid netmask

    $bits = 0;
    $netmask = explode(".", $netmask);

    foreach($netmask as $octect)
        $bits += strlen(str_replace("0", "", decbin($octect)));

    return $bits;
}

function parseToJson($text){
    //converts a Check Point style .C file to JSON format
    $addComma = false;
    $sJson = "";
    $cnt = 0; $depth = 0;
    $arr = []; // for adding array elements

    //make an array of each line, ignore bad UTF8 characters, common in old checkpoint object files.
    $sArr = explode("\n",iconv('UTF-8', 'UTF-8//IGNORE', $text));

    foreach ($sArr AS $line) {
        $s = str_replace("\\","|",trim($line));
        if (strlen($s) <= 0) continue;

        //depth and line counters
        $depth = $depth + substr_count($s,"(") - substr_count($s, ")");
        $cnt++;

        if ($s[0]=="(") {
            $sJson .= "{";
            $addComma = false;

        } elseif ($s[0]==")") {
            //close this section, add array objects if we have them.
            if (count($arr)>0) {
                if ($addComma) $sJson .= ",\n";
                $sJson .= '"objects": '.json_encode($arr);
                $arr = [];
            }
            $sJson .= "}";
            $addComma = true;

        } elseif (substr($s,0,3) == ": (" && $s[strlen($s)-1] == ")") {
            //ex: ": name" - array of elements
            $arr[] = trim($s, ': ()"');

        } elseif (substr($s,0,3) == ": (") {
            //ex: ": (name", ": ("
            $svar = trim(trim($s, ": ("),'"');
            if ($svar == "") $svar = $cnt;
            if ($addComma) $sJson .= ",\n";
            $sJson .= '"' . $svar . '": {'.PHP_EOL;
            $addComma = false;

        } elseif (substr($s,0,2) == ": ") {
            //ex: ": name" - array of elements
            $arr[] = trim($s, ": ");

        } elseif ($s[0] == ":") {
            //ex: ":name (value)", ":name (", ":name ()", && errors like ":name (val"
            $svar = trim(substr($s,1,strpos($s," (")-1), '"');
            $sval = trim(trim(substr($s,strpos($s," (")+1), " ()"), '"');

            //add comma
            if ($addComma) $sJson.=",\n";

            if ($svar != "" && !strpos($s,")") && !strpos($s,'("')) { // we have the start of a sub-array
                $sJson .= '"'.$svar.'": {'."\n";
                $addComma = false;
            } else { //there's a name:value combo
                if ($sval != "false" && $sval != "true" && !is_numeric($sval)) $sval = '"'.$sval.'"';
                if (is_numeric($sval)) $sval = intval($sval);
                $sJson .= '"'.$svar.'": '.$sval;
                $addComma = true;
            }

            /* } elseif ($s[0] == ":" && $s[strlen($s)-1] == ")") {
                 //ex: ":param_type (string_id)"
                 $svar = str_replace(" ","",substr($s,1,strpos($s,"(")-1));
                 $sval = trim(trim(substr($s,strpos($s,"(")+1),' )('),'"');
                 // make sure we have a valid variable & value
                 if ($svar != "" && $sval != "") {
                     if ($svar == "") $svar = $sval;
                     if ($sval != "false" && $sval != "true" && !is_numeric($sval)) $sval = '"'.$sval.'"';
                     if ($addComma) $sJson.=",\n";
                     $sJson .= '"'.$svar.'": '.$sval;
                     $addComma = true;
                 }

             } elseif ($s[0] == ":" && strpos($s,"(")>0 && substr($s,-1) != ")") {
                 // ex: ": (format3" || ":format3 ("
                 $svar = trim($s,':( "');
                 if ($addComma) $sJson.=",\n";
                 $sJson .= '"'.$svar.'": {'."\n";
                 $addComma = false; */
        } else {
            outputText("Line $cnt not processed. Text = [$s]");
        }
    }

    return $sJson;
}

function getLastJsonError(){
    switch (json_last_error()) {
        case JSON_ERROR_NONE:
            return ' - No errors';
            break;
        case JSON_ERROR_DEPTH:
            return ' - Maximum stack depth exceeded';
            break;
        case JSON_ERROR_STATE_MISMATCH:
            return ' - Underflow or the modes mismatch';
            break;
        case JSON_ERROR_CTRL_CHAR:
            return ' - Unexpected control character found';
            break;
        case JSON_ERROR_SYNTAX:
            return ' - Syntax error, malformed JSON';
            break;
        case JSON_ERROR_UTF8:
            return ' - Malformed UTF-8 characters, possibly incorrectly encoded';
            break;
        default:
            return ' - Unknown error';
            break;
    }

}


function sshRunCommand(&$sshSession, $cmd, $timeout = 10, $sshNewReadTo = '', $logCmd = true){
    //return $sshSession->exec($cmd);
    static $sshReadTo;
    if ($sshNewReadTo != '' || !isset($sshReadTo)) $sshReadTo = $sshNewReadTo;

    //run the command
    if ($logCmd) outputText("> ".$cmd);
    outputText("+ Reading until prompt: $sshReadTo");
    //write in chunks otherwise the session may insert newlines
    foreach(str_split($cmd,32) as $chunk) { $sshSession->write($chunk); $sshSession->setTimeout(0.1); $sshSession->read(); }
    $sshSession->write("\n");
    //$sshSession->setTimeout(0.1);
    //$sshSession->read();

    $sshSession->setTimeout($timeout);
    $ret = $sshSession->read($sshReadTo); //$ssh->read('_.*@.*[$#>]_', NET_SSH2_READ_REGEX);
    
    //detect a timeout and change the prompt accordingly
    if($sshSession->isTimeout()) { 
        $sshReadTo = substr($ret, strrpos($ret,"\n")+1); 
        outputText("+ Timeout reached. Changed detected prompt to '$sshReadTo'"); 
    }
    
    //remove the command and prompt from the output
    if (strpos($ret,$cmd) !== false) $ret = substr($ret,strlen($cmd));
    $p = strrpos($ret, $sshReadTo);
    if ($p !== false) $ret = substr($ret,0, $p);
    $ret = trim($ret,"\n\r");

    //return the cleaned up output
    outputText("+ " . strlen($ret) . " bytes read.");
    return $ret;

}

function outputText($string){
    //echo $string . "\n";
    file_put_contents(LOG_FILE,date(DATE_ATOM) . ": " . $string . "\n",FILE_APPEND);
}

/* OLD RUN COMMAND
function sshRunCommand(&$sshSession, $cmd, $timeout = 10, $sshNewReadTo = '', $logCmd = true){
    //return $sshSession->exec($cmd);
    static $sshReadTo;
    if ($sshNewReadTo != '') $sshReadTo = $sshNewReadTo;
    
    if (!isset($sshReadTo)) {
        //get the command prompt
        outputText("Detecting prompt...");
        $sshSession->read(); //clear out the buffer
        $sshSession->write("\n");
        $sshSession->setTimeout(3);
        $read = "\n" . $sshSession->read('>');
        $sshReadTo = trim(substr($read, strrpos($read,"\n")+1));
        outputText("Found prompt: $sshReadTo");
    }

    //run the command
    if ($logCmd) outputText("> ".$cmd);
    outputText("+ Reading until prompt: $sshReadTo");
    $cmd .= "\n";
    $sshSession->write($cmd);
    //$sshSession->read(); //clear out the command echo
    //$sshSession->write("\n");
    $sshSession->setTimeout($timeout);
    $ret = $sshSession->read($sshReadTo); //$ssh->read('_.*@.*[$#>]_', NET_SSH2_READ_REGEX);
    
    if($sshSession->isTimeout()) { $sshReadTo = substr($ret, strrpos($ret,"\n")+1); outputText("+ Timeout reached. Changed detected shell to '$sshReadTo'"); }
    
    //remove the command and prompt from the output
    $ret = strpos($ret,$cmd) !== false ? substr($ret,strlen($cmd)) : $ret;  //remove the command
    //$ret = str_replace($sshReadTo,"",$ret); //remove the prompt
    $p = strrpos($ret, $sshReadTo);
    if ($p !== false) $ret = substr($ret,$p);

    outputText("+ " . strlen($ret) . " bytes read.");
    //outputText("+ Prompt is now: $sshReadTo");
    return $ret;

}
*/

/*
 *   END CHECK POINT SCRIPT
 */