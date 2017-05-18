#!/usr/bin/php
<?php
/**
 * Uses multi-threading to call the specified collection script
 *
 * Example:
 * runCollection.php -a configuration -d localhost --config-script "checkpoint.php" --config-profile "{\"username\": \"fwadmin\", \"password\": \"testing\" }"
 *
 */

include "_functions.php";

// some variables
$logFile = "collector.log";
$maxThreads = 100;
$actions = ACTION_PING . " " . ACTION_TRACEROUTE . " " . ACTION_SNMP . " " . ACTION_CONFIGURATION;
$help = '
Usage: php runCollection.php -d "10.10.10.10"
    -a <action>: Available actions ['.$actions.']
	-d <device>: The device name or IP
';

// Get command line arguments. optionally only from command line: if (PHP_SAPI == "cli") {}
$o = getopt("a:d:",["snmp-type:", "config-script:","config-profile:"]); // 1 : is required, 2 :: is optional
$action = array_key_exists("a",$o) ? trim($o["a"]) : "";
$device = array_key_exists("d",$o) ? trim($o["d"]) : "";
if ($device=="") { echo "Device is required. \n$help"; exit; }
//clean up extra characters that may be used to separate devices
$device = str_replace(["\t","\r","\n","|",";",","]," ",$device);
while(strpos($device,"  ") > 0) { $device = str_replace("  "," ",$device); }


//include the necessary action library
if (file_exists("_".$action.".php")) include "_".$action.".php";
else { echo "Action '$action' not supported.$help"; exit;}



// LOOP THROUGH ALL DEVICES, THEN EXIT

if ($device=="all" || $device=="*" || strpos($device, " ") > 0) {
    writeLogFile($logFile, "Running ".$action." on ALL devices."); //also echos to the console

    // Load the devices
    if (strpos($device, " ") > 0) {
        //multiple devices were specified in the command line
        $arrDevices = explode(" ", $device);
    } else {
        $arrDevices = getDevicesArray(); //json_decode(file_get_contents($pingOutFile), true);
    }

    $processArr = []; //return array

    // Loop through each device
    foreach($arrDevices as $device=>$deviceInfo) {
        //An alternate way of threading, just recall this script in the background and define a device. Only works on linux
        $processArr[$device] = new BackgroundProcess('php ' . __FILE__ . " -a $action -d $device");
        $processArr[$device]->run();

        //only allow the number of processes defined by maxThreads
        while (count($processArr) > $maxThreads) {
            foreach ($processArr as $key => $val) if (!$val->isRunning()) unset($processArr[$key]);
        }
    }

    //stay resident until all processes finish
    while (count($processArr) > 0) {
        foreach ($processArr as $key => $val) if (!$val->isRunning()) unset($processArr[$key]);
    }

    //done with all devices, write to the log and exit
    writeLogFile("", "Completed running ".$action." on ALL devices.");
    exit;

}



// PERFORM THE ACTION FOR THE SINGLE DEVICE

// Check configuration to see if we should collect the info
$devInfo = getDeviceSettings($device);
if (is_array($devInfo) && array_key_exists("collectors",$devInfo) && array_key_exists($action,$devInfo["collectors"])) {
    if ($devInfo["collectors"][$action] == "no" || $devInfo["collectors"][$action] == false ) {
        //we shouldn't collect this information
        echo "Device settings say we shouldn't collect $action on $device, so we won't.\n";
        exit;
    }
}


include_once "_db_flatfiles.php";
writeLogFile($logFile, "Collecting ".$action." on single device: $device"); //also echos to the console
processDevice($device, $action, $devInfo, $o);
exit;


function processDevice($device, $action, $devInfo = [], $opts = []) {
    switch ($action) {
        case ACTION_PING:
            $oldPing = getDeviceData($device,$action);
            $newPing = pingSingleDevice($device);
            updateDeviceData($device,$action,$newPing);
            updateDeviceHistory($device,$action,$newPing['ms']);

            //Now compare
            $pingDiff = pingCompare($oldPing, $newPing);
            if ($pingDiff != "" ) {
                writeLogForDevice($device, $action, $pingDiff);
                //device state changed, lets also collect an updated traceroute
                processDevice($device, ACTION_TRACEROUTE);
            }

            break;

        case ACTION_TRACEROUTE:
            $oldTrace = getDeviceData($device,$action);
            $newTrace = tracerouteRun($device);
            updateDeviceData($device, $action, $newTrace);

            //Now compare
            $traceDiff = tracerouteCompare($oldTrace, $newTrace); //returns a string describing the difference or an empty string
            if ($traceDiff != "" ) {
                $changeID = updateDeviceHistory($device, $action, $newTrace);
                writeLogForDevice($device, $action, $traceDiff, $changeID);
            }

            break;

        case ACTION_SNMP:
            echo "snmpbulkget $device";
            break;

        case ACTION_CONFIGURATION:
            //Use hidden options if those are set, mainly just for testing
            $ovScript = isset($opts["config-script"]) ? $opts["config-script"] : "";
            $ovProf = isset($opts["config-profile"]) ? json_decode($opts["config-profile"], true) : $opts["config-profile"];

            //Collect the previous and current configs
            $oldConfig = getDeviceData($device,$action);
            $newConfig = configurationGet($device, $devInfo, $ovScript, $ovProf);
            updateDeviceData($device, $action, $newConfig);

            //Now compare
            $confDiff = configurationCompare($oldConfig, $newConfig); //returns a string describing the difference or an empty string
            if ($confDiff != "" ) {
                $changeID = updateDeviceHistory($device, $action, $newConfig);
                writeLogForDevice($device, $action, $confDiff, $changeID);
            }

            break;
    }

}



class BackgroundProcess
{
    private $command;
    private $pid;

    public function __construct($command)
    {
        $this->command = $command;
    }

    public function run($outputFile = '/dev/null')
    {
        $this->pid = shell_exec(sprintf(
            '%s > %s 2>&amp;1 &amp; echo $!',
            $this->command,
            $outputFile
        ));
    }

    public function isRunning()
    {
        try {
            $result = shell_exec(sprintf('ps %d', $this->pid));
            if(count(preg_split("/\n/", $result)) > 2) {
                return true;
            }
        } catch(Exception $e) {}

        return false;
    }

    public function getPid()
    {
        return $this->pid;
    }
}




/**
 * Class traceThread Will start a thread to run a traceroute on a device
 */
/*
class collectionThread extends Thread {
    public $complete;
    public function __construct($device, $deviceInfo, $deviceFolder) {
        $this->complete = false;
        $this->device = $device;
        $this->deviceInfo = $deviceInfo;
        $this->deviceFolder = $deviceFolder;
        $this->data = "";
    }
    public function run() {
        //doTraceroute($this->device, $this->deviceFolder);
        $this->complete = true;
    }
    public function isComplete() {
        return $this->complete;
    }
}
class collectionPool extends Pool
{
    public $data = array();
    public function process()
    {
        // Run this loop as long as we have
        // jobs in the pool
        while (count($this->work)) {
            $this->collect(function (CollectionThread $task) {
                // If a task was marked as done
                // collect its results
                if ($task->isComplete()) {
                    $tmpObj = new stdclass();
                    $tmpObj->complete = $task->complete;
                    //this is how you get your completed data back out [accessed by $pool->process()]
                    $this->data[] = $tmpObj;
                }
                return $task->isComplete();
            });
        }
        // All jobs are done
        // we can shutdown the pool
        $this->shutdown();
        return $this->data;
    }
}

//to use thread
$worker[$device] = new collectionThread($device, $deviceInfo,"$gFolderScanData/$device");
//*/