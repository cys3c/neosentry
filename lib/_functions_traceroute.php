<?php //_functions_traceroute.php - traceroute functions


function doTraceroute($device){
	//$tr1cmd = "traceroute -n -N30 -q1";
	//$tr2cmd = "traceroute -n -N30 -q1 -T -p80";
	
	//lets get some global vars
	//global $file_tr;
	$file_tr = "$scandataFolder/traceroute_";
	
        //lets load the previous successful traceroute into memory so we can compare to the new and report on any path changes
	$oldTraceroute = (file_exists($file_tr.$device."_success"))?file_get_contents($file_tr.$device."_success"):"";
	
	//Try the first traceroute by ICMP
	$trCmdIcmp = "traceroute -n -N30 -q1 $device";
	$trRet = shell_exec("$trCmdIcmp");
	echo "$trRet\n\n";
	$rows = explode("\n", trim($trRet));
	
	//if we have the last IP then the traceroute was a success
	$lastIP = trim(substr(end($rows),4,strpos(end($rows)," ",5)-4));
	$lastHop = $rows[count($rows)-2];
	$lastHop = trim(substr($lastHop,4,strpos($lastHop," ",5)-4));
	echo "ICMP TRACEROUTE\nLast IP is ($lastIP)\nLast Hop is ($lastHop)\n\n";
	
        //get the IP, if its a hostname
        $deviceIP = $device;
        if (ip2long($device) === FALSE) $deviceIP = gethostbyname($device);
	
	//if (strpos(end($rows), '*' === FALSE)) { //old comparison
        if ($lastIP == $deviceIP) {
		//successful traceroute. write it to the file
		$trRet = "Date-Time:\t".date(DATE_RFC2822)."\n\nCommand:\t".$trCmdIcmp."\n".$trRet;
		file_put_contents($file_tr.$device."_success",$trRet);
		
		//lets write the last hop to the device list so we can use this info in creating a network map.
		updateLastHop($lastHop,$device);
		
		//compare the traceroutes and log any path changes
		compareTraceroutes($device,$oldTraceroute,$trRet);
		
	} else {
		//ICMP Failed so lets try TCP. First lets find an open port
		$oPort = shell_exec("nmap -PN -p80,443,22,21,25,8080,8081,53,3389,23,110,445,135,139,5000,6002,500,389 $device | grep \"open\"");
		$oPort = substr($oPort,0,strpos($oPort,"/"));
		
		//The default tcp scan
		$trCmdTcp = "traceroute -n -N30 -q1 -T $device";
		
		//if we found an open port, lets use that
		if ($oPort!="") $trCmdTcp = "traceroute -n -N30 -q1 -T -p$oPort $device";
		
		//lets run the traceroute
		$trRetTcp = shell_exec("$trCmdTcp");
		echo "$trRetTcp\n\n";
		$rows = explode("\n", trim($trRetTcp));
		
		//check if IP's match
		$lastIP = trim(substr(end($rows),4,strpos(end($rows)," ",5)-4));
		$lastHop = $rows[count($rows)-2];
		$lastHop = trim(substr($lastHop,4,strpos($lastHop," ",5)-4));
		echo "TCP TRACEROUTE\nLast IP is ($lastIP)\nLast Hop is ($lastHop)\n\n";
		
		//if (strpos(end($rows), '*') === FALSE) { //old comparison
		if ($lastIP == $device) {
			//TCP scan was successful, lets output it to the file
			$trRetTcp = "Date-Time:\t".date(DATE_RFC2822)."\n\nCommand:\t".$trCmdTcp."\n".$trRetTcp;
			file_put_contents($file_tr.$device."_success",$trRetTcp);
			
			//write the last hop to the devicelist table for use in creating a network map
			updateLastHop($lastHop,$device);
			
			//compare the traceroutes and log any path changes
			compareTraceroutes($device,$oldTraceroute,$trRetTcp);
			
		} else {
			//TCP scan failed, lets write both failed traces to the failed file
			file_put_contents($file_tr.$device."_fail","Date-Time:\t".date(DATE_RFC2822)."\n\nCommand:\t$trCmdIcmp\n$trRet\n\nCommand:\t$trCmdTcp\n$trRetTcp");
		}
	}
}

function updateLastHop($lastHop, $sDeviceIp) {
	//if we have an IP then lets write it
	$lastHop = trim($lastHop); $sDeviceIp = trim($sDeviceIp);
	if ($lastHop != "") {
		//Update the last hop if there IS a last hop
		echo "Last Hop = $lastHop\n";
		
		//$qry = "UPDATE devicelist SET lasthop='$lastHop' WHERE ip='$sDeviceIp'";
		//$retval = queryMysql($qry);
	}
}

function compareTraceroutes($deviceToUpdate, $oTR, $nTR) {
	$ot = explode("\n",$oTR."\n");
	$nt = explode("\n",$nTR."\n");
        
	//shift the arrays until we get to the first hop. gets rid of any header information.
	for ($a=0;$a<count($ot);$a++) {
		if (substr($ot[0],0,2) == " 1") break; array_shift($ot); }
	for ($a=0;$a<count($nt);$a++) {
		if (substr($nt[0],0,2) == " 1") break; array_shift($nt); }
	
        //remove extra empty array slots
        $ot = array_filter($ot); $nt = array_filter($nt);
	//var_dump($ot); var_dump($nt);
	
	echo "\nOT count = ".count($ot)."\nNT count = ".count($nt)."\n\n";
	
	//if the hop count is over 5 (change this based on research) then don't log path changes.
	//	it could be going over the internet, in which case this will change constanstly.
	if (count($ot) > 5 ) {
		echo "Hop count is over 5. No traceroute comparison or change logging will be done on this.\n";
		return;
	}
	
	//now we compare
	for ($a=0;$a < (count($nt)>count($ot)?count($nt):count($ot));$a++) {
		//strip off the response time
		if (strlen($nt[$a]) > 5 && $nt[$a] != "") $nt[$a] = substr($nt[$a],4,strpos($nt[$a]," ",5)-4);
		if (strlen($ot[$a]) > 5 && $ot[$a] != "") $ot[$a] = substr($ot[$a],4,strpos($ot[$a]," ",5)-4);
		
		echo "$a: Comparing old (".$ot[$a].") to new (".$nt[$a].")\n";

		//compare and report if different
		if ($ot[$a] != $nt[$a] || count($nt)!=count($ot)) {
			if (strpos($ot[$a],"*") === FALSE && strpos($nt[$a],"*") === FALSE) {
			//a * indicates the node didn't respond, which happens alot. lets only update the log if we know for sure the IP changed
			echo "Writing to change log\n";
			writeChangeLog("traceroute",$deviceToUpdate,"The network path to this device has changed. This could indicate a MITM attack.\n\nHop ".($a+1)."changed from {$ot[$a]} to {$nt[$a]}",$oTR, $nTR);
			break;
			}
		}
		
		//just in case, to prevent long loops.
		if ($a > 30) break;
	}
}


?>