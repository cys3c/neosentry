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

    /*
    $ret = "";
    $readTo = "$username@";
    $read = $ssh->read($readTo); //$ssh->read('_.*@.*[$#>]_', NET_SSH2_READ_REGEX);
    echo $read;
    //get the console prompt so we know when to stop reading text
    if ($ssh->isTimeout()) $readTo = substr($read, strrpos($read,"\n"));
    */
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
            sshRunCommand($ssh,$cmd);

        }



    }

    //disconnect
    $ssh->disconnect();

    echo "\nConnection Closed\n";
}

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
    if($sshSession->isTimeout()) { outputText("+ Timeout reached."); /* $sshReadTo = substr($ret, strrpos($ret,"\n")+1);*/ }
    $ret = str_replace($sshReadTo,"",$ret); //remove the prompt

    outputText("+ " . strlen($ret) . " bytes read.");
    //outputText("+ Prompt is now: $sshReadTo");
    return $ret;

}
function outputText($string){
    echo $string . "\n";
    //file_put_contents(LOG_FILE,date(DATE_ATOM) . ": " . $string . "\n",FILE_APPEND);
}
