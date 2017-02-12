<?php //index.php

include 'header.html';
include '_functions_snmp.php';

$retSuccess = $retError = "";

function drawDeviceTable($whereQry) {
		//pull the device information from mysql
	//$devArr = getDevicesArray($whereQry);
	$devArr = getSqlArray("SELECT a.dateadded,a.country,a.site,a.objtype,a.devicename,a.ip,a.monitorsnmp,a.monitorports,a.snmpcommunity,a.pingstatus,a.pingtimestamp,a.ifthroughputin,a.ifthroughputout,
			b.Load1, b.Load5, b.Load15,
			c.AvailReal, c.TotalReal,
			d.ifInErrorsDiff, d.ifOutErrorsDiff, d.ifOutQLenDiff
		FROM `devicelist` a
		LEFT JOIN device_cpu b ON a.ip = b.device
		LEFT JOIN device_mem c ON a.ip = c.device
		LEFT JOIN (SELECT device, SUM(ifInErrorsDiff) as ifInErrorsDiff,SUM(ifOutErrorsDiff) as ifOutErrorsDiff,SUM(ifOutQLenDiff) as ifOutQLenDiff FROM device_iftable GROUP BY device) d ON a.ip = d.device
		$whereQry ORDER BY country,site,objtype,devicename ASC;"); //JOIN device_mem, device_cpu, device_if (sum errors)
	
	//get distinct information for sorting
	//$sortCountry = getSqlArray("SELECT DISTINCT(country) FROM devicelist");
	
	//put the column headers
	echo "	<table class='reference' id='device_table' width='100%'>
			<thead><tr>
				<th>Ping</th>
				<th>Date Added</th>
				<th>Country</th>
				<th>Site</th>
				<th>Device Type</th>
				<th>Device Name</th>
				<th>IP</th>
				<th>Services</th>
				<th>SNMP</th>
				<th>CPU</th>
				<th>MEM</th>
				<th title=\"Current Throughput of all interfaces in Mbps\">IF [In/Out]</th>
			</tr></thead>";

	//output the device info 
	foreach ($devArr as $row) {
		//ping status
		$row['pingtimestamp'] = date("D M j @ g:i:s a Y",strtotime($row['pingtimestamp']));
		$imgtag = '<img src=images/s_ok.png title="Host is Up. Last Successful Ping on '.$row['pingtimestamp'].'"/>';
		if ($row['pingstatus']=="unreachable") { 
			$imgtag = '<img src=images/s_critical.png title="Host is Unreachable. Last FAILED Ping on '.$row['pingtimestamp'].'"/>';
			$row['pingstatus'] = "down";}
		if (trim($row['pingstatus'])=="") $imgtag = '<img src=images/s_pending.png title="Device has not been pinged yet. Must be newly added :)"/>';
		
		//snmp info images: s_up.png, s_down.png
		if ($row['monitorsnmp']!="y") {
			$snmptag = '<img src=images/s_pending.png title="SNMP is not being monitored. Change this under [Modify Devices]."/>';
			//nullify health stats since snmp is down
		} else {
			$snmptag = '<img src=images/s_critical.png title="No valid snmpwalk command worked.&#013;{'.$row['snmpcommunity'].'}"/>';
			if (substr($row['snmpcommunity'],0,8)=="snmpwalk") $snmptag = '<img src=images/s_ok.png title="An snmpwalk string is working. {^_^}&#013;'.$row['snmpcommunity'].'"/>';
			if (trim($row['snmpcommunity'])=="") $snmptag = '<img src=images/s_info.png title="Patience.. community strings have not been updated yet if you just added a device. This can also be forced under Admin->SNMP"/>';
		}
		
		//health info
		if (substr($row['snmpcommunity'],0,8)=="snmpwalk") {
			//interface stats
			$ifIn = round($row['ifthroughputin'] * 8 / 1024 / 1024,2);
			$ifOut = round($row['ifthroughputout'] * 8 / 1024 / 1024,2);
			$ifHealthStr = "$ifIn Mb / $ifOut Mb";
			$ifHealth = '<img src=images/s_ok.png title="No Errors on the interfaces.">';
			if($row['ifOutQLenDiff']>0) $ifHealth='<img src="images/s_warning.png" title="The Output Queue Length has Increased.">';
			$errTot=$row['ifInErrorsDiff']+$row['ifOutErrorsDiff'];
			if ($errTot > 0 ) $ifHealth = '<img src=images/s_critical.png title="There are '.$errTot.' current Errors on this interface.">';
			//memory
			$memHealth = '<img src="images/s_ok.png" title="memory does not appear to be overly utilized.">';
			$pmem = -1;
			if ($row['TotalReal']>0) $pmem = round(100-($row['AvailReal']/$row['TotalReal']*100),0);
			if ($pmem > 90) $memHealth = '<img src="images/s_warning.png" title="Memory availability is under 10%!">';
			if ($pmem > 98) $memHealth = '<img src="images/s_critical.png" title="Memory usage is at capacity! Could be a memory leak, you should probably check this out.">';
			if ($pmem == -1) $memHealth = '<img src="images/s_pending.png" title="No memory information collected. SNMPMAP file may need updated to include this vendor.">';
			$memHealthStr = $pmem.'%';
			//cpu
			$hLoad = $row['Load1']; if($row['Load5']>$hLoad) $hLoad = $row['Load5']; if($row['Load15']>$hLoad) $hLoad = $row['Load15'];
			$cpuHealthStr = round($hLoad * 100,2).'%';
			$cpuHealth = '<img src=images/s_ok.png title="Recent max load is normal.">';
			if ($hLoad > .90) $cpuHealth = '<img src="images/s_warning.png" title="CPU Usage is above 90%!">';
			if ($hLoad > .98) $cpuHealth = '<img src="images/s_critical.png" title="CPU Usage is peaked! Check this out immediately, this can cause packet loss.">';
			if ($hLoad == null) $cpuHealth = '<img src="images/s_pending.png" title="No CPU information collected. SNMPMAP file may need updated to include this vendor.">';
		} else {
			//nullify health stats since snmp isn't active
			$cpuHealth = $memHealth = $ifHealth = '<img src=images/s_pending.png title="SNMP is not being monitored. Change this under [Modify Devices]."/>';
			$cpuHealthStr = $memHealthStr = $ifHealthStr = "";
		}
		
		//display the table info
		echo "<tr>
				<td>$imgtag {$row['pingstatus']}</td>
				<td title='{$row['dateadded']}'>".date("M d, Y",strtotime($row['dateadded']))."</td>
				<td>{$row['country']}</td>
				<td>{$row['site']}</td>
				<td>{$row['objtype']}</td>
				<td>{$row['devicename']}</td>
				<td><a href=?device={$row['ip']}>{$row['ip']}</a></td>
				<td>{$row['monitorports']}</td>
				<td>$snmptag</td>
				<td>$cpuHealth $cpuHealthStr</td>
				<td>$memHealth $memHealthStr</td>
				<td>$ifHealth $ifHealthStr</td>
			</tr>";
	}

	//close the  table
	echo '	</table>';
}




//get variables being passed
$edit = (isset($_GET['edit']))?TRUE:FALSE; //Are we in edit mode?
$device = (isset($_GET['device']))?cleanSqlString(trim($_GET['device'])):""; //file data to add
$device = (isset($_POST['device']))?cleanSqlString(trim($_POST['device'])):$device; //file data to add
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
	
	//Start a form if we are in edit mode
	if ($edit) echo '<form class="form-horizontal" action="device_list.php?device='.$device.'" method="post">
		<input type="hidden" name="update" value="all" />';
	
	//see if we have the option to edit this device
	$editLink = "<a href='device_list.php?device=$device&edit=true'>Edit This Device</a>";
	if ($edit)	$editLink = "<a href='device_list.php?device=$device'>Done Editing</a>";
	
	
	
	//DEVICE INFO
	echo '<span id="device-info"></span>
		<div class="section_header">DEVICE INFO: <b>'.$device.'</b>
			<span class="pull-right"><b>'.$editLink.'</b></span>
		</div>';
	//edit link
	
		
	//pull the device information from mysql and draw the table
	drawDeviceTable("WHERE ip='$device'");
	
	//put snmp system info if we have it:
	echo "<br>";
	$file_snmp_sys = "./data/device_scan_data/snmp_".$device."_system";
	if (file_exists($file_snmp_sys)) showSnmpTable($device, $file_snmp_sys, "System");
	
	//add the notes / description section
	echo "<table class='reference' width='100%'>
			<tr><td valign=top width=200px>Custom Notes and Description:</td>
			<td>";
	if ($edit) {
		echo "<textarea name=notes class=\"form-control\" style=\"height:75px;width:100%\" placeholder=\"Additional notes or description on this device\">".$deviceNotes."</textarea>
		<input type=submit name=\"btnsubmitnotes\" value=\"Update Notes\" class=\"btn btn-orange pull-right\">";
	} else {
		if ($deviceNotes=="")
			echo "<span class=subtle><i>Click on Edit in the upper right to add custom notes.</i></span>";
		else
			echo $deviceNotes;
	}
	echo "</td></tr></table>";

	//popup window code
	echo "<script type='text/javascript'>
			function newPopup(url) { popupWindow = window.open(url,'popUpTools','height=700,width=800,left=10,top=10,resizable=yes,scrollbars=yes,toolbar=yes,menubar=no,location=no,directories=no,status=yes')}
		</script>";

	
	//TOOLS AND ACTIONS
	echo '<span id="actions"></span><br><div class="section_header">ACTIONS / TOOLS</div>';
	echo '<SPAN class="tool_link"><a href="telnet://'.$device.':22"><img border="0" src="images/t_console.png" title="SSH to this device. Requires Registry Fix."> SSH</a> <span class="subtle" style="font-size:10px;">[Fix: <a href="putty_telnet_win7_32bit.reg" download="putty_fix_win7_32bit.reg">32</a> | <a href="putty_telnet_win7_64bit.reg" download="putty_fix_win7_64bit.reg">64</a>]</span></SPAN>
	<SPAN class="tool_link"><img border="0" src="images/t_web.png" title="Run/View a full nmap scan"> Browse: <a href="http://'.$device.'/" target="_blank">HTTP</a>, <a href="https://'.$device.'/" target="_blank">HTTPS</a>, <a href="ftp://'.$device.'/" target="_blank">FTP</a></SPAN>
	<SPAN class="tool_link"><a href="JavaScript:newPopup(\'tools.php?device='.$device.'&action=nmap\');"><img border="0" src="images/t_nmap.png" title="Run/View a full nmap scan"> NMAP Scan</a></SPAN>
	<SPAN class="tool_link"><a href="JavaScript:newPopup(\'tools.php?device='.$device.'&action=snmp\');"><img border="0" src="images/t_snmp.png" title="Run/View a full snmp scan"> SNMP Scan</a></SPAN>
	<SPAN class="tool_link"><a href="JavaScript:newPopup(\'tools.php?device='.$device.'&action=dig\');"><img border="0" src="images/t_dig.png" title="Get info on this IP"> DIG</a></SPAN>
	<br>
	';

	
	//PING AND TRACEROUTE
	echo '<span id="ping-traceroute"></span><br><div class="section_header">PING / TRACEROUTE MONITORING</div>';
	//Show the graph of the ping history
	echo '<div id=graph_ping style="width:100%; height:150px;"></div><script>getContent("graph.php?device='.$device.'&type=ping&timeframe=day","graph_ping");</script>';
	
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
		
	
	
	
	
	//SNMP INFO
	echo '<span id="snmp-info"></span><br><div class="section_header">SNMP INFO</div>';
	
	$file_snmp = "./data/device_scan_data/snmp_".$device;
	
	//showSnmpFile($file_snmp."_system"); //shows all tables within. good for full scan
	//echo "<h4><b>System</b> Information</h4>";
	//showSnmpTable($device, $file_snmp."_system", "System");
	
	//INTERFACE info and Graph
	echo "<span id='snmp-interface'></span><h4><b>Interface</b> Information</h4>";
	echo '<div id=graph_if style="position:relative;width:100%; height:200px;"></div><script>getContent("graph.php?device='.$device.'&type=if&timeframe=day","graph_if");</script>';
	showSnmpTable($device, $file_snmp."_iftable", "Network");
	echo "<span id='snmp-routing'></span><h4><b>Routing Table</b> Information</h4>";
	showSnmpTable($device, $file_snmp."_ip.ipRouteTable", "Routing Table");
	
	//CPU info and Graph
	echo "<span id='snmp-cpu'></span><h4><b>CPU</b> Stats</h4>";
	echo '<div id=graph_cpu style="width:100%; height:150px;"></div><script>getContent("graph.php?device='.$device.'&type=cpu&timeframe=day","graph_cpu");</script>';
	showSnmpTable($device, $file_snmp."_cpu", "CPU");
	
	//Mem info and Graph
	echo "<span id='snmp-mem'></span><h4><b>Memory</b> Stats</h4>";
	echo '<div id=graph_mem style="width:100%; height:150px;"></div><script>getContent("graph.php?device='.$device.'&type=mem&timeframe=day","graph_mem");</script>';
	showSnmpTable($device, $file_snmp."_mem", "Memory");
	
	//HDD Info
	echo "<span id='snmp-hdd'></span><h4><b>HDD</b> Stats</h4>";
	showSnmpTable($device, $file_snmp."_hdd", "HDD");
	
	//echo '<code>'.$res.'</code>';
	if (file_exists($file_snmp."_other")) {
		echo "<span id='snmp-other'></span><h4><b>Custom</b> Info</h4>";
		showSnmpTable($device, $file_snmp."_other", "OTHER");
	}
	//output the custom information grabbed, defined in snmpmap
	
	
	
	//NMAP SCAN / SERVICE MONITORING
	echo '<span id="nmap"></span><br><div class="section_header">SERVICE MONITORING</div>';
	
	//echo "<span id=port_scan></span><script>getContent('.nmap.php','port_scan');</script>"; //
	$file_nmap = "./data/device_scan_data/nmap_".$device;
	$res = ""; //nmap -sSU -sV -O -PE -PP -PM $device
	if (file_exists($file_nmap)) $res = file_get_contents($file_nmap);
	echo '<blockquote><pre>';
	echo $res;
	echo '</pre></blockquote>';
	
	
	
	//ALERTS
	echo '<span id="alerts"></span><br><div class="section_header">ALERTS</div>';
	
	
	
	
	//LOGS
	echo '<span id="logs"></span><br><div class="section_header">LOGS FOR THIS DEVICE</div>';
	$logRows = getSqlValue("SELECT count(*) FROM log WHERE device='$device'");
	$arrLogs = getSqlArray("SELECT ts,type,value FROM log WHERE device='$device' ORDER BY ts DESC LIMIT 0, 19;");
	
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
						<div class="section_header">DEVICE LIST 
							<span class="pull-right"><a href="devices_manage.php" role="button"><b>Modify Devices</b></a></span>
						</div>
						<div class="clearfix" /><br>
	';
	//to make a button: <a class="btn btn-orange" href="devices_manage.php" role="button">Modify Devices</a>

	
		//graph.php?device=209.82.42.101&type=ping&timeframe=day
	//pull the device information from mysql and draw the table
	drawDeviceTable("");
	
	//Add dynatable: id=device_table, class=reference
	//for dynatable to find the headers, it must be in this form: <thead><tr><th>head1</th><th>head2</th></tr></thead>
	echo '
	
	<script type="text/javascript">
		$(".reference").dynatable({
        dataset: {
            //ajax: true,
            //ajaxUrl: appPrefixed("/a/system/indices/failures/dynatable"),
            //ajaxOnLoad: true,
            //records: [],
            perPageDefault: 50
        },
		inputs: {
            perPageText: "Per page: ",
			queryEvent: "blur change",
			//paginationLinkTarget: null,
			//paginationLinkPlacement: "after",
			//paginationPrev: "Previous",
			//paginationNext: "Next",
			//paginationGap: [1,2,2,1],
			//searchTarget: null,
			searchPlacement: "before",
			searchText: "Filter: ",
			//perPageTarget: null,
			perPagePlacement: "before"
			//recordCountText: "Showing ",
			//processingText: "Processing..."
        },
        features: {
            //sort: false,
            //pushState: true,
            //search: false
        }
    });
	</script>';
	
	//The end of the container
	if ($edit) echo "</form>";
	echo '			</div><!-- /.row -->
				</div><!-- /.container -->
			</div><!-- /#t-home -->

	';
}

include 'footer.html';
?>
