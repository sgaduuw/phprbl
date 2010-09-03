<?php

#
# PHPrbl: PHP realtime black listing
# Released under GNU GPL License version 2, see LICENSE for more info
#
# (c) Eelco Wesemann (eelco@init1.nl)
# http://phprbl.init1.nl || http://eol.init1.nl
# (Version 0.4, Oct 26 2005)

# Configuration section

# The following aray contains the RBL services we want to use
# Spamhaus and AHBL are tested to be quite fast, you can 
# add your own, or remove unwanted services from the array.
$rbl_services = array ('rbl.init1.nl', 'sbl-xbl.spamhaus.org', 'dnsbl.ahbl.org');

# set $mysql_enable to 1 if you want to log blocked hosts to mysql
# please note that the table structure has changed between version 0.1 
# and version 0.2. The table needs to be dropped and recreated. See the
# README for more info.
$mysql_enable = 0;

# Set $mysql_precheck to 1 if you want to check an IP address against the local
# database first
$mysql_precheck = 0;

# set check_keywords to 1 if you want to check referrer URL's for bad words
# or strings. This option needs $mysql_enable to be set to 1.
$check_keywords = 0;

# set $keywords_autoblock_mysql to 1 if you want the clients IP address that gave
# a bad referrerstring to be added to the local database of bad IPs.
$keywords_autoblock_mysql = 0;

# if $mysql_enable is 1, you will need to enter the following information
$mysql_host = "MYSQLHOST";		# mysql host (usually localhost)
$mysql_user = "MYSQLUSER";		# mysql username
$mysql_pass = "MYSQLPASS";		# mysql password
$mysql_data = "MYSQLDATA";		# mysql database

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

# function blockpage:
# arguments: client ip address, services IP is listed in.
# Pass the 403 Forbidden Header, and show a bit of HTML explaining
# what is going on, and why the page is not loading.
function blockpage ($client_ip,$service) {
	header("HTTP/1.0 403 Forbidden");
	echo "<html><head>\n";
	echo "<title>403 Forbidden - PHPrbl</title>\n";
	echo "</head><body>\n";
	echo "<h1>403 Forbidden</h1><br />\n";
	echo "Your client IP ($client_ip) is listed as an open proxy or abusive host at the following services:<br />\n";
	echo "<ul>\n";
	$blockedby = explode(";", $service);
	foreach ($blockedby as $rbl) {
		if (strlen($rbl) > 0) {
			echo " <li>$rbl</li>\n";
		}
	}
	echo "</ul>\n";
	echo "Users of open proxies are unwanted on this site because of various types of SPAM.<br />\n";
	echo "IP address denied and thus the page is exiting<br /><br />\n";
	exit("This site is protected against open proxies and abusive hosts by <a href=\"http://phprbl.init1.nl\">PHPrbl</a>.\n</body></html>");
}
#end function blockpage

# function blockkeyword:
# arguments: keyword(s) that triggered the block to display on 403 page
# Pass the 403 Forbidden Header, and show a bit of HTML explaining
# what is going on, and why the page is not loading.
function blockkeyword ($keywordmatches) {
	header("HTTP/1.0 403 Forbidden");
	echo "<html><head>\n";
	echo "<title>403 Forbidden - PHPrbl</title>\n";
	echo "</head><body>\n";
	echo "<h1>403 Forbidden</h1><br />\n";
	echo "The referring page that linked to this site seems to contain a pattern of letters that are banned by this site's admin.<br />";
	echo "This should only block automated referrer spam runs.<br />";
	echo "<ul>\n";
	foreach ($keywordmatches as $keyword) {
		if (strlen($keyword) > 0) {
			echo " <li>$keyword</li>\n";
		}
	}
	echo "</ul>\n";
	echo "If you still want to view this page, go back to the previous page, and copy-paste the link into the location bar. This page should load fine then.<br /><br />\n";
	# please leave the following line intact.
	exit("This site is protected against open proxies and abusive hosts by <a href=\"http://phprbl.init1.nl\">PHPrbl</a>.\n</body></html>");
}
#end function blockpage

$matches = 0;
$service = "";

if ($mysql_enable == 1) {
	$mysql_link = mysql_connect("$mysql_host", "$mysql_user", "$mysql_pass") or die("Unable to connect to database");
	mysql_select_db($mysql_data, $mysql_link) or die ("Unable to select database");

	if ($mysql_precheck == 0) {
		$query_visits = mysql_query("SELECT visits FROM blocked WHERE ip='$client_ip'");
		while($row = mysql_fetch_object($query_visits)) {
			$visits = $row->visits;	
		}
	} else {
		$query_count_ip = mysql_query("SELECT count(ip) AS precheck_ip,service FROM blocked WHERE ip='$client_ip' GROUP BY service");

		while($row = mysql_fetch_object($query_count_ip)) {
			$precheck_ip = $row->precheck_ip;
			$service = $row->service;
		}
		if ($precheck_ip > 0) {
			if (!preg_match("/localmysql/i", $service)) {
				$service .= "localmysql;";
			}

			mysql_query("UPDATE blocked SET lastseen='$timestamp', service='$service', visits=visits+1, referer='$referer' WHERE ip='$client_ip'");
			mysql_close($mysql_link);
			blockpage($client_ip,$service);
		}
	}

	# well well, the client IP passed the MySQL prechecks.
	# Let's see if the referring URL contains any unwanted strings
	if ($check_keywords == 1) {
		$mysql_link = mysql_connect("$mysql_host", "$mysql_user", "$mysql_pass") or die("Unable to connect to database");
		mysql_select_db($mysql_data, $mysql_link) or die ("Unable to select database");
		# we want to check the referrer, but... is there one?
		# Let's find out
		if (strlen($referer) > 0) {
			# make $referer lowercase, makes things easier
			$referer = strtolower($referer);
			$word_matches = 0;
			$keywordmatches = array();
		
			$mysql_query = mysql_query("SELECT keyword,occurances FROM keywords");
			while ($row = mysql_fetch_object($mysql_query)) {
				$keyword = strtolower($row->keyword);
				$occurances = $row->occurances;
				$pattern = "/$keyword/";
				if (preg_match($pattern, $referer, $out, PREG_OFFSET_CAPTURE)) {
					$word_matches++;
					array_push($keywordmatches, $keyword);
					mysql_query("UPDATE keywords SET occurances=occurances+1 WHERE keyword='$keyword'");
				}
			}
			if ($word_matches > 0) {
				if ($keywords_autoblock_mysql == 1 && $visits == 0) {
					$blockreason = "AUTOBLOCK: ";
					foreach ($keywordmatches as $keyword) {
						if (strlen($keyword) > 0) {
							$blockreason .= "$keyword ";
						}
					}
					mysql_query("INSERT INTO blocked (ip,lastseen,service,visits,referer) VALUES ('$client_ip', '$timestamp', 'localkeywordblock;', '1', '$blockreason')");
				}
				blockkeyword($keywordmatches);
			}
		}
	}
}

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
		if ($visits > 0) {
			# visits > 0; This means we know the IP already and have to raise it by 1
			mysql_query("UPDATE blocked SET lastseen='$timestamp', service='$service', visits=visits+1, referer='$referer' WHERE ip='$client_ip'");
		} else {
			# visits = 0; We haven't seen the IP address yet, and will insert it for the first time with a visits value of 1
			mysql_query("INSERT INTO blocked (ip,lastseen,service,visits,referer) VALUES ('$client_ip', '$timestamp', '$service', '1', '$referer')");
		}
		mysql_close($mysql_link);
	}
	blockpage($client_ip,$service);
}
?>
