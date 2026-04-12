<?php

#
# PHPrbl: PHP realtime black listing
# Released under GNU GPL License version 2, see LICENSE for more info
#
# (c) Eelco Wesemann (eelco@init1.nl)
# http://phprbl.init1.nl || http://eol.init1.nl
# (Version 1.0)
#
# Usage:
# Place this file anywhere on your server and use auto_prepend_file to
# have PHP execute it before any other code. No changes to your site needed.
#
#   Apache (.htaccess):   php_value auto_prepend_file /path/to/rbl.php
#   PHP-FPM (.user.ini):  auto_prepend_file = /path/to/rbl.php
#   Or set it in php.ini for server-wide protection.
#
# Alternatively, you can still require_once('rbl.php') at the top of your
# site's index.php, just like the old days.
#

# ── Configuration section ────────────────────────────────────────────────────

# The following array contains the RBL services we want to use.
# Spamhaus is tested to be quite fast, you can add your own,
# or remove unwanted services from the array.
$rbl_services = array('sbl-xbl.spamhaus.org', 'zen.spamhaus.org');

# set $mysql_enable to 1 if you want to log blocked hosts to mysql.
# please note that the table structure has changed between version 0.4
# and version 1.0. See the README for upgrade instructions.
$mysql_enable = 0;

# Set $mysql_precheck to 1 if you want to check an IP address against the local
# database first. This skips DNS lookups for known bad IPs.
$mysql_precheck = 0;

# set $check_keywords to 1 if you want to check referrer URL's for bad words
# or strings. This option needs $mysql_enable to be set to 1.
$check_keywords = 0;

# set $keywords_autoblock_mysql to 1 if you want the clients IP address that gave
# a bad referrer string to be added to the local database of bad IPs.
$keywords_autoblock_mysql = 0;

# if $mysql_enable is 1, you will need to enter the following information
$mysql_host = "MYSQLHOST";		# mysql host (usually localhost)
$mysql_user = "MYSQLUSER";		# mysql username
$mysql_pass = "MYSQLPASS";		# mysql password
$mysql_data = "MYSQLDATA";		# mysql database

#
# there should be no real need to edit anything below this
#

# ── Request data ─────────────────────────────────────────────────────────────

$client_ip = $_SERVER["REMOTE_ADDR"] ?? '';
$referer   = $_SERVER["HTTP_REFERER"] ?? '';
$timestamp = time();

# reverse the IP address order for the lookups
$reverse_ip = array_reverse(explode('.', $client_ip));

# the default RBL services return something like 127.0.0.2 if the IP
# address is listed, if it's not listed, gethostbyname() will return
# the host we wanted to look up.
$phprbl_pattern = '/^127\.0\.0\.\d+$/';

# ── Block page functions ─────────────────────────────────────────────────────

# All functions are prefixed with phprbl_ to avoid name collisions when this
# file is auto-prepended to another application.

# function phprbl_blockpage:
# arguments: client ip address, services IP is listed in.
# Pass the 403 Forbidden Header, and show a bit of HTML explaining
# what is going on, and why the page is not loading.
function phprbl_blockpage(string $client_ip, string $service): never {
	header("HTTP/1.1 403 Forbidden");
	echo "<html><head>\n";
	echo "<title>403 Forbidden - PHPrbl</title>\n";
	echo "</head><body>\n";
	echo "<h1>403 Forbidden</h1><br />\n";
	echo "Your client IP (" . htmlspecialchars($client_ip, ENT_QUOTES, 'UTF-8') . ") is listed as an open proxy or abusive host at the following services:<br />\n";
	echo "<ul>\n";
	foreach (array_filter(explode(";", $service)) as $rbl) {
		echo " <li>" . htmlspecialchars($rbl, ENT_QUOTES, 'UTF-8') . "</li>\n";
	}
	echo "</ul>\n";
	echo "Users of open proxies are unwanted on this site because of various types of SPAM.<br />\n";
	echo "IP address denied and thus the page is exiting<br /><br />\n";
	exit("This site is protected against open proxies and abusive hosts by <a href=\"https://github.com/eelcowesemann/phprbl\">PHPrbl</a>.\n</body></html>");
}

# function phprbl_blockkeyword:
# arguments: keyword(s) that triggered the block to display on 403 page
# Pass the 403 Forbidden Header, and show a bit of HTML explaining
# what is going on, and why the page is not loading.
function phprbl_blockkeyword(array $keywordmatches): never {
	header("HTTP/1.1 403 Forbidden");
	echo "<html><head>\n";
	echo "<title>403 Forbidden - PHPrbl</title>\n";
	echo "</head><body>\n";
	echo "<h1>403 Forbidden</h1><br />\n";
	echo "The referring page that linked to this site seems to contain a pattern of letters that are banned by this site's admin.<br />";
	echo "This should only block automated referrer spam runs.<br />";
	echo "<ul>\n";
	foreach ($keywordmatches as $keyword) {
		if (strlen($keyword) > 0) {
			echo " <li>" . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . "</li>\n";
		}
	}
	echo "</ul>\n";
	echo "If you still want to view this page, go back to the previous page, and copy-paste the link into the location bar. This page should load fine then.<br /><br />\n";
	# please leave the following line intact.
	exit("This site is protected against open proxies and abusive hosts by <a href=\"https://github.com/eelcowesemann/phprbl\">PHPrbl</a>.\n</body></html>");
}

# ── Main logic ───────────────────────────────────────────────────────────────

$matches = 0;
$service = "";
$visits = 0;
$db = null;

if ($mysql_enable == 1) {
	try {
		$db = new PDO(
			"mysql:host=$mysql_host;dbname=$mysql_data;charset=utf8mb4",
			$mysql_user,
			$mysql_pass,
			array(
				PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
				PDO::ATTR_EMULATE_PREPARES   => false,
			)
		);
	} catch (PDOException $e) {
		# if the database is unreachable, fail open — don't take down the site
		$mysql_enable = 0;
		$db = null;
	}
}

if ($db !== null) {
	if ($mysql_precheck == 0) {
		# just grab the visit count for later use
		$stmt = $db->prepare("SELECT visits FROM blocked WHERE ip = :ip");
		$stmt->execute(array('ip' => $client_ip));
		$row = $stmt->fetch();
		if ($row) {
			$visits = (int) $row->visits;
		}
	} else {
		# full precheck: if the IP is already in our local DB, block immediately
		$stmt = $db->prepare("SELECT service FROM blocked WHERE ip = :ip");
		$stmt->execute(array('ip' => $client_ip));
		$row = $stmt->fetch();

		if ($row) {
			$service = $row->service;
			if (stripos($service, 'localmysql') === false) {
				$service .= "localmysql;";
			}

			$stmt = $db->prepare("UPDATE blocked SET lastseen = :lastseen, service = :service, visits = visits + 1, referer = :referer WHERE ip = :ip");
			$stmt->execute(array(
				'lastseen' => $timestamp,
				'service'  => $service,
				'referer'  => $referer,
				'ip'       => $client_ip,
			));

			phprbl_blockpage($client_ip, $service);
		}
	}

	# well well, the client IP passed the MySQL prechecks.
	# Let's see if the referring URL contains any unwanted strings
	if ($check_keywords == 1 && strlen($referer) > 0) {
		$referer_lower = strtolower($referer);
		$keywordmatches = array();

		$stmt = $db->prepare("SELECT keyword, occurances FROM keywords");
		$stmt->execute();

		while ($row = $stmt->fetch()) {
			$keyword = strtolower($row->keyword);
			if (str_contains($referer_lower, $keyword)) {
				$keywordmatches[] = $keyword;

				$upd = $db->prepare("UPDATE keywords SET occurances = occurances + 1 WHERE keyword = :keyword");
				$upd->execute(array('keyword' => $row->keyword));
			}
		}

		if (count($keywordmatches) > 0) {
			if ($keywords_autoblock_mysql == 1 && $visits == 0) {
				$blockreason = "AUTOBLOCK: " . implode(' ', $keywordmatches);
				$ins = $db->prepare("INSERT INTO blocked (ip, lastseen, service, visits, referer) VALUES (:ip, :lastseen, :service, 1, :referer)");
				$ins->execute(array(
					'ip'       => $client_ip,
					'lastseen' => $timestamp,
					'service'  => 'localkeywordblock;',
					'referer'  => $blockreason,
				));
			}
			phprbl_blockkeyword($keywordmatches);
		}
	}
}

# ── DNSBL lookups ────────────────────────────────────────────────────────────

foreach ($rbl_services as $check) {
	$lookup_rbl_ip = implode('.', $reverse_ip) . '.' . $check;
	$do_lookup = gethostbyname($lookup_rbl_ip);
	if (preg_match($phprbl_pattern, $do_lookup)) {
		$matches++;
		$service .= "$check;";
	}
}

if ($matches > 0) {
	if ($db !== null) {
		if ($visits > 0) {
			# visits > 0; This means we know the IP already and have to raise it by 1
			$stmt = $db->prepare("UPDATE blocked SET lastseen = :lastseen, service = :service, visits = visits + 1, referer = :referer WHERE ip = :ip");
			$stmt->execute(array(
				'lastseen' => $timestamp,
				'service'  => $service,
				'referer'  => $referer,
				'ip'       => $client_ip,
			));
		} else {
			# visits = 0; We haven't seen the IP address yet, insert it for the first time
			$stmt = $db->prepare("INSERT INTO blocked (ip, lastseen, service, visits, referer) VALUES (:ip, :lastseen, :service, 1, :referer)");
			$stmt->execute(array(
				'ip'       => $client_ip,
				'lastseen' => $timestamp,
				'service'  => $service,
				'referer'  => $referer,
			));
		}
	}
	phprbl_blockpage($client_ip, $service);
}
