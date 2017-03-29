<?php
/**
 * This will pull certain information from managed devices based on the vendor, or some other criteria
 * Similar to the custom snmp map, I will defer collection to a subscript
 */

include "_functions.php";
include "_db_flatfiles.php"; //to load the device data

/* Get command line arguments, for internal processing
 *	-d: The device name or IP
 *  -t: Type of device so we know what script to run to collect the data
 *	Usage: php [this file] -d "10.10.10.10" -t "Check Point"
 */
$o = getopt("d:t:"); // 1 : is required, 2 :: is optional
$device = array_key_exists("d",$o) ? sanitizeString($o["d"]) : "";
$type = array_key_exists("t",$o) ? $o["t"] : "";


if ($device=="" || $device=="all") {
    //load all devices, loop through each, and run the configuration management script that matches to each one
    exit;
}

//we have a single device, lets match and collect
$var["devicefolder"] = $gFolderScanData."/".$device;
$var["username"] = "loaded-username";
$var["password"] = "loaded-decrypted-pass";
$var["password2"] = "loaded-decrypted-pass";


//$deviceFolder = $gFolderScanData."/".$device."/";

$connection = ssh2_connect($device, 22);
ssh2_auth_password($connection, $var["username"], $var["password"]);
ssh2_exec($connection,"whoami");
ssh2_shell();

//SCP Exampt to get
ssh2_scp_recv($connection, '/remote/filename', '/local/filename');

//SFTP Example
$sftp = ssh2_sftp($connection);
$stream = fopen("ssh2.sftp://$sftp/path/to/file", 'r');

/*
Firewall Files: R77 Mgmt
$FWDIR/database/objects.C
$FWDIR/database/rules.C

Older Backup files:
$FWDIR/conf/objects_5_0.C
$FWDIR/conf/objects.C_41
$FWDIR/conf/objects.C
$FWDIR/conf/rulebases_5_0.fws


$FWDIR/log/
fw log -l -n -p -z -s "March 20, 2017 10:50:00" fw.log | grep "; rule: "

fw log -l -p -n -z -o -b "March 20, 2017 10:50:00" "March 23, 2017 10:52:00" fw.log
fw log -l -p -n -z -o -s "March 20, 2017 10:50:00" fw.log

22Mar2017 13:42:02 accept 10.59.31.10 >Internal inzone: Internal; outzone: External; service_id: http; src: 10.59.0.58; dst: 165.225.32.40; proto: tcp; xlatesrc: 38.131.4.154; NAT_rulenum: 5; NAT_addtnl_rulenum: 1; rule: 13; product: VPN-1 & FireWall-1; service: 80; s_port: 56985; xlatesport: 12825; product_family: Network;
=> 22Mar2017 13:42:02 accept 10.59.31.10 >Internal inzone: Internal; outzone: External; service_id: http; src: 10.59.0.58; dst: 165.225.32.40; proto: tcp; xlatesrc: 38.131.4.154; NAT_rulenum: 5; NAT_addtnl_rulenum: 1; rule: 13; product: VPN-1 & FireWall-1; service: 80; s_port: 56985; xlatesport: 12825; product_family: Network;

22Mar2017 13:42:02 monitor 10.59.31.10 >Internal src: 10.59.17.104; dst: 165.225.32.41; proto: tcp; message_info: Address spoofing; product: VPN-1 & FireWall-1; service: 80; s_port: 60163; product_family: Network;
=> 22Mar2017 13:42:02 monitor 10.59.31.10 >Internal src: 10.59.17.104; dst: 165.225.32.41; proto: tcp; message_info: Address spoofing; product: VPN-1 & FireWall-1; service: 80; s_port: 60163; product_family: Network;


 */