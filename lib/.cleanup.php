<?php //.cleanup.php // Reads settings, removes entries older than x days

include "_functions.php";

//get values for history storage
$pingHistory = getSettingsValue("history_ping"); if ($pingHistory=="") $pingHistory=365;
$trHistory = getSettingsValue("history_traceroute"); if ($trHistory=="") $trHistory=365;
$pingAlertHistory = getSettingsValue("history_pingalerts"); if ($pingAlertHistory=="") $pingAlertHistory=365;
$snmpHistory = getSettingsValue("history_snmp"); if ($snmpHistory=="") $snmpHistory=365;
$snmpChangeHistory = getSettingsValue("history_snmpchange"); if ($snmpChangeHistory=="") $snmpChangeHistory=365;
$snmpAlertHistory = getSettingsValue("history_snmpalerts"); if ($snmpAlertHistory=="") $snmpAlertHistory=365;

echo "<pre>\n";
//cleanup system logs (to be added later) stored in ./data/logs with file name = to the date
//$logfile = "./data/logs/system_".date('Y-m-d').".log";
//shell_exec("find ./data/logs/system* -mtime +".$systemLogHistory." -exec rm {} \;

//cleanup ping
//SELECT * from history_ping where ts < (now() - INTERVAL 60 DAY)
$cnt = getSqlValue("SELECT count(ts) FROM history_ping where ts < (now() - INTERVAL $pingHistory DAY)");
echo date("Y-m-d H:i:s").": Deleting $cnt Rows of ping history from table 'history_ping' older than $pingHistory Day(s)...";
$res = queryMysql("DELETE from history_ping where ts < (now() - INTERVAL $pingHistory DAY)");
echo "Returned: $res\n";
//Ping Log
$cnt = getSqlValue("SELECT count(*) FROM log where type='ping' AND ts < (now() - INTERVAL $pingHistory DAY)");
echo date("Y-m-d H:i:s").": Deleting $cnt Rows of ping change history from table 'log' older than $pingHistory Day(s)...";
$res = queryMysql("DELETE from log where type='ping' AND ts < (now() - INTERVAL $pingHistory DAY)");
echo "Returned: $res\n";
//cleanup traceroute log history
$cnt = getSqlValue("SELECT count(*) FROM log where type='traceroute' AND ts < (now() - INTERVAL $trHistory DAY)");
echo date("Y-m-d H:i:s").": Deleting $cnt Rows of traceroute history from table 'log' older than $trHistory Day(s)...";
$res = queryMysql("DELETE from log where type='traceroute' AND ts < (now() - INTERVAL $trHistory DAY)");
echo "Returned: $res\n";

//cleanup snmp history
//interface
$cnt = getSqlValue("SELECT count(ts) FROM history_if where ts < (now() - INTERVAL $snmpHistory DAY)");
echo date("Y-m-d H:i:s").": Deleting $cnt Rows of history from table 'history_if' older than $snmpHistory Day(s)...";
$res = queryMysql("DELETE from history_if where ts < (now() - INTERVAL $snmpHistory DAY)");
echo "Returned: $res\n";
//cpu
$cnt = getSqlValue("SELECT count(ts) FROM history_cpu where ts < (now() - INTERVAL $snmpHistory DAY)");
echo date("Y-m-d H:i:s").": Deleting $cnt Rows of history from table 'history_cpu' older than $snmpHistory Day(s)...";
$res = queryMysql("DELETE from history_cpu where ts < (now() - INTERVAL $snmpHistory DAY)");
echo "Returned: $res\n";
//mem
$cnt = getSqlValue("SELECT count(ts) FROM history_mem where ts < (now() - INTERVAL $snmpHistory DAY)");
echo date("Y-m-d H:i:s").": Deleting $cnt Rows of history from table 'history_mem' older than $snmpHistory Day(s)...";
$res = queryMysql("DELETE from history_mem where ts < (now() - INTERVAL $snmpHistory DAY)");
echo "Returned: $res\n";
//SNMP LOG info
$cnt = getSqlValue("SELECT count(*) FROM log where type='snmp' AND ts < (now() - INTERVAL $snmpChangeHistory DAY)");
echo date("Y-m-d H:i:s").": Deleting $cnt Rows of snmp change history from table 'log' older than $snmpChangeHistory Day(s)...";
$res = queryMysql("DELETE from log where type='snmp' AND ts < (now() - INTERVAL $snmpChangeHistory DAY)");
echo "Returned: $res\n";


//optimize the tables to recover free space
//alternatively you can run 'mysql -u root -p -o [database_name] [--all-databases]'
echo date("Y-m-d H:i:s").": Optimizing Table history_if...";
queryMysql("optimize table history_if");
echo "DONE\n";
echo date("Y-m-d H:i:s").": Optimizing Table history_cpu...";
queryMysql("optimize table history_cpu");
echo "DONE\n";
echo date("Y-m-d H:i:s").": Optimizing Table history_mem...";
queryMysql("optimize table history_mem");
echo "DONE\n";
echo date("Y-m-d H:i:s").": Optimizing Table history_ping...";
queryMysql("optimize table history_ping");
echo "DONE\n";
echo date("Y-m-d H:i:s").": Optimizing Table log...";
queryMysql("optimize table log");
echo "DONE\n";

//ADDITIONAL NOTES: Add the following lines to '/etc/my.cnf' under the '[mysqld]' section and 'service mysqld restart'
/*
#Keeps the innodb file size down when optimizing tables
innodb_file_per_table
innodb_flush_method=O_DIRECT
*/


echo "</pre>";

?>