#!/usr/bin/php
<?php
/**
 * Uses multi-threading to call the specified collection script
 */


include "_functions.php";
include_once "_db_flatfiles.php";

$maxThreads = 100;
$wait = 100; // wait time in milliseconds


// Get command line arguments. optionally only from command line: if (PHP_SAPI == "cli") {}
$help = '
Usage: php runCollection.php -d "10.10.10.10"
    -a <action>: Available actions [ping, traceroute, snmp, configuration]
	-d <device>: The device name or IP
';

$o = getopt("a:d:"); // 1 : is required, 2 :: is optional
$action = array_key_exists("a",$o) ? $o["a"] : "";
$device = array_key_exists("d",$o) ? $o["d"] : "";

if ($device=="") { echo "Device is required. \n$help"; exit; }



if ($device=="all" || $device=="*") { //defaults to all
    writeLogFile("", "Running ".$action." on ALL devices."); //also echos to the console

    // Load the devices
    $arrDevices = getDevicesArray(); //json_decode(file_get_contents($pingOutFile), true);

    // Loop through each device and run traceroute
    $count = 0;
    foreach($arrDevices as $device=>$val) {
        if ($device!="") {
            // start a thread for each trace
            $worker[$device] = new CollectionThread($device, "$gFolderScanData/$device/$tracerouteFilename");
            $count++;

            // lazily just wait x milliseconds every y threads.

            if ($count >= $maxThreads) {

                usleep($wait);
            }
        }
    }
} else {	//only do the traceroute for the 1 device
    echo "Running traceroute on a single device: $device";
    doTraceroute($device, "$gFolderScanData/$device/$tracerouteFilename");

}


/**
 * Class traceThread Will start a thread to run a traceroute on a device
 */
class CollectionThread extends Threaded {
    protected $complete;
    public function __construct($device, $outputFile) {
        $this->complete = false;
        $this->device = $device;
        $this->outputFile = $outputFile;
        $this->data = "";
    }
    public function run() {
        doTraceroute($this->device, $this->outputFile);
        $this->complete = true;
    }
    public function isComplete() {
        return $this->complete;
    }
}
class CollectionPool extends Pool
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