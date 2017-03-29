<?php //snmp.php

//MORE INFORMATION ON SNMP
//	http://kaivanov.blogspot.com/2012/02/linux-snmp-oids-for-cpumemory-and-disk.html
//	http://www.net-snmp.org/docs/mibs/


include "_functions.php";
include "_snmp.php";



//get variables being passed
$refresh = (isset($_GET['refresh']))?sanitizeString($_GET['refresh']):""; //file data to add
if ($refresh=="") $refresh = (isset($_POST['refresh']))?sanitizeString($_POST['refresh']):""; //file data to add

$device = (isset($_GET['device']))?sanitizeString($_GET['device']):""; //file data to add
if ($device=="") $device = (isset($_POST['device']))?sanitizeString($_POST['device']):"all"; //file data to add

$type = (isset($_GET['type']))?sanitizeString($_GET['type']):""; //file data to add
if ($type=="") $type = (isset($_POST['type']))?sanitizeString($_POST['type']):"all"; //file data to add
//type = sys, net, all

/* To get certain snmp information:
	$snmpConString -M $mibLocation iftable	//get network interface stats
	$snmpConString -M $mibLocation ip.ipRouteTable	//ip routing table
	$snmpConString -M $mibLocation system	//get system information. use to test connection string
	$snmpConString -M $mibLocation ucdavis	//get disk, cpu, mem stats for some devices. Cisco for example uses a different mib tree for cpu and mem.
*/

//get command line arguments, for internal processing
//	-d: The device name or IP, -t: Type, -r: Refresh
//	Usage: php snmp.php -d "10.10.10.10" -t "full" -r
$o = getopt("d:t:r:"); // 1 : is required, 2 :: is optional
if (array_key_exists("d",$o)) $device = sanitizeString($o["d"]);
if (array_key_exists("t",$o)) $type = sanitizeString($o["t"]);
if (array_key_exists("r",$o)) $refresh = true;
print_r($o);
//done getting arguments


$logFileName = "snmpinfo_".$type."_".$device;
//if (!file_exists($logFile)) shell_exec("touch $logFile");

echo "<pre>"; //all the output in this script is equivalent to a log file

if ($device=="all") {
	//its too time consuming to gather all info for all devices so exit.
	if ($type=="all") {
		echo "Gathering all snmp information for all devices should not be done due to the amount of information that could be passed. Instead only pull all information from 1 device at a time or you can pull more specific information from all devices for monitoring purposes.";
		exit;
	}
	
	//get all devices, loop through, and kick off an individual scan
	$arrSnmp = getSqlArray("SELECT ip,snmpcommunity FROM devicelist WHERE monitorsnmp='y';");
	if (!is_array($arrSnmp)) {
		echo "No devices are being monitored with snmp.";
		exit;
	}
	
	//update the community string if thats what we are doing
	if ($type=="updatecommunities") {
		foreach($arrSnmp as $row) {		
			if (substr($row['snmpcommunity'],0,12)!="snmpbulkwalk") {
				$cmd = "php snmp.php -d".$row['ip']." -t".$type." > /dev/null &";
				echo str_pad($row["ip"],15," ",STR_PAD_LEFT).": Updating community string, ran $cmd: ".trim(shell_exec($cmd))."\n";
			} else {
				echo str_pad($row["ip"],15," ",STR_PAD_LEFT).": Community string is already valid.\n";
			}
		}
		exit;
	}

	
	//run all other commands for individual devices
	foreach($arrSnmp as $row) {		
		if (substr($row['snmpcommunity'],0,12)=="snmpbulkwalk") {
			//we have an snmpwalk command, lets get the data
			//$cmd = 'wget -o "'.getcwd().'$gFolderData/logs/snmpinfo_'.$type."_".$row["ip"].'" "'.$baseUrl.'/snmp.php?device='.$row['ip'].'&type='.$type.'"';
			//$cmd = "wget -b ".escapeshellarg($baseUrl.'/snmp.php?device='.$row['ip'].'&type='.$type);
			//$cmd = "php snmp.php -d '".$row['ip']."' -t '$type' > '$gFolderData/logs/snmpinfo_".$type."_".$row["ip"]."' &";
			$cmd = "php snmp.php -d".$row['ip']." -t".$type." > /dev/null &";
			//echo "\t".trim(shell_exec($cmd))."\n";
			echo str_pad($row["ip"],15," ",STR_PAD_LEFT).": Found snmpwalk command, ran $cmd: ".trim(shell_exec($cmd))."\n";
		
		} else {
			echo str_pad($row["ip"],15," ",STR_PAD_LEFT).": No valid snmpwalk command to try.\n";
		}
	}
	exit;
}


//now we handle individual devices in their own process
//**consider a lock file if issues start occurring


//set up variables
$snmpStr = getSqlValue("SELECT snmpcommunity FROM devicelist WHERE ip='$device' limit 1;");
$snmpMonitor = getSqlValue("SELECT monitorsnmp FROM devicelist WHERE ip='$device' limit 1;");
$snmpFile = "$gFolderData/device_scan_data/snmp_".$device."_";
$snmpFileTmp = "";
$cmd = $snmpStr . " -M $mibLocation $device";
$curScanTs = getSqlValue("SELECT NOW()");
$oidMinLength = 5; //oid must be at least this many characters for the query to run. prevents pulling all snmp info.


//make sure we want to pull info
if ($snmpMonitor!="y") {
	echo "$device is not being monitored. No action will be taken.";
	exit;
}

//update the community string if that's the request
if ($type=="updatecommunities") {
	echo "Updating the community string for $device\n";
	updateSnmpCommunity($device);
	exit;
}

//make sure we have a valid snmpwalk string
if (!substr($snmpStr,0,12)=="snmpbulkwalk") {
	echo "$device does not have a valid snmpwalk string. No action will be taken. String returned: '$snmpStr'";
	exit;
}

//write the start to the log
writeLogFile($logFileName, "Started retrieval of $type from $device.");
//file_put_contents($logFile,date("F j, Y, g:i a").":\tStarted retrieval of $type from $device.\n",FILE_APPEND);
echo "Attempting to retrieve $type data from $device using snmp.\n";
switch($type) {
	case "full":
		$snmpFile .= "full"; $snmpFileTmp = $snmpFile."_tmp";
		echo date("Y-m-d H:i:s").": Retrieving full snmp data. Outputting to: $snmpFile\n";
		shell_exec($cmd . " > 2&> $snmpFile"); //output errors to the file if there's any
		echo date("Y-m-d H:i:s").": Done running command.\n";
		break;
		
	case "sys":
		$snmpFile .= "system"; $snmpFileTmp = $snmpFile."_tmp";
		echo date("Y-m-d H:i:s").": Retrieving system snmp data. Outputting to: $snmpFile\n";
		shell_exec($cmd." system > $snmpFileTmp");
		$table = getSnmpTableArr($snmpFileTmp);
		if (is_array($table)) {
			//load the content into sql
			$upTime = substr($table["0"]["sysUpTimeInstance"],strpos($table["0"]["sysUpTimeInstance"]," ")+1);
					
			$sysExist = getSqlValue("SELECT count(device) FROM `device_sys` WHERE device='$device' limit 1");
			echo "\tSYS-Exist returned $sysExist\n";
			if (intval($sysExist) > 0) { //the line exists lets update
				$ret = queryMySql("UPDATE device_sys SET ts=TIMESTAMP('$curScanTs'),sysUpTime='$upTime', 
					sysName='{$table["0"]["sysName"]}', sysLocation='{$table["0"]["sysLocation"]}', sysContact='{$table["0"]["sysContact"]}', 
					sysServices='{$table["0"]["sysServices"]}', sysDescr='{$table["0"]["sysDescr"]}' WHERE device='$device'");
				echo "\tRunning UPDATE Query on device_sys Returned: $ret\n";
			} else { //line doesn't exist lets insert
				$ret = queryMySql("INSERT INTO device_sys (ts, device, sysUpTime, sysName, sysLocation, sysContact, sysServices, sysDescr) 
					VALUES (TIMESTAMP('$curScanTs'), '$device', '$upTime', '{$table["0"]["sysName"]}', '{$table["0"]["sysLocation"]}', 
					'{$table["0"]["sysContact"]}', '{$table["0"]["sysServices"]}', '{$table["0"]["sysDescr"]}')");
				echo "\tRunning INSERT Query on device_sys Returned: $ret\n";
			}
			
		}
		echo date("Y-m-d H:i:s").": Done running command.\n";
		break;
		
	case "routing":
		$snmpFile .= "ip.ipRouteTable"; $snmpFileTmp = $snmpFile."_tmp";
		echo date("Y-m-d H:i:s").": Retrieving routing table snmp data. Outputting to: $snmpFile\n";
		//shell_exec($cmd." ip.ipRouteTable > $snmpFileTmp");
		//echo date("Y-m-d H:i:s").": Done running command.\n";
		
		//the route oid information to gather
		$jsonRoute = '{
			"ipRouteDest":"1.3.6.1.2.1.4.21.1.1","ipRouteIfIndex":"1.3.6.1.2.1.4.21.1.2","ipRouteNextHop":"1.3.6.1.2.1.4.21.1.7",
			"ipRouteType":"1.3.6.1.2.1.4.21.1.8","ipRouteProto":"1.3.6.1.2.1.4.21.1.9","ipRouteAge":"1.3.6.1.2.1.4.21.1.10",
			"ipRouteMask":"1.3.6.1.2.1.4.21.1.11","ipRouteMetric1":"1.3.6.1.2.1.4.21.1.3","ipRouteMetric2":"1.3.6.1.2.1.4.21.1.4",
			"ipRouteMetric3":"1.3.6.1.2.1.4.21.1.5","ipRouteMetric4":"1.3.6.1.2.1.4.21.1.6","ipRouteMetric5":"1.3.6.1.2.1.4.21.1.12"
		}';
		$oids = json_decode($jsonRoute,true);
		shell_exec("rm -f $snmpFileTmp");
		
		//load the previous table so we can see what changed
		$oldTable = getSnmpTableArr($snmpFile);
		
		//loop through each oid, gather the info and insert it into sql.
		foreach($oids as $key => $oid) {
			if (strlen($oid) > $oidMinLength) { //$oid = $oidMap["MEM"][$key]
				//an OID is present, get snmp info
				echo "\tCollecting '$key' Info using OID $oid\n";
				$sVal = trim(shell_exec($cmd." $oid -Ovq"));

				//Write value to the file / sql if we have data (MEM::$key = $sVal\n)
				if ($sVal != "" && $sVal != "No Such Instance currently exists at this OID") {
					//this is an array so lets break it up
					$vArr = explode("\n",$sVal);
					
					for ($a=0;$a<count($vArr);$a++) {
						$sVal = trim($vArr[$a]); $index = $a+1;
						if ($sVal != "") {
							//first write to the file
							file_put_contents($snmpFileTmp,"ROUTE::$key.$index = $sVal\n",FILE_APPEND);
						}
					}
					/*
					//run an update/insert query
					if (intval($cpuExist) > 0) { //the line exists lets update
						$ret = queryMySql("UPDATE device_cpu SET ts=TIMESTAMP('$curScanTs'),$key='$sVal' WHERE device='$device'");
						echo "\tRunning UPDATE Query on device_cpu Returned: $ret\n";
					} else { //line doesn't exist lets insert
						$ret = queryMySql("INSERT INTO device_cpu (ts, device, $key) VALUES (TIMESTAMP('$curScanTs'), '$device', '$sVal')");
						echo "\tRunning INSERT Query on device_cpu Returned: $ret\n";
					}
					/*
					//update the history table, only for certain keys
					if (is_numeric(strpos("Load1, Load5, Load15",$key))) {
						//found a key we are supposed to update the history for
						if ($createHistory) { //run insert
							$ret = queryMySql("INSERT INTO history_cpu (ts,device,$key) VALUES (TIMESTAMP('$curScanTs'),'$device','$sVal')");
							echo "\tRunning INSERT Query on history_cpu for $key='$sVal' Returned: $ret\n";
							$createHistory = false;
						} else {
							$ret = queryMySql("UPDATE history_cpu SET $key='$sVal' WHERE device='$device' AND ts=TIMESTAMP('$curScanTs')");
							echo "\tRunning UPDATE Query on history_cpu for $key='$sVal' Returned: $ret\n";
						}
					}
					*/
				}
			}
		}
		
		//compareArrays(Array1, Array2, UniqueColumn, ComparedColumns);
		//compareArrays(arr1,arr2,"ifIndex","ifIndex:
		//load the new data and compare
		if (isValidSnmpString($snmpFileTmp)) {
			$logColumns = array("ipRouteMask","ipRouteNextHop","ipRouteIfIndex","ipRouteType","ipRouteProto","ipRouteMetric1");
			compareArrays(getSnmpTableArr($snmpFile), getSnmpTableArr($snmpFileTmp), "ipRouteDest", $logColumns, $device, "route", "");
		}
		echo date("Y-m-d H:i:s").": Done running commands and updating database.\n";
		
		
		break;
	
	case "hwstats":
		$snmpFileCpu = $snmpFile."cpu"; $snmpFileMem = $snmpFile."mem"; $snmpFileHdd = $snmpFile."hdd"; $snmpFileOther = $snmpFile."other";
		$snmpFile .= "ucdavis"; $snmpFileTmp = $snmpFile."_tmp";
		//clear files
		//shell_exec("rm -f $snmpFileCpu"); shell_exec("rm -f $snmpFileMem");
		
		//load the SNMPMAP file and determine what we will be getting
		//function getSnmpMapArray($sysDescription); //sysdescription is the string to be searched to find the right map
		$oidMap = False;
		$jsonDefault = '{"SEARCHSTRING":"Linux",
		"CPU": {"Load1":".1.3.6.1.4.1.2021.10.1.3.1","Load5":".1.3.6.1.4.1.2021.10.1.3.2","Load15":".1.3.6.1.4.1.2021.10.1.3.3","LoadMax":12 },
		"MEM": {"TotalSwap":".1.3.6.1.4.1.2021.4.3.0", "AvailSwap":".1.3.6.1.4.1.2021.4.4.0", "TotalReal":".1.3.6.1.4.1.2021.4.5.0", "AvailReal":".1.3.6.1.4.1.2021.4.6.0",
				"TotalFree":".1.3.6.1.4.1.2021.4.11.0", "Shared":".1.3.6.1.4.1.2021.4.13.0", "Buffered":".1.3.6.1.4.1.2021.4.14.0", "Cached":".1.3.6.1.4.1.2021.4.15.0"},
		"HDD": {"dskIndex":".1.3.6.1.4.1.2021.9.1.1", "dskTotal":".1.3.6.1.4.1.2021.9.1.6", "dskAvail":".1.3.6.1.4.1.2021.9.1.7", "dskUsed":".1.3.6.1.4.1.2021.9.1.8",
				"dskPercent":".1.3.6.1.4.1.2021.9.1.9", "dskErrorFlag":".1.3.6.1.4.1.2021.9.1.100", "dskErrorMsg":".1.3.6.1.4.1.2021.9.1.101"},
		"OTHER": {}}';
		
		//Load the map json file
		echo "Loading SNMPMAP\n";
		$jData = trim(strstr(file_get_contents("SNMPMAP"),'{'));
		$oids = json_decode($jData,true);
		if (!is_array($oids)) echo "There's a JSON error in the SNMPMAP File.\n\n";

		//Get the description for this device
		$sysDescription = getSqlValue("SELECT sysDescr FROM device_sys WHERE device='$device' limit 1;");
		echo "sysdesc: $sysDescription\n";
		
		//Find a matching map
		if ($sysDescription != "" && is_array($oids)) {
			foreach($oids as $t) {
				if (strpos($sysDescription,$t["SEARCHSTRING"]) !== FALSE) {
					echo "SNMP MAP String Match on ".$t["SEARCHSTRING"]."\n";
					$oidMap = $t;
					break;
				}
			}
		}
		if ($oidMap == false) $oidMap = json_decode($jsonDefault,true);

		
		//make sure we have the device in the device_health table
		//queryMySql("INSERT INTO device_health (ts,device) VALUES (TIMESTAMP('$curScanTs'),'$device')");
		
		
		
		//Get CPU info, easier because we are only getting one value, not a whole table
		echo date("Y-m-d H:i:s").": Retrieving cpu snmp data. Writing directly to SQL\n";
		$createHistory = True;
		shell_exec("rm -f $snmpFileCpu");
		$cpuExist = getSqlValue("SELECT count(device) FROM `device_cpu` WHERE device='$device' limit 1");
		
		$dat["Load1"] = getArrayVal($oidMap["CPU"],"Load1");
		$dat["Load5"] = getArrayVal($oidMap["CPU"],"Load5");
		$dat["Load15"] = getArrayVal($oidMap["CPU"],"Load15");
		$dat["Load1Max"] = getArrayVal($oidMap["CPU"],"Load1Max");
		$dat["Load5Max"] = getArrayVal($oidMap["CPU"],"Load5Max");
		$dat["Load15Max"] = getArrayVal($oidMap["CPU"],"Load15Max");
		$sub = floatval(getArrayVal($oidMap["CPU"],"Subtract"));
		
		//Get the data from the device
		foreach($dat as $key => $oid) {
			if ((!is_numeric($oid)) && (strlen($oid) > $oidMinLength)) { //$oid = $oidMap["MEM"][$key]
				echo "\tCollecting CPU '$key' Info using OID $oid: ";
				$dat[$key] = trim(shell_exec($cmd." $oid -Ovq"));
				echo "Retrieved ".$dat[$key]."\n";
				if (!is_numeric($dat[$key])) $dat[$key] = "";
			} else {
				$dat[$key] = floatval($oid);
				echo "\Using CPU '$key' Value of $oid\n";
			}
			//write to the file
			file_put_contents($snmpFileCpu,"CPU::$key = ".$dat[$key]."\n",FILE_APPEND);
		}
		file_put_contents($snmpFileCpu,"CPU::SubtractVal = $sub\n",FILE_APPEND);
		
		echo "\t Subtracting $sub from values\n";
		if (floatval($dat["Load1Max"])==0) $dat["Load1Max"] = 1;
		if (is_numeric($dat["Load1"])) $dat["Load1"] = ($dat["Load1"] - $sub) / $dat["Load1Max"];
		if (floatval($dat["Load5Max"])==0) $dat["Load5Max"] = 1;
		if (is_numeric($dat["Load5"])) $dat["Load5"] = ($dat["Load5"] - $sub) / $dat["Load5Max"];
		if (floatval($dat["Load15Max"])==0) $dat["Load15Max"] = 1;
		if (is_numeric($dat["Load15"])) $dat["Load15"] = ($dat["Load15"] - $sub) / $dat["Load15Max"];
		
		//update SQL if we have data
		if (is_numeric($dat["Load1"]) || is_numeric($dat["Load5"]) || is_numeric($dat["Load15"])) {
			//Device Table
			if (intval($cpuExist) > 0) { //the line exists lets update
				$qry = "UPDATE device_cpu SET Load1='{$dat["Load1"]}',Load5='{$dat["Load5"]}',Load15='{$dat["Load15"]}' WHERE device='$device'";
				$ret = queryMySql($qry);
				echo "\tRunning $qry Returned: $ret\n";
			} else { //line doesn't exist lets insert
				$qry = "INSERT INTO device_cpu (ts,device,Load1,Load5,Load15) VALUES (TIMESTAMP('$curScanTs'),'$device','{$dat["Load1"]}','{$dat["Load5"]}','{$dat["Load15"]}')";
				$ret = queryMySql($qry);
				echo "\tRunning $qry Returned: $ret\n";
			}
			//History Table
			$qry = "INSERT INTO history_cpu (ts,device,Load1,Load5,Load15) VALUES (TIMESTAMP('$curScanTs'),'$device','{$dat["Load1"]}','{$dat["Load5"]}','{$dat["Load15"]}')";
			$ret = queryMySql($qry);
			echo "\tRunning $qry Returned: $ret\n";
		}
		//update the last-updated timestamp
		queryMySql("UPDATE device_cpu SET ts=TIMESTAMP('$curScanTs') WHERE device='$device'");
		echo date("Y-m-d H:i:s").": Done running commands and updating database.\n";

		
		//get Mem info. This is returned in Kb
		echo date("Y-m-d H:i:s").": Retrieving mem snmp data. Writing directly to SQL.\n";
		$memReal = getSqlValue("SELECT TotalReal FROM `device_mem` WHERE device='$device' limit 1");
		$memExist = getSqlValue("SELECT count(device) FROM `device_mem` WHERE device='$device' limit 1");
		$createHistory = True;
		shell_exec("rm -f $snmpFileMem");
		foreach($oidMap["MEM"] as $key => $oid) {
			if (strlen($oid) > $oidMinLength) { //$oid = $oidMap["MEM"][$key]
				//an OID is present, get snmp info
				echo "\tCollecting MEM '$key' Info using OID $oid: ";
				$sVal = trim(shell_exec($cmd." $oid -Ovq"));
				echo "Retrieved $sVal\n";
				
				//Write value to the file / sql if we have data (MEM::$key = $sVal\n)
				if ($sVal != "") {
					//first write to the file
					file_put_contents($snmpFileMem,"MEM::$key = $sVal\n",FILE_APPEND);
					$iDivide = floatval(getArrayVal($oidMap["MEM"],"DivideForKB")); if ($iDivide==0) $iDivide=1;
					$sVal = floatval($sVal) / $iDivide; //remove the Extension (typically Kb) at the end.
					
					//run an update/insert query
					if (intval($memExist) > 0) { //the line exists lets update
						$ret = queryMySql("UPDATE device_mem SET $key='$sVal'  WHERE device='$device'");
						echo "\tRunning UPDATE Query on device_mem for $key='$sVal' Returned: $ret\n";
					} else { //line doesn't exist lets insert
						$ret = queryMySql("INSERT INTO device_mem (ts, device, $key) VALUES (TIMESTAMP('$curScanTs'), '$device', $sVal)");
						echo "\tRunning INSERT Query on device_cpu for $key='$sVal' Returned: $ret\n";
					}
					
					//update the history table, only for certain keys
					if (is_numeric(strpos("AvailSwap, AvailReal, Buffered, Cached",$key))) {
						//found a key we are supposed to update the history for
						if ($createHistory) { //run insert
							$ret = queryMySql("INSERT INTO history_mem (ts,device,$key) VALUES (TIMESTAMP('$curScanTs'),'$device','$sVal')");
							echo "\tRunning INSERT Query on history_mem for $key='$sVal' Returned: $ret\n";
							$createHistory = false;
						} else {
							$ret = queryMySql("UPDATE history_mem SET $key='$sVal' WHERE device='$device' AND ts=TIMESTAMP('$curScanTs')");
							echo "\tRunning UPDATE Query on history_mem for $key='$sVal' Returned: $ret\n";
						}
					}
					
					//update the change log
					if ($key=="TotalReal") if ($memReal != $sVal) writeLog("memory",$device, "Memory 'TotalReal' value changed from '$memReal KB' to '$sVal KB'");
					
					/* health update
					$pmem = intval($v4/$v3*100);
					$memCheck = '<img src="images/s_ok.png" title="memory does not appear to be overly utilized.">';
					if ($pmem < 10) $memCheck = '<img src="images/s_warning.png" title="Memory availability is under 10%!">';
					if ($pmem < 2) $memCheck = '<img src="images/s_critical.png" title="Memory usage is at capacity! Could be a memory leak, you should probably check this out.">';
					$ret = queryMySql("UPDATE device_health SET mem_html='$memCheck',mem_val='$pmem%'	WHERE device='$device'");
					echo "\tRunning HEALTHCHECK UPDATE Query on device_health for Device Memory Returned: $ret\n";
					*/
				}
			}
		}
		//update the last-updated timestamp
		queryMySql("UPDATE device_mem SET ts=TIMESTAMP('$curScanTs') WHERE device='$device'");
		echo date("Y-m-d H:i:s").": Done running commands and updating database.\n";
		
		
		//get hdd
		echo date("Y-m-d H:i:s").": Retrieving hdd snmp data. Writing directly to SQL.\n";
		shell_exec("rm -f $snmpFileHdd");
		$valArr = array();
		foreach($oidMap["HDD"] as $key => $val) {
			if (strlen($val) > $oidMinLength) { //$val = $oidMap["HDD"][$key]
				//an OID is present, get snmp info
				echo "\tCollecting HDD '$key' Info using OID $val\n";
				$sHddRet = shell_exec($cmd." $val -Ovq");
				if(substr($sHddRet,-1)=="\n") $sHddRet=substr($sHddRet,0,-1);
				$valArr = explode("\n",$sHddRet);
				
				//loop through each value and write it to the file / sql (HDD::$key = $val\n)
				for ($a=0;$a<count($valArr);$a++) {
					$sVal = trim($valArr[$a]);
					if ($sVal=="") $sVal=" ";
					if ($sVal == "No Such Instance currently exists at this OID") $sVal = "N/A";
					$index = $a+1;
					if ($sVal != "") {
						//first write to the file
						file_put_contents($snmpFileHdd,"HDD::$key.$index = $sVal\n",FILE_APPEND);
						
						//update the device info in sql, this is only for reporting purposes
						$sType = "UPDATE";
						$ret = queryMySql("UPDATE device_hdd SET $key='$sVal' WHERE device='$device' AND dskIndex=$index");
						if (substr($ret,0,5)=="ERROR") { //run insert
							$sType = "INSERT";
							$ret = queryMySql("INSERT INTO device_hdd (ts,device,dskIndex,$key) VALUES (TIMESTAMP('$curScanTs'),'$device',$index,'$sVal')");
						}
						echo "\tRunning $sType Query on device_hdd for $key='$sVal' Returned: $ret\n"; //*/
					}
				}
			}
		}
		//update the last-updated timestamp for this device
		queryMySql("UPDATE device_hdd SET ts=TIMESTAMP('$curScanTs') WHERE device='$device'");
		echo date("Y-m-d H:i:s").": Done running commands and updating database.\n";
		
		//get other
		//$snmpFileOther = $snmpFile."other";
		echo date("Y-m-d H:i:s").": Retrieving OTHER snmp data. Writing directly to File.\n";
		shell_exec("rm -f $snmpFileOther");
		$valArr = array();
		foreach($oidMap["OTHER"] as $key => $val) {
			if (is_array($val)) {
				//Get A TABLE Array
				foreach($val as $key2 => $val) {
					if (strlen($val) > $oidMinLength) { //$val = $oidMap["HDD"][$key]
						echo "\tCollecting OTHER Array Value '$key2' Info using OID $val: ";
						$sCust = shell_exec($cmd." $val -Ovq");
						if(substr($sCust,-1)=="\n") $sCust=substr($sCust,0,-1);
						$valArr = explode("\n",$sCust);
						echo "Retrieved ".count($valArr)." Rows\n";
						
						//loop through each returned Value
						for ($a=0;$a<count($valArr);$a++) {
							$index=$a+1;
							$sVal = trim($valArr[$a]);
							if ($sVal == "No Such Instance currently exists at this OID") $sVal = "N/A";
							file_put_contents($snmpFileOther,"$key::$key2.$index = $sVal\n",FILE_APPEND);
						}
					}
				}
			} else {
				//Get just the single Value
				if (strlen($val) > $oidMinLength) { //$val = $oidMap["HDD"][$key]
					echo "\tCollecting OTHER Single Value '$key' Info using OID $val: ";
					$sCust = trim(shell_exec($cmd." $val -Ovq"));
					$sCust = str_replace("\n",", ",$sCust);
					echo "Retrieved $sCust\n";
					file_put_contents($snmpFileOther,"OTHER::$key = $sCust\n",FILE_APPEND);
				}
			}
		}
		echo date("Y-m-d H:i:s").": Done running commands and updating database.\n";
		
		
		break;
		
		
	case "net":
		$snmpFileIp = $snmpFile."iptable";
		$snmpFile .= "iftable"; $snmpFileTmp = $snmpFile."_tmp";
		echo date("Y-m-d H:i:s").": Retrieving INTERFACE snmp data. Outputting to: $snmpFile\n";
		
		//set up the json table for the data to retrieve
		$jsonNet = '{
			"ifIndex":"1.3.6.1.2.1.2.2.1.1","ifDescr":"1.3.6.1.2.1.2.2.1.2","ifType":"1.3.6.1.2.1.2.2.1.3","ifMtu":"1.3.6.1.2.1.2.2.1.4",
			"ifSpeed":"1.3.6.1.2.1.2.2.1.5","ifPhysAddress":"1.3.6.1.2.1.2.2.1.6",
			"ifAdminStatus":"1.3.6.1.2.1.2.2.1.7","ifOperStatus":"1.3.6.1.2.1.2.2.1.8","ifLastChange":"1.3.6.1.2.1.2.2.1.9",
			"ifInOctets":"1.3.6.1.2.1.2.2.1.10","ifOutOctets":"1.3.6.1.2.1.2.2.1.16","ifInUcastPkts":"1.3.6.1.2.1.2.2.1.11","ifOutUcastPkts":"1.3.6.1.2.1.2.2.1.17",
			"ifInNUcastPkts":"1.3.6.1.2.1.2.2.1.12","ifOutNUcastPkts":"1.3.6.1.2.1.2.2.1.18","ifInDiscards":"1.3.6.1.2.1.2.2.1.13","ifOutDiscards":"1.3.6.1.2.1.2.2.1.19",
			"ifInErrors":"1.3.6.1.2.1.2.2.1.14","ifOutErrors":"1.3.6.1.2.1.2.2.1.20","ifInUnknownProtos":"1.3.6.1.2.1.2.2.1.15","ifOutQLen":"1.3.6.1.2.1.2.2.1.21",
			"ifHCInOctets":"1.3.6.1.2.1.31.1.1.1.6","ifHCOutOctets":"1.3.6.1.2.1.31.1.1.1.10",
			"ifAlias":"1.3.6.1.2.1.31.1.1.1.18","ifPromiscuousMode":"1.3.6.1.2.1.31.1.1.1.16"
		
		}';
		$oids = json_decode($jsonNet,true);
		
		//get the data we need before manipulating
		$oldTable = getSqlArray("SELECT * FROM device_iftable WHERE device='$device' ORDER BY ifIndex ASC");
		$lastScanTs = getSqlValue("SELECT ifts from device_iftable WHERE device='$device' LIMIT 1;");
		$scanTsDiff = getSqlValue("SELECT TIMESTAMPDIFF(SECOND, '$lastScanTs', '$curScanTs')");
		$newCount = -1; $hasHC = false;
		
		//Get the SNMP Information
		$valArr = array(); $indexArr = array();
		foreach($oids as $key => $oid) {
			//get snmp info
			echo "\tCollecting NET '$key' Info using OID $oid\n";
			$sRet = shell_exec($cmd." $oid -Ovq"); //get JUST the values
			
			//Check if we have data
			if ($sRet != "" && trim($sRet) != "No Such Instance currently exists at this OID") {
				$valArr = explode("\n",$sRet);
				array_pop($valArr); //there's an extra \n returned
				
				if ($key=="ifIndex") {
					$newCount = count($valArr); //see how many interfaces we have
					$indexArr = $valArr; //we'll need this as a reference
				}
				
				//loop through each value and write it to the file / sql (NET::$key.$index = $val\n)
				for ($a=0;$a<count($valArr);$a++) {
					$sVal = trim($valArr[$a]); $index = $indexArr[$a];
					//first write to the file
					file_put_contents($snmpFileTmp,"NET::$key.$index = $sVal\n",FILE_APPEND);
					
					//set up the sql queries, run and log
					$qry = "UPDATE device_iftable SET $key='$sVal' WHERE device='$device' AND ifIndex='$index';";
					if ($key=="ifIndex") $qry = "INSERT INTO device_iftable (device,ifIndex) VALUES ('$device',$index)"; //The first array item it gets is the ifIndex
					$ret = queryMySql($qry);
					echo "\tRunning '$qry' Returned: $ret\n";
					
					//create the history entry for each interface
					if ($key=="ifIndex") {
						$ret = queryMySql("INSERT INTO history_if (ts,device,ifIndex) VALUES (TIMESTAMP('$curScanTs'),'$device',$index)");
						echo "\tCreating the History Table row for Interface Index $index Returned: $ret\n";
					}
					
					//update the DIFF tuple if we need to & the HISTORY table
					if (is_array($oldTable) && array_key_exists($key."Diff",$oldTable[$a])) {
						//since ifIndex isn't linear, loop through to find the right val
						$tOld = 0;
						for ($b=0;$b<count($oldTable);$b++){
							if (getArrayVal($oldTable[$a],"ifIndex")==$index) {
								$tOld = floatval(getArrayVal($oldTable[$a],$key));
								break;
							}
						}
						$tNew = floatval($sVal);// $tOld = floatval(getArrayVal($oldTable[$a],$key));
						if ($tOld==0) $tOld = $tNew;
						$tDiff = $tNew == 0 ? 0 : $tNew - $tOld;
						echo "---------- tNew = $tNew :: tOld = $tOld :: tDiff = $tDiff ----------\n";
						
						//see if the counter wrapped, and adjust
						if (substr($key,0,4)=="ifHC") {
							//we have a 64-bit counter
							if ($tDiff < 0 ) $tDiff += 18446744073709551615;
							$hasHC = true;
						} else {
							//we have a 32-bit counter: @ 5minute captures we can accurately calculate a max of 100mbps. @1min = 546mbps
							//	snmp wraps around after 2^32. to calc bw: (Diff * 8 * 100) / (Time * ifSpeed)
							if ($tDiff < 0 ) $tDiff += 4294967295;
						}
						
						//update the sql iftable
						$qry = "UPDATE device_iftable SET $key"."Diff=CEIL($tDiff / $scanTsDiff) WHERE device='$device' AND ifIndex='$index';";
						$ret = queryMySql($qry);
						echo "\tRunning '$qry' Returned: $ret\n";
						
						//update the history table
						$qry = "UPDATE history_if SET $key"."Diff=CEIL($tDiff / $scanTsDiff) WHERE device='$device' AND ifIndex='$index' AND ts=TIMESTAMP('$curScanTs')"; 
						$ret = queryMySql($qry);
						echo "\tRunning '$qry' Returned: $ret\n";
						
					}
				}
			}
		}
		
		//get the IP Table
		echo date("Y-m-d H:i:s").": Getting Interface IP addresses and Netmasks and updating the database.\n";
		$tIp = array();
		$aIP = explode("\n",shell_exec($cmd." .1.3.6.1.2.1.4.20.1.1 -Ovq"));
		$aIndex = explode("\n",shell_exec($cmd." .1.3.6.1.2.1.4.20.1.2 -Ovq"));
		$aMask = explode("\n",shell_exec($cmd." .1.3.6.1.2.1.4.20.1.3 -Ovq"));
		for ($a=0; $a < count($aIndex); $a++) {
			$tIndex = intval($aIndex[$a]); $tMask = $aMask[$a];
			$tIp[$tIndex] = getArrayVal($tIp,$tIndex)." ".$aIP[$a];
			if ($tIndex > 0 ) {
				file_put_contents($snmpFileTmp,"NET::ipAddr.$tIndex = ".$aIP[$a]."\n",FILE_APPEND);
				$ret = queryMySql("UPDATE device_iftable SET ipAddr='".trim($tIp[$tIndex])."', ipNetMask='$tMask' WHERE device='$device' AND ifIndex='$tIndex';");
				echo "\tRunning UPDATE Query on device_iftable for ipAddr='".trim($tIp[$tIndex])."', ipNetMask='$tMask', ifIndex='$tIndex' Returned: $ret\n";
			}
		}
		
		//exit if no interface index info was received
		if (count($indexArr)<=0) {
			echo date("Y-m-d H:i:s").": No Interface Index information was received, exiting.";
			break;
		}
		
		
		//update the device with the current throughput in/out
		if($hasHC)
			queryMySql("UPDATE devicelist SET ifthroughputin=(SELECT SUM(ifHCInOctetsDiff) from device_iftable where device='$device'), ifthroughputout=(SELECT SUM(ifHCOutOctetsDiff) from device_iftable where device='$device') where ip='$device'");
		else
			queryMySql("UPDATE devicelist SET ifthroughputin=(SELECT SUM(ifInOctetsDiff) from device_iftable where device='$device'), ifthroughputout=(SELECT SUM(ifOutOctetsDiff) from device_iftable where device='$device') where ip='$device'");
		//update the ifts
		queryMySql("UPDATE device_iftable SET ifts=TIMESTAMP('$curScanTs') WHERE device='$device';");
		//remove interfaces
		//if ($newCount>0) queryMySql("DELETE FROM device_iftable WHERE device='$device' AND ifIndex > $newCount;");
		
		
		//Get Interface Changes / Removals / Additions
		$removedEntries = $addedEntries = $changedEntries = "";
		echo date("Y-m-d H:i:s").": Logging any changes. Old Table had ".count($oldTable)." Interfaces. New table has ".count($indexArr)." Interfaces.\n";
		
		//see if any interfaces were REMOVED
		$oldFlatIndex = "";
		$newFlatIndex = "(".implode($indexArr,")(").")";
		for ($a=0;$a<count($oldTable);$a++) {
			$oldIndx = getArrayVal($oldTable[$a],"ifIndex");
			$oldFlatIndex .= "(".$oldIndx.")";
			
			if (strpos($newFlatIndex,"(".$oldIndx.")")===false) {
				//this index was REMOVED
				queryMySql("DELETE FROM device_iftable WHERE device='$device' AND ifIndex = $oldIndx;");
				$removedEntries .= "<tr><td>".getArrayVal($oldTable[$a],"ifIndex")."</td><td>".getArrayVal($oldTable[$a],"ifDescr")."</td><td>".getArrayVal($oldTable[$a],"ifPhysAddress")."</td><td>".getArrayVal($oldTable[$a],"ipAddr")."</td></tr>";
				echo "Interface Index ".$oldTable[$a]["ifIndex"]." was REMOVED\n";
			}
		}
		if ($removedEntries != "") $removedEntries = "<table><tr><td>ifIndex</th><td>ifDescr</th><td>ifPhysAddress</th><td>ipAddr</th></tr>$removedEntries</table>";
		
		//see if any interfaces were ADDED
		$newTable = getSqlArray("SELECT * FROM device_iftable WHERE device='$device' ORDER BY ifIndex ASC"); //$oldTable is grabbed before the change
		for ($a=0;$a<count($indexArr);$a++) {
			$newIndx = $indexArr[$a];
			
			if (strpos($oldFlatIndex,"(".$newIndx.")")===false) {
				//this index was ADDED
				$addedEntries .= "<tr><td>".getArrayVal($newTable[$a],"ifIndex")."</td><td>".getArrayVal($newTable[$a],"ifDescr")."</td><td>".getArrayVal($newTable[$a],"ifPhysAddress")."</td><td>".getArrayVal($newTable[$a],"ipAddr")."</td></tr>";
				echo "Interface Index ".$newTable[$a]["ifIndex"]." was ADDED\n";
			}
		}
		if ($addedEntries != "") $addedEntries = "<table><tr><td>ifIndex</th><td>ifDescr</th><td>ifPhysAddress</th><td>ipAddr</th></tr>$addedEntries</table>";
		
		
		//see if any interfaces CHANGED
		$monitorColumns = array('ifAdminStatus','ifOperStatus','ifMtu','ifSpeed','ifPhysAddress','ipAddr');
		for ($a=0; $a < count($newTable); $a++) {
			$row = ""; $hasChanged = false;
			foreach ($monitorColumns as $mc) {
				$oldVal = getArrayVal($oldTable[$a],$mc); $newVal = getArrayVal($newTable[$a],$mc);
				if ($oldVal != "" && $newVal != $oldVal) {
					$hasChanged = true;
					$row .= "<td>'$oldVal' -> '<b>$newVal</b>'</td>";
					echo "Value CHANGED from '$oldVal' to '$newVal' on Interface Index ".$newTable[$a]["ifIndex"]."\n";
				} else $row .= "<td></td>";
			}
			if ($hasChanged) $changedEntries .= "<tr><td>".$newTable[$a]["ifIndex"]."</td>$row</tr>";
		}
		if ($changedEntries != "") $changedEntries = "<table><tr><td>ifIndex</th><td>ifAdminStatus</th><td>ifOperStatus</th><td>ifMtu</th><td>ifSpeed</th><td>ifPhysAddress</th><td>ipAddr</th></tr>$changedEntries</table>";
		
		
		//write to the log file
		if ($removedEntries !="") writeLog("interface",$device,"The following interfaces have been REMOVED:<br><br>".trim($removedEntries));
		if ($addedEntries !="") writeLog("interface",$device,"The following interfaces have been ADDED:<br><br>".trim($addedEntries));
		if ($changedEntries != "") writeLog("interface",$device, "The following interfaces have CHANGED:<br><br>".trim($changedEntries));
		
		echo date("Y-m-d H:i:s").": Done running commands and updating database.\n";
		
		
		
		
		
		
		
		
		
		
		
		
		
		//=========================THE OLD WAY=============================== 
		/*
		//run the command
		shell_exec($cmd." iftable > $snmpFileTmp");
		echo date("Y-m-d H:i:s").": Done running commands.\n";
		
		//check to see if we have valid data
		if (!isValidSnmpString($snmpFileTmp)) break;
		
		//cleanup the device table in case it became corrupt. there should never be an ifIndex 0 with snmp
		queryMysql("DELETE FROM device_iftable WHERE ifIndex=0;");
		
		//now update 
		$tnewTable = getSnmpTableArr($snmpFileTmp);
		$newTable = ""; //this will hold the realigned array
		$oldTable = getSqlArray("SELECT * FROM device_iftable WHERE device='$device' ORDER BY ifIndex ASC");
		$lastScanTs = getSqlValue("SELECT ifts from device_iftable WHERE device='$device' LIMIT 1;");
		$scanTsDiff = getSqlValue("SELECT TIMESTAMPDIFF(SECOND, '$lastScanTs', '$curScanTs')");
		//$curScanTs = getSqlValue("SELECT NOW()");
		//$qryUpdateDevTS = "UPDATE device_iftable SET ifts=NOW() WHERE device='$device'";
		$removedEntries = $addedEntries = $changedEntries = "";
		
		/* TO test the array and see what we have
		print_r($oldTable);
		echo "<br><br><br>";
		print_r($tnewTable);
		echo "<br><br><br>";
		//exit;//
		
		
		//realign the array so key 0 is the first element, matches with sql
		//Also add the ip address to the table
		shell_exec($cmd." .1.3.6.1.2.1.4.20.1 > $snmpFileIp");
		$ipTable = getSnmpTableArr($snmpFileIp);
		for ($a=0; $a < count($tnewTable); $a++) {
			$newTable[$a] = $tnewTable[$a+1];
			$newTable[$a]["ipAddr"] = "";
			$newTable[$a]["ipNetMask"] = "";
			foreach($ipTable as $ipRow) {
				$tIndex = getArrayVal($ipRow,"ipAdEntIfIndex"); $tIp = getArrayVal($ipRow,"ipAdEntAddr"); $tMask = getArrayVal($ipRow,"ipAdEntNetMask");
				if ($newTable[$a]["ifIndex"] == $tIndex) {
					$newTable[$a]["ipAddr"] = $tIp;
					$newTable[$a]["ipNetMask"] = $tMask;
					break;
				}
			}
		}
		
		
		//loop through each row, aka the interface, and update each interface accordingly
		$len = count($newTable) > count($oldTable) ? count($newTable) : count($oldTable);
		for ($i=0; $i <= $len; $i++) {
			//create the insert and update queries
			$qryInsertCol = $qryInsertVal = $qryUpdate = $qryUpdateIfDiff = $qryInsertHistoryCol = $qryInsertHistoryVal = "";
			if (array_key_exists($i,$newTable)) {
				foreach ($newTable[$i] as $colName => $colVal) {
					$cn = trim(cleanSqlString($colName)); $cv = trim(cleanSqlString($colVal));
					if ($cn != "ifSpecific") {
						$qryInsertCol .= ", $cn";
						$qryInsertVal .= ", '$cv'";
						$qryUpdate .= ", $cn='$cv'";
						
						//the difference portion of the table. check if there's a diff in the sql array for the value
						if (is_array($oldTable) && array_key_exists($cn."Diff",$oldTable[$i])) {
							$tNew = floatval(getArrayVal($newTable[$i],$cn)); $tOld = floatval(getArrayVal($oldTable[$i],$cn));
							if ($tOld==0) $tOld = $tNew;
							$tDiff = $tNew == 0 ? 0 : $tNew - $tOld;
							//If the counter is 32bit. @ 5minute captures we can accurately calculate a max of 100mbps. @1min = 546mbps
							//	if 64 bit counters are used then i need to change this number. ***** get counter from new scan.
							if ($tDiff < 0 ) $tDiff += 4294967295; //snmp wraps around after 2^32. to calc bw: (Diff * 8 * 100) / (Time * ifSpeed)
							
							//$qryUpdateIfDiff .= ", $cn"."Diff=$tDiff ";
							$qryUpdateIfDiff .= ", $cn"."Diff=CEIL($tDiff / $scanTsDiff)"; //ifts gets updated later. this sets the throughput per second packets / time_difference = rate per second
							
							//for inserting into the history table
							$qryInsertHistoryCol .= ", $cn"."Diff";
							$qryInsertHistoryVal .= ", CEIL($tDiff / $scanTsDiff)";
						}
					}
				}
				
				//used a few times later
				$ifIndex = getArrayVal($newTable[$i],"ifIndex");
				
				//add device to the beginning and make it into an sql statement
				$qryInsertCol = "INSERT INTO device_iftable (device".$qryInsertCol.") ";
				$qryInsertVal = " VALUES ('$device'".$qryInsertVal.");";
				$qryUpdate = "UPDATE device_iftable SET ".substr($qryUpdate,2)
					." WHERE device='$device' AND ifIndex=".$ifIndex.";";
				
				//create the throughput change update query
				if ($qryUpdateIfDiff != "") 
					$qryUpdateIfDiff = "UPDATE device_iftable SET ".substr($qryUpdateIfDiff,2)
					.",ifts=TIMESTAMP('$curScanTs') WHERE device='$device' AND ifIndex=".$ifIndex.";";
				//update the history table
				$qryInsertHistoryCol = "INSERT INTO history_if (ts, device, ifIndex".$qryInsertHistoryCol.") ";
				$qryInsertHistoryVal = " VALUES (TIMESTAMP('$curScanTs'), '$device', '".$ifIndex."'".$qryInsertHistoryVal.");";
				
			}
			
			//run the needed query
			if (array_key_exists($i,$newTable) && array_key_exists($i,$oldTable)) {
				//both exists, lets update
				echo "Device Stat UPDATE QUERY: $qryUpdate\n";
				$ret = queryMySql($qryUpdate);
				echo " ==== Returned $ret\n";
				echo "Device Diff UPDATE QUERY: $qryUpdateIfDiff\n";
				$ret = queryMySql($qryUpdateIfDiff);
				echo " ==== Returned $ret\n";
				
				//also update the history table with the throughput difference
				echo "History INSERT QUERY: $qryInsertHistoryCol $qryInsertHistoryVal\n";
				$ret = queryMySql($qryInsertHistoryCol.$qryInsertHistoryVal);
				echo " ==== Returned $ret\n";
				
				//if any important settings were changed, lets log it.
				//for iftable lets monitor, ifMtu, ifPhysAddress, ifAdminStatus, ifOperStatus
				$monitorColumns = array('ifMtu','ifPhysAddress','ifAdminStatus','ifOperStatus');
				foreach ($monitorColumns as $mc) {
					$oldVal = getArrayVal($oldTable[$i],$mc); $newVal = getArrayVal($newTable[$i],$mc);
					if ($oldVal != "" && $newVal != $oldVal)
						$changedEntries .= "Interface value '$mc' changed from '$oldVal' to '$newVal' on: ifIndex=".
						$newTable[$i]["ifIndex"].", ifDescr=".$newTable[$i]["ifDescr"]."\n";
				}	
				
			} elseif (!array_key_exists($i,$newTable) && array_key_exists($i,$oldTable)) {
				//the entry was removed, lets take it out of the sql table
				if (trim($oldTable[$i]["ifIndex"]) != "") {
					$qryDelete = "DELETE FROM device_iftable WHERE device='$device' AND ifIndex=".trim($oldTable[$i]["ifIndex"]);
					echo "Running DELETE QUERY: $qryDelete\n";
					$ret = queryMySql($qryDelete);
					echo " ==== Returned $ret\n";
					$removedEntries .= "ifIndex=".$oldTable[$i]["ifIndex"].", ifDescr=".$oldTable[$i]["ifDescr"].", ifPhysAddress=".$oldTable[$i]["ifPhysAddress"].", ipAddr=".getArrayVal($oldTable[$i],"ipAddr")." \n";
				}
			} elseif (array_key_exists($i,$newTable)) {
				//the entry is being added to sql
				echo "Device INSERT QUERY: $qryInsertCol $qryInsertVal\n";
				$ret = queryMySql($qryInsertCol.$qryInsertVal);
				echo " ==== Returned $ret\n";
				$addedEntries .= "ifIndex=".$newTable[$i]["ifIndex"].", ifDescr=".$newTable[$i]["ifDescr"].", ifPhysAddress=".$newTable[$i]["ifPhysAddress"].", ipAddr=".getArrayVal($newTable[$i],"ipAddr")." \n";
			}
			echo "\n";
		}
		
		
		//lets log the removed and added
		if ($removedEntries !="") writeLog("interface",$device,"The following interfaces have been REMOVED:<br><br>".trim($removedEntries));
		if ($addedEntries !="") writeLog("interface",$device,"The following interfaces have been ADDED:<br><br>".trim($addedEntries));
		if ($changedEntries != "") writeLog("interface",$device, trim($changedEntries));
						
		*/
		
		break;
		
	default:
		echo "No Valid Type Defined\n";
		exit;

}

//write the end to the log
writeLogFile($logFileName, "Retrieval complete.");
//file_put_contents($logFile,date("F j, Y, g:i a").":\tRetrieval complete.\n",FILE_APPEND);

//check to see if the file we downloaded contains snmp info
//only check these because they are the only ones with a valid file.
$cntFile = "$gFolderData/device_scan_data/snmpinfo_failures_".$device;
if ($type!="hwstats" && $type!="testing") {
	if (!isValidSnmpString($snmpFileTmp)) {
		//lets wait x failures before making the change
		$count = file_exists($cntFile)?intval(file_get_contents($cntFile)):0;
		$count++;
		file_put_contents($cntFile,$count);
		echo "SNMP Failure Count: $count\n";
		if ($count>5) {
			//command failed x times, lets update the community string and enter a changelog
			echo "$cmd FAILED on $device. Updating DB to reflect that the snmp string has not worked $count times.\n";
			queryMysql("UPDATE devicelist SET snmpcommunity='Community String no longer works' WHERE ip='$device'");
			writeLog("SNMP",$device,"Device SNMP Retrieval changed state to DOWN.");
		}
		//remove the temp file
		shell_exec("rm -f $snmpFileTmp");
	} else {
		//success, move the tmp file with the data to the main file
		rename($snmpFileTmp,$snmpFile);
		echo "Successfully retrieved snmp data from $device.\n";
		if (file_exists($cntFile)) {
			writeLog("SNMP",$device,"Device SNMP Retrieval changed state to UP.");
			shell_exec("rm -f $cntFile");
		}
	}
}

echo "</pre>";
exit;



?>