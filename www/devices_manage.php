<?php //index.php

include 'header.html';

//Start of the container, header of the page
echo '
		<div id="m-devices_manage">
            <div class="container">
                <div class="row">
                    <div class="section_header">MANAGE DEVICES</div>
';



//some variables
$device_list_cur = "$dataFolder/backups/device_list_changes/device_list";
$device_list_backup = $device_list_cur."_".date("Y-m-d_H.i.s"); //date like 2001-03-31_12.55.00
$device_ip_file = "$dataFolder/device_ip_list";
$device_ip_list = "";

$retRem = $retAdd = "";
$retFail = false;

//get the devices
$devices = "#Each Entry should be in the following order (Trailing and leading Spaces and Tabs are removed):
#Country: Site: ObjType (Router, Switch, Firewall, Server, etc): DeviceName: IP: SNMP Monitor?: Ports to Monitor
#Note:	putting 'yes' for SNMP Monitor will cycle through the snmp settings you have under the Admin section until it finds a working one and will store that.

#Example:
#AMERICAS:\tBoca Raton, FL:\tRouter:\tBCRT1:\t10.38.27.254:\tyes:\t21,22,80,443\n";


//write the device list from POST
$devicelist = (isset($_POST['devicelist']))?$_POST['devicelist']:""; //file data to add

//echo "\nDEVICE LIST:\n$devicelist\n!";
if ($devicelist != "") {
	//create the directory structure
	if (!file_exists("$dataFolder/backups/device_list_changes")) mkdir("$dataFolder/backups/device_list_changes", 0777, true);
	
	//erase the IP list so we can update it using append
	file_put_contents($device_ip_file, "");
	
	//put the info in sql
	$lines = explode("\n", $devicelist);
	for ($x=0;$x<count($lines);$x++){
		if (substr($lines[$x],0,1) != "#") {
			//get the elements
			$elements = explode(":",$lines[$x]."::::::::::");
			
			if (strlen($elements[4])>=8) { // IP must be 8 or more characters
				//Add or Update the device
				$retAdd = addDevice(trim($elements[0]),trim($elements[1]),trim($elements[2]),trim($elements[3]),trim($elements[4]),trim($elements[5]),trim($elements[6]),trim($elements[7]));
				//echo "Add Device Returned: ".$ret.'<br>';
				if ($retAdd != 1) $retFail = true;
				
				//update the IP list
				$device_ip_list .= trim($elements[4])." ";
				file_put_contents($device_ip_file, trim($elements[4])."\n", FILE_APPEND);
			}
		}
	}
	
	//remove devices that are no longer in the list
	$retRem = removeDevices($device_ip_list);
	
	//Error or Success?
	if ($retFail) {
		echo '<div class="error">One or more device updates / additions have FAILED</div><br>';
	} else {
		echo '<div class="success">Update SUCCESS.</div>';
	}
	
	
	//if there's no errors and a change was made lets update the current and back up the previous
	$devices = file_get_contents($device_list_cur);
	if ($devices != $devicelist) {
		echo `mv -f $device_list_cur $device_list_backup`;
		file_put_contents($device_list_cur, $devicelist); //add ,FILE_APPEND to append
		
		//Call the admin page to update the crontab if we haven't done this already
		if (!file_exists($cron_file)) 	shell_exec("php admin.php &");
		
		//Find the SNMP Community Strings
		shell_exec("wget -b -q http://localhost/quicknms/.snmpinfo.php?type=updatecommunities &");
		echo '<div class="info">Kicked off the process to find a valid SNMP Community String. This typically takes 20 seconds per snmpwalk command you have configured.</div>';
		
	}
}


//read the device list. This is also stored in SQL, this method is quicker, easier, and allows for comments.
if (file_exists($device_list_cur)) $devices = file_get_contents($device_list_cur);





//page content goes here
echo '

	<form class="form-horizontal" action="devices_manage.php" method="post">
		<div class="form-group">
			<center><textarea  name="devicelist" class="form-control" style="height:500px;width:97.5%" placeholder="Device List">'.$devices.'</textarea></center>
		</div>
		<input type="submit" class="btn btn-orange pull-right" value="SUBMIT"/>
		<p class="pull-right">&nbsp;</p>
	</form>
	<a href="device_list.php"><button class="btn btn-orange pull-right">CANCEL</button></a>
	<div class="clearfix"></div>
';

//The end of the container
echo '
                </div><!-- /.row -->
            </div><!-- /.container -->
        </div><!-- /#t-home -->

';

include 'footer.html';
?>
