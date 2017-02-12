<?php //tools.php

include '_functions.php';
include '_functions_snmp.php';

//get the variables
$device = (isset($_GET['device']))?cleanSqlString(trim($_GET['device'])):""; //file data to add
if ($device=="") $device = (isset($_POST['device']))?cleanSqlString(trim($_POST['device'])):"all"; //file data to add

$action = (isset($_GET['action']))?cleanSqlString(trim($_GET['action'])):""; //file data to add
if ($action=="") $action = (isset($_POST['action']))?cleanSqlString(trim($_POST['action'])):""; //file data to add






//run the command only for ajax requests, allows the page to load
if (isset($_GET['ajax']) || isset($_POST['ajax'])) {
	switch($action) {
		case "nmap":
			$c1 = shell_exec("nmap -PN -PE -PP -PM $device"); // nmap -sSU -sV -O -PE -PP -PM $device
			//$c1 = shell_exec("nmap -sV -PE -PP -PM $device");
			echo "NMap Scan<pre>$c1</pre>";
			break;
			
		case "snmp":
			$snmpStr = getSqlValue("SELECT snmpcommunity FROM devicelist WHERE ip='$device' limit 1;");
			$snmpFile = "./data/device_scan_data/snmp_".$device."_full";
			$c1 = "No Data";
			if (!file_exists($snmpFile)) {
				if (substr($snmpStr,0,8)=="snmpwalk") {
					shell_exec("$snmpStr $device > $snmpFile");
					//$c1 = file_get_contents($snmpFile);
					showSnmpFile($snmpFile);
				} else { 
					echo "<pre>Valid SNMP Community String is not present</pre>";
				}
			} else {
				//$c1 = file_get_contents($snmpFile);
				showSnmpFile($snmpFile);
			}
			//echo "<pre>$c1</pre>";
			
			break;
			
		case "dig":
			$c1 = shell_exec("nslookup $device");
			$c2 = shell_exec("nslookup $device 8.8.8.8"); //google dns lookup
			$c3 = shell_exec("dig $device");
			$c4 = shell_exec("dig -t MX $device"); //mx record
			$c5 = shell_exec("whois $device");
			$c6 = shell_exec("curl ipinfo.io/$device");
			echo "	<p>IP Info</p><pre>";
			echo "$c6\n";
			echo "--------------------------------------------\n";
			echo "$c1\n$c2\n";
			echo "--------------------------------------------\n";
			echo "$c3\n";
			echo "--------------------------------------------\n";
			echo "$c4\n";
			echo "--------------------------------------------\n";
			echo "$c5";
			echo "</pre>";
			break;
			
		default:
			echo "Invalid Action.";
	}
	exit;
}


//print the html
echo '<html lang=en>
	<head><title>TycoNMS: Tools - $device - $action</title>
		<link rel="icon" href="images/favicon.ico" type="image/x-icon"/>
		
		<!-- Bootstrap core CSS -->
		<link href="css/bootstrap.css" rel="stylesheet" type="text/css">

		<!-- Custom styles for this template -->
		<link href="js/colorbox/colorbox.css"  rel="stylesheet" type="text/css">
		<link href="css/templatemo_style.css"  rel="stylesheet" type="text/css">
		<link href="css/datadisplay.css"  rel="stylesheet" type="text/css">
		<link href="css/jquery.dynatable.css"  rel="stylesheet" type="text/css">
		
		<!-- Scripts -->
		<script src="js/jquery-1.11.1.min.js"></script>
		<script type="text/javascript" src="js/jquery.dynatable.js"></script>
		<script type="text/javascript" src="js/canvasjs.min.js"></script>
		<script src="js/functions.js"></script>
		

		<!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
		<!--[if lt IE 9]>
		  <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
		  <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
		<![endif]-->

	</head>
	<BODY>';

//Start of the container, header of the page
echo '
	<div id="m-devices_manage">
		<div class="container">
			<div class="row">';
					
//display the header
echo "<form action='tools.php'>
	<div width=100% height=100px>
		<h4><span>Device Name/IP: <input type='text' name='device' value='$device'></span>&nbsp;&nbsp;&nbsp;
		<span>Action: <select name='action' value='$action'>
			<option value='dig' "; if($action=="dig") echo "selected"; echo ">Dig</option>
			<option value='nmap' "; if($action=="nmap") echo "selected"; echo ">NMAP Scan</option>
			<option value='snmp' "; if($action=="snmp") echo "selected"; echo ">SNMP Query</option>
		</select>&nbsp;&nbsp;&nbsp;<input type=submit value='Submit' class=''></span></h4>
	</div>
</form>";
	
//display the content
echo '<div id=content style="width:100%; height:200px;"></div>	<script>getContent("tools.php?device='.$device.'&action='.$action.'&ajax=true","content");</script>';



echo '</div></div></div></body></head>';
?>