<?php

#
# PHPrbl: PHP realtime black listing
# Released under GNU GPL License version 2, see LICENSE for more info
#
# (c) Eelco Wesemann (eelco@init1.nl)
# http://phprbl.init1.nl || http://eol.init1.nl
# (Version 0.2, May 10 2005)

# Configuration section

# The following aray contains the RBL services we want to use
# Spamhaus and AHBL are tested to be quite fast, you can 
# add your own, or remove unwanted services from the array.
$rbl_services = array ('sbl-xbl.spamhaus.org', 'dnsbl.ahbl.org');

# set $mysql_enable to 1 if you want to log blocked hosts to mysql
# please note that the table structure has changed between version 0.1 
# and version 0.2. The table needs to be dropped and recreated. See the
# README for more info.
$mysql_enable = 0;

# if $mysql_enable is 1, you will need to enter the following information
$mysql_host = "MYSQLHOST";
$mysql_user = "MYSQLUSER";
$mysql_pass = "MYSQLPASS";
$mysql_data = "MYSQLDATA";

#
# there should be no real need to edit anything below this
#

# this is what we need to do our magic
$client_ip = $_SERVER["REMOTE_ADDR"];

# get the referer the spammer wanted to pass on
$referer = $_SERVER["HTTP_REFERER"];

# reverse the IP address order for the lookups
$reverse_ip = array_reverse(explode('.', $client_ip));

# timestamp for the lastseen field
$timestamp = time();

# the default RBL services return something like 127.0.0.2 if  the IP 
# address is listed, if it's not listed, gethostbyname() will return
# the host we wanted to look up.
# this is a quick and easy match
$pattern = '/127.0.0.?/';


$matches = 0;
$service = "";
foreach ($rbl_services as $check) {
	$lookup_rbl_ip = implode('.', $reverse_ip) . '.' . $check;
	$do_lookup = gethostbyname($lookup_rbl_ip);
	if (preg_match($pattern, $do_lookup, $pat_match)) {
		$matches++;
		$service .= "$check;";
	}
}

if ($matches > 0) {
	if ($mysql_enable == 1) {
		$mysql_link = mysql_connect("$mysql_host", "$mysql_user", "$mysql_pass") or die("Unable to connect to database");
		mysql_select_db($mysql_data, $mysql_link) or die ("Unable to select database"); 
		
		$query_ip = mysql_query("SELECT count FROM blocked WHERE ip='$client_ip'", $mysql_link);
		if ($row = mysql_fetch_array($query_ip)) {
			$count = $row[0];
			$count++;
			mysql_query("UPDATE blocked SET lastseen='$timestamp', service='$service', count='$count', referer='$referer' WHERE ip='$client_ip'");
		} else {
			# We haven't seen the IP address yet, and will insert it for the first time with a count of 1
			mysql_query("INSERT INTO blocked (ip,lastseen,service,count,referer) values ('$client_ip', '$timestamp', '$service', '1', '$referer')");
		}

		mysql_close($mysql_link);
	}

	header("HTTP/1.0 403 Forbidden");
	echo "<h1>403 Forbidden</h1><br />\n";
	echo "Your client IP ($client_ip - $lookup_client_ip) is listed as an open proxy at the following services:<br />\n";
	echo "<ul>\n";
	$blockedby = explode(";", $service);
	foreach ($blockedby as $rbl) {
		if (strlen($rbl) > 0) {
			echo " <li>$rbl</li>\n";
		}
	}
	echo "</ul>\n";
	echo "Users of open proxies are unwanted on this site because of various types of SPAM.<br />\n";
	echo "IP address denied and thus the page is exiting";
	echo "<br /><br />";
	exit("This site is protected against open proxies by <a href=\"http://phprbl.init1.nl\">PHPrbl</a>.");
}

?>
