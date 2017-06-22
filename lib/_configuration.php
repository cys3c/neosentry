<?php
/**
 * This will pull certain information from managed devices based on the vendor, or some other criteria
 * Similar to the custom snmp map, I will defer collection to a subscript
 */

//include "_functions.php";
//include "_db_flatfiles.php"; //to load the device data
//include "iptools.php";



function configurationGet($device, &$deviceInfo, $overrideScript = "", $overrideProfile = "") {
    //get the username and passwords from the assigned account profile name.
    $accProfile = isset($deviceInfo['collectors']['configuration'])?$deviceInfo['collectors']['configuration']:[];
    $accProfile = isset($accProfile[1]) ? $accProfile[1] : ""; //0 stores if it should collect config, 1 stores acc profile name
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
    $script = str_replace("%includes%",getcwd(),$script);
    $script = str_replace("%device%",$device,$script);
    $script = str_replace("%username%",isset($accVals['username'])?$accVals['username']:"",$script);
    $accVals['password'] = isset($accVals['password'])?$accVals['password']:"";// decryptString($accVals['password']);
    $pw = decryptString($accVals['password']);
    $script = str_replace("%password%",$pw==''?$accVals['password']:$pw,$script);
    $accVals['password2'] = isset($accVals['password2'])?$accVals['password2']:"";// decryptString($accVals['password']);
    $pw2 = decryptString($accVals['password2']);
    $script = str_replace("%password2%",$pw2==''?$accVals['password']:$pw2,$script);


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
    $curDir = getcwd();
    chdir($outPath); //set the working directory to the scripts dir
    $ret = shell_exec($cmd);
    chdir($curDir); //change the cwd back
    echo $ret;
    $retArr = json_decode($ret,true);
    if (!is_array($retArr)) $retArr = array("Error"=>"Script $scriptName did not return the expected JSON configuration. \n\nReturn Data: \n$ret");
    echo "Execution completed in " . (microtime(true) - $start) . " seconds\n";


    //cleanup, securely delete it (linux) and then delete
    echo "cleaning up files\n";
    shell_exec("shred -u \"$outFile\"");
    if (file_exists($outFile)) unlink($outFile);


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

function increaseHitCounts(&$oldConfigArray, &$newConfigArray) {
    if (isset($oldConfigArray["Firewall Rules"])) {
        foreach ($oldConfigArray["Firewall Rules"] as $rule) {

        }
    }

}

function configurationCompare($oldConfigArray, $newConfigArray) {
    if (!is_array($oldConfigArray) || !is_array($newConfigArray)) return "";

    //consider a better comparison
    //$retRemoved = array_diff($oldConfigArray, $newConfigArray);
    //if (count($retRemoved)>0) return count($retRemoved) . " elements were removed from the configuration array";
    //$retAdded = array_diff($newConfigArray, $oldConfigArray);
    //if (count($retAdded)>0) return count($retAdded) . " elements were added from the configuration array";

    return "";
}

