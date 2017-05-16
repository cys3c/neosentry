<?php
/*
  * All this script does is run 'doTraceroute' from the _traceroute.php file
  * for each device we are request a traceroute. [Either 'all' or a single device]
 */
include "_functions.php";
include "_traceroute.php";
include_once "_db_flatfiles.php";

$maxThreads = 10;
$wait = 100; // wait time in milliseconds


// Get command line arguments, for internal processing
//	-d: The device name or IP
//	Usage: php .traceroute.php -d "10.10.10.10"
$o = getopt("d:"); // 1 : is required, 2 :: is optional
if (array_key_exists("d",$o)) $device = sanitizeString($o["d"]);



if ($device=="all") { //defaults to all
    echo "Running traceroute on ALL devices.";

	// Load the devices allowing ping which also allows traceroute
    $arrDevices = json_decode(file_get_contents($pingOutFile), true);

	// Loop through each device and run traceroute
    $count = 0;
	foreach($arrDevices as $device=>$val) {
		if ($device!="") {
		    //doTraceroute($device, "$gFolderScanData/$device/$tracerouteFilename");

            // start a thread for each trace
            $worker[$device] = new traceThread($device, "$gFolderScanData/$device/$tracerouteFilename");

            // lazily just wait x milliseconds every y threads.
            $count++;
            if ($count%$maxThreads==0) usleep($wait);
        }
	}
} elseif ($device != "") {	//only do the traceroute for the 1 device
    echo "Running traceroute on a single device: $device";
	doTraceroute($device, "$gFolderScanData/$device/$tracerouteFilename");
	
}


/**
 * Class traceThread Will start a thread to run a traceroute on a device
 */
class traceThread extends Thread {
    public function __construct($device, $outputFile) {
        $this->device=$device;
        $this->outputFile=$outputFile;
    }
    public function run() {
        doTraceroute($this->device, $this->outputFile);
    }
}