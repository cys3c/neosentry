<?php
/**
 * This will pull certain information from managed devices based on the vendor, or some other criteria
 * Similar to the custom snmp map, I will defer collection to a subscript
 */

include "_functions.php";
include "_db_flatfiles.php"; //to load the device data
//include "iptools.php";

set_include_path('./phpseclib');
include('Net/SSH2.php');
include('Net/SCP.php');

/* Get command line arguments, for internal processing */
$help = <<<EOT
  -d: [Required] The device name or IP
  -t: [Optional] Type of device so we know what script to run to collect the data
  -c: [Optional] Get a console connectoin to test commands. 
Example: php [this file] -d "10.10.10.10" -t "Check Point"
EOT;
$o = getopt("d:t:c"); // 1 : is required, 2 :: is optional
$device = array_key_exists("d",$o) ? sanitizeString($o["d"]) : "";
$type = array_key_exists("t",$o) ? $o["t"] : "";
$console = array_key_exists("c",$o);


if ($device=="" || $device=="all") {
    //load all devices, loop through each, and run the configuration management script that matches to each one
    echo "Device is required. Use \"-d [device]\"\n";
    echo $help . "\n";
    exit;
}

// Set up some variables
$devSettings = getDeviceSettings($device);
$account = getSettingsValue("Account Profiles",$devSettings["Collectors"]["Account Profile"]);
$saveToFolder = $gFolderScanData."/".$device;
$saveToFile = "configuration.json";
$username = "fwadmin";
$password = "1<3@n0v3mb3rm00n#";
$password2 = "1<3@n0v3mb3rm00n#";




// For testing
if ($console) {
    showConsoleConnection($device,$username,$password,$saveToFolder);
    exit;
}


//create the device directory if its not
if (!is_dir($saveToFolder)) mkdir($saveToFolder, 0777, true);


// CHECK POINT SCRIPT
include "../config/scripts/checkpoint.php";
$ret = runCollector($device,$saveToFolder,$saveToFile, $username,$password, $password2);
writeLog("Configuration", $device, $ret);
// END CHECK POINT SCRIPT









function showConsoleConnection($device, $username, $password, $saveToFolder = ".") {
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