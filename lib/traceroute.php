<?php //.traceroute.php
include "_functions.php";
include "_functions_traceroute.php";

//get variables being passed
$device = (isset($_GET['device']))?cleanSqlString(trim($_GET['device'])):""; //file data to add
if ($device=="") $device = (isset($_POST['device']))?cleanSqlString(trim($_POST['device'])):"all"; //file data to add

$scanDir = "../data/scandata";
$device_ip_file = "$scanDir/iplist";
if (!file_exists($scanDir)) mkdir($scanDir, 0777, true);

//get command line arguments, for internal processing
//	-d: The device name or IP
//	Usage: php .traceroute.php -d "10.10.10.10"
$o = getopt("d:"); // 1 : is required, 2 :: is optional
if (array_key_exists("d",$o)) $device = $o["d"];
file_put_contents("traceroute.txt", "command line device = $device");

//done getting arguments


//$file_trSuccess = "./data/device_scan_data/traceroute_{$deviceIp}_success";
//$file_trSuccess_Backup = "./data/device_scan_data/traceroute_{$deviceIp}_".date("Y-m-d_H.i.s")."_success";
//$file_trFail = "./data/device_scan_data/traceroute_{$deviceIp}_fail";
//$file_trFail_Backup = "./data/device_scan_data/traceroute_{$deviceIp}_".date("Y-m-d_H.i.s")."_fail";

//$res = shell_exec("sudo fping -e -f data/device_ip_list");
//echo $res;

if ($device=="all") { //defaults to all
	//put the devices into an array for easier parsing
	$rows = explode("\n", file_get_contents($device_ip_file));

	//do the traceroute for each device in the list
	foreach($rows as $row) {
		$sIp = trim($row);
		if ($sIp!="") doTraceroute($sIp);
	}
} elseif ($device != "") {
	//only do the traceroute for the 1 device
	doTraceroute($device);
	
}

//alerts should be called here so if a device goes down and then back up, we can determine that it came back up.
//alerts_check_ping();
?>