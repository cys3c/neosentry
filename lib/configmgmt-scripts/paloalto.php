#!/usr/bin/php
<?php

error_reporting(E_COMPILE_ERROR);


// Collects the firewall and NAT rules from a Palo Alto device

/* An example format is:
{
    "Firewall Rules": {
        "rule-1": {
            "Disabled": false,
			"Rulenum": 1,
			"Hits": 0,
			"Name": "",
			"Source": ["101.200.173.48\/32"],
			"Destination": ["Any"],
			"VPN": "Any",
			"Services": ["Any"],
			"Action": "drop",
			"Track": "Log",
			"Install On": "Test-Firewall",
			"Time": ["Any"]
		}
    },
	"NAT Rules": {
        "rule-1": {
            "Disabled": false,
			"Hits": 0,
			"Name": "",
			"Original Source": ["192.168.0.111\/32"],
			"Original Destination": ["Any"],
			"Original Service": ["Any"],
			"Translated Source": ["!method_hide!",  "10.10.10.10\/32"],
			"Translated Destination": ["!method_static!", "Any"],
			"Translated Services": ["!method_static!", "Any"],
			"Install On": "Any"
		}
    },
    "Configuration": "String of configuration output, or optionally an array. each array entry will be a row"
}
*/


set_include_path('%includes%/phpseclib'); //required since these libraries include other libraries
include('Net/SSH2.php');
include('Net/SCP.php');


//this script will be copied to ~/data/devices/%device%/tmp so lets use this scripts current folder as a scratch directory
$scratchFolder = dirname(__FILE__);    //a temporary working directory to store and work with files.
const LOG_FILE = "configuration.log";

// these %variable% strings will be replaced with the appropriate informatoin before running.
$device = "%device%";           //the device IP or hostname we're connecting to
$username = "%username%";
$password = "%password%";
$password2 = "%password2%";     //this 2nd password is an optional variable only used if a 2nd level password is needed


// print the json of the data. The return data will be compared to the previous data for configuration change tracking
// and will be written to the configuration storage file/location/db-table/etc.
$configArr = runCollector($device, $username, $password, $password2);

//print the output so the parent program can collect it.
if (is_array($configArr)) echo json_encode($configArr,JSON_PRETTY_PRINT);
else echo $configArr;



exit;


// ALL OTHER FUNCTIONS BELOW THIS

function runCollector($device, $username, $password, $password2="") {
    /* palo collection commands:
        set cli pager off   //without this you can pipe to the except command to bypass pagers. ex: | except "some text not found"
        show config merged
        show running nat-policy
        show running security-policy
    */

    global $scratchFolder;
    $arrConfig = [];

    outputText("connecting to $device");
    $ssh = new Net_SSH2($device);
    if (!$ssh->login($username, $password)) {
        return "Error: Login Failed using username '$username'. " . $ssh->getLastError();
    }
    $scp = new Net_SCP($ssh);
    $ssh->enablePTY();


    //turn off paging so we get the whole output at once without line breaks. Enable scripting-mode so output is displayed properly
    $ret = sshRunCommand($ssh,'set cli pager off', 3);
    $ret = sshRunCommand($ssh,'set cli scripting-mode on', 3);

    //get Configuration
    sleep(1);
    sshRunCommand($ssh,'', 3);
    $ret = trim(sshRunCommand($ssh,'show config merged', 20)); //get the local policy and panorama policy.
    file_put_contents($scratchFolder."/collected_configuration.original",$ret);
    $arrConfig["Configuration"] = $ret;

    //get NAT Rules
    $ret = trim(sshRunCommand($ssh,'show running nat-policy'));
    file_put_contents($scratchFolder."/collected_nat_rules.original",$ret);
    $outputArr = explode("\n",$ret);
    $arrRules["nat"] = paloJsonToArray($outputArr);
    file_put_contents($scratchFolder."/collected_nat_rules.parsed",json_encode($arrRules["nat"],JSON_PRETTY_PRINT));

    $cnt = 0;
    foreach ($arrRules["nat"] as $key => $item) {
        $id = $key; //key($item);
        $section = "NAT Rules";
        //make sure we only collect a valid rule
        if(isset($item['source']) && isset($item['destination'])) {
            $arrTemp = [];
            //$arrTemp["ID"] = $id;
            $arrTemp["Disabled"] = isset($item["disabled"]) ? $item["disabled"] : "no";
            $arrTemp["Rulenum"] = $cnt;
            $arrTemp["Hits"] = 0;
            $arrTemp["Name"] = $key;
            $arrTemp["Original Source"] = $item['source'];
            $arrTemp["Source Zone"] = $item['from'];
            $arrTemp["Original Destination"] = $item['destination'];
            $arrTemp["Destination Zone"] = $item['to'];
            $arrTemp["Original Service"] = $item['service'];
            $arrTemp["Translated To"] = $item['translate-to'];
            $arrTemp["Nat Type"] = $item['nat-type'];

            //add the rule to the array
            $arrConfig[$section][] = $arrTemp;

            $cnt++;
        }

    }



    //get Firewall Rules
    $ret = trim(sshRunCommand($ssh,'show running security-policy'));
    file_put_contents($scratchFolder."/collected_firewall_rules.original",$ret);
    $outputArr = explode("\n",$ret);
    $arrRules["fw"] = paloJsonToArray($outputArr);
    file_put_contents($scratchFolder."/collected_firewall_rules.parsed",json_encode($arrRules["fw"],JSON_PRETTY_PRINT));

    $cnt = 0;
    $fwNameMap = []; //ex $fwNameMap["Cleanup"] = 12; maps name to rule number. used for adding hitcounts
    foreach ($arrRules["fw"] as $key => $item) {
        $id = $key; //key($item);
        $section = "Firewall Rules";
        //only collect a valid rule
        if(isset($item['source']) && isset($item['destination'])) {
            $arrTemp = [];
            //$arrTemp["ID"] = $id;
            $arrTemp["Disabled"] = isset($item["disabled"]) ? $item["disabled"] : "no";
            $arrTemp["Rulenum"] = $cnt;
            $arrTemp["Hits"] = 0;
            $arrTemp["Name"] = $key;
            $arrTemp["Source"] = $item['source'];
            $arrTemp["Source Zone"] = $item['from'];
            $arrTemp["Destination"] = $item['destination'];
            $arrTemp["Destination Zone"] = $item['to'];
            $arrTemp["Destination Region"] = $item['destination-region'];
            $arrTemp["Services"] = $item['application/service'];
            $arrTemp["Action"] = $item['action'];
            $arrTemp["User"] = $item['user'];
            $arrTemp["Category"] = $item['category'];
            $arrTemp["Terminal"] = $item['terminal'];

            //add the rule to the array
            $fwNameMap[$key] = $cnt;
            $arrConfig[$section][] = $arrTemp;

            $cnt++;
        }
    }


    //traffic logs for rule hit count?
    //show log traffic
    $stime = file_exists($scratchFolder."/collected_logs.out")?filemtime($scratchFolder."/collected_logs.out"):0;
    $etime = filemtime($scratchFolder."/collected_firewall_rules.original");
    $dayAgo = $etime - 86400;
    if ($stime < $dayAgo || $stime >= $etime - 60) $stime = $dayAgo; //we only want to collect a max of 24 hours of logs due to resource utilization
    $strStart = date("Y/m/d@h:m:s", $stime);
    $strEnd = date("Y/m/d@h:m:s", $etime);
    $cmd = 'show log traffic start-time equal ' . $strStart . ' end-time equal ' . $strEnd . ' csv-output equal yes';
    outputText("Collecting logs to see hitcounts: " . $cmd);
    $ret = "";
    $cnt=0;
    //$ret = sshRunCommand($ssh,$cmd,1200); //20 minute timeout
    foreach (explode("\n",$ret) as $line) {
        $a = explode(",",$line);
        if (sizeof($a) >= 10) {
            $rulename = isset($a[11]) ? $a[11] : "";
            if (isset($arrConfig["Firewall Rules"][$fwNameMap[$rulename]])) $arrConfig["Firewall Rules"][$fwNameMap[$rulename]]["Hits"]++;
            $cnt++;
        }
    }
    outputText("Done collecting logs. $cnt log entries parsed.");


    //return the array
    return $arrConfig;
}

function paloJsonToArray(&$outputArray) {
    static $level = 0; //the inception level
    $retArr = [];

    $level++;
    //outputText("starting json conversion on level $level");
    do {
        //echo "in do statement\n";
        while (count($outputArray) > 0) {
            $line = trim(array_shift($outputArray));
            //outputText("processing line: $line");
            if ($line == "}") break;

            //split the line by words
            preg_match_all('/"(?:\\\\.|[^\\\\"])*"|\S+/', $line, $words);
            $words = isset($words[0]) ? $words[0] : [""]; //each parenthesized pattern gets its own array, we only have 1 () so lets move it over
            //print_r($words);

            //get the array key and value
            $f = substr($words[0], 0, 1);
            $key = ($f == '{') ? 0 : trim(array_shift($words), '"');
            $val = trim(array_shift($words), ';"');
            if ($val == "[") {
                array_pop($words); //remove the last "];"
                $val = $words; //set the value to the array
                $words = [];
            }

            //assign
            if ($key != "") {
                $retArr[$key] = ($val == "{") ? paloJsonToArray($outputArray) : $val;
            }

        }
    } while($level <= 1 && count($outputArray) > 0);
    $level--;


    return $retArr;

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
function sshRunCommand($sshSession, $cmd, $timeout = 10){
    static $sshReadTo;
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
    outputText("> ".$cmd);
    outputText("+ Reading until prompt: $sshReadTo");
    $sshSession->write($cmd);
    $sshSession->read(); //clear out the command echo
    $sshSession->write("\n");
    $sshSession->setTimeout($timeout);
    $ret = $sshSession->read($sshReadTo); //$ssh->read('_.*@.*[$#>]_', NET_SSH2_READ_REGEX);
    //if($sshSession->isTimeout()) { outputText("+ Timeout reached."); $sshReadTo = substr($ret, strrpos($ret,"\n")+1); }
    $ret = str_replace($sshReadTo,"",$ret); //remove the prompt

    outputText("+ " . strlen($ret) . " bytes read.");
    //outputText("+ Prompt is now: $sshReadTo");
    return $ret;

}
*/