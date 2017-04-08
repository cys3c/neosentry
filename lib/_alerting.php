<?php //_alerting.php
include_once "_functions.php";

$device = $type = "";
//get command line arguments, for internal processing
//	-d: The device name or IP, -t: Type, -r: Refresh
//	Usage: php snmp.php -d "10.10.10.10" -t "full" -r
$o = getopt("d:t:r:"); // 1 : is required, 2 :: is optional
if (array_key_exists("d",$o)) $device = $o["d"];
if (array_key_exists("t",$o)) $type = $o["t"];
print_r($o);
//done getting arguments

//if ($device =="") { echo "Device name must be specified\n";exit;}


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

function sendMailFromLocal($to,$from,$subject,$body) {
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
	$headers[] = "From: $from";
	//$headers[] = "Bcc: Someone Else <bcc@domain2.com>";
	$headers[] = "Reply-To: Do Not Reply <$from>";
	$headers[] = "Subject: $subject";
	$headers[] = "X-Mailer: PHP/".phpversion();
	
	//send and return. returns true if the message was accepted, this doesn't mean it was actually delivered though.
	$ret = mail($to,$subject,$message,implode("\r\n", $headers));
	return $ret;
}



function sendMail($to, $subject, $body) {
    $strTo = "";
    $arrSettings = getSettingsArray("Mail Settings");


    require_once 'phpmailer/PHPMailerAutoload.php';
    $mail = new PHPMailer;

    //$mail->SMTPDebug = 3;                                 // Enable verbose debug output

    $mail->isSMTP();                                        // Set mailer to use SMTP
    $mail->Host = $arrSettings["Host"];    // Specify main and backup SMTP servers
    $mail->SMTPAuth = $arrSettings["SMTPAuth"];    // Enable SMTP authentication
    $mail->Username = $arrSettings["Username"];    // SMTP username
    $mail->Password = $arrSettings["Password"];    // SMTP password
    $mail->SMTPSecure = $arrSettings["Security"];  // Enable 'tls' or 'ssl'
    $mail->Port = $arrSettings["Port"];    // TCP port to connect to

    $mail->setFrom($arrSettings["From"]);

    if (is_array($to)) {                                    // Add a recipient
        foreach ($to as $value) {
            $mail->addAddress($value);
            $strTo .= $value.";";
        }
    } else {
        $mail->addAddress($to);
        $strTo = $to.";";
    }
    //$mail->addReplyTo('info@example.com', 'Information');
    //$mail->addCC('cc@example.com');
    //$mail->addBCC('bcc@example.com');

    //$mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
    //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name
    $mail->isHTML(true);                                    // Set email format to HTML

    $mail->Subject = $subject;
    $mail->Body    = $body;
    $mail->AltBody = strip_tags($body);                     //non-html alternative for non-html mail clients

    $msg = 'Message has been sent to '.$strTo." Subject: $subject";
    if ($arrSettings["Host"] == "localhost" || $arrSettings["Host"] == "") {
        //send email from the localhost
        if (!sendMailFromLocal($to, $arrSettings["From"],$subject, $body)) {
            $msg = 'Error: Message could not be sent. '.error_get_last();
        }

    } else {
        //remote host is configured, send using the php library
        if(!$mail->send()) {
            $msg = 'Error: Message could not be sent. ' . $mail->ErrorInfo;
        }
    }

    echo $msg;
    writeLogFile('email.log',$msg);
    return $msg;

}

