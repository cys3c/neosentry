<?php

/**
    Some useful functions for parsing, converting, and testing IPs
**/
class IPTools
{
    //find out if an IP or Subnet is inside a larger subnet
    function addressInNetwork($ip, $range)
    {
        if (strpos($ip,"/")) $ip = explode("/",$ip)[0];
        if (!strpos($range,"/")) $range .= "/32";

        list ($subnet, $bits) = explode('/', $range);
        if (strpos($bits,".")) $bits = $this->netmask2cidr($bits);

        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnet &= $mask; # nb: in case the supplied subnet wasn't correctly aligned
        return ($ip & $mask) == $subnet;
    }

    // convert cidr to netmask
    // e.g. 21 = 255.255.248.0
    public function cidr2netmask($cidr)
    {
        $bin = "";
        for( $i = 1; $i <= 32; $i++ )
            $bin .= $cidr >= $i ? '1' : '0';

        $netmask = long2ip(bindec($bin));

        if ( $netmask == "0.0.0.0")
            return false;

        return $netmask;
    }

    // get network address from cidr subnet
    // e.g. 10.0.2.56/21 = 10.0.0.0
    public function cidr2network($ip, $cidr)
    {
        $network = long2ip((ip2long($ip)) & ((-1 << (32 - (int)$cidr))));

        return $network;
    }

    // convert netmask to cidr
    // e.g. 255.255.255.128 = 25
    public function netmask2cidr($netmask)
    {
        if (!strpos($netmask,".")) return $netmask; //this is not a valid netmask

        $bits = 0;
        $netmask = explode(".", $netmask);

        foreach($netmask as $octect)
            $bits += strlen(str_replace("0", "", decbin($octect)));

        return $bits;
    }

    // is ip in subnet
    // e.g. is 10.5.21.30 in 10.5.16.0/20 == true
    //      is 192.168.50.2 in 192.168.30.0/23 == false
    public function cidr_match($ip, $network, $cidr)
    {
        if ((ip2long($ip) & ~((1 << (32 - $cidr)) - 1) ) == ip2long($network))
        {
            return true;
        }

        return false;
    }
}