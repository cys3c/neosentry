#!/bin/bash
#
# =============================================================
# NeoSentry SETUP SCRIPT 
# Written for Debian/Ubuntu and CentOS/RedHat
#
# This will install NeoSentry into the /usr/share directory
#   Possible Names: NeoSentry NMS, Quick NMS, 
# 
# Directory structure:
# + /usr/share/[neosentry]   - default install directory
# | +-- bin     owned by root. stores scripts and executables. ping/traceroute/snmp/etc collecting scripts
# |  | neosentry.bin         [root]. aka handler.c. executable responsible for message queuing, scheduling, etc.
# | +-- data    owned by apache. stores variable data & backups which are read from the front-end
# |  + mibs     self explanatory
# |  + backups
# |  | devices.json     stores devices and device information.
# |  | snmpmap.json     maps specific OID's to a name for easier parsing.
# |  | settings.json    stores app settings on the admin tab
# |  | neosentry.conf        [root] stores settings only root should know, ie. sql write password, device snmp string/ssh password/
# | +-- www     owned by apache. stores front-end
# 
#
# =============================================================

# Make sure only root can run this script
if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root" 1>&2
   exit 1
fi


####### INSTALL PREREQUISITES ####### 
echo Installing Prerequisite packages.

if [$(which yum) != '']; then 
	# Installer logic for CentOS / RedHat Distros
	
	# ---------- Install Apache, PHP, and other necessary packages ----------
	echo Installing some useful linux network Administration and Security related packages
	yum -y install mtr traceroute tcpdump bind-utils jwhois net-snmp-utils curl wget net-tools iputils diffutils openssh-server
	yum -y install nmap
	
	echo Installing Apache, MySQL, and PHP7.1
	yum -y install httpd httpd-devel mysql mysql-server mysql-devel mod_ssl openssl
	#yum -y install php php-mysql php-common php-gd php-mbstring php-mcrypt php-devel php-xml
	rpm -Uvh http://rpms.famillecollet.com/enterprise/remi-release-6.rpm
    rpm -Uvh https://mirror.webtatic.com/yum/el6/latest.rpm
    rpm -Uvh http://repo.mysql.com/mysql-community-release-el6-5.noarch.rpm
	yum -y install php71w php71w-mysql php71w-gd php71w-mcrypt php71w-mbstring php71w-json php71w-pear php71-php-pecl-ssh2 php71w-pecl-mongodb.

	# ---------- Start Services and Set Autorun -----------
	echo Starting Services. 
	/etc/init.d/mysqld start
	/etc/init.d/httpd restart
	echo Setting httpd and mysqld to autostart
	chkconfig httpd on
	chkconfig mysqld on

else
	# Installer logic for Debian / Ubuntu Distros

	# ---------- Install Apache, PHP, and other necessary packages ----------
	echo Installing some useful linux network admin related packages
	apt-get -m -y install mtr traceroute dnsutils tcpdump whois snmp curl wget htop diffutils ipcalc
	apt-get -m -y install nmap
	
	echo Installing Apache, MySQL, PHP, and Python
	apt-get -m -y install apache2 mysql-server libapache2-mod-python libapache2-mod-php5 openssh-server sudo mod_ssl openssl
	apt-get -m -y install php5 php-pear php5-mysql
	
	# ---------- Start Services -----------
	echo Starting Services.
	service mysqld start
	service apache2 restart
	
fi


# ---------- Compile the handler application ----------

echo Installing gcc to compile the application
yum -y install gcc
echo Compiling NeoSentry command handler for the web interface
cd lib
gcc -o neosentry c-handler.c
chown root neosentry
chmod u+s neosentry
cd ..


# ---------- Make directories, copy files, and set permissions ----------

INSTALL_DIR="/usr/share/neosentry"
echo Moving files and directories to $INSTALL_DIR and making APACHE the Owner
mkdir -p $INSTALL_DIR
mv www $INSTALL_DIR/
mv data $INSTALL_DIR/
mv lib $INSTALL_DIR/
mkdir -p $INSTALL_DIR/data/devices
chmod -R g+w $INSTALL_DIR/data
#chmod g+w settings.conf

if [$(which chcon) != '']; then 
	#SELinux is installed so reset the context to the new path. moving files preserves the old context and will cause access issues.
	restorecon -RvF $INSTALL_DIR
fi


# ---------- Configure Apache ----------

echo Configuring Apache
# Consider prompting the user for the domain name and asking if this is the only website. Then modify the configuration accordingly.
if [ -d "/etc/httpd/conf.d" ]; then 
	# CentOS / RedHat Distros
	cp 000-neosentry-apache.conf /etc/httpd/conf.d/NeoSentry.conf
	/etc/init.d/httpd restart
elif [ -d "/etc/apache2/sites-available" ]; then
	# Assume Debian / Ubuntu Distros
	cp 000-neosentry-apache.conf /etc/apache2/sites-available/000-neosentry.conf
	ln -s /etc/apache2/sites-available/000-neosentry.conf /etc/apache2/sites-enabled/000-neosentry.conf
	/etc/init.d/apache2 restart
else
	echo "A valid Apache installation was not found. You will have to manually configure the web service to use the applications installation directory of $INSTALL_DIR"
	read "Hit any key to continue..."
fi

# Allow firewall connections if iptables is installed and the rule doesn't exist
if [$(which iptables) != '']; then
	echo iptables firewall detected...
	echo Allowing port 80 and 443 connections if the rules dont already exist.
	iptables -C INPUT -p tcp --dport 80 -j ACCEPT || iptables -I INPUT -p tcp --dport 80 -j ACCEPT
	iptables -C INPUT -p tcp --dport 443 -j ACCEPT || iptables -I INPUT -p tcp --dport 443 -j ACCEPT
	/sbin/service iptables save
	#iptables -L -v #list all rules
fi


# To Generate a new private key, CSR, and Self Signed Cert for HTTPS:
#openssl genrsa -out ca.key 2048 
#openssl req -new -key ca.key -out ca.csr
#openssl x509 -req -days 365 -in ca.csr -signkey ca.key -out ca.crt
#cp ca.crt /etc/pki/tls/certs
#cp ca.key /etc/pki/tls/private/ca.key
#cp ca.csr /etc/pki/tls/private/ca.csr
# Then update the ssl.conf apache file to point to the new cert. Default cert is localhost.crt & localhost.key


# Get the user and group the web daemon is running as and make that the owner...
#WWWUSER=$(ps aux | egrep '(httpd|apache)' | egrep -v 'root' | head -n1 | awk '{print $1}')
#WWWGROUP=$(groups $WWWUSER | awk '{print $3}')
#echo Changing the owner of $INSTALL_DIR to $WWWUSER:$WWWGROUP
#echo ... If this is not the web service user then you will have to manually change this
#chown -Rf $WWWUSER:$WWWGROUP $INSTALL_DIR/data $INSTALL_DIR/www
#useradd -g $WWWGROUP -d $INSTALL_DIR -s /bin/noshell neosentry

#default html directory is:  /var/www/html/
#edit /etc/httpd/conf/httpd.conf OR /etc/apache2/apache2.conf and add the following:
#Alias /neosentry "/usr/share/neosentry"
#<Directory "/usr/share/neosentry">
#	AllowOverride None
#	Order allow,deny
#	Allow from localhost
#	Allow from 10.x.x.x
#</Directory>

#modify sudoers file (run visudo). this allows apache to run nmap... not needed
#	comment out the line: Default	tty
#	Add: Apache	ALL:(ALL)	NOPASSWD: ALL



# ---------- Configure Application Database ----------

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




# ---------- Install and Configure Additional Features ----------

#Prompt to install dnsbind to turn this into a DNS server that:
# 1. keeps its monitored devices up to date
# 2. allows for minor status updates in the dns response
# 3. allows for automatic ip changes in the event a device goes down, host record will update to its configured working pair


#Prompt to install tacacs+ server with a custom gui interface




# ---------- NeoSentry cronjobs and Settings Configuration ----------

#run admin.php?update=all so it updates the database with the defaults
# **To Do: don't use curl but rather call the handler application.
#curl http://localhost/neosentry/admin.php?update=all



# --------------- Display Firewall Rules if IPTables is installed ---------------
# More info on adding IPTables rules at http://www.thegeekstuff.com/2011/02/iptables-add-rule
if [$(which iptables) != '']; then
	echo .
	echo Current Firewall Rules:
	iptables -L -v
	echo .
	echo If you have trouble accessing the site, ensure iptables is allowing web access. 'system-config-firewall' to configure.
fi


# --------------- Install Complete ---------------
echo .
echo Configuration Complete.
echo ..This website: 	http://localhost/neosentry
echo .
exit


#SOME USEFUL MYSQL COMMANDS:

# uncomment to reset mysql root password. must be run as root. 'mysql -u root -p' to log in
#printf "UPDATE mysql.user SET Password=PASSWORD('P@ssw0rd!') WHERE user='root';\nFLUSH PRIVILEGES;\n" > /root/tmpreset_root_pw.sql
#/etc/rc.d/init.d/mysqld stop
#mysqld_safe --init-file=/root/tmpreset_root_pw.sql &
#sleep 10
#rm -f /root/reset_root_pw.sql
#/etc/rc.d/init.d/mysqld restart

#UPDATE user SET Password=PASSWORD('guest') WHERE user='guest';
#GRANT ALL PRIVILEGES ON demo.* TO 'guest'@'localhost' IDENTIFIED BY 'guest' WITH GRANT OPTION;
#ALTER TABLE table ADD column_name INT UNSIGNED DEFAULT 0;
#ALTER TABLE 'table' ADD INDEX ('column_name');


