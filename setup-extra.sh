#!/bin/bash
#
# NeoSentry SETUP SCRIPT for other packages used in development
#


if [$(which yum) != '']; then 
	# Installer logic for CenOS / RedHat Distros
	
	# Install OpenVAS Security Scanner and Update it: https://localhost:9392
	read -p "Do you wish to install OpenVAS, it could take around 10 minutes? [Y/n]" yn
	case $yn in
		[Yy]* ) yum -y install openvas-client openvas-scanner; openvas-nvt-sync;;
	esac
	
	# Some potentially useful python libraries. if used then add to setup.sh
	yum -y install python python-devel net-snmp-python python-netaddr python-ipaddr python-ldap
	
	# phpmyadmin for database manipulations
	rpm -ivh http://ftp.jaist.ac.jp/pub/Linux/Fedora/epel/6/i386/epel-release-6-8.noarch.rpm
	yum -y install phpmyadmin libapache2-mod-ldap-userdir libapache2-mod-geoip php5-snmp
	
else
	# Installer logic for Debian / Ubuntu Distros
	
	# Install OpenVAS Security Scanner and Update it: https://localhost:9392
	read -p "Do you wish to install OpenVAS, it could take around 10 minutes? [Y/n]" yn
	case $yn in
		[Yy]* ) apt-get -m -y openvas-server openvas-client openvas-scanner; openvas-nvt-sync;;
	esac
	
	# Some potentially useful python libraries
	apt-get -m -y install python python-mysqldb python-ldap python-devel net-snmp-python python-netaddr
	# phpmyadmin and other packages
	apt-get -m -y install phpmyadmin libapache2-mod-ldap-userdir libapache2-mod-geoip php5-snmp
	
fi


# ????????? other optional packages ?????????
# Install CPanel (browse to https://localhost:2087 )
#wget -N http://httpupdate.cpanel.net/latest
#sh latest
#/usr/local/cpanel/cpkeyclt

####### METASPLOIT: https://localhost:3790 ####### 
#some prerequisites may be needed, along with updates to YAML, Ruby, and Postgres. see darkoperator.com/msf-centosrhel/
read -p "Do you wish to install Metasploit? [Y/n]" yn
case $yn in
	[Yy]* ) wget http://downloads.metasploit.com/data/releases/metasploit-latest-linux-x64-installer.run; chmod +x metasploit-latest-linux-x64-installer.run; ./metasploit-latest-linux-x64-installer.run;;
esac



#NETFLOW
#https://code.google.com/p/flowd/


echo .
echo Done Configuring. Some Useful Links:
echo ....Metasploit:	https://localhost:3790
echo .......openVAS:	https://localhost:9392
echo ....PHPMyAdmin:	https://localhost/phpmyadmin	root:[blank]


exit






################################################
#   LEGACY CONFIGURATION SCRIPT BELOW
################################################

# ---------- Configure MySQL Database ----------

echo .
echo Configuring MySQL
echo .
echo Its recommended to securely configure MySQL and set a root password. If you have already done this then you can type (n) below, otherwise hit Enter to start the secure configuration wizard.
read  (Y)
read -p "Would you like to run mysql_secure_installation? [Y/n]" yn
case $yn in
	[Nn]* ) echo Skipping MySQL secure configuration wizard;;
	* ) mysql_secure_installation;;
esac

echo .
echo Creating and importing the MySQL Application Database
#now create the mysql user, database, and tables.  Modify this info.
DBNAME="neosentrydb"
DBUSER="neosentryuser"
DBT=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9@$!.*()_+@-' | fold -w 16 | head -n 1) #"P@ssw0rd!_Rw39aGiwql@qpfYJZ+"
echo
echo "The following password should be unique and random. It's inserted into the application scripts to read and write database information."
echo "If you're migrating an installation then you should use the previous installations password. Otherwise hit Enter to use the auto-generated password."
read -p "Enter the Applications MySQL password > [$DBT] " -s DBPASS
if [$DBPASS == '']; then DBPASS=$DBT; fi
echo
printf "CREATE DATABASE $DBNAME;\n" > mysql_initialize_db.sql
printf "CREATE USER '$DBUSER'@'localhost' IDENTIFIED BY '$DBPASS';\n" >> mysql_initialize_db.sql
printf "USE $DBNAME;\nGRANT SELECT,INSERT,UPDATE,DELETE ON $DBNAME.* to '$DBUSER'@'localhost';\n" >> mysql_initialize_db.sql

#devicelist table.  ip is 45 length so it supports up to ipv6 with ipv4 tunneling
printf "CREATE TABLE devicelist (dateadded TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	country VARCHAR(255), site VARCHAR(255), objtype VARCHAR(32), devicename VARCHAR(64), ip VARCHAR(45), monitorsnmp VARCHAR(1), monitorports VARCHAR(1024),
	snmpcommunity VARCHAR(255), pingstatus VARCHAR(12), pingtimestamp TIMESTAMP, lasthop VARCHAR(45),
	ifthroughputin BIGINT UNSIGNED, ifthroughputout BIGINT UNSIGNED, notes VARCHAR(2048),
	PRIMARY KEY (ip), INDEX(country), INDEX(site), INDEX(objtype), INDEX(devicename), INDEX(ip));\n" >> mysql_initialize_db.sql
#printf "CREATE TABLE devicemoninfo ();\n" >> mysql_initialize_db.sql
printf "CREATE TABLE history_ping (ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP, device VARCHAR(45), value VARCHAR(1024),
	PRIMARY KEY(ts,device), INDEX(ts), INDEX(device));\n" >> mysql_initialize_db.sql

#**** create history_if, history_cpu, history_mem
printf "CREATE TABLE history_if (ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP, device VARCHAR(45), ifIndex INT,
	ifInOctetsDiff INT UNSIGNED, ifInUcastPktsDiff INT UNSIGNED, ifInNUcastPktsDiff INT UNSIGNED,
	ifInDiscardsDiff INT UNSIGNED, ifInErrorsDiff INT UNSIGNED, ifInUnknownProtosDiff INT UNSIGNED,
	ifOutOctetsDiff INT UNSIGNED, ifOutUcastPktsDiff INT UNSIGNED, ifOutNUcastPktsDiff INT UNSIGNED,
	ifOutDiscardsDiff INT UNSIGNED, ifOutErrorsDiff INT UNSIGNED, ifOutQLenDiff INT UNSIGNED,
	ifHCInOctetsDiff BIGINT UNSIGNED, ifHCOutOctetsDiff BIGINT UNSIGNED,
	PRIMARY KEY(ts,device,ifIndex), INDEX(ts), INDEX(device));\n" >> mysql_initialize_db.sql
#**** create device_iftable, device_routes
printf "CREATE TABLE device_iftable (ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP, device VARCHAR(45), ifIndex INT, ifDescr VARCHAR(512),
	ifType VARCHAR(32), ifMtu INT UNSIGNED, ifSpeed BIGINT UNSIGNED, ifPhysAddress VARCHAR(45), ifAdminStatus VARCHAR(10), ifOperStatus VARCHAR(10),
	ifLastChange VARCHAR(64), ipAddr VARCHAR(2048), ipNetMask VARCHAR(45),

	ifInOctets BIGINT UNSIGNED, ifInUcastPkts BIGINT UNSIGNED, ifInNUcastPkts BIGINT UNSIGNED,
	ifInDiscards BIGINT UNSIGNED, ifInErrors BIGINT UNSIGNED, ifInUnknownProtos BIGINT UNSIGNED,
	ifOutOctets BIGINT UNSIGNED, ifOutUcastPkts BIGINT UNSIGNED, ifOutNUcastPkts BIGINT UNSIGNED,
	ifOutDiscards BIGINT UNSIGNED, ifOutErrors BIGINT UNSIGNED, ifOutQLen BIGINT UNSIGNED,

	ifInOctetsDiff INT UNSIGNED, ifInUcastPktsDiff INT UNSIGNED, ifInNUcastPktsDiff INT UNSIGNED,
	ifInDiscardsDiff INT UNSIGNED, ifInErrorsDiff INT UNSIGNED, ifInUnknownProtosDiff INT UNSIGNED,
	ifOutOctetsDiff INT UNSIGNED, ifOutUcastPktsDiff INT UNSIGNED, ifOutNUcastPktsDiff INT UNSIGNED,
	ifOutDiscardsDiff INT UNSIGNED, ifOutErrorsDiff INT UNSIGNED, ifOutQLenDiff INT UNSIGNED,

	ifHCInOctets BIGINT UNSIGNED, ifHCOutOctets BIGINT UNSIGNED, ifHCInOctetsDiff BIGINT UNSIGNED, ifHCOutOctetsDiff BIGINT UNSIGNED,
	ifAlias VARCHAR(512), ifPromiscuousMode VARCHAR(45),

	ifts TIMESTAMP,
	PRIMARY KEY(device,ifIndex), INDEX(ts), INDEX(device));\n" >> mysql_initialize_db.sql
printf "CREATE TABLE device_routes (ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP, device VARCHAR(45), ipRouteDest VARCHAR(45), ipRouteMask VARCHAR(45),
	ipRouteNextHop VARCHAR(45), ipRouteIfIndex INT , ipRouteMetric1 INT, ipRouteMetric2 INT, ipRouteMetric3 INT,
	ipRouteMetric4 INT, ipRouteMetric5 INT, ipRouteType VARCHAR(24), ipRouteProto VARCHAR(24), ipRouteAge VARCHAR(24),
	PRIMARY KEY(device,ipRouteDest), INDEX(device), INDEX(ipRouteDest));\n" >> mysql_initialize_db.sql

#**** CREATE device_cpu, device_mem, device_hdd, device_sys, device_health
printf "CREATE TABLE device_cpu (ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP, device VARCHAR(45), Load1 DECIMAL(3,2), Load5 DECIMAL(3,2), Load15 DECIMAL(3,2),
	PRIMARY KEY(device), INDEX(ts), INDEX(device));\n" >> mysql_initialize_db.sql
printf "CREATE TABLE device_mem (ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP, device VARCHAR(45), TotalSwap BIGINT UNSIGNED, AvailSwap BIGINT UNSIGNED,
	TotalReal BIGINT UNSIGNED, AvailReal BIGINT UNSIGNED, TotalFree BIGINT UNSIGNED, Shared BIGINT UNSIGNED, Buffered BIGINT UNSIGNED, Cached BIGINT UNSIGNED,
	PRIMARY KEY(device), INDEX(ts), INDEX(device));\n" >> mysql_initialize_db.sql
printf "CREATE TABLE device_sys (ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP, device VARCHAR(45), sysUpTime VARCHAR(1024), sysName VARCHAR(1024),
	sysLocation VARCHAR(2048), sysContact VARCHAR(2048), sysServices INT, sysDescr VARCHAR(2048),
	PRIMARY KEY(device), INDEX(ts), INDEX(device));\n" >> mysql_initialize_db.sql
printf "CREATE TABLE device_hdd (ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP, device VARCHAR(45), dskIndex INT, dskPath VARCHAR(1024),
	dskDevice VARCHAR(1024), dskTotal BIGINT UNSIGNED, dskAvail BIGINT UNSIGNED, dskUsed BIGINT UNSIGNED, dskPercentUsed DECIMAL(3,2), dskErrorFlag INT, dskErrorMsg VARCHAR(1024),
	PRIMARY KEY(device,dskIndex), INDEX(dskIndex), INDEX(device), INDEX(dskPercentUsed));\n" >> mysql_initialize_db.sql
printf "CREATE TABLE device_health (ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP, device VARCHAR(45), component VARCHAR(128), val VARCHAR(2048),
	html VARCHAR(4096),
	PRIMARY KEY(device,component), INDEX(component), INDEX(device));\n" >> mysql_initialize_db.sql



#**** CREATE HISTORY cpu/mem/hdd tables
printf "CREATE TABLE history_cpu (ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP, device VARCHAR(45), Load1 DECIMAL(3,2), Load5 DECIMAL(3,2), Load15 DECIMAL(3,2),
	PRIMARY KEY(ts,device), INDEX(ts), INDEX(device));\n" >> mysql_initialize_db.sql
printf "CREATE TABLE history_mem (ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP, device VARCHAR(45), AvailSwap BIGINT UNSIGNED, AvailReal BIGINT UNSIGNED, Buffered BIGINT UNSIGNED, Cached BIGINT UNSIGNED,
	PRIMARY KEY(ts,device), INDEX(ts), INDEX(device));\n" >> mysql_initialize_db.sql


#printf "CREATE TABLE alerts (ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP, device VARCHAR(45), type VARCHAR(64), status VARCHAR(24), ts_resolved TIMESTAMP
#	PRIMARY KEY(device,type,ts_resolved), INDEX(ts), INDEX(device), INDEX(status);\n" >> mysql_initialize_db.sql
#printf "CREATE TABLE changelog ();\n" >> mysql_initialize_db.sql
printf "CREATE TABLE settings (name VARCHAR(32) NOT NULL, value VARCHAR(255) NOT NULL,
	PRIMARY KEY(name), INDEX(name));\n" >> mysql_initialize_db.sql
printf "CREATE TABLE log (ts TIMESTAMP DEFAULT CURRENT_TIMESTAMP PRIMARY KEY, type VARCHAR(32) not null, device VARCHAR(45), value VARCHAR(64512),
	INDEX(ts), INDEX(type), INDEX(device));\n" >> mysql_initialize_db.sql

#root pw:  0l2wtRtefWEBaguj4j0b
echo ***MysQL \'root\' password needed to create the application database and user.***
mysql -u root -p < mysql_initialize_db.sql




#update the _functions.php file so it uses the right DB information
echo updating _functions.php to use the right MySQL connection information
cd $INSTALL_DIR/www
sed -i -e 's/$dbname = .*/$dbname = '$DBNAME';/g' -e 's/$dbuser = .*/$dbuser = '$DBUSER';/g' -e 's/$dbpass = .*/$dbpass = '$DBPASS';/g' _functions.php
#update the _functions.php file to include a generated encryption key and iv
#This is done in the scripts
#ENCKEY=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9@$!.*()_+@-' | fold -w 24 | head -n 1)
#ENCIV=$(cat /dev/urandom | tr -dc 'a-zA-Z0-9@$!.*()_+@-' | fold -w 16 | head -n 1)
#sed -i -e 's/$secret_iv = .*/$secret_iv = '$ENCIV';/g' -e 's/$secret_key = .*/$secret_key = '$ENCKEY';/g' _functions.php
#ENCIV=$(openssl rand -base64 16)


