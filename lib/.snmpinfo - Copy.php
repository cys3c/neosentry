<?php //.snmpinfo.php

//MORE INFORMATION ON SNMP
//	http://kaivanov.blogspot.com/2012/02/linux-snmp-oids-for-cpumemory-and-disk.html
//	http://www.net-snmp.org/docs/mibs/


include "_functions.php";
include "_functions_snmp.php";


//get variables being passed
$refresh = (isset($_GET['refresh']))?cleanSqlString(trim($_GET['refresh'])):""; //file data to add
if ($refresh=="") $refresh = (isset($_POST['refresh']))?cleanSqlString(trim($_POST['refresh'])):""; //file data to add

$device = (isset($_GET['device']))?cleanSqlString(trim($_GET['device'])):""; //file data to add
if ($device=="") $device = (isset($_POST['device']))?cleanSqlString(trim($_POST['device'])):"all"; //file data to add

$type = (isset($_GET['type']))?cleanSqlString(trim($_GET['type'])):""; //file data to add
if ($type=="") $type = (isset($_POST['type']))?cleanSqlString(trim($_POST['type'])):"all"; //file data to add
//type = sys, net, all

/* To get certain snmp information:
	$snmpConString -M $mibLocation iftable	//get network interface stats
	$snmpConString -M $mibLocation ip.ipRouteTable	//ip routing table
	$snmpConString -M $mibLocation system	//get system information. use to test connection string
	$snmpConString -M $mibLocation ucdavis	//get disk, cpu, mem stats for some devices. Cisco for example uses a different mib tree for cpu and mem.
*/

if ($type=="updatecommunities") {
	updateSnmpCommunities();
	exit;
} elseif ($type=="hwstats") {
	$type = "ucdavis";
} elseif ($type=="net") {
	$type = "iftable";
} elseif ($type=="sys") {
	$type = "system";
} elseif ($type=="routing") {
	$type = "ip.ipRouteTable";
} elseif ($type=="all") {
	$type = "all";
} else {
	echo "No Valid Type Defined";
	exit;
}

if ($device=="all" && $type=="all") {
	echo "Gathering all snmp information for all devices should not be done due to the amount of information that could be passed. Instead only pull all information from 1 device at a time or you can pull more specific information from all devices for monitoring purposes.";
	exit;
}

echo "<html><body><pre>";

//cleanup the device table in case it became corrupt. there should never be an ifIndex 0 with snmp
queryMysql("DELETE FROM device_iftable WHERE ifIndex=0;"); //some cleanup


if ($device=="all") {
	//$qry = "SELECT ip,snmpcommunity FROM devicelist WHERE monitorsnmp='y' AND snmpcommunity LIKE 'snmpwalk%';";
	$qry = "SELECT ip,snmpcommunity FROM devicelist WHERE monitorsnmp='y';";
	$arrSnmp = getSqlArray($qry);
	
	//loop through each device and get the snmp information requested
	foreach($arrSnmp as $row) {
		if (substr($row['snmpcommunity'],0,8)=="snmpwalk") {
			//$snmpFile = "./data/device_scan_data/snmp_".$row["ip"]."_$type";
			$snmpFileBase = "./data/device_scan_data/snmp_".$row["ip"]."_";
			$cmd = trim($row['snmpcommunity']) . " -M $mibLocation ".$row["ip"];
			
			//get other stuff
			switch ($type) {
				case "iftable":
					//get iftable
					shell_exec($cmd." $type > $snmpFileBase$type &");
					//get iptable
					shell_exec($cmd." .1.3.6.1.2.1.4.20.1 > ".$snmpFileBase."iptable &");
					break;
					
				case "ucdavis":
					//get cpu load table (1min, 5min, 15min), Normalize it since each vendor is different. base is .1.3.6.1.4.1.2021.10
					shell_exec("rm -f ".$snmpFileBase."cpu");
					system($cmd." .1.3.6.1.4.1.2021.10.1.3.1 -Ov | sed 's//STRING:/CPU::Load1 =/g' >> ".$snmpFileBase."cpu &");
					exec($cmd." .1.3.6.1.4.1.2021.10.1.3.2 -Ov | sed 's//STRING:/CPU::Load5 =/g' >> ".$snmpFileBase."cpu &");
					shell_exec($cmd." .1.3.6.1.4.1.2021.10.1.3.3 -Ov | sed 's//STRING:/CPU::Load15 =/g' >> ".$snmpFileBase."cpu &");
					
					//get mem stats, Normalize. Base is .1.3.6.1.4.1.2021.4
					shell_exec("rm -f ".$snmpFileBase."mem");
					shell_exec($cmd." .1.3.6.1.4.1.2021.4.3.0 -Ov | sed 's//INTEGER:/MEM::TotalSwap =/g' >> ".$snmpFileBase."mem &");
					shell_exec($cmd." .1.3.6.1.4.1.2021.4.4.0 -Ov | sed 's//INTEGER:/MEM::AvailSwap =/g' >> ".$snmpFileBase."mem &");
					shell_exec($cmd." .1.3.6.1.4.1.2021.4.5.0 -Ov | sed 's//INTEGER:/MEM::TotalReal =/g' >> ".$snmpFileBase."mem &");
					shell_exec($cmd." .1.3.6.1.4.1.2021.4.6.0 -Ov | sed 's//INTEGER:/MEM::AvailReal =/g' >> ".$snmpFileBase."mem &");
					shell_exec($cmd." .1.3.6.1.4.1.2021.4.11.0 -Ov | sed 's//INTEGER:/MEM::TotalFree =/g' >> ".$snmpFileBase."mem &"); //includes swap
					shell_exec($cmd." .1.3.6.1.4.1.2021.4.13.0 -Ov | sed 's//INTEGER:/MEM::Shared =/g' >> ".$snmpFileBase."mem &");
					shell_exec($cmd." .1.3.6.1.4.1.2021.4.14.0 -Ov | sed 's//INTEGER:/MEM::Buffered =/g' >> ".$snmpFileBase."mem &");
					shell_exec($cmd." .1.3.6.1.4.1.2021.4.15.0 -Ov | sed 's//INTEGER:/MEM::Cached =/g' >> ".$snmpFileBase."mem &");
					
					//get hd stats, normalize. base is .1.3.6.1.4.1.2021.9
					//shell_exec("rm -f ".$snmpFileBase."hd");
					shell_exec($cmd." .1.3.6.1.4.1.2021.9 > ".$snmpFileBase."hdd &");
					//shell_exec(trim($row['snmpcommunity']) . " -M $mibLocation {$row["ip"]} .1.3.6.1.4.1.2021.13.15.1.1 > ".$snmpFileBase."hd &");
					break;
					
				default:
					//get the default information we are requesting, no addition stuff needed
					shell_exec($cmd." $type > $snmpFileBase$type &");
					break;
			}
			
			
			/*get 1 value
			if ($type == "system") { //-Ovq returns JUST the value
				$sysDescr = trim(shell_exec(trim($row['snmpcommunity']) . " -M $mibLocation -Ovq {$row["ip"]} sysDescr"));
				$sysUpTimeInstance = trim(shell_exec(trim($row['snmpcommunity']) . " -M $mibLocation -Ovq {$row["ip"]} sysUpTimeInstance"));
				$sysContact = trim(shell_exec(trim($row['snmpcommunity']) . " -M $mibLocation -Ovq {$row["ip"]} sysContact"));
				$sysName = trim(shell_exec(trim($row['snmpcommunity']) . " -M $mibLocation -Ovq {$row["ip"]} sysName"));
				$sysLocation = trim(shell_exec(trim($row['snmpcommunity']) . " -M $mibLocation -Ovq {$row["ip"]} sysLocation"));
				$sysServices = trim(shell_exec(trim($row['snmpcommunity']) . " -M $mibLocation -Ovq {$row["ip"]} sysServices"));
			} //*/
		}
	}
	
	//sleep for x seconds to allow all the processes to finish.
	sleep(60);
	
	//get the current timestamp so we can update the fields with the right timestamp
	$curScanTs = getSqlValue("SELECT NOW()");
	
	//now loop through each device to determine what was successful and import that information
	foreach($arrSnmp as $row) {
		if (substr($row['snmpcommunity'],0,8)=="snmpwalk") {
			$snmpBaseFile =  "./data/device_scan_data/snmp_".$row["ip"];
			$snmpFile = "./data/device_scan_data/snmp_".$row["ip"]."_$type";
			
			
			//check to see
			clearstatcache(); //so we get the correct file size
			//if (filesize($snmpFile) < 5) {
			if (!isValidSnmpString($snmpFile)) {
				//command failed, lets update the community string
				echo $row['snmpcommunity']." FAILED on ".$row["ip"].". Updating DB.<br>";
				queryMysql("UPDATE devicelist SET snmpcommunity='Community String no longer works' WHERE ip='".$row["ip"]."'");
			} else {
				//success.
				//process and import the information. 
				echo $row['snmpcommunity']." SUCCEEDED on ".$row["ip"].". Updating DB.<br>";
				
				//load table, and import to sql
				//switch ($type) { case "iftable": echo "if"; break; }
				switch ($type) {
				case "iftable":
					$devTableName = "device_iftable"; $histTableName = "history_if"; $device = $row["ip"];
					$tnewTable = getSnmpTableArr($snmpFile);
					$newTable = ""; //this will hold the realigned array
					$oldTable = getSqlArray("SELECT * FROM $devTableName WHERE device='$device' ORDER BY ifIndex ASC");
					$ipTable = getSnmpTableArr($snmpBaseFile."_iptable");
					$lastScanTs = getSqlValue("SELECT ifts from $devTableName WHERE device='$device' LIMIT 1;");
					$scanTsDiff = getSqlValue("SELECT TIMESTAMPDIFF(SECOND, '$lastScanTs', '$curScanTs')");
					//$curScanTs = getSqlValue("SELECT NOW()");
					//$qryUpdateDevTS = "UPDATE $devTableName SET ifts=NOW() WHERE device='$device'";
					$removedEntries = ""; $addedEntries = "";
					
					/* TO test the array and see what we have
					print_r($oldTable);
					echo "<br><br><br>";
					print_r($tnewTable);
					echo "<br><br><br>";
					//exit;//*/
					
					
					//realign the array so key 0 is the first element, matches with sql
					//Also add the ip address to the table
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
										$tDiff = $tNew <= $tOld ? 0 : $tNew - $tOld;
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
							$qryInsertCol = "INSERT INTO $devTableName (device".$qryInsertCol.") ";
							$qryInsertVal = " VALUES ('$device'".$qryInsertVal.");";
							$qryUpdate = "UPDATE $devTableName SET ".substr($qryUpdate,2)
								." WHERE device='$device' AND ifIndex=".$ifIndex.";";
							
							//create the throughput change update query
							if ($qryUpdateIfDiff != "") 
								$qryUpdateIfDiff = "UPDATE $devTableName SET ".substr($qryUpdateIfDiff,2)
								.",ifts=TIMESTAMP('$curScanTs') WHERE device='$device' AND ifIndex=".$ifIndex.";";
							if ($histTableName != "") {
								$qryInsertHistoryCol = "INSERT INTO $histTableName (ts, device, ifIndex".$qryInsertHistoryCol.") ";
								$qryInsertHistoryVal = " VALUES (TIMESTAMP('$curScanTs'), '$device', '".$ifIndex."'".$qryInsertHistoryVal.");";
							}
						}
						
						//run the needed query
						if (array_key_exists($i,$newTable) && array_key_exists($i,$oldTable)) {
							//both exists, lets update
							echo "Running UPDATE QUERY: $qryUpdate<br>";
							$ret = queryMySql($qryUpdate);
							echo " ==== Returned $ret<br>";
							echo "Running UPDATE QUERY: $qryUpdateIfDiff<br>";
							$ret = queryMySql($qryUpdateIfDiff);
							echo " ==== Returned $ret<br>";
							
							//also update the history table with the throughput difference
							echo "Running UPDATE QUERY: $qryInsertHistoryCol $qryInsertHistoryVal<br>";
							$ret = queryMySql($qryInsertHistoryCol.$qryInsertHistoryVal);
							echo " ==== Returned $ret<br>";
							
							//if any important settings were changed, lets log it.
							//for iftable lets monitor, ifMtu, ifPhysAddress, ifAdminStatus, ifOperStatus
							$monitorColumns = array('ifMtu','ifPhysAddress','ifAdminStatus','ifOperStatus');
							foreach ($monitorColumns as $mc) {
								$oldVal = getArrayVal($oldTable[$i],$mc); $newVal = getArrayVal($newTable[$i],$mc);
								if ($oldVal != "" && $newVal != $oldVal)
									writeLog("interface",$device, "Interface value '$mc' changed from '$oldVal' to '$newVal' on: ifIndex=".
									$newTable[$i]["ifIndex"].", ifDescr=".$newTable[$i]["ifDescr"]."");
							}	
							
						} elseif (!array_key_exists($i,$newTable) && array_key_exists($i,$oldTable)) {
							//the entry was removed, lets take it out of the sql table
							if (trim($oldTable[$i]["ifIndex"]) != "") {
								$qryDelete = "DELETE FROM $devTableName WHERE device='$device' AND ifIndex=".trim($oldTable[$i]["ifIndex"]);
								echo "Running DELETE QUERY: $qryDelete<br>";
								$ret = queryMySql($qryDelete);
								echo " ==== Returned $ret<br>";
								$removedEntries .= "ifIndex=".$oldTable[$i]["ifIndex"].", ifDescr=".$oldTable[$i]["ifDescr"].", ifPhysAddress=".$oldTable[$i]["ifPhysAddress"].", ipAddr=".getArrayVal($oldTable[$i],"ipAddr")." <br>";
							}
						} elseif (array_key_exists($i,$newTable)) {
							//the entry is being added to sql
							echo "Running INSERT QUERY: $qryInsertCol $qryInsertVal<br>";
							$ret = queryMySql($qryInsertCol.$qryInsertVal);
							echo " ==== Returned $ret<br>";
							$addedEntries .= "ifIndex=".$newTable[$i]["ifIndex"].", ifDescr=".$newTable[$i]["ifDescr"].", ifPhysAddress=".$newTable[$i]["ifPhysAddress"].", ipAddr=".getArrayVal($newTable[$i],"ipAddr")." <br>";
						}
					}
					
					//lets log the removed and added
					if ($removedEntries !="") writeLog("interface",$device,"The following interfaces have been REMOVED:<br><br>$removedEntries");
					if ($addedEntries !="") writeLog("interface",$device,"The following interfaces have been ADDED:<br><br>$addedEntries");
					
					//update the current device with the current throughput in/out
					queryMySql("UPDATE devicelist SET ifthroughputin=(SELECT SUM(ifInOctetsDiff) from device_iftable where device='$device'), ifthroughputout=(SELECT SUM(ifOutOctetsDiff) from device_iftable where device='$device') where ip='$device'");
					
					break;
				
				//end if type=iftable
				case "ucdavis": //UPDATE MEMORY, CPU, AND HD STATS
					$tblCpu = getSnmpTableArr($snmpBaseFile."_cpu");
					$tblMem = getSnmpTableArr($snmpBaseFile."_mem");
					$tblHdd = getSnmpTableArr($snmpBaseFile."_hdd");
					$oldTblMem = getSqlArray("SELECT * FROM device_mem WHERE device='$device'"); //only 1 row
					
					//$hddExist = getSqlValue("SELECT count(device) FROM `device_hdd` WHERE device='$device' limit 1");
					
					//update the CPU tables
					if (is_array($tblCpu)) {
						$v1 = getArrayVal($tblCpu[0],"Load1"); $v2 = getArrayVal($tblCpu[0],"Load5"); $v3 = getArrayVal($tblCpu[0],"Load15");
						if ($v1 != "" || $v2 != "" || $v3 != "") { //we have data to insert/update
							$cpuExist = getSqlValue("SELECT count(device) FROM `device_cpu` WHERE device='$device' limit 1");
							if (intval($cpuExist) > 0) { //the line exists lets update
								$ret = queryMySql("UPDATE device_cpu SET Load1='$v1', Load5='$v2', Load15='$v3' WHERE device='$device'");
								echo "Running UPDATE Query on device_cpu Returned: $ret\n";
							} else { //line doesn't exist lets insert
								$ret = queryMySql("INSERT INTO device_cpu (ts, device, Load1, Load5, Load15) 
									VALUES (TIMESTAMP('$curScanTs'), '$device', $v1, $v2, $v3)");
								echo "Running INSERT Query on device_cpu Returned: $ret\n";
							}
							//update the history table
							$ret = queryMySql("INSERT INTO history_cpu (ts, device, Load1, Load5, Load15) 
									VALUES (TIMESTAMP('$curScanTs'), '$device', $v1, $v2, $v3)");
							echo "Running INSERT Query on history_cpu Returned: $ret\n";
						}
					} else echo $tblCpu;
					
					//update MEM table
					if (is_array($tblMem)) {
						$memExist = getSqlValue("SELECT count(device) FROM `device_mem` WHERE device='$device' limit 1");
						$v1 = getArrayVal($tblMem[0],"TotalSwap"); $v2 = getArrayVal($tblMem[0],"AvailSwap"); 
						$v3 = getArrayVal($tblMem[0],"TotalReal"); $v4 = getArrayVal($tblMem[0],"AvailReal");
						$v5 = getArrayVal($tblMem[0],"TotalFree"); $v6 = getArrayVal($tblMem[0],"Shared"); 
						$v7 = getArrayVal($tblMem[0],"Buffered"); $v8 = getArrayVal($tblMem[0],"Cached"); 
						if ($v3 != "") { //we have data to insert/update
							$memExist = getSqlValue("SELECT count(device) FROM `device_mem` WHERE device='$device' limit 1");
							if (intval($memExist) > 0) { //the line exists lets update
								$ret = queryMySql("UPDATE device_mem SET TotalSwap='$v1', AvailSwap='$v2', TotalReal='$v3', AvailReal='$v4',
									TotalFree='$v5', Shared='$v6', Buffered='$v7', Cached='$v8'
									WHERE device='$device'");
								echo "Running UPDATE Query on device_mem Returned: $ret\n";
							} else { //line doesn't exist lets insert
								$ret = queryMySql("INSERT INTO device_mem (ts, device, TotalSwap, AvailSwap, TotalReal, AvailReal,
									TotalFree, Shared, Buffered, Cached) 
									VALUES (TIMESTAMP('$curScanTs'), '$device', $v1, $v2, $v3, $v4, $v5, $v6, $v7, $v8)");
								echo "Running INSERT Query on device_cpu Returned: $ret\n";
							}
							//update the history table
							$ret = queryMySql("INSERT INTO history_mem (ts, device, AvailSwap, AvailReal, Buffered, Cached) 
									VALUES (TIMESTAMP('$curScanTs'), '$device', $v2, $v4, $v7, $v8)");
							echo "Running INSERT Query on history_mem Returned: $ret\n";
						}
					} else echo $tblMem;
					
					break; //case
				}//end case select
			}
		} else {
			echo $row["ip"]." does not have a valid snmpwalk command to try.<br>";
		}
	}

} else {	//only get information for 1 Device
	if ($type=="all") {
		$success = false;
		$snmpStr = getSqlValue("SELECT snmpcommunity FROM devicelist WHERE ip='$device' limit 1;");
		//$arrSnmpCommand = explode("\n",trim($snmpStr."\n".$snmpCommands));
		$snmpFile = "./data/device_scan_data/snmp_".$device."_full";
		$snmpFileTmp = $snmpFile."_tmp";
		
		//since pulling full info takes a long time, lets only pull the info if refresh is set.
		if ($refresh=="yes") {
			if (substr($snmpStr,0,8)=="snmpwalk") {
				$cmd = $snmpStr . " -M $mibLocation $device";
				$res = shell_exec($cmd . " > $snmpFileTmp");
				
				//test the file size to see if the pull was successful
				clearstatcache(); //so we get the correct file size
				if (isValidSnmpString($snmpFileTmp)) {
					//success
					$success = true;
					shell_exec("mv -fT $snmpFileTmp $snmpFile");
					
					//success so lets break out of this loop
					//echo $cmd." Succeeded<br>";
					break;
				} else {
					//this one failed. echo for troubleshooting
					//echo $cmd." Failed - tmp file size is ".filesize($snmpFileTmp)."<br>";
					//queryMysql("UPDATE devicelist SET snmpcommunity='None Worked' WHERE ip='$device'");
				}
			}
		}
		
		//display the information that we pulled
		//displaySnmp($device,"full");
		showSnmpTable($snmpFile,"Full");
		
	}
}

echo "</pre></body></html>";

?>