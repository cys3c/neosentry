#!/usr/bin/php
<?php // Collects the firewall and NAT rules from a Palo Alto device

//this script will be copied to ~/data/devices/%device%/tmp so lets use this scripts current folder as a scratch directory
$scratchFolder = dirname(__FILE__);    //a temporary working directory to store and work with files.

// these %variable% strings will be replaced with the appropriate informatoin before running.
$device = "%device%";           //the device IP or hostname we're connecting to
$username = "%username%";
$password = "%password%";
$password2 = "%password2%";     //this 2nd password is an optional variable only used if a 2nd level password is needed


// print the json of the data. The return data will be compared to the previous data for configuration change tracking
// and will be written to the configuration storage file/location/db-table/etc.
echo runCollector($device,$scratchFolder, $username, $password, $password2);

/* An example format is:
{
    "Firewall Rules": {
    "rule-1": {
        "Disabled": false,
			"Rulenum": 1,
			"Hits": 0,
			"Name": "",
			"Source": ["101.200.173.48\/32"],
			"Destination": ["Any"],
			"VPN": "Any",
			"Services": ["Any"],
			"Action": "drop",
			"Track": "Log",
			"Install On": "Test-Firewall",
			"Time": ["Any"]
		}
    },
	"NAT Rules": {
    "rule-1": {
        "Disabled": false,
			"Hits": 0,
			"Name": "",
			"Original Source": ["192.168.0.111\/32"],
			"Original Destination": ["Any"],
			"Original Service": ["Any"],
			"Translated Source": ["!method_hide!",  "10.10.10.10\/32"],
			"Translated Destination": ["!method_static!", "Any"],
			"Translated Services": ["!method_static!", "Any"],
			"Install On": "Any"
		}
    },
    "Configuration": "String of configuration output, or optionally an array. each array entry will be a row"
}
*/

exit;


// ALL OTHER FUNCTIONS BELOW THIS

function runCollector($device, $scratchFolder, $username, $password, $password2="") {
    $retJson = '';

    /* palo collection commands:
        set cli pager off   //without this you can pipe to the except command to bypass pagers. ex: | except "some text not found"
        show config merged
        show running nat_policy
        show running security_policy
    */
    return $retJson;
}