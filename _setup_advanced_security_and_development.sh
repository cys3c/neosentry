#!/bin/bash
#
# NeoSentry SETUP SCRIPT for other packages used in development


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
