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
# Note: not all DNSBL services support IPv6 lookups.
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

# set $whitelist_enable to 1 if you want to check incoming IPs against a whitelist
# table before doing any lookups. Whitelisted IPs are never blocked.
# This option needs $mysql_enable to be set to 1 and the whitelist table to exist.
$whitelist_enable = 0;

# set $log_only to 1 to log blocked IPs to the database without actually blocking
# them. Useful for testing PHPrbl before going live. Requires $mysql_enable = 1.
$log_only = 0;

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

# the default RBL services return something like 127.0.0.2 if the IP
# address is listed, if it's not listed, gethostbyname() will return
# the host we wanted to look up.
$phprbl_pattern = '/^127\.0\.0\.\d+$/';

# ── Helper functions ─────────────────────────────────────────────────────────

# All functions are prefixed with phprbl_ to avoid name collisions when this
# file is auto-prepended to another application.

# function phprbl_reverse_ip:
# Reverse an IP address for DNSBL lookups.
# IPv4: reverse the four octets (1.2.3.4 → 4.3.2.1)
# IPv6: expand to full form, then reverse each nibble
#        (2001:db8::1 → 1.0.0.0...8.b.d.0.1.0.0.2)
# Returns null if the IP is not valid.
function phprbl_reverse_ip(string $ip): ?string {
	if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
		return implode('.', array_reverse(explode('.', $ip)));
	}

	if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
		# expand the IPv6 address to its full 32-nibble hex representation
		$packed = inet_pton($ip);
		if ($packed === false) {
			return null;
		}
		$hex = bin2hex($packed);
		# split into individual nibbles, reverse, and dot-separate
		$nibbles = str_split($hex);
		return implode('.', array_reverse($nibbles));
	}

	return null;
}

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

# function phprbl_log_block:
# Log a block event to the database (insert or update).
function phprbl_log_block(PDO $db, string $ip, int $timestamp, string $service, string $referer, int $visits): void {
	if ($visits > 0) {
		$stmt = $db->prepare("UPDATE blocked SET lastseen = :lastseen, service = :service, visits = visits + 1, referer = :referer WHERE ip = :ip");
	} else {
		$stmt = $db->prepare("INSERT INTO blocked (ip, lastseen, service, visits, referer) VALUES (:ip, :lastseen, :service, 1, :referer)");
	}
	$stmt->execute(array(
		'ip'       => $ip,
		'lastseen' => $timestamp,
		'service'  => $service,
		'referer'  => $referer,
	));
}

# ── Main logic ───────────────────────────────────────────────────────────────

# reverse the IP address for DNSBL lookups (supports both IPv4 and IPv6)
$phprbl_reversed = phprbl_reverse_ip($client_ip);

# if the IP is not valid, there's nothing we can do
if ($phprbl_reversed === null) {
	return;
}

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

# ── Whitelist check ──────────────────────────────────────────────────────────

if ($db !== null && $whitelist_enable == 1) {
	try {
		$stmt = $db->prepare("SELECT id FROM whitelist WHERE ip = :ip");
		$stmt->execute(array('ip' => $client_ip));
		if ($stmt->fetch()) {
			# whitelisted — skip all checks entirely
			return;
		}
	} catch (PDOException $e) {
		# whitelist table probably doesn't exist; continue without it
	}
}

# ── MySQL precheck ───────────────────────────────────────────────────────────

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

			if ($log_only == 0) {
				phprbl_blockpage($client_ip, $service);
			}
			return;
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
			if ($log_only == 0) {
				phprbl_blockkeyword($keywordmatches);
			}
			return;
		}
	}
}

# ── DNSBL lookups ────────────────────────────────────────────────────────────

foreach ($rbl_services as $check) {
	$lookup_host = $phprbl_reversed . '.' . $check;
	$result = gethostbyname($lookup_host);
	if (preg_match($phprbl_pattern, $result)) {
		$matches++;
		$service .= "$check;";
	}
}

if ($matches > 0) {
	if ($db !== null) {
		phprbl_log_block($db, $client_ip, $timestamp, $service, $referer, $visits);
	}
	if ($log_only == 0) {
		phprbl_blockpage($client_ip, $service);
	}
}
