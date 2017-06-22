<?php
/**
 * Test script for ssh connections
 */

 error_reporting(E_COMPILE_ERROR);



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
    $ssh->enablePTY();

    // Give console access
    echo "\nConnected to " . $device . "\n";
    echo "To get a file run command '\$fileget [remote_file] [local_file]'\n";
    echo "To get a file run command '\$fileput [remote_file] [local_file]'**\n";
    echo "** Files will be copied to " . $saveToFolder . "\n\n";

    //get the prompt
    //$ssh->write("\n");
    //sleep(2);
    //$read = $ssh->read(); //$ssh->read('_.*@.*[$#>]_', NET_SSH2_READ_REGEX);
    //$readTo = substr($read, strrpos($read,"\n"));
    //echo "+ Detected prompt: $readTo\n";
    //echo $read;
    //get the console prompt so we know when to stop reading text
    //if ($ssh->isTimeout()) $readTo = substr($ssh->read(), strrpos($read,"\n"));

    sshRunCommand($ssh, "", 1);

    while($ssh->isConnected()) {
        echo "\n";
        $cmd = rtrim(readline("Enter Command> "));

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
            echo "'$ret'";

        }



    }

    //disconnect
    $ssh->disconnect();

    echo "\nConnection Closed\n";
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


function sshRunCommand2(&$sshSession, $cmd, $readTo = '', $timeout = 5){
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
