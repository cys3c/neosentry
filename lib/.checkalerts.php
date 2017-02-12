<?php //.checkalerts.php
//include "_functions.php";

$device = $type = "";
//get command line arguments, for internal processing
//	-d: The device name or IP, -t: Type, -r: Refresh
//	Usage: php .snmpinfo.php -d "10.10.10.10" -t "full" -r
$o = getopt("d:t:r:"); // 1 : is required, 2 :: is optional
if (array_key_exists("d",$o)) $device = $o["d"];
if (array_key_exists("t",$o)) $type = $o["t"];
print_r($o);
//done getting arguments

//if ($device =="") { echo "Device name must be specified\n";exit;}

//get the mail settings
$emailServer = getSettingValue("email_server");
$emailFrom = "qNMS-Email-Agent@tycoint.com";//getSettingValue("email_from");
$emailUser = getSettingValue("email_user");
$emailPass = getSettingValue("email_pass");
$emailTo = "danelliott@tycoint.com";//getSettingValue("email_to");
$subject = "qNMS: Test Email";
$message = '<p>This is just a test</p>';

//test email
//$ret = mail($emailTo,$subject,$message,implode("\r\n", $headers));
//$ret = sendMail($emailTo,$emailFrom,"qNMS Test Email",$message);
//echo "mail sent, returned $ret";

function checkAlerts($device, $logCategory, $logText) {
	//check if we have a new alert first
	if ($logCategory=="ping") {
		if (strpos($var,"DOWN")!==False) {
			//device went down, lets create an alert
		}
	}
	//sendMail($emailTo,$emailFrom,"qNMS Alert:  Test Email",$message);
	
	//next check if there's any active alerts and if so then lets see if any are resolved.
}

function sendMail($to,$from,$subject,$body) {
	//create the html message
	$message = '<html>
	<head>
	   <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	   <title>'.$subject.'</title>
	</head>
	<body>'.$body.'
	</body>
	</html>';

	//create the headers
	$headers = array();
	$headers[] = "MIME-Version: 1.0";
	$headers[] = "Content-type: text/html; charset=utf-8"; //Content-type: text/html; charset=utf-8 || Content-type: text/plain; charset=iso-8859-1
	$headers[] = "From: qNMS <$from>";
	//$headers[] = "Bcc: JJ Chong <bcc@domain2.com>";
	$headers[] = "Reply-To: qNMS Do Not Reply <$from>";
	$headers[] = "Subject: $subject";
	$headers[] = "X-Mailer: PHP/".phpversion();
	
	//send and return. returns true if the message was accepted, this doesn't mean it was actually delivered though.
	$ret = mail($to,$subject,$message,implode("\r\n", $headers));
	return $ret;
}

?>