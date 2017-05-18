# NeoSentry NMS

NeoSentry NMS is an open-source, linux based, network monitoring solution, 
built with security in mind. Real-time monitoring for all types of deviceng 
for all types of devices via Ping, SNMP, SSH, and vulnerability scanning. 
Works via Ping, SNMP, SSH, and vulnerability scanning. Works out of the box 
with minimal initial configuration needed but also allows for advanced 
customization.Works via Ping, SNMP, SSH, and vulnerability scanning. Works 
out of the box with minimal initial configuration needed but also allows 
for advanced customization. Tracking, reporting, and alerting for service 
availability, network changes, and for any other data element collected. 
Custom responsive, mobile friendly, and intuitive front-end design. 
Front end for local tacacs+, and local DNS if you decide to set this up. 


Structure
---------

/config  - stores configs and settings
/data    - stores variable data collected, and for data collection
/lib     - core functions and scripts
/www     - front end web interface.
/www/api - backend REST api for communicating with the stored data and /lib/


Features
--------

- Responsive and intuitive front-end Web GUI
  - Dashboard with the standard graphs and top x lists
- Ping and Traceroute monitoring and tracking
  - Track and Alert on route changes and outages
- SNMP Monitoring and SNMP Trap collection
- Device Configuration collection and tracking
- Service Monitoring


Installation
------------

It's recommended to have PHP7.1 which performs far better than PHP5, but is also a little tricky to setup. 
This will install the prerequisites, configure apache, build the binary, and initialize the app.
I'd recommend CentOS, but Debian based OS's are supported in the script.

``git clone https://github.com/dje144/neosentry.git /usr/share/neosentry``
``./_setup.sh`` supports Debian, CentOS, and RedHat based linux distros.

If you download the source then running setup.sh will copy the files to /usr/share/neosentry


Changes
-------

#### v.01
  - Initial release, still under development


What to work on
---------------

SETUP WIZARD FOR BULK COLLECTION
	- Enter Connection Information for all devices you wish to monitor
		- SNMP Strings, Telnet/SSH username/passwords (blank user for only pw connect), (WMI?)
	- Enter Subnet Range to Scan
		- Consider Host active if it responds to (ping OR port 22/80/etc are open)
	- Have user review list of active devices. report on successful/failed snmp/ssh  

FIREWALL MANAGEMENT [Firemon, Tufin, Indeni, Algosec]
	- Collect rule usage/logs
	- Integrate with security scans to see if something could be blocked/improved
	- Rulebase consolidation/removal/re-arranging recommendations
	
TICKETING / FIREWALL REQUESTS [ServiceNow, Remedy]
	- Allow users to enter ticket request
		- email approvals
	- Check to see if firewall/router/switch/etc changes occurred during the change window
	
	
License
-------

NeoSentry is licensed under the MIT License - see the [LICENSE](LICENSE) file for details


[rootSecure](https://www.rootsecure.io/)
