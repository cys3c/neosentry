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

if hash yum 2>/dev/null; then
	# Installer logic for CentOS / RedHat Distros
	
	# ---------- Install prerequisite packages ----------
	echo Installing some useful linux network Administration and Security related packages
	yum -y install mtr traceroute tcpdump bind-utils jwhois net-snmp-utils curl wget net-tools iputils diffutils openssh-server
	yum -y install nmap

	# ---------- Install Apache ----------
	echo Installing Apache
	yum -y install httpd httpd-devel mod_ssl openssl pecl

	# ---------- Install PHP 7.1 ----------
	if ! hash php 2>/dev/null; then
	    echo Installing PHP 7.1
	    #yum -y install php-zts.x86_64 php php-mysql php-common php-gd php-mbstring php-mcrypt php-devel php-xml
        rpm -Uvh http://rpms.famillecollet.com/enterprise/remi-release-6.rpm
        rpm -Uvh https://mirror.webtatic.com/yum/el6/latest.rpm
        rpm -Uvh http://repo.mysql.com/mysql-community-release-el6-5.noarch.rpm
        yum -y install php71w mod_php71w php71w-opcache php71w-devel php71w-mysql php71w-gd php71w-mcrypt php71w-mbstring php71w-json php71w-pear php71w-pecl-mongodb php71w-snmp php71w-ldap php71-php-pecl-ssh2
    else
        echo " - PHP is already installed, skipping this step"
        echo " -- It is recommended to have PHP 7.1 so you could uninstall previous versions and re-run this script to install v7.1"
    fi

    # ---------- Install Database ----------
    #echo Installing MySQL
    #yum -y install mysql mysql-server mysql-devel
    #service mysqld start
	#chkconfig mysqld on
	#/etc/init.d/mysqld start
    if ! hash mongodb 2>/dev/null; then
        echo Installing MongoDB 3
        VAR_REPO="/etc/yum.repos.d/mongodb-org-3.4.repo"
        echo "[mongodb-org-3.4]" > $VAR_REPO
        echo "name=MongoDB Repository" >> $VAR_REPO
        echo "baseurl=https://repo.mongodb.org/yum/amazon/2013.03/mongodb-org/3.4/x86_64/" >> $VAR_REPO
        echo "gpgcheck=1" >> $VAR_REPO
        echo "enabled=1" >> $VAR_REPO
        echo "gpgkey=https://www.mongodb.org/static/pgp/server-3.4.asc" >> $VAR_REPO
        yum -y install mongodb-org
        service mongod start
        chkconfig mongod on
    else
        echo " - MongoDB is already installed, skipping installation"
    fi

	# ---------- Start Services and Set Autorun -----------
	echo Starting Services. 
	/etc/init.d/httpd restart
	echo Setting httpd to autostart
	chkconfig httpd on


else
	# Installer logic for Debian / Ubuntu Distros

	# ---------- Install prerequisite packages ----------
	echo Installing some useful linux network Administration and Security related packages
	apt-get -m -y install mtr traceroute dnsutils tcpdump whois snmp curl wget htop diffutils ipcalc
	apt-get -m -y install nmap openssh-server sudo openssl

    # ---------- Install Apache ----------
	echo Installing Apache
    apt-get -m -y install apache2 python3 libapache2-mod-python


    # ---------- Install PHP 5 ----------
	if ! hash php 2>/dev/null; then
        echo Installing PHP 5
        apt-get -m -y install php5 php-pear php5-mysql libapache2-mod-php5 php-pecl-ssh2
	else
        echo " - PHP is already installed, skipping this step"
        echo " -- It is recommended to have PHP 7.1 but this will require manual installation on Debian based systems"
    fi

    # ---------- Install Database ----------
    #echo Installing MySQL
    #apt-get -m -y install mysql mysql-server mysql-devel
	service mysqld start
    if ! hash mongodb 2>/dev/null; then
        echo "MongoDB is not installed and installation varies depending on the version of Debian"
        echo " - See https://docs.mongodb.com/manual/administration/install-on-linux/"
    else
        echo " - MongoDB is already installed, skipping installation"
    fi

	
	# ---------- Start Services -----------
	echo Starting Services.
	service apache2 restart
	
fi


# ---------- Compile the handler application ----------

if ! hash gcc 2>/dev/null; then
    echo Installing gcc to compile the application
    yum -y install gcc
fi
echo Compiling NeoSentry command handler for the web interface
gcc -o neosentry neosentry.c
chown root neosentry
chmod u+s neosentry
ln neosentry /usr/bin/neosentry



# ---------- Make directories, copy files, and set permissions ----------

INSTALL_DIR="/usr/share/neosentry"
if [ "$PWD" == "$INSTALL_DIR" ]; then
    echo Moving files and directories to $INSTALL_DIR and making APACHE the Owner
    mkdir -p $INSTALL_DIR
    mv www $INSTALL_DIR/
    mv data $INSTALL_DIR/
    mv lib $INSTALL_DIR/
    mkdir -p $INSTALL_DIR/data/devices
    #chmod g+w settings.conf
else
    echo " - No need to move files, you're already in $INSTALL_DIR"
fi

chmod -R g+w $INSTALL_DIR/data

if hash chcon 2>/dev/null; then
	#SELinux is installed so reset the context to the new path. moving files preserves the old context and will cause access issues.
	echo Resetting SELinux security context on $INSTALL_DIR
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
	echo "! ERROR ! A valid Apache installation was not found. You will have to manually configure the web service to use the applications installation directory of $INSTALL_DIR"
	read "Hit any key to continue..."
fi

# Allow firewall connections if iptables is installed and the rule doesn't exist
if hash iptables 2>/dev/null; then
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
if hash iptables 2>/dev/null; then
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


