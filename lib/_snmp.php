<?php //_snmp.php - snmp monitoring, parsing, and displaying functions

//Some Global Vars
include_once '_functions.php';
//$gFolderData = "../data";

//Sorts a multidimentional array by the common column name. aka Database Sort
//usage: array_orderby(ARRAY_VAR, COL_NAME, SORT_DESC/ASC [,COL_NAME2, SORT_ORDER [,...]])
function array_orderby(&$data) {
    
    $args = func_get_args();
    //$data = array_shift($args);
    array_shift($args);
    foreach ($args as $n => $field) {
        if (is_string($field)) {
            $tmp = array();
            foreach ($data as $key => $row)
                $tmp[$key] = $row[$field];
            $args[$n] = $tmp;
            }
    }
    $args[] = &$data;
    call_user_func_array('array_multisort', $args);
    return array_pop($args);
}

function convertBps($spd) {
	//converts 100,000,000bps to 100Mbps
	$arrBps = array("Kbps","Mbps","Gbps","Tbps"); //last is Terabits per second
	$ret = $spd."bps";
	foreach ($arrBps as $suffix) {
		if (floatval($spd) < 1000) break;
		$spd = floatval($spd) / 1000;
		$ret = round($spd,2).$suffix;
	}
	return $ret;
}
function convertBytes($bytes) {
	//converts 100,000,000bytes to 100Mb
	$arrBps = array("KB","MB","GB","TB"); //last is Terabits per second
	$ret = $bytes."";
	foreach ($arrBps as $suffix) {
		if (floatval($bytes) < 1024) break;
		$bytes = floatval($bytes) / 1024;
		$ret = round($bytes,2).$suffix;
	}
	return $ret;
}

function time_elapsed_string($ptime) {
    $etime = $ptime;//time() - $ptime;

    if ($etime < 1) return '0 seconds';
    
    $a = array( 365 * 24 * 60 * 60  =>  'year',
                 30 * 24 * 60 * 60  =>  'month',
                      24 * 60 * 60  =>  'day',
                           60 * 60  =>  'hour',
                                60  =>  'minute',
                                 1  =>  'second'
                );
    $a_plural = array( 'year'   => 'years',
                       'month'  => 'months',
                       'day'    => 'days',
                       'hour'   => 'hours',
                       'minute' => 'minutes',
                       'second' => 'seconds'
                );

    foreach ($a as $secs => $str) {
        $d = $etime / $secs;
        if ($d >= 1) {
            $r = round($d);
            return $r . ' ' . ($r > 1 ? $a_plural[$str] : $str) . ' ago';
        }
    }
}

Function getArrayVal($array, $keyName) { 
	//returns the value only if the keyname exists. prevents errors
	if (!is_array($array)) return "";
	if (array_key_exists($keyName,$array))
		return $array[$keyName];
	return "";
}

function getSnmpTableArr($snmpFile) {
	$table="";
	if (file_exists($snmpFile)) {
		//get file date
		//$fileDate = date("F d Y H:i:s.",filemtime($snmpFile));
		
		//get file contents
		$data = file_get_contents($snmpFile);
		$dlines = explode("\n",$data);
		
		//load contents into the array
		foreach ($dlines as $row) {
			//Example Mib Format:  SNMPv2-MIB::sysServices.0 = INTEGER: 76
			//SNMPv2-MIB::sysORLastChange.0 = Timeticks: (0) 0:00:00.00
			//SNMPv2-MIB::sysORID.1 = OID: IF-MIB::ifCompliance3
			//SNMPv2-MIB::sysORID.2 = OID: SNMP-FRAMEWORK-MIB::snmpFrameworkMIBCompliance
			
			//Get each of the values
			if (strpos($row,"::") > 0) { //we have a valid snmp string
				
				//parse the string
				$tableName = substr($row,0,strpos($row,"::"));
				$row = substr($row,strpos($row,"::")+2);
				$valName = substr($row,0,strpos($row," = ")); //includes the .# if its present
				$rowNum = "0";
				if (strpos($valName,".") !== false) { //there's a row num
					$rowNum = substr($valName,strpos($valName,".")+1);
					$valName = substr($valName,0,strpos($valName,"."));
				}
				$row = substr($row,strpos($row," = ")+3);
				$valType = substr($row,0,strpos($row,": "));
				$valData = ($valType=="")?$row:substr($row,strpos($row,": ")+2);
				
				
				//add these values to an array
				//echo "$tableName - [$rowNum][$valName] = $valData ($valType)<br>";
				$table[$rowNum][$valName] = $valData; //$valType is also available
				
			}
		}
	} else {
		$table = "File Does Not Exist.";
	}
	
	return $table;
}

function showSnmpFile($snmpFile) { //use to show a full snmp scan
	if (!file_exists($snmpFile)) {
		echo "Scan Info For <i>".basename($snmpFile)."</i> does not exist.<br>";
		return;
	}
	
	//get file date
	$fileDate = date("F d Y H:i:s.",filemtime($snmpFile));

	$data = file_get_contents($snmpFile);
	$dlines = explode("\n",$data);
	
	//load contents into the array
	$tables="";		
	foreach ($dlines as $row) {
		//Example Mib Format:  SNMPv2-MIB::sysServices.0 = INTEGER: 76
		//SNMPv2-MIB::sysORLastChange.0 = Timeticks: (0) 0:00:00.00
		//SNMPv2-MIB::sysORID.1 = OID: IF-MIB::ifCompliance3
		//SNMPv2-MIB::sysORID.2 = OID: SNMP-FRAMEWORK-MIB::snmpFrameworkMIBCompliance
		
		//Get each of the values
		if (strpos($row,"::") > 0) { //we have a valid snmp string
		
			$tableName = substr($row,0,strpos($row,"::"));
			
			$row = substr($row,strpos($row,"::")+2);
			$valName = substr($row,0,strpos($row," = ")); //includes the .# if its present
			$rowNum = "0";
			if (strpos($valName,".") !== false) { //there's a row num
				$rowNum = substr($valName,strpos($valName,".")+1);
				$valName = substr($valName,0,strpos($valName,"."));
			}
			
			$row = substr($row,strpos($row," = ")+3);
			$valType = substr($row,0,strpos($row,": "));
			$valData = ($valType=="")?$row:substr($row,strpos($row,": ")+2);
			
			
			//add these values to an array
			//echo "$tableName - [$rowNum][$valName] = $valData ($valType)<br>";
			
			if ($rowNum=="0") {
				//add to the base array
				//$tables[MIBTable][SubTable][Row][Column] - row and column keys are $rowNum & $valName
				//$tables[$tableName]['BaseTable'][$rowNum][$valName] = $valData . " ($valType)";
				$tables[$tableName]['BaseTable'][$valName][1] = $valName; //this makes each entry a row instead of all one row.
				$tables[$tableName]['BaseTable'][$valName][2] = $valData;
			} else {
				//add to a new array
				$tables[$tableName]['SecondTable']["heading"][$valName] = $valName; //row heading
				$tables[$tableName]['SecondTable'][$rowNum][$valName] = $valData . " ($valType)";
				
			}
		}
	}
	

	//display the information
	echo "<div><h3>Showing scan data for <b>".basename($snmpFile)."</b></h3> From <i>$fileDate</i><br><br>";
	//display the 4 dimensional array
	foreach ($tables as $key1 => $arr1) {
		echo "Table: $key1<br><table>";
		foreach($arr1 as $key2 => $arr2) {
			echo "<table class=reference width=100%>";
			foreach ($arr2 as $rowNum => $rowArr) {
				echo "<tr>";
				foreach ($rowArr as $colName => $val) {
					if ($rowNum == "heading") echo "<th>$val</th>";
					else echo "<td>$val</td>";
				}
				echo "</tr>";
			}
			echo "</table>";
		}
		echo "<br>";
	}
	echo "</div>";
		
		
	//return $tables;
}


function showSnmpTable($device, $snmpFile, $infoType) {
	//some variables
        global $gFolderData;
	$table = ""; $collectedDate = "Never"; $rowCount = 0;
	
	//display the section header
	//echo "<div><h4><b>$infoType</b> Information</h4>";
	
	//remove spaces
	$infoType = str_replace(" ","",$infoType);
	
	//see if we have the data
	if (!file_exists($snmpFile)) {
		echo "<div class=subtle><i>Data Not Collected</i></div>";
		return;
	}
	$collectedDate = date("F d Y H:i:s.",filemtime($snmpFile));
	
	
	//display the table
	//lets display what we need to
	if ($infoType!="OTHER") echo "<table id=\"$infoType\" class=reference width=100%>";
	switch($infoType) {
		case "System": //===============SYSTEM===============
			//get the table
			$table = getSnmpTableArr($snmpFile);
			if (!is_array($table)) { $collectedDate = $table; break; }
			
			//headers
			echo "<tr><th>SNMP Info: Name</th><th>Location</th><th>Contact</th><th>Services</th><th>Description</th><th>Up-Time</th></tr>";
			//output the data
			$upTime = substr($table["0"]["sysUpTimeInstance"],strpos($table["0"]["sysUpTimeInstance"]," ")+1);
			echo "<tr><td>".getArrayVal($table["0"],"sysName")."</td><td>".getArrayVal($table["0"],"sysLocation")."</td><td>".getArrayVal($table["0"],"sysContact")."
					</td><td>".getArrayVal($table["0"],"sysServices")."</td><td>".getArrayVal($table["0"],"sysDescr")."</td><td>$upTime</td></tr>";
			break;
			
		case "Network": //===============INTERFACES===============
			//get the table
			$table = getSqlArray("SELECT * FROM device_iftable WHERE device='$device' ORDER BY ifIndex ASC");
			$rowCount = count($table);
			
			//print_r($table);
			if (!is_array($table) || $rowCount==0) { $collectedDate = $table; break; }
			
			//make the headers
			echo "<thead><tr><th>Health</th><th>Status:<br>Adm / Op</th><th>Index</th><th>Descr</th><th>Alias</th><th>MTU</th><th>Speed</th>
				<th>Physical Address</th><th>IP Address</th><th>Netmask</th>
				<th>Last Change</th><th title='Average current throughput rate over the span of the poll period.'>Traffic In</th>
				<th title='Average current throughput rate over the span of the poll period.'>Traffic Out</th>
				<th title='New packet discards since the last poll'>Discards:<br>in / out</th><th>Counters</th></tr></thead>";
			
			//format and show the information
			$totalIn = $totalOut = 0;
			foreach ($table as $row) {
				//get counter and error status
				$counters = "Octets:	 	{$row["ifInOctets"]}(in)	{$row["ifOutOctets"]}(out) &#013;";
				$counters .= "UcastPkts: 	{$row["ifInUcastPkts"]}(in)	{$row["ifOutUcastPkts"]}(out) &#013;";
				$counters .= "NUcastPkts: 	{$row["ifInNUcastPkts"]}(in)	{$row["ifOutNUcastPkts"]}(out) &#013;";
				$counters .= "*Discards: 	{$row["ifInDiscards"]}(in)	{$row["ifOutDiscards"]}(out) &#013;";
				$counters .= "**Errors: 		{$row["ifInErrors"]}(in)	{$row["ifOutErrors"]}(out) &#013;";
				$counters .= "??? Protocols: 	{$row["ifInUnknownProtos"]}(in)	n/a(out) &#013;";
				$counters .= "Out Queue: 	n/a(in)	{$row["ifOutQLen"]}(out) &#013;";
				//get interface errors
				$health = ""; $fontColor = "";
				if ($row["ifInErrorsDiff"] > 0 || $row["ifOutErrorsDiff"] > 0) 
					$health = 'IF: <img src="images/s_critical.png" title="There are active errors on this interface. Check Duplex settings, cable quality, EMI.&#013;in: '.$row["ifInErrorsDiff"].', out: '.$row["ifOutErrorsDiff"].'">';
				//interface up/down stats
				$ifAdmin = (strpos($row["ifAdminStatus"],'up') !== FALSE)?"<img src='images/s_up.png'>":"<img src='images/s_down.png'>";
				$ifOp = (strpos($row["ifOperStatus"],'up') !== FALSE)?"<img src='images/s_up.png'>":"<img src='images/s_down.png'>";
				//get interface bw percentage
				if ($row["ifHCInOctetsDiff"]>0) $row["ifInOctetsDiff"] = $row["ifHCInOctetsDiff"]; //use the 64-bit value if we have it
				if ($row["ifHCOutOctetsDiff"]>0) $row["ifOutOctetsDiff"] = $row["ifHCOutOctetsDiff"];
				$bwIn = ($row["ifSpeed"]>0)?intval($row["ifInOctetsDiff"] * 8 / $row["ifSpeed"] * 100):0; //diff is avg/sec
				$bwOut = ($row["ifSpeed"]>0)?intval($row["ifOutOctetsDiff"] * 8 / $row["ifSpeed"] * 100):0; 
				if ($bwIn >= 95 || $bwOut >= 95) {
					if ($health != "") $health .= ", ";
					$health .= 'BW: <img src="images/s_critical.png" title="Bandwidth is saturated.&#013;in: '.$bwIn.'%, out: '.$bwOut.'%">';
				} elseif ($bwIn >= 90 || $bwOut >= 90) {
					if ($health != "") $health .= ", ";
					$health .= 'BW: <img src="images/s_warning.png" title="Bandwidth usage is near capacity.&#013;in: '.$bwIn.'%, out: '.$bwOut.'%">';
				}
				if ($health=="") $health = '<img src="images/s_ok.png" title="Bandwidth usage looks good and no current Errors.&#013;BW in: '.$bwIn.'%, BW out: '.$bwOut.'%">';
				//if (strpos($row["ifOperStatus"],'up') === FALSE) $fontColor = 'color="#fafafa"';
				
				//print the row
				echo '<tr>
					<td>'.$health.'</td>
					<td title="'.$row["ifAdminStatus"].'"><center>'.$ifAdmin.' / '.$ifOp.'</center></td>
					<td>'.$row["ifIndex"].'</td>
					<td title="Interface Type: '.$row["ifType"].'">'.$row["ifDescr"].'</td>
					<td>'.$row["ifAlias"].'</td>
					<td>'.$row["ifMtu"].'</td>
					<td>'.convertBps($row["ifSpeed"]).'</td>
					<td>'.$row["ifPhysAddress"].'</td>
					<td>'.str_replace(" ","<br>",$row["ipAddr"]).'</td>
					<td>'.$row["ipNetMask"].'</td>
					<td>'.substr($row["ifLastChange"],strpos($row["ifLastChange"],") ")+2).'</td>
					<td>'.$bwIn.'% ['.convertBps($row["ifInOctetsDiff"] * 8).']</td>
					<td>'.$bwOut.'% ['.convertBps($row["ifOutOctetsDiff"] * 8).']</td>
					<td>'.$row["ifInDiscards"].' / '.$row["ifOutDiscards"].'</td>
					<td><img src="images/s_info.png" title="'.$counters.'"></td>
				</tr>';
				
				//increase the totals for the last line
				$totalIn += $row["ifInOctetsDiff"]; $totalOut += $row["ifOutOctetsDiff"];
			}
			
			
			
			//show the throughput totals
			if ($rowCount <= 20) {
				echo '<tr><td colspan=11 style="text-align:right;"><b>Total Throughput:</b></td><td>'.convertBps($totalIn * 8).'</td><td>'.convertBps($totalOut * 8).'</td><td></td><td></td></tr>';
			}
			
			/* data, as displayed from the file view
			foreach ($table as $row) {
				//get counter and error status
				$counters = "Octets:	 	{$row["ifInOctets"]}(in)	{$row["ifOutOctets"]}(out) &#013;UcastPkts: 	{$row["ifInUcastPkts"]}(in)	{$row["ifOutUcastPkts"]}(out) &#013;NUcastPkts: 	{$row["ifInNUcastPkts"]}(in)	{$row["ifOutNUcastPkts"]}(out) &#013;*Discards: 	{$row["ifInDiscards"]}(in)	{$row["ifOutDiscards"]}(out) &#013;**Errors: 		{$row["ifInErrors"]}(in)	{$row["ifOutErrors"]}(out) &#013;??? Protocols: 	{$row["ifInUnknownProtos"]}(in)	n/a(out) &#013;Out Queue: 	n/a(in)	{$row["ifOutQLen"]}(out) &#013;";
				$errorOut = "";
				if ($row["ifOutErrors"] > 0 || $row["ifOutErrors"] > 0) $errorOut = "<img src='images/s_warning.png' title='There are errors on this interface.'>";
				$ifAdmin = (strpos($row["ifAdminStatus"],'up') !== FALSE)?"<img src='images/s_up.png'>":"<img src='images/s_down.png'>";
				$ifOp = (strpos($row["ifOperStatus"],'up') !== FALSE)?"<img src='images/s_up.png'>":"<img src='images/s_down.png'>";
				//print the row
				echo "<tr><td>{$row["ifIndex"]}</td><td>{$row["ifDescr"]}</td><td>{$row["ifType"]}</td><td>{$row["ifMtu"]}</td>
					<td>".convertBps($row["ifSpeed"])."</td><td>{$row["ifPhysAddress"]}</td>
					<td>$ifAdmin</td><td>$ifOp</td><td>{$row["ifLastChange"]}</td>
					<td>{$row["ifInOctets"]}</td><td>{$row["ifOutOctets"]}</td>
					<td><img src='images/s_info.png' title='$counters'> $errorOut</td>
				</tr>";
			}//*/
			
			break;
			
		case "RoutingTable": //===============ROUTING TABLE===============
			//headers
			echo "<thead><tr><th>Destination</th><th>Netmask</th><th>Gateway</th><th>Interface index</th><th>Metric (1-5)</th><th>Type</th><th>Protocol</th><th>Age</th></tr></thead>";
			
			//exit if we don't have data
			//get the table, exit if no data returned
			$table = getSnmpTableArr($snmpFile);
			$rowCount=count($table);
			
			//print_r($table);
			if (!is_array($table) ) { echo "<tr><td colspan=8>$table</td></tr>"; break; }
			if (array_key_exists("0",$table)) { echo "<tr><td colspan=8>".implode("",$table[0])."</td></tr>"; break; } //table array starts with 1. 0 on on error
			
			//sort the array by netmask
			array_orderby($table,'ipRouteMask',SORT_DESC,'ipRouteDest',SORT_ASC);
			//data
			foreach ($table as $row) {
				//calculate the age
				$age = getArrayVal($row,"ipRouteAge");
				if (intval($age) > 0) $age = time_elapsed_string($age);//($age) / (60 * 60 * 24) . " days"; //time_elapsed_string($age + time());
				
				echo "<tr><td>".getArrayVal($row,"ipRouteDest")."</td><td>".getArrayVal($row,"ipRouteMask")."</td>
					<td>".getArrayVal($row,"ipRouteNextHop")."</td><td>".getArrayVal($row,"ipRouteIfIndex")."</td>
					<td>".getArrayVal($row,"ipRouteMetric1")." [".getArrayVal($row,"ipRouteMetric2").",".getArrayVal($row,"ipRouteMetric3").",".getArrayVal($row,"ipRouteMetric4").",".getArrayVal($row,"ipRouteMetric5")."]</td>
					<td>".getArrayVal($row,"ipRouteType")."</td><td>".getArrayVal($row,"ipRouteProto")."</td><td>".$age."</td>
				</tr>";
			}
			
			break;
			
		case "CPU":
			//get the table
			//$table = getSnmpTableArr("$gFolderData/device_scan_data/snmp_".$device."_cpu");
			$table = getSqlArray("SELECT * FROM device_cpu WHERE device='$device'");
			//print_r($table);
			if (!is_array($table) || count($table)==0) { $collectedDate = "No Data Collected. Update the SNMP community String or SNMPMAP file."; break; }
			
			//some health checks
			$cpuCheck = '<img src="images/s_ok.png" title="CPU Usage appears just fine">';
			$pload = $table[0]['Load1'];
			if ($pload < $table[0]['Load5']) $pload = $table[0]['Load5'];
			if ($pload < $table[0]['Load15']) $pload = $table[0]['Load15'];
			if ($pload > .90) $cpuCheck = '<img src="images/s_warning.png" title="CPU Usage is above 90%!">';
			if ($pload > .98) $cpuCheck = '<img src="images/s_critical.png" title="CPU Usage is peaked! Check this out immediately, this can cause packet loss.">';
			
			//make the headers
			echo "<tr><th>Health</th><th>1 Minute Load</th><th>5 Minute Load</th><th>15 Minute Load</th></tr>";
			echo "<tr><td>$cpuCheck</td><td>".intval($table[0]['Load1']*100)."%</td><td>".intval($table[0]['Load5']*100)."%</td><td>".intval($table[0]['Load15']*100)."%</td></tr>";
			$collectedDate = $table[0]['ts'];
			
			break;
			
		case "Memory":
			//$table = getSnmpTableArr("$gFolderData/device_scan_data/snmp_".$device."_mem");
			$table = getSqlArray("SELECT * FROM device_mem WHERE device='$device'");
			//print_r($table);
			if (!is_array($table) || count($table)==0) { $collectedDate = "No Data Collected. Update the SNMP community String or SNMPMAP file."; break; }
			
			//some health checks
			$memCheck = '<img src="images/s_ok.png" title="memory does not appear to be overly utilized.">';
			$pmem = ($table[0]['TotalReal']>0)?intval($table[0]['AvailReal']/$table[0]['TotalReal']*100):0;
			if ($pmem < 10) $memCheck = '<img src="images/s_warning.png" title="Memory availability is under 10%!">';
			if ($pmem < 2) $memCheck = '<img src="images/s_critical.png" title="Memory usage is at capacity! Could be a memory leak, you should probably check this out.">';
			if (floatval($table[0]['TotalReal'])==0) $memCheck = '<img src="images/s_pending.png" title="No Memory Information.">';
			
			//get the percentages, not dividing by 0 ;)
			$swapPcnt = floatval($table[0]['TotalSwap'])==0?"n/a": intval($table[0]['AvailSwap']/$table[0]['TotalSwap']*100)."%";
			$RealPcnt = floatval($table[0]['TotalReal'])==0?"n/a": intval($table[0]['AvailReal']/$table[0]['TotalReal']*100)."%";
			
			//make the headers
			echo "<tr><th>Health</th><th>Total Real</th><th>Available Real</th><th>Total Swap</th><th>Available Swap</th>
			<th>Total Free (Inc. Swap)</th><th>Shared</th><th>Buffered</th><th>Cached</th></tr>";
			
			echo "<tr><td>$memCheck</td>
			<td>".convertBytes($table[0]['TotalReal']*1024)."</td><td><b>$RealPcnt</b> [".convertBytes($table[0]['AvailReal']*1024)."]</td>
			<td>".convertBytes($table[0]['TotalSwap']*1024)."</td><td><b>$swapPcnt</b> [".convertBytes($table[0]['AvailSwap']*1024)."]</td>
			<td>".convertBytes($table[0]['TotalFree']*1024)."</td><td>".convertBytes($table[0]['Shared']*1024)."</td>
			<td>".convertBytes($table[0]['Buffered']*1024)."</td><td>".convertBytes($table[0]['Cached']*1024)."</td>
			</tr>";
			$collectedDate = $table[0]['ts'];
			
			break;
			
		case "HDD":
			$table = getSnmpTableArr("$gFolderData/device_scan_data/snmp_".$device."_hdd");
			if (!is_array($table)) { $collectedDate = $table; break; }
			
			//display the table
			$cnt=0;
			foreach($table as $row) {
				//print headers if we need to
				if ($cnt==0) { //show headers
					echo '<tr>';
					foreach($row as $key => $tuple) {
						echo "<th>$key</th>";
					}
					echo '</tr>';
				}
				//print the row
				echo '<tr>';
				foreach($row as $key=>$tuple) {
					if($key=="dskTotal" || $key=="dskAvail" || $key=="dskUsed") $tuple = convertBytes($tuple*1024);
					elseif($key=="dskPercentUsed" || $key=="dskPercent") $tuple = $tuple."%";
					echo "<td>$tuple</td>";
				}
				echo '</tr>';
				$cnt++;
			}
			

			
			//print_r($table);
			break;
			
		case "OTHER":
			
			$table = getSnmpTableArr("$gFolderData/device_scan_data/snmp_".$device."_other");
			if (!is_array($table)) { $collectedDate = $table; break; }
			
			//echo "<pre>";
			//print_r($table);
			//echo "</pre>";
			
			//load the array ******************************************COMPLETE WORK ON THIS ******************************
			$data = file_get_contents("$gFolderData/device_scan_data/snmp_".$device."_other");
			$dlines = explode("\n",$data);
			
			//load contents into the array
			$tables="";
			foreach ($dlines as $row) {
				//Example Mib Format:  SNMPv2-MIB::sysServices.0 = INTEGER: 76
				//Get each of the values
				if (strpos($row,"::") > 0) { //we have a valid snmp string
					$tableName = substr($row,0,strpos($row,"::"));
					$row = substr($row,strpos($row,"::")+2);
					$valName = substr($row,0,strpos($row," = ")); //includes the .# if its present
					$valData = substr($row,strpos($row," = ")+3);
					$rowNum = "0";
					if (strpos($valName,".") !== false) { //there's a row num
						$rowNum = substr($valName,strpos($valName,".")+1);
						$valName = substr($valName,0,strpos($valName,"."));
					}
					
					//add these values to an array
					//echo "$tableName - [$rowNum][$valName] = $valData ($valType)<br>";
					
					if ($rowNum=="0") {	//add to the base array
						$tables[$tableName][$valName] = $valData;
					} else {
						//add to a new array
						$tables[$tableName][$rowNum][$valName] = $valData;
						
					}
				}
			}
			
			foreach($tables as $tName => $sub) {
				echo "<b>".$tName."</b><table class='reference'>";
				$printedHeaders = false;
				foreach($sub as $row => $val) {
					if (is_array($val)) {
						//its a sub table, print headers
						echo "<tr>";
						if (!$printedHeaders) {
							foreach($val as $row => $val) echo "<th>$row</th>";
							$printedHeaders = true;
						} else
							foreach($val as $row => $val) echo "<td>$val</td>";
						echo "</tr>";
						
					} else {
						//its the flat list
						echo "<tr><td>$row</td><td>$val</td></tr>";
					}
				}
				echo "</table><br>";
			}

			
			//print_r($table);
			break;
			
		default: //===============OTHER===============
			//get the table
			$table = getSnmpTableArr($snmpFile);
			if (!is_array($table)) { $collectedDate = $table; break; }
			
			//display the table
			showSnmpFile($snmpFile);
			break;
	
	}
	
	
	//close the table
	if ($infoType!="OTHER") echo "</table>";
	//make the table smaller and with pages
	if ($rowCount > 20) {
		echo '<script type="text/javascript">
			$("#'.$infoType.'").dynatable({dataset: {perPageDefault: 15},
			inputs: {searchText: "Filter: "},
			features: {pushState: false}
			});
		</script><br>';
	}
	echo "<div align='right' class=subtle><i>Data Collected: $collectedDate</i></div>";
	

}


function updateSnmpCommunity($device) {
	GLOBAL $snmpCommandsFile;
	$arrSnmpCommand = explode("\n",file_get_contents($snmpCommandsFile)."\n");
	$oid = "1.3.6.1.2.1.1.5"; //the oid to test connectivity. System Name
	$snmpStr = getSqlValue("SELECT snmpcommunity FROM devicelist WHERE ip='$device' limit 1;");
	
	echo "Updating community string for $device\n";
	
	//loop through the commands until we get a success
	$a=1;$total=count($arrSnmpCommand); $success = false;
	foreach ($arrSnmpCommand as $val) {
		$val = cleanSqlString(trim($val));
		if (substr($val,0,12)=="snmpbulkwalk") {
			//run the snmpwalk command
			$res = shell_exec("$val $device $oid");
			
			//check to see if we have a success!
			if (strpos($res,"::") > 0) {
				echo "$a of $total: Successful snmpwalk command found, updating database\n";
				$ret = queryMysql("UPDATE devicelist SET snmpcommunity='$val' WHERE ip='$device'");
				echo "\t$ret\n";
				$success=true;
				break;
			} else {
				echo "$a of $total: snmpwalk command failed, moving on...\n";
				
			}
		}
		$a++;
	}
	
	if (!$success) {
		echo "No snmpwalk string worked, updating database.\n";
		$ret = queryMysql("UPDATE devicelist SET snmpcommunity='No snmpwalk string worked' WHERE ip='$device'");
		echo "\t$ret\n";
	} else {
		//success, log and get info
		if (substr($snmpStr,0,12)!="snmpbulkwalk") {
			writeLog("SNMP",$device,"Device SNMP Retrieval changed state to UP.");
			echo "Gather SNMP System information.\n";
			shell_exec("php snmp.php -d".$device." -tsys > /dev/null &");
		}
	}
	
	echo "Process complete.\n";
	
}


function isValidSnmpString($file) { 
	//reads a file and determines if there's a valid oid
	echo "----Validating-File----\n";
	$ret = shell_exec("head -1 $file");
	echo "First line of $file is '$ret'\n";
	if (strpos($ret,"::") > 0) return true; //its a valid snmp oid string
	
	return false;
}

function splitSnmpString($snmpString) {
	//example snmp strings:	SNMPv2-MIB::sysServices.0 = INTEGER: 76
	//						SNMPv2-MIB::sysORLastChange = Timeticks: (0) 0:00:00.00
	//						SNMPv2-MIB::sysORID.1 = OID: IF-MIB::ifCompliance3
	
	$row = $snmpString;
	$tableName = substr($row,0,strpos($row,"::"));

	$row = substr($row,strpos($row,"::")+2);
	$valName = substr($row,0,strpos($row," = ")); //includes the .# if its present
	$rowNum = "0";
	if (strpos($valName,".") !== false) { //there's a row num
		$rowNum = substr($valName,strpos($valName,".")+1);
		$valName = substr($valName,0,strpos($valName,"."));
	}

	$row = substr($row,strpos($row," = ")+3);
	$valType = substr($row,0,strpos($row,": "));
	$valData = ($valType=="")?$row:substr($row,strpos($row,": ")+2);
	
        //	return array(0 => $tableName, 1 => $valName, 2 => $rowNum, 3 => $valType, 4 => $valData);
	return array('mib' => $tableName, 'name' => $valName, 'rownum' => $rowNum, 'datatype' => $valType, 'data' => $valData);
}

function displaySnmp($device, $snmpInfoType) {
    //puts the snmp info into a table and displays it
    global $gFolderData;
    $snmpFile = "$gFolderData/device_scan_data/snmp_".$device."_".$snmpInfoType;

    if (file_exists($snmpFile)) {
            $pullDate = date("F d Y H:i:s.",filemtime($snmpFile));
            $snmpData = file_get_contents($snmpFile);
            echo "Information retrieved on $pullDate<br><br><pre>".$snmpData."</pre>";
    } else {
            echo "No SNMP Information is stored on this device";
    }
}


function updateSnmpInfo($device,$snmpInfoType) {
    //imports information into the db
    global $gFolderData;
    $snmpFile = $gFolderData."/device_scan_data/snmp_".$device."_".$snmpInfoType;
    echo $snmpFile;
        
}


/* Deprecated, bulky
function updateSnmpCommunities() {
	//loop through each device to ensure we have the correct snmp community
	//get the snmpwalk commands to cycle through
	GLOBAL $snmpCommandsFile;
	//$snmpCommands = file_get_contents($snmpCommandsFile);
	
	$oid = "1.3.6.1.2.1.1.5"; //the oid to test connectivity. System Name
	
	$devArr = getSqlArray("SELECT ip,snmpcommunity FROM devicelist WHERE monitorsnmp = 'y' AND snmpcommunity NOT LIKE 'snmpwalk%' OR snmpcommunity IS NULL;");
	$arrSnmpCommand = explode("\n",file_get_contents($snmpCommandsFile)."\n");
	
	//clear the previous test results
	shell_exec("rm -f ./data/device_scan_data/snmp_*_test");
	echo "Cleared previous test results<br>";
	
	//loop through the commands until we get a success
	foreach ($arrSnmpCommand as $val) {
		$val = cleanSqlString(trim($val));
		if (substr($val,0,8)=="snmpwalk") {
		
			//loop through each device.
			foreach ($devArr as $row) {
				$device = $row["ip"];
				$snmpFile = "./data/device_scan_data/snmp_".$device."_test";
				
				//only run the command on the device if the previous one failed
				//clearstatcache(); //so we get the correct file size
				//if (!file_exists($snmpFile) || filesize($snmpFile) < 5) {
				if (!isValidSnmpString($snmpFile)) {
					//previous command failed, try another command in the background
					$res = shell_exec("$val $device $oid > $snmpFile &");
				}
			}
			
			sleep(20); //allow the background processes to finish
			
			//now check if anything was successful. & allows me to directly modify the element
			foreach ($devArr as &$row) {
				$device = $row["ip"];
				$snmpFile = "./data/device_scan_data/snmp_".$device."_test";
				
				//only run the command on the device if the previous one failed
				//clearstatcache(); //so we get the correct file size
				//if (filesize($snmpFile) >= 5) {
				if (isValidSnmpString($snmpFile)) {
					//success, update the db
					//echo "$val SUCCEEDED on $device<br>";
					if ($row["snmpcommunity"] != "updated") {
						queryMysql("UPDATE devicelist SET snmpcommunity='$val' WHERE ip='$device'");
						echo "$val SUCCEEDED on $device.  [OLD SNMPCOMMUNITY = ".$row["snmpcommunity"]."]<br>";
						$row["snmpcommunity"] = "updated";
					}
				} else {
					//Failed, write that none worked
					queryMysql("UPDATE devicelist SET snmpcommunity='None Worked' WHERE ip='$device'");
					echo "$val FAILED on $device<br>";
				}
			}
			unset($row); //break the & reference
			
		}
	}
	
	echo "<br>Done updating community strings on all devices.";
}
*/

?>