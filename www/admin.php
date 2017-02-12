<?php //admin.php

include "_functions.php";

$ret = $retSuccess = $retError = "";


function getCrontabInterval($stringToGrep, $defaultCronTime) {
	global $phpBin;
	$ct = trim(shell_exec('crontab -l | grep "'.$stringToGrep.'"'));
	$ct = substr($ct,0,strpos($ct,$phpBin));
	if ($ct =="") $ct = $defaultCronTime;
	return trim($ct);
}


//get crontab intervals for each script
$pingInterval = getCrontabInterval(".fping.php", "*/5 * * * *");	//every 5 minutes
$trInterval = getCrontabInterval(".traceroute.php", "0 0 * * *"); //every day at midnight
$snmpHWStatsInterval = getCrontabInterval(".snmpinfo.php?type=hwstats", "*/15 * * * *");	//every 15 minutes;
$snmpSysInterval = getCrontabInterval(".snmpinfo.php?type=sys", "0 1 * * *");	//every day at 1am;
$snmpNetInterval = getCrontabInterval(".snmpinfo.php?type=net", "*/10 * * * *");	//every 10 minutes;
$snmpUpdateInterval = getCrontabInterval(".snmpinfo.php?type=updatecommunities", "0 */12 * * *");	//every 12 hours;
$snmpRoutingInterval = getCrontabInterval(".snmpinfo.php?type=routing", "0 */1 * * *");	//every 1 hour;

//get crontab variables being passed
$updateVars = (isset($_POST['update']))?cleanSqlString(trim($_POST['update'])):"";
$pingInterval = (isset($_POST['pingfrequency']))?cleanSqlString(trim($_POST['pingfrequency'])):$pingInterval;
$trInterval = (isset($_POST['trfrequency']))?cleanSqlString(trim($_POST['trfrequency'])):$trInterval;
$snmpHWStatsInterval = (isset($_POST['snmphwstatsfrequency']))?cleanSqlString(trim($_POST['snmphwstatsfrequency'])):$snmpHWStatsInterval;
$snmpSysInterval = (isset($_POST['snmpsysfrequency']))?cleanSqlString(trim($_POST['snmpsysfrequency'])):$snmpSysInterval;
$snmpNetInterval = (isset($_POST['snmpnetfrequency']))?cleanSqlString(trim($_POST['snmpnetfrequency'])):$snmpNetInterval;
$snmpUpdateInterval = (isset($_POST['snmpupdatefrequency']))?cleanSqlString(trim($_POST['snmpupdatefrequency'])):$snmpUpdateInterval;
$snmpRoutingInterval = (isset($_POST['snmproutingfrequency']))?cleanSqlString(trim($_POST['snmproutingfrequency'])):$snmpRoutingInterval;

//update crontab
if ($updateVars=="all" || !file_exists($cron_file)) { //we update the settings
	//write the cron information
	/* EXAMPLES
		/usr/bin/php /var/www/quicknms/.ping.php
		/usr/bin/curl http://localhost/qnms/.ping.php
		/usr/bin/wget -q http://localhost/qnms/.ping.php */
	$curPath = getcwd();
	//$curURL = 'http://'.$_SERVER['HTTP_HOST'].dirname($_SERVER['PHP_SELF']);
	$curURL = 'http://localhost'.dirname($_SERVER['PHP_SELF']);
	
	//	$contents = "SHELL=/bin/sh\nPATH=/sbin:/bin:/usr/sbin:/usr/bin\nMAILTO=root\nHOME=$curPath/\n\n";
	//$contents .= $snmpUpdateInterval." $wgetBin -o $curPath/data/.snmpinfo_update.log $curURL/.snmpinfo.php?type=updatecommunities&device=all\n";
	$contents = "HOME=$curPath/\n\n";
	$contents .= "0 2 * * * $phpBin $curPath/.cleanup.php > $curPath/data/.cleanup.log\n"; //run cleanup at 2am every day
	$contents .= $pingInterval." $phpBin $curPath/.fping.php > $curPath/data/.fping.log\n";
	$contents .= $trInterval." $phpBin $curPath/.traceroute.php > $curPath/data/.traceroute.log\n";
	$contents .= $snmpUpdateInterval." $phpBin $curPath/.snmpinfo.php -dall -tupdatecommunities > $curPath/data/.snmpinfo_update.log \n";
	$contents .= $snmpSysInterval." $phpBin $curPath/.snmpinfo.php -dall -tsys > $curPath/data/.snmpinfo_sys.log\n";
	$contents .= $snmpHWStatsInterval." $phpBin $curPath/.snmpinfo.php -dall -thwstats > $curPath/data/.snmpinfo_hwstats.log\n";
	$contents .= $snmpNetInterval." $phpBin $curPath/.snmpinfo.php -dall -tnet > $curPath/data/.snmpinfo_net.log\n";
	$contents .= $snmpRoutingInterval." $phpBin $curPath/.snmpinfo.php -dall -trouting > $curPath/data/.snmpinfo_routing.log\n";
	
	//write the information to the temp file and run crontab to import it
	file_put_contents($cron_file, $contents);
	$ret = shell_exec("crontab $cron_file");
	
	if ($ret!="") {
		$retErr = "An error occurred running crontab as ".trim(`whoami`).": $ret.\n";
	} else {
		$retSuccess = "Successfully updated the crontab file.\n";
	}
}



//write and get snmp settings
//if (isset($_POST['snmpCommands'])) writeSettingValue("snmp_commands", cleanSqlString(trim($_POST['snmpCommands'])));
//$snmpCommands = getSettingValue("snmp_commands"); if ($snmpCommands=="") $snmpCommands = "snmpwalk -v2c -c public";

if (isset($_POST['snmpCommands'])) file_put_contents($snmpCommandsFile,trim($_POST['snmpCommands']));
$snmpCommands = file_exists($snmpCommandsFile)?file_get_contents($snmpCommandsFile):"snmpbulkwalk -v2c -c public";

//if (isset($_POST['snmpMibLocation'])) writeSettingValue("snmp_miblocation", cleanSqlString(trim($_POST['snmpMibLocation'])));
//$mibLocation = getSettingValue("snmp_commands"); if ($mibLocation=="") $mibLocation = "/usr/share/snmp/mibs"; // or ./data/mibs
//method 2 that doesn't work
//$ml=explode(":",trim(shell_exec("net-snmp-config --default-mibdirs")).":");
//$mibLocation = trim($ml[1]);
//if ($mibLocation == "") $mibLocation = trim($ml[0]);

if (isset($_POST['mibsubmitbtn']) && isset($_FILES['mibfile'])) {
	if ($_FILES['mibfile']['error'] > 0) {
		$retErr .= "\nError uploading MIB file: " . $_FILES['mibfile']["error"] . "";
	} else {
		$retSuccess .= "\nSuccessfully uploaded ".$_FILES['mibfile']['name']." of size ".($_FILES['mibfile']['size'] / 1024) . " KB.";
		if (file_exists($mibLocation."/".$_FILES['mibfile']['name'])) $retSuccess .= " (Overwrote previous file)";
		
		move_uploaded_file($_FILES['mibfile']['tmp_name'], $mibLocation."/".$_FILES['mibfile']['name']);
	}
}

//get and write history storage setting variables
if (isset($_POST['pingHistory'])) writeSettingValue("history_ping", intval(trim($_POST['pingHistory'])));
if (isset($_POST['trHistory'])) writeSettingValue("history_traceroute", intval(trim($_POST['trHistory'])));
if (isset($_POST['pingAlertHistory'])) writeSettingValue("history_pingalerts", intval(trim($_POST['pingAlertHistory'])));
if (isset($_POST['snmpHistory'])) writeSettingValue("history_snmp", intval(trim($_POST['snmpHistory'])));
if (isset($_POST['snmpChangeHistory'])) writeSettingValue("history_snmpchange", intval(trim($_POST['snmpChangeHistory'])));
if (isset($_POST['snmpAlertHistory'])) writeSettingValue("history_snmpalerts", intval(trim($_POST['snmpAlertHistory'])));

//get values for history storage
$pingHistory = getSettingValue("history_ping"); if ($pingHistory=="") $pingHistory=365;
$trHistory = getSettingValue("history_traceroute"); if ($trHistory=="") $trHistory=365;
$pingAlertHistory = getSettingValue("history_pingalerts"); if ($pingAlertHistory=="") $pingAlertHistory=365;
$snmpHistory = getSettingValue("history_snmp"); if ($snmpHistory=="") $snmpHistory=365;
$snmpChangeHistory = getSettingValue("history_snmpchange"); if ($snmpChangeHistory=="") $snmpChangeHistory=365;
$snmpAlertHistory = getSettingValue("history_snmpalerts"); if ($snmpAlertHistory=="") $snmpAlertHistory=365;


//DNS Settings
if (isset($_POST['dnsSave'])) {
	$dns1 = $_POST['dns1'];
	$dns2 = $_POST['dns2'];
	$dns3 = $_POST['dns3'];
	$dnsSearch = $_POST['dnssearch'];
	$fileText = "search $dnsSearch\nnameserver $dns1\nnameserver $dns2\nnameserver $dns3\n";
	//$ret = shell_exec("echo search $dnsSearch > /etc/resolv.conf");
	//shell_exec("echo nameserver $dns1 >> /etc/resolv.conf");
	//shell_exec("echo nameserver $dns2 >> /etc/resolv.conf");
	//shell_exec("echo nameserver $dns3 >> /etc/resolv.conf");
	$ret = file_put_contents("/etc/resolv.conf",$fileText);
	$retSuccess .= "Writing DNS settings to resolv.conf returned '$ret'.\n";
	
}
$dns1 = $dns2 = $dns3 = $dnsSearch = ""; $cnt = 1;
$dnsSettings = explode("\n",file_get_contents("/etc/resolv.conf"));
foreach ($dnsSettings as $row) {
	if (substr($row,0,7)=="search ") $dnsSearch = trim(substr($row,7));
	if (substr($row,0,11)=="nameserver ") { 
		if ($cnt==1) $dns1 = trim(substr($row,11));
		if ($cnt==2) $dns2 = trim(substr($row,11));
		if ($cnt==3) $dns3 = trim(substr($row,11));
		$cnt++;
	}
}

//Mail server settings
if (isset($_POST['emailfrom'])) {
	writeSettingValue("email_server", $_POST['emailserver']);
	writeSettingValue("email_from", $_POST['emailfrom']);
	//writeSettingValue("email_user", $_POST['emailuser']);
	//writeSettingValue("email_pass", $_POST['emailpass']);
	writeSettingValue("email_to", $_POST['emailto']);
	
	//write the information to the ini file. do this before sending mail.
	//ini_set("SMTP", $emailServer);
	//ini_set("smtp_port", 25);
	//ini_set("sendmail_from", $emailFrom);
}
$emailServer = getSettingValue("email_server");
$emailFrom = getSettingValue("email_from");
$emailUser = getSettingValue("email_user");
$emailPass = getSettingValue("email_pass");
$emailTo = getSettingValue("email_to");
if ($emailFrom == "") $emailFrom = "qNMS-Notifications@your-email-domain.com";

if (isset($_POST['emailTest'])) {
	$ret = sendMail($emailTo,$emailFrom,"qNMS: Test Email","<p>Testing Email Functionality.</p>");
	if ($ret==1) $retSuccess .= "Test email was successfully accepted. This does not guarantee delivery though, sendmail must be configured properly and the email server must also accept the message.\n";
	else $retError .= "Test email was not accepted. Check the servers sendmail configuration.\n";
}



//Start of the content, header of the page
echo '<div class="templatemo-top-nav-container">
          <div class="row">
            <nav id="scrollNavbar" class="templatemo-top-nav col-lg-12 col-md-12 navbar-fixed-top">
              <ul class="text-uppercase">
                    <li><a href="#section1">System Status</a></li>
                    <li><a href="#section2">Mail Settings</a></li>
                    <li><a href="#section5">Manage Users</a></li>
                    <li><a href="#section3">Ping</a></li>
                    <li><a href="#section5">SNMP</a></li>
              </ul>  
            </nav> 
          </div>
        </div>
   
       ';




//give notification if it was successful
if ($retSuccess!="") 	echo '<div class="success">'.str_replace("\n","<br>",trim($retSuccess)).'</div>';
if ($retError!="") 	echo '<div class="error">'.str_replace("\n","<br>",trim($retError)).'</div>';

if (isset($_POST['formsubmitbtn_snmp'])) {
	//scan for snmp communities.
	shell_exec("wget -b -q $curURL/.snmpinfo.php?type=updatecommunities &");
	echo '<div class="info">Kicked off the process to find a valid SNMP Community String. This typically takes 20 seconds per snmpwalk command you have configured.</div>';
}

//System Check:
$diskTotalMB = round(disk_total_space(".")/1024/1024,2);
$diskFreeMB = round(disk_free_space(".")/1024/1024,2);
if ($diskFreeMB < 100) {
	echo '<div class="error">Drive free space is CRITICALLY LOW. Remote in and free up some space.</div>'; //Drive space is less than 250 MB
} elseif ($diskFreeMB / $diskTotalMB < .10) {
	echo '<div class="info">Drive free space is under 10%. Consider cleaning up the drive.</div>';
}



//SYSTEM INFORMATION
echo '				<br><div class="section_header">SYSTEM INFORMATION</div>';

$sqlInfoQry = 'SELECT table_schema "DB Name",
	CAST(sum( data_length + index_length ) / 1024 / 1024 as DECIMAL(9,2)) "DB Size in MB",
	CAST(sum( data_free )/ 1024 / 1024 as DECIMAL(9,2)) "Free Space in MB"
	FROM information_schema.TABLES
	GROUP BY table_schema ;';
$retArr = getSqlArray($sqlInfoQry);
$retArr = end($retArr);
echo "<PRE>Web Service is running as: ".trim(`whoami`)."<br>";
echo "Web service directory is ".getcwd()."<br><br>";
echo "<div class=\"pull-left\">Database Space Used: \t{$retArr['DB Size in MB']} MB<br>Database Space Free: \t{$retArr['Free Space in MB']} MB\t<br></div>";
echo "<div class=\"pull-left\">Drive Space Total: \t".$diskTotalMB." MB<br>Drive Space Free: \t".$diskFreeMB." MB\t<br></div>";
echo "<div class=\"pull-left\">".shell_exec("cat /proc/meminfo | grep \"Mem\"")."\t</div>";
echo "<div class=\"clearfix\"></div>";
echo "<div>CRONTAB
-------------------------------
".trim(shell_exec("crontab -l"))."</div>";
echo "\n<div>DNS Settings from /etc/resolv.conf
-------------------------------
".trim(shell_exec("cat /etc/resolv.conf"))."</div>";
echo "</PRE>";

//Start the form
echo '<form class="form-horizontal" action="admin.php" method="post" enctype="multipart/form-data"><input type="hidden" name="update" value="all" />';

//DNS SETTINGS
/*
echo '				<br><div class="section_header">DNS SETTINGS</div>';
echo "	<table class='reference' width='100%'>
			<tr>
				<th width=200>Nameserver 1</th>
				<th width=200>Nameserver 2</th>
				<th width=200>Nameserver 3</th>
				<th>Search Suffixes</th>
				<th></th>
			</tr>
			<tr>
				<td><input type=text name=\"dns1\" value=\"$dns1\"></td>
				<td><input type=text name=\"dns2\" value=\"$dns2\"></td>
				<td><input type=text name=\"dns3\" value=\"$dns3\"></td>
				<td><input style=\"width:100%;\" title=\"Divide multiple entries with a space.\" type=text name=\"dnssearch\" value=\"$dnsSearch\"></td>
				<td><input type=\"submit\" name=\"dnsSave\" class=\"btn btn-orange pull-right\" value=\"SAVE\"/></td>
			</tr>
		</table><br>

"; //*/

//MAIL SERVER SETTINGS
echo '				<br><div class="section_header">EMAIL SETTINGS</div>';
//Ping interval, traceroute interval
/* echo "	<table class='reference' width='100%'>
			<tr>
				<th>Mail Server</th>
				<th>From Email</th>
				<th>Username</th>
				<th>Password</th>
				<th></th>
			</tr>
			<tr>
				<td><input type=text name=\"emailserver\" value=\"$emailServer\"></td>
				<td><input type=text name=\"emailfrom\" value=\"$emailFrom\"></td>
				<td><input type=text name=\"emailuser\" value=\"$emailUser\"></td>
				<td><input type=password name=\"emailpass\" value=\"$emailPass\"></td>
				<td>Leave the username/password blank for no authentication</td>
			</tr>
		</table><br>

"; */
echo "	<table class='reference' width='100%'>
			<tr>
				<th width=200>Email Server</th>
				<th width=200>From Email</th>
				<th width=200>TO Email</th>
				<th></th>
			</tr>
			<tr>
				<td><input type=text name=\"emailserver\" value=\"$emailServer\">*</td>
				<td><input type=text name=\"emailfrom\" value=\"$emailFrom\"></td>
				<td><input type=text name=\"emailto\" value=\"$emailTo\"></td>
				<td>*Additional sendmail configuration may be needed. <input type=\"submit\" name=\"emailTest\" class=\"btn btn-orange pull-right\" value=\"TEST\"/></td>
			</tr>
		</table><br>

";



//PING AND TRACEROUTE MONITORING
echo '				<br><div class="section_header">PING / TRACEROUTE MONITORING</div>';
//Ping interval, traceroute interval
echo "	<table class='reference' width='100%'>
			<tr>
				<th width=20%>Update Frequency</th>
				<th>These values are cronjob times without the final CMD portion. More information can be found <a href='http://www.thegeekstuff.com/2009/06/15-practical-crontab-examples/' target='_blank'>here</a>.</th>
			</tr>
			<tr><td><b>Ping</b> interval:</td><td><input type=text name=\"pingfrequency\" value=\"$pingInterval\"> *Default [*/5 * * * *] = run every 5 minutes.</td></tr>
			<tr><td><b>Traceroute</b> interval:</td><td><input type=text name=\"trfrequency\" value=\"$trInterval\"> *Default [0 0 * * *] = run every day at midnight.</td></tr>
		</table><br>
		<table class='reference' width='100%'>
			<tr><th width=20%>History to Store</th><th>Values are # of days. Use 0 for indefinite.</th></tr>
			<tr><td>Ping history to keep:</td><td><input type=number name=\"pingHistory\" value=\"$pingHistory\"></td></tr>
			<tr><td>Traceroute change history to keep:</td><td><input type=number name=\"trHistory\" value=\"$trHistory\"></td></tr>
			<tr><td>Alert history to keep:</td><td><input type=number name=\"pingAlertHistory\" value=\"$pingAlertHistory\"> *These are the alerts generated from ping or traceroute.</td></tr>
		</table>
";
	
	

//SNMP MONITORING
echo '				<br><div class="section_header">SNMP MONITORING</div>';
//snmpwalk interval, snmpwalk commands to cycle through, mib list
//MIB Location Command: net-snmp-config --default-mibdirs
//MIB LOCATIONS: 	$HOME/.snmp/mibs
//					/usr/share/snmp/mibs (maybe /usr/local/share/snmp/mibs)


echo "	
		<table class='reference' width='100%'>
			<tr>
				<th width=20%>Update Frequency</th>
				<th>These values are cronjob times without the final CMD portion. More information can be found <a href='http://www.thegeekstuff.com/2009/06/15-practical-crontab-examples/' target='_blank'>here</a>.</th>
			</tr>
			<tr><td><b>Update Communities</b> interval:</td><td><input type=text name=\"snmpupdatefrequency\" value=\"$snmpUpdateInterval\"> *Default [0 */12 * * *] = run every 12 hours. Cycles through the connection strings to choose the one that works.</td></tr>
			<tr><td><b>System Information</b> interval:</td><td><input type=text name=\"snmpsysfrequency\" value=\"$snmpSysInterval\"> *Default [0 1 * * *] = run every Day at 1AM. Gets system Description, Name, Contact, Location, and Uptime.</td></tr>
			<tr><td><b>HW Stats Information</b> interval:</td><td><input type=text name=\"snmphwstatsfrequency\" value=\"$snmpHWStatsInterval\"> *Default [*/15 * * * *] = run every 15 minutes. Gets CPU, Mem, HD, Power Supply, etc. Stats.</td></tr>
			<tr><td><b>Network Information</b> interval:</td><td><input type=text name=\"snmpnetfrequency\" value=\"$snmpNetInterval\"> *Default [*/10 * * * *] = run every 10 minutes.</td></tr>
			<tr><td><b>Routing Information</b> interval:</td><td><input type=text name=\"snmproutingfrequency\" value=\"$snmpRoutingInterval\"> *Default [0 */1 * * *] = run every hour.</td></tr>
			
		</table><br>
		
		<table class='reference' width='100%'>
			<tr><th width=20%>History to Store</th><th>Values are # of days. Use 0 for indefinite.</th></tr>
			<tr><td>SNMP <b>data history</b> to keep:</td><td><input type=number name=\"snmpHistory\" value=\"$snmpHistory\"></td></tr>
			<tr><td>SNMP <b>change history</b> to keep:</td><td><input type=number name=\"snmpChangeHistory\" value=\"$snmpChangeHistory\"></td></tr>
			<tr><td>SNMP <b>Alert history</b> to keep:</td><td><input type=number name=\"snmpAlertHistory\" value=\"$snmpAlertHistory\"></td></tr>
		</table><br>
		
		<table class='reference' width='100%'>
			<tr>
				<th width=20%>Other Options</th>
				<th></th>
			</tr>
			<tr>
				<td><b>snmpwalk</b> connection string commands to cycle through:<br><br><i>Each line will be tried until one is successful. Must start with 'snmpwalk' and contain the connection information. The IP will be appended at runtime.</i></td>
				<td><textarea  name=\"snmpCommands\" class=\"form-control\" style=\"height:150px;width:100%\" placeholder=\"ex: snmpwalk -v2c -c public\">".$snmpCommands."</textarea>".
				'<input type="submit" name="formsubmitbtn_snmp" class="btn btn-orange pull-right" value="Save & Run SNMP Community Update"/>
				<div class="clearfix" />'."</td>
			</tr>
			<tr>
				<td><b>Import a MIB file:</b></td><td><input type=file name=\"mibfile\" class=\"pull-left\" width=50%><input type=submit name=\"mibsubmitbtn\" value=\"Add MIB\" class=\"btn btn-orange pull-right\"></td>
			</tr>
			<tr>
				".'<script>$(document).ready(function() {  $("#miblist").hide(); $("#mibshow").click(function(){$("#miblist").show();$("#mibshow").hide();}); });</script>'."
				<td><b>Current MIB List:</b></td><td><pre><b>MIBs are located in ".$mibLocation."</b><br><div class=clickable id=mibshow>show</div><div id=miblist><br>".shell_exec("ls -lho ".$mibLocation)."</div></pre></td>
			</tr>
		</table><br>

";


//SERVICE MONITORING
echo '				<br><div class="section_header">SERVICE MONITORING</div>';
//nmap command?, service monitoring interval (scans only the ports on the device list)


//VULNERABILITY SCANNING with OpenVAS
//echo '				<br><div class="section_header">VULNERABILITY SCANNING</div>';

//CONFIGURATION MANAGEMENT / CHANGE LOG
//echo '				<br><div class="section_header">CONFIGURATION AND CHANGE LOG MANAGEMENT</div>';


echo '
		<input type="submit" name="formsubmitbtn" class="btn btn-orange pull-right" value="SAVE CHANGES"/>
		<div class="clearfix" />
		</form>';



//The end of the container
echo '
				</div><!-- /.row -->
			</div><!-- /.container -->
		</div><!-- /#t-home -->

';




?>