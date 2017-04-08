<?php
//mysql uses port 3307
//mssql uses port 1433
//the firewall will need to allow this connection from 127.0.0.1

//MySQL Stuff
//$mySQL_DateTime = date("Y-m-d H:i:s");

//Changing a password:
//SET PASSWORD FOR 'root'@'localhost' = PASSWORD('MySQL1234!');

//A good sql reference:  http://www.pantz.org/software/mysql/mysqlcommands.html
//initial setup, to be run as the root
//CREATE DATABASE dom;
//CREATE USER 'username'@'localhost' IDENTIFIED BY 'SecondTreatise0F';
//USE dom
//GRANT SELECT,INSERT,UPDATE,DELETE ON dom.* to 'username'@'localhost'


//Database Connection Variables
$dbhost = 'localhost';
$dbname = 'qnmsdb';
$dbuser = 'qnmsuser';
$dbpass = 'X2GX1!Me1KhopGBz';
//End Database Connection Variables

if ($dbname=='' || $dbname=='%dbname%') {
    echo 'Please run setup.sh as root to initialize the database and set up this instance.';
    exit;
    die('Please run _setup.sh as root to initialize the database and set up this instance.');
}


//mysql_connect($dbhost, $dbuser, $dbpass) or die(mysql_error());
$mysqli = new mysqli($dbhost, $dbuser, $dbpass, $dbname);
if ($mysqli->connect_errno) {
    printf("MySql Connect Failed: (%s) %s\n", $mysqli->connect_errno, $mysqli->connect_error);
    exit();
    //$mysqli->close(); //close the connection
}




function cleanSqlString($string) {
    global $mysqli;
    return $mysqli->real_escape_string($string);
}
function closeSql() {
    global $mysqli;
    $mysqli->close();
}
function createTable($name, $query) {
    if (tableExists($name)) {
        echo "Table '$name' already exists<br />";
    } else {
        $result = queryMysql("CREATE TABLE $name($query)");
        if (!queryMysql("CREATE TABLE $name($query)")) {
            echo "Error creating table '$name': ".$mysqli->error;
        } else {
            echo "Table '$name' created<br />";
        }
    }
}

function tableExists($name) {
    $result = queryMysql("SHOW TABLES LIKE '$name'");
    return $result->num_rows;
}

function queryMysql($query) {
    global $mysqli;
    //$result = mysql_query($query) or die(mysql_error());
    $result = $mysqli->query($query);
    if (!$result) {
        $result = "ERROR: (" . $mysqli->errno . ") " .$mysqli->error;
    } else {
        if (strtolower(substr($query,6))=="delete") $result = "Deleted ".$mysqli->affected_rows." Rows: ".$mysqli->info();
        elseif (strtolower(substr($query,6))=="update") $result = "Updated ".$mysqli->affected_rows." Rows: ".$mysqli->info();
        elseif (strtolower(substr($query,6))=="insert") $result = "Inserted ".$mysqli->affected_rows." Rows: ".$mysqli->info();

    }
    return $result;
}

function getSqlArray($query) {
    $result = queryMysql($query);
    $retarr = array();

    while ($row = $result->fetch_assoc())
        $retarr[] = $row;

    $result->free();
    return $retarr;
}
function getSqlValue($query) {
    global $mysqli;
    //gets the first value in the result
    //$result = queryMysql($query);
    $result = $mysqli->query($query);
    if (!$result) {
        return "";
    } else {
        $row = $result->fetch_row();
        $result->free();
        return $row[0];
    }
}


// LOGGING //

/*
 * this is the old code, now in the _functions.php file and uses a different date format
function writeLogFile($fileName, $line) {
    //First make sure we have a log file to write to
    global $gFolderLogs;
    $logFile = "$gFolderLogs/$fileName";
    if (!file_exists($gFolderLogs)) mkdir($gFolderLogs);
    if (!file_exists($logFile)) touch($logFile);

    //now write the output and display it on the console
    $output = date("F j, Y, g:i a").":\t$line\n";
    echo $output;
    file_put_contents($logFile,$output,FILE_APPEND);

}
*/

function writeLog($category, $device, $data) {
    //make the data safe for sql
    $category = cleanSqlString(trim(strtolower($category)));
    $device = cleanSqlString(trim(strtolower($device)));
    $data = cleanSqlString(trim($data));

    //Insert into SQL
    $retval = queryMysql("INSERT INTO log (type, device, value) VALUES('$category','$device','$data');");

    //check for alerts (from _alerting.php)
    //checkAlerts($device, $category, $data);

    //for testing
    //echo "<p>LOG: $date: $category - $device - $data</p>";

    return $retval;
}

function writeChangeLog($category, $device, $text, $beforeChange, $afterChange) {
    return writeLog($category, $device, $text."<br><br><table><tr><td>Before</td><td>After</td></tr><tr><td><pre>$beforeChange</pre></td><td><pre>$afterChange</pre></td></tr></table>");
}

function compareArrays($oldTable, $newTable, $uniqueColumn, $logColumns, $device, $logGroup, $deleteFromTable) {
    //usage: compareArrays($oldArr, $newArr, "ifIndex", array('col1','col2',..), "10.11.12.13", "interface", "device_iftable");

    $removedEntries = $addedEntries = $changedEntries = "";
    echo date("Y-m-d H:i:s").": Logging any changes. Old Table had ".count($oldTable)." Rows. New table has ".count($newTable)." Rows.\n";

    //flatten the unique column for easy comparing
    $oldFlatIndex = $newFlatIndex = "";
    foreach ($oldTable as $i) $oldFlatIndex .= "(".getArrayVal($i,$uniqueColumn).")";
    foreach ($newTable as $i) $newFlatIndex .= "(".getArrayVal($i,$uniqueColumn).")";

    //see if any rows were REMOVED\
    foreach($oldTable as $row) {
        //for ($a=0;$a<count($oldTable);$a++) {
        $oldIndx = getArrayVal($row,$uniqueColumn);

        if (strpos($newFlatIndex,"(".$oldIndx.")")===false) {
            //this index was REMOVED
            $removedEntries .= "<tr><td>".getArrayVal($row,$uniqueColumn)."</td>";
            foreach ($logColumns as $lc) $removedEntries .= "<td>".getArrayVal($row,$lc)."</td>";
            $removedEntries .= "</tr>";
            echo "$uniqueColumn ".$oldIndx." was REMOVED\n";

            //delete from sql if specified
            if ($deleteFromTable != "") queryMySql("DELETE FROM $deleteFromTable WHERE device='$device' AND $uniqueColumn = $oldIndx;");
        }
    }
    if ($removedEntries != "") $removedEntries = "<table><tr><td>$uniqueColumn</td><td>".implode("</td><td>",$logColumns)."</td></tr>$removedEntries</table>";

    //see if any rows were ADDED
    foreach($newTable as $row) {
        //for ($a=0;$a<count($newTable);$a++) {
        $newIndx = getArrayVal($row,$uniqueColumn);

        if (strpos($oldFlatIndex,"(".$newIndx.")")===false) {
            //this index was ADDED
            $addedEntries .= "<tr><td>".getArrayVal($row,$uniqueColumn)."</td>";
            foreach ($logColumns as $lc) $addedEntries .= "<td>".getArrayVal($row,$lc)."</td>";
            $addedEntries .= "</tr>";
            echo "$uniqueColumn ".$newIndx." was ADDED\n";
        }
    }
    if ($addedEntries != "") $addedEntries = "<table><tr><td>$uniqueColumn</td><td>".implode("</td><td>",$logColumns)."</td></tr>$addedEntries</table>";


    //see if any rows CHANGED
    $monitorColumns = $logColumns;
    foreach($newTable as $header => $val) {
        //for ($a=0; $a < count($newTable); $a++) {
        if (array_key_exists($header,$oldTable)) {
            $row = ""; $hasChanged = false;
            foreach ($monitorColumns as $mc) {
                $oldVal = getArrayVal($oldTable[$header],$mc); $newVal = getArrayVal($val,$mc);
                if ($oldVal != "" && $newVal != $oldVal) {
                    $hasChanged = true;
                    $row .= "<td>'$oldVal' -> '<b>$newVal</b>'</td>";
                    echo "Value CHANGED from '$oldVal' to '$newVal' on $uniqueColumn ".$val[$uniqueColumn]."\n";
                } else $row .= "<td></td>";
            }
            if ($hasChanged) $changedEntries .= "<tr><td>".$val[$uniqueColumn]."</td>$row</tr>";
        }
    }
    if ($changedEntries != "") $changedEntries = "<table><tr><td>$uniqueColumn</td><td>".implode("</td><td>",$monitorColumns)."</td></tr>$changedEntries</table>";

    //write to the log file
    if ($removedEntries !="") writeLog($logGroup,$device,"The following $logGroup rows have been REMOVED:<br><br>".trim($removedEntries));
    if ($addedEntries !="") writeLog($logGroup,$device,"The following $logGroup rows have been ADDED:<br><br>".trim($addedEntries));
    if ($changedEntries != "") writeLog($logGroup,$device, "The following $logGroup rows have CHANGED:<br><br>".trim($changedEntries));

}


// DEVICE MANAGEMENT FUNCTIONS //
//TABLE: devicelist.  COLUMNS: country, site, objtype, devicename, ip, monitorsnmp, monitorports, snmpcommunity
function addDevice($sCountry, $sSite, $sObjtype, $sDevicename, $sIp, $sSnmp, $sPorts, $sSnmpCommunity) {
    //make the data safe for sql
    $sCountry = cleanSqlString($sCountry); $sSite = cleanSqlString($sSite); $sObjtype = cleanSqlString($sObjtype);
    $sDevicename = cleanSqlString($sDevicename); $sIp = cleanSqlString($sIp); $sSnmp = cleanSqlString($sSnmp);
    $sPorts = cleanSqlString($sPorts); $sSnmpCommunity = cleanSqlString($sSnmpCommunity);

    //Add new device
    $qry = "INSERT INTO devicelist (country, site, objtype, devicename, ip, monitorsnmp, monitorports, snmpcommunity) 
					   VALUES('$sCountry','$sSite','$sObjtype','$sDevicename','$sIp','$sSnmp','$sPorts','$sSnmpCommunity');";
    if (trim($sSnmpCommunity) == "") {
        $qry = "INSERT INTO devicelist (country, site, objtype, devicename, ip, monitorsnmp, monitorports) 
					   VALUES('$sCountry','$sSite','$sObjtype','$sDevicename','$sIp','$sSnmp','$sPorts');";
    }
    $retval = queryMysql($qry);
    //ECHO "INSERT Query: $qry<br>Returned $retval<br>";

    //update device if the insert failed
    if ($retval != 1) {
        $qry = "UPDATE devicelist SET country='$sCountry', site='$sSite', objtype='$sObjtype', devicename='$sDevicename', ip='$sIp', 
						monitorsnmp='$sSnmp', monitorports='$sPorts', snmpcommunity='$sSnmpCommunity' 
						WHERE ip='$sIp'";
        if (trim($sSnmpCommunity) == "") {
            $qry = "UPDATE devicelist SET country='$sCountry', site='$sSite', objtype='$sObjtype', devicename='$sDevicename', ip='$sIp', 
						monitorsnmp='$sSnmp', monitorports='$sPorts' 
						WHERE ip='$sIp'";
        }
        $retval = queryMysql($qry);
        //ECHO "UPDATE Query: $qry<br>Returned $retval<br>";
    }

    return $retval;
}

//each ip is separated by a space
function removeDevices($validIPList) {
    $qry = "DELETE FROM devicelist WHERE ip != '".str_replace(" ","' AND ip != '",trim($validIPList))."';";
    //for ($x=0;$x<count($iplst);$x++)
    //	$qry .= "ip != '".$iplst[$x]."' AND ";
    //$qry = rtrim($qry," AND ").";";
    $ret = queryMysql($qry);
    //ECHO "DELETE query: $qry<br>Returned: $ret<br>";
    return $ret;
}


// DEVICE RETRIEVAL FUNCTIONS //
function getDevice($ipAddr) {
    return getDevicesArray("WHERE ip='$ipAddr'");
}
function getDevicesArray($whereStatement) {
    return getDevicesArrayWithOrder($whereStatement, "country,site,objtype,devicename ASC");
}
function getDevicesArrayWithOrder($whereStatement, $orderBy) {
    return getSqlArray("select * from devicelist $whereStatement ORDER BY $orderBy;");
}



