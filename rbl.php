<?php

#
# PHPrbl: PHP realtime black listing
# Released under GNU GPL License version 2, see LICENSE for more info
#
# (c) Eelco Wesemann (eelco@init1.nl)
# http://phprbl.init1.nl || http://eol.init1.nl
# (Version 0.1, May 4 2005)

# Configuration section

# The following aray contains the RBL services we want to use
# Spamhaus and AHBL are tested to be quite fast, you can 
# add your own, or remove unwanted services from the array.
$rbl_services = array ('sbl-xbl.spamhaus.org', 'dnsbl.ahbl.org');

# set $mysql_enable to 1 if you want to log blocked hosts to mysql
$mysql_enable = 0;

# if $mysql_enable is 1, you will need to enter the following information
$mysql_host = "MYSQLSERVER";
$mysql_user = "MYSQLUSER";
$mysql_pass = "MYSQLPASSWORD";
$mysql_data = "DATABASENAME";

#
# there should be no real need to edit anything below this
#

# this is what we need to do our magic
$client_ip = $_SERVER["REMOTE_ADDR"];
$reverse_ip = array_reverse(explode('.', $client_ip));
$timestamp = time();

# the default RBL services return something like 127.0.0.2 or 127.1.0.20 if
# the IP address is listed, if it's not listed, gethostbyname() will return
# the host we wanted to look up.
# this is a quick and easy match
$pattern = '/127.?.0.?/';


$matches = 0;
foreach ($rbl_services as $check) {
	$lookup_rbl_ip = implode('.', $reverse_ip) . '.' . $check;
	$do_lookup = gethostbyname($lookup_rbl_ip);
	if (preg_match($pattern, $do_lookup, $pat_match)) {
		$matches++;
		if ($check =="sbl-xbl.spamhaus.org") {
			$spamhaus = Y;
		}
		if ($check == "dnsbl.ahbl.org") {
			$ahbl = Y;
		}
	}
}

if ($matches > 0) {
	if ($mysql_enable == 1) {
		$mysql_link = mysql_connect("$mysql_host", "$mysql_user", "$mysql_pass") or die("Unable to connect to database");
		mysql_select_db($mysql_data, $mysql_link) or die ("Unable to select database"); 
		mysql_query("INSERT INTO blocked (ip_address,time,spamhaus,ahbl) VALUES ('$client_ip','$timestamp', '$spamhaus', '$ahbl')") ;
		mysql_close($mysql_link);
	}

	header("HTTP/1.0 403 Forbidden");
	echo "<h1>403 Forbidden</h1><br />\n";
	echo "Your client IP ($client_ip - $lookup_client_ip) is listed as an open proxy at the following services:<br />\n";
	echo "<ul>\n";
	if ($spamhaus == Y) {
		echo "<li><a href=\"http://www.spamhaus.org/query/bl?ip=$client_ip\">Spamhaus</a></li>";
	}
	if ($ahbl == Y) {
		echo "<li><a href=\"http://www.ahbl.org/tools/lookup.php?ip=$client_ip\">AHBL</a></li>";
	}
	echo "</ul>\n";
	echo "Users of open proxies are unwanted on this site because of various types of SPAM.<br /><br />\n";
	echo "IP address denied and thus the page is exiting";
	echo "<br /><br />";
	exit("This site is protected against open proxies by <a href=\"http://phprbl.init1.nl\">PHPrbl</a>.");
}

?>
