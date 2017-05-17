<?php //_traceroute.php - traceroute functions


// THIS IS THE MAIN FUNCTION //
function tracerouteRun($device, $tcpScan = false, $tcpPort = ""){
	//$tr1cmd = "traceroute -n -N30 -q1";
	//$tr2cmd = "traceroute -n -N30 -q1 -T -p80";

    $arrTraceroute = [];

	//Set up the command [TCP or ICMP]
    $isWIN = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    $trCmd = ($isWIN)?"tracert -d -w 500 $device" : "traceroute -n -N30 -q1 $device";
    if ($tcpScan && !$isWIN) { //wont work on windows
        //set up the default tcp scan
        $trCmd = "traceroute -n -N30 -q1 -T $device";

        //find an open port and use that if available
        $oPort = intval($tcpPort);
        if ($oPort = 0) { //no port defined, lets do a quick scan
            $oPort = shell_exec("nmap -PN -p80,443,22,21,25,8080,8081,53,3389,23,110,445,135,139,5000,6002,500,389 $device | grep \"open\"");
            $oPort = intval(substr($oPort,0,strpos($oPort,"/")));
        }
        if ($oPort > 0 && $oPort < 65535) $trCmd = "traceroute -n -N30 -q1 -T -p$oPort $device";
    }

    //Run the command
    $trRet = shell_exec($trCmd);

	//if we have the last IP then the traceroute was a success
    $hopArr = tracerouteToArray($trRet);
    $lastHop = tracerouteGetHop($hopArr,-2);
	$lastIP = tracerouteGetHop($hopArr, -1);
    $deviceIPs = gethostbynamel($device); //get the IP of the device

	echo "ICMP TRACEROUTE\n  > Last hop: [$lastHop] Last IP: [$lastIP] Device IP(s): [" . rtrim(implode(", ",$deviceIPs), ", ") . "]\n";


    if (in_array($lastIP, $deviceIPs) || $lastIP == "127.0.0.1" || $lastIP == "::1") {
        //successful traceroute. update the array
        echo "Trace Succeeded\n";
        $arrTraceroute['lasthop'] = $lastHop;
        $arrTraceroute['status'] = "success";

    } else {
        // Scan failed, try a TCP scan if we haven't already
        if (!$tcpScan && !$isWIN) return tracerouteRun($device, true);

        //ICMP & TCP scans both failed, lets update the array
        echo "Trace Failed\n";
        $arrTraceroute['status'] = "failed";

    }

    // return the results of the scan
    $arrTraceroute["date"] = date(DATE_ATOM);
    $arrTraceroute["command"] = $trCmd;
    $arrTraceroute["result"] = $trRet;
    return $arrTraceroute;
}


// COMPARE OLD AND NEW TRACES TO DETERMINE IF WE SHOULD UPDATE THE HISTORY AND TO WRITE TO THE LOG
function tracerouteCompare($oldTraceroute, $newTraceroute, $maxHopCount = 5) {
    $ot = isset($oldTraceroute["result"])?tracerouteToArray($oldTraceroute["result"]):[];
    $nt = isset($newTraceroute["result"])?tracerouteToArray($newTraceroute["result"]):[];

	//if the hop count is over $maxHopCount then don't log path changes.
	//	it could be going over the internet, in which case this will change constantly.
	if (count($nt) > $maxHopCount ) {
		echo "Hop count is over $maxHopCount. No traceroute comparison or change logging will be done on this.\n";
		return "";
	}
	if (count($ot) == 0 ) return ""; //0 most likely means this is the first trace so no need to log a comparison


	//compare the hop count
    echo "\nOT count = ".count($ot)."\nNT count = ".count($nt)."\n\n";
    if (count($nt)<count($ot)) return "Network path decreased from " . count($ot) . " hops to " . count($nt) . " hops.";
    if (count($nt)>count($ot)) return "Network path increased from " . count($ot) . " hops to " . count($nt) . " hops.";


    //compare the hop IP's
	for ($a=0;$a < (count($nt)>count($ot)?count($nt):count($ot));$a++) {
		$oIP = array_key_exists('ip',$ot[$a])?$ot[$a]['ip']:"";
        $nIP = array_key_exists('ip',$nt[$a])?$ot[$a]['ip']:"";

		//compare and report if different
        echo "$a: Comparing old ($oIP) to new ($nIP)\n";
        if ($oIP != "" && $nIP != "" && $oIP != $nIP) {
			return "The network path to this device has changed. This could indicate a MITM attack. Hop ".($a+1)." changed from $oIP to $nIP";

		}
	}


}

// SOME ADDITIONAL FUNCTIONS TO SUPPORT THE MAIN LOGIC

function tracerouteToArray($tracerouteOutput){
    $rows = explode("\n", trim($tracerouteOutput));
    $all = [];

    foreach ($rows as $row) {
        $row = trim($row);
        $hop = (strlen($row)>5)?floatval(substr($row,0,3)):0;
        if ($hop > 0) {
            //we've come to the hop list, parse it and add it to the array
            $tmp = [];
            $tmp["hop"] = $hop;
            $tmp["ms"] = "";
            $tmp["ip"] = "";
            foreach (explode("  ",$row) as $val) {
                $val = trim($val);
                if (filter_var($val,FILTER_VALIDATE_IP)) $tmp["ip"] = $val;
                if (strpos($val," ms") > 0) $tmp["ms"] = floatval($val);
            }
            //add row to the main array
            $all[] = $tmp;
        }
    }

    return $all;
}
// Returns the IP of the specified hop number. defaults to the last ip. -2 is the last hop before the destination
function tracerouteGetHop($tracerouteOutput, $hopNumber = -1) {
    //get the array of hops
    $rows = is_array($tracerouteOutput)?$tracerouteOutput:tracerouteToArray($tracerouteOutput);
    $index = ($hopNumber<0)?count($rows) + $hopNumber : $hopNumber;
    if (count($rows) <= 0 || $index < 0 || $index > count($rows)) return "";

    return array_key_exists("ip",$rows[$index])?$rows[$index]["ip"]:"";
}



