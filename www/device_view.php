<?php //index.php

include 'header.html';
$retSuccess = $retError = "";

function drawDeviceTable($whereQry) {
		//pull the device information from mysql
	$devArr = getDevicesArray($whereQry);

	//put the column headers
	echo "	<table class='reference' width='100%'>
			<tr>
				<th>Ping</th>
				<th>Date Added</th>
				<th>Country</th>
				<th>Site</th>
				<th>Device Type</th>
				<th>Device Name</th>
				<th>IP</th>
				<th>Monitor SNMP?</th>
				<th>Monitored Ports</th>
				<th>SNMP String</th>
			</tr>";

	//output the device info 
	foreach ($devArr as $row) {
		$imgtag = '<img src=images/s_ok.png title="Host is Up. Last Successful Ping on '.$row['pingtimestamp'].'"/>';
		if ($row['pingstatus']=="unreachable" || $row['pingstatus']=="") $imgtag = '<img src=images/s_critical.png title="Host is Down. Last FAILED Ping on '.$row['pingtimestamp'].'"/>';
		echo "<tr>
				<td>$imgtag {$row['pingstatus']}</td>
				<td>{$row['dateadded']}</td>
				<td>{$row['country']}</td>
				<td>{$row['site']}</td>
				<td>{$row['objtype']}</td>
				<td>{$row['devicename']}</td>
				<td><a href=?device={$row['ip']}>{$row['ip']}</a></td>
				<td>{$row['monitorsnmp']}</td>
				<td>{$row['monitorports']}</td>
				<td>{$row['snmpcommunity']}</td>
			</tr>";
	}

	//close the  table
	echo '	</table>';
}



//get variables being passed
$device = (isset($_GET['device']))?mysql_real_escape_string(trim($_GET['device'])):""; //file data to add
$device = (isset($_POST['device']))?mysql_real_escape_string(trim($_POST['device'])):$device; //file data to add
$deviceNotes = (isset($_POST['notes']))?strip_tags(trim($_POST['notes'])):"";

//update or retrieve notes
$notesFile = "./data/device_scan_data/notes_".$device;
if (isset($_POST['btnsubmitnotes'])) {
	file_put_contents($notesFile,str_replace('\r',"",$deviceNotes));
	$retSuccess = "Device Notes Updated!";
} else {
	$deviceNotes = file_exists($notesFile)?file_get_contents("./data/device_scan_data/notes_".$device):"";
}
$deviceNotes=str_replace('\n',"<br>",$deviceNotes);


if (strlen($device)>0) {	// -------------------------------- GET AND LIST ONE DEVICE --------------------------------
	//Start of the container, header of the page
	echo '
			<div id="m-devices_manage">
				<div class="container">
					<div class="row">';
					
					
	//give notification if it was successful
	if ($retSuccess!="") 	echo '<div class="success">'.str_replace("\n","<br>",trim($retSuccess)).'</div>';
	if ($retError!="") 	echo '<div class="error">'.str_replace("\n","<br>",trim($retError)).'</div>';

	
	//DEVICE INFO
	echo '				<div class="section_header">DEVICE INFO: <b>'.$device.'</b></div>
						
						<form class="form-horizontal" action="device_list.php?device='.$device.'" method="post">
						<input type="hidden" name="update" value="all" />
	';						
		
	
	//pull the device information from mysql and draw the table
	drawDeviceTable("WHERE ip='$device'");
	
	//add the notes / description section
	echo "<br><b>Notes and Description:</b><br>
	<textarea name=notes class=\"form-control\" style=\"height:75px;width:100%\" placeholder=\"Additional notes or description on this device\">".$deviceNotes."</textarea>
	<input type=submit name=\"btnsubmitnotes\" value=\"Update Notes\" class=\"btn btn-orange pull-right\"><br>
	";


	//OVERVIEW INFO
	echo '<br><div class="section_header">OVERVIEW</div>';
	
	
	//PING AND TRACEROUTE
	echo '<br><div class="section_header">PING / TRACEROUTE MONITORING</div>';
	//Show the graph of the ping history
	
	//show traceroute information
	$file_tr = "./data/device_scan_data/traceroute_".$device;
	$res_success = (file_exists($file_tr."_success"))?shell_exec("cat ".$file_tr."_success"):"No Successful Traceroute stored";
	$res_fail = (file_exists($file_tr."_fail"))?shell_exec("cat ".$file_tr."_fail"):"No Failed Traceroute stored.";
	echo " <table class='reference' width='100%'>
			<tr>
				<th width=50%>Last <font color=green>SUCCESSFUL</font> Traceroute</th>
				<th width=50%>Current <font color=red>FAILED</font> Traceroute (if the node is down)</th>
			</tr>
			<tr>
				<td>".str_replace("\n","<br>",$res_success)."</td>
				<td>".str_replace("\n","<br>",$res_fail)."</td>
			</tr>
		</table><br>";
		
	
	
	
	//NMAP SCAN / SERVICE MONITORING
	echo '<br><div class="section_header">SERVICE MONITORING</div>';
	//echo "<span id=port_scan></span><script>getContent('.nmap.php','port_scan');</script>"; //
	//$res = shell_exec("sudo nmap -sSU -O -PE -PP -PM $device"); //nmap -sSU -sV -O -PE -PP -PM $device
	echo '<blockquote><pre>';
	//echo $res;
	echo '</pre></blockquote>';
	
	
	
	//SNMP INFO
	echo '<br><div class="section_header">SNMP INFO</div>';
	//$res = shell_exec("sudo snmpwalk -v2c -c Tyc0-safety-pr0d".$row['snmpcommunity']." $device");
	//$res = shell_exec("sudo snmpwalk -v2c -c Tyc0-safety-pr0d".$row['snmpcommunity']." $device");
	echo '<code>'.$res.'</code>';
	
	
	
	
	//ALERTS
	echo '<br><div class="section_header">ALERTS</div>';
	
	
	
	
	//LOGS
	echo '<br><div class="section_header">LOGS FOR THIS DEVICE</div>';
	$logRows = getSqlValue("SELECT count(*) FROM log WHERE device='$device'");
	$arrLogs = getSqlArray("SELECT ts,type,value FROM log WHERE device='$device' ORDER BY ts DESC;");
	
	//put the column headers
	echo "	<b>$logRows Records Found</b><br><table class='reference' width='100%'>
			<tr>
				<th width=100>Event Timestamp</th>
				<th width=100>Event Type</th>
				<th>Event Data</th>
			</tr>";

	//output the log info 
	foreach ($arrLogs as $row) {
		//$imgtag = '<img src=images/s_ok.png title="Host is Up. Last Successful Ping on '.$row['pingtimestamp'].'"/>';
		//if ($row['pingstatus']=="unreachable" || $row['pingstatus']=="") $imgtag = '<img src=images/s_critical.png title="Host is Down. Last FAILED Ping on '.$row['pingtimestamp'].'"/>';
		echo "<tr>
				<td>{$row['ts']}</td>
				<td>{$row['type']}</td>
				<td>".str_replace("\n","<br>",$row['value'])."</td>
			</tr>";
	}

	//close the  table
	echo '	</table><br>';
	
	
	
	
	//The end of the container
	echo '
					</div><!-- /.row -->
				</div><!-- /.container -->
			</div><!-- /#t-home -->
	';


	
	
	
	
} else { // -------------------------------- GET AND LIST ALL DEVICES --------------------------------

	//Start of the container, header of the page
	echo '
			<div id="m-devices_manage">
				<div class="container">
					<div class="row">
						<div class="section_header">DEVICE LIST</div>
	';

	//page content goes here
	echo '

		<div class="pull-right">
			<a class="btn btn-orange" href="devices_manage.php" role="button">Modify Devices</a>
		</div>
		<div class="clearfix"> </div>

		<table class="reference" width="100%">';
		
	//pull the device information from mysql and draw the table
	drawDeviceTable("");

	//The end of the container
	echo '				</form>
					</div><!-- /.row -->
				</div><!-- /.container -->
			</div><!-- /#t-home -->

	';
}

include 'footer.html';
?>
