<?php
/**
 * Test script for ssh connections
 */

$help = '
Usage: ... -d <device> -u <username> -p <password>
';

// Get command line arguments. optionally only from command line: if (PHP_SAPI == "cli") {}
$o = getopt("d:u:p:"); // 1 : is required, 2 :: is optional
if(isset($o['d']) && isset($o['d']) && isset($o['d'])) {
    showConsoleConnection($o['d'], $o['u'], $o['p']);
} else {
    echo $help;
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


    $read = $ssh->read(); //$ssh->read('_.*@.*[$#>]_', NET_SSH2_READ_REGEX);
    $readTo = substr($ssh->read(), strrpos($read,"\n"));
    echo "+ Detected prompt: $readTo\n";
    echo $read;
    //get the console prompt so we know when to stop reading text
    //if ($ssh->isTimeout()) $readTo = substr($ssh->read(), strrpos($read,"\n"));

    sshRunCommand($ssh, "\n",2);

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
            /*
            $ssh->write(str_replace('$ret', $ret, $cmd)."\n");
            //$read = $ssh->read('_@.*[$#>]_', NET_SSH2_READ_REGEX);
            $read = $ssh->read($readTo);
            echo $read;
            //if we reached a timeout then we have a new console prompt, lets get it so we know where to read till
            if ($ssh->isTimeout()) $readTo = trim(substr($read, strrpos($read,"\n")));
            */
            $ret = sshRunCommand($ssh,$cmd);
            echo $ret;

        }



    }

    //disconnect
    $ssh->disconnect();

    echo "\nConnection Closed\n";
}

function sshRunCommand(&$sshSession, $cmd, $readTo = '', $timeout = 10){
    //run the command
    $cmd = rtrim($cmd,"\n") . "\n";
    $sshSession->write($cmd);

    //get the output
    $sshSession->setTimeout($timeout);
    $ret = $sshSession->read($readTo); //$ssh->read('_.*@.*[$#>]_', NET_SSH2_READ_REGEX);
    if($sshSession->isTimeout()) outputText("+ Timeout reached.");

    //clear out the command echo and command prompt
    if(substr($ret,0,strlen($cmd)) == $cmd) $ret = substr($ret,strlen($cmd)+1);
    if(substr($ret,-1 - strlen($readTo)) == $readTo) $ret = substr($ret,-1 - strlen($readTo));

    return $ret;

}
function outputText($string){
    echo $string . "\n";
    //file_put_contents(LOG_FILE,date(DATE_ATOM) . ": " . $string . "\n",FILE_APPEND);
}
