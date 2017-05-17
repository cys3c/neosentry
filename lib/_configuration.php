<?php
/**
 * This will pull certain information from managed devices based on the vendor, or some other criteria
 * Similar to the custom snmp map, I will defer collection to a subscript
 */

//include "_functions.php";
//include "_db_flatfiles.php"; //to load the device data
//include "iptools.php";



// Set up some variables
$saveToFile = "configuration.json";
$username = "fwadmin";
$password = "1<3@n0v3mb3rm00n#";
$password2 = "1<3@n0v3mb3rm00n#";


// CHECK POINT SCRIPT

// END CHECK POINT SCRIPT


function configurationGet($device, &$deviceInfo, $overrideScript = "", $overrideProfile = "") {
    //get the username and passwords from the assigned account profile name.
    $accProfile = isset($devInfo['collectors']['configuration-profile'])?$devInfo['collectors']['configuration-profile']:"";
    $accVals = getSettingsValue(SETTING_CATEGORY_PROFILES, $accProfile, []);
    if (is_array($overrideProfile)) $accVals = $overrideProfile;

    //Then get the script we need to run
    if ($overrideScript != "") $scriptName = $overrideScript;
    else $scriptName = configurationTestRules($deviceInfo);

    if ($scriptName=="") {  //error out if no rules matched
        $txt = "No " . SETTING_CATEGORY_CONFIGMGMT . " rule matched for this device.";
        writeLogForDevice($device, ACTION_CONFIGURATION, $txt);
        return array("Error"=>$txt);
    }

    //see if the configured script exists
    $folderLib = realpath(dirname(__FILE__)."/../") . DIRECTORY_SEPARATOR . "lib";;
    $scriptPath = $folderLib . DIRECTORY_SEPARATOR . "configmgmt-scripts" . DIRECTORY_SEPARATOR . $scriptName;
    if (!file_exists($scriptPath)) {
        //error out
        writeLogFile('application.log', "The following configuration script was not found: $scriptPath");
        return array("Error"=>"The configured script $scriptName was not found.");
    }

    //load the script and update the dynamic variables
    $script = file_get_contents($scriptPath);
    $script = str_replace("%device%",$device,$script);
    $script = str_replace("%username%",isset($accVals['username'])?$accVals['username']:"",$script);
    $script = str_replace("%password%",decryptString(isset($accVals['password'])?$accVals['password']:""),$script);
    $script = str_replace("%password2%",decryptString(isset($accVals['password2'])?$accVals['password2']:""),$script);


    //save it to the specified location, run it, and save the results
    global $gFolderScanData;
    $outPath = $gFolderScanData . "/$device/tmp/";
    $outFile = $outPath . $scriptName;
    if (!file_exists($outPath)) mkdir($outPath, 0777, true);
    file_put_contents($outFile, $script);

    //run the script
    $cmd = $outFile;
    if (substr($scriptName,-4)==".php") $cmd = "php $outFile";
    if (substr($scriptName,-3)==".py") $cmd = "python $outFile";
    echo "About to run $cmd\n";
    $start = microtime(true);
    $ret = shell_exec($cmd);
    echo $ret;
    $retArr = json_decode($ret,true);
    if (!is_array($retArr)) $retArr = array("Error"=>"Script $scriptName did not return the expected JSON configuration.", "Return Data"=>$ret);
    echo "Execution completed in " . (microtime(true) - $start) . " seconds\n";


    //cleanup, securely delete it (linux) and then delete
    echo "cleaning up files\n";
    //shell_exec("shred -u \"$outFile\"");
    //unlink($outFile);


    return $retArr;
}

function configurationTestRules(&$deviceInfo, $ruleOverride = false) {
    //sample rule: "vendor contains \"check point\" and vendor contains software or name contains 'bob lobla' run checkpoint.php"
    if (is_string($ruleOverride)) $rules[] = $ruleOverride;
    else $rules = getSettingsValue(SETTING_CATEGORY_CONFIGMGMT, "rules",[]);

    foreach ($rules as $rule) {
        $rule = strtolower($rule);

        $a = explode(" run ",$rule);
        $rLogic = $a[0];
        $rScript = isset($a[1])?$a[1]:"not set";
        //print_r($a);

        if ($rScript != "") {
            //parse the logic
            $logicPassed = true; //AND comparing this so it must start true
            foreach (explode(" and ",$rLogic) as $rAnd) {
                $blockPassed = false; //OR comparing this so it must start false
                foreach (explode(" or ",$rAnd) as $rOr) {
                    //test the contains block
                    $c = explode(" contains ", $rOr);
                    $c[0] = trim($c[0],' "\'');
                    $devText = isset($deviceInfo[$c[0]])?strtolower($deviceInfo[$c[0]]):"";
                    $devSearch = isset($c[1])?trim($c[1],' "\''):"";
                    $test = ($devSearch=="")?false:is_numeric(strpos($devText,$devSearch));

                    //see if we're testing an OR block or an AND block
                    if (count($rOr)>0) $blockPassed = $blockPassed || $test; //we have an or statement
                    else $blockPassed = $test;

                    //echo "[$rOr] = $blockPassed\ndevText = [$devText]\ndevSearch = [$devSearch]";
                }
                $logicPassed = $logicPassed && $blockPassed;
            }

            //done testing logic, if we have a winner then lets return it
            if ($logicPassed) return trim($rScript);
        }

    }

    //no rules were found that matched, return nothing
    return "";

}

function configurationCompare($oldConfigArray, $newConfigArray) {
    if (!is_array($oldConfigArray) || !is_array($newConfigArray)) return "";

    //consider a better comparison
    $retRemoved = array_diff($oldConfigArray, $newConfigArray);
    if (count($retRemoved)>0) return count($retRemoved) . " elements were removed from the configuration array";
    $retAdded = array_diff($newConfigArray, $oldConfigArray);
    if (count($retAdded)>0) return count($retAdded) . " elements were added from the configuration array";

    return "";
}






function showConsoleConnection($device, $username, $password, $saveToFolder = ".") {
    //Required includes
    set_include_path(dirname(__FILE__) . '/phpseclib');
    include('Net/SSH2.php');
    include('Net/SCP.php');


    //Set up the SSH and SCP constructors
    echo "connecting to $device\n";
    $ssh = new Net_SSH2($device);
    if (!$ssh->login($username, $password)) {
        exit('Login Failed'."\n");
    }
    $scp = new Net_SCP($ssh);
    //$ssh->_initShell();
    echo $ssh->getBannerMessage();

    //$ssh->write("?");
    //$ssh->enablePTY();

    // Give console access
    echo "\nConnected to " . $device . "\n";
    echo "To get a file run command '\$fileget [remote_file] [local_file]'\n";
    echo "To get a file run command '\$fileput [remote_file] [local_file]'**\n";
    echo "** Files will be copied to " . $saveToFolder . "\n\n";

    $ret = "";
    $readTo = "$username@";
    $read = $ssh->read($readTo); //$ssh->read('_.*@.*[$#>]_', NET_SSH2_READ_REGEX);
    echo $read;
    //get the console prompt so we know when to stop reading text
    if ($ssh->isTimeout()) $readTo = substr($read, strrpos($read,"\n"));

    while($ssh->isConnected()) {
        $cmd = rtrim(readline());

        if ($cmd=="quit") break;
        if (strstr($cmd,'$fileget')) { //$fileget [remote file] [local file]
            $a = explode(" ",$cmd);
            if (!$scp->get($a[1], $saveToFolder."/".$a[2])) {
                echo "Failed Download. Syntax is \$fileget [remote_file] [local_file]\n";
                throw new Exception("Failed to get file");
            }

        } elseif (strstr($cmd,'$fileput')) { //$fileput [remote file] [local file]
            $a = explode(" ",$cmd);
            if (!$scp->put($a[1], $saveToFolder."/".$a[2], NET_SCP_LOCAL_FILE)) {
                echo "Failed Upload. Syntax is \$fileput [remote_file] [local_file]\n";
                throw new Exception("Failed to send file");
            }
        } else {

            //$ret = $ssh->exec(str_replace('$ret', $ret, $cmd));
            //echo $ret;

            $ssh->write(str_replace('$ret', $ret, $cmd)."\n");
            //$read = $ssh->read('_@.*[$#>]_', NET_SSH2_READ_REGEX);
            $read = $ssh->read($readTo);
            echo $read;
            //if we reached a timeout then we have a new console prompt, lets get it so we know where to read till
            if ($ssh->isTimeout()) $readTo = trim(substr($read, strrpos($read,"\n")));

        }



    }

    //disconnect
    $ssh->disconnect();

    echo "\nConnection Closed\n";
}