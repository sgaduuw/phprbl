<?php

#
# PHPrbl: PHP realtime black listing
# Released under GNU GPL License version 2, see LICENSE for more info
#
# (c) Eelco Wesemann (eelco@init1.nl)
# http://phprbl.init1.nl || http://eol.init1.nl
# (Version 0.4, Oct 26 2005)



require("config.php");

while (!isset($_SERVER["PHP_AUTH_USER"])) {
	header("WWW-Authenticate: Basic realm=\"PHPrbl Admin Area\"");
	header("HTTP/1.0 401 Unauthorized");
	echo "<h1>401 Unauthorized</h1><br />";
	echo "Try a little harder";
	exit();
}
if ($_SERVER["PHP_AUTH_USER"] == $admin_user && $_SERVER["PHP_AUTH_PW"] == $admin_pass && $enable_admin_area == 1) {
	if ($_POST) {
		$keyword = $_POST['keyword'];
	} elseif ($_GET) {
		$keyword = $_GET['keyword'];
	} else {
		echo "Whatchoo doing?\n";
		exit("<div align=\"center\"><a href=\"index.php\">back to index</a></div>");
	}

	$errors = 0;
	if (strlen($keyword) == 0) {
		echo "Please enter a keyword<br />\n";
		$errors++;
	}

	if ($errors > 0) {
		echo "You did something wrong!<br />\n";
		exit("<div align=\"center\"><a href=\"index.php\">back to index</a></div>");
	}

	$datestring = time();

	$mysql_link = mysql_connect("$mysql_host", "$mysql_user", "$mysql_pass") or die("Unable to connect to database");
	mysql_select_db($mysql_data, $mysql_link) or die ("Unable to select database");

	$query_keyword = mysql_query("SELECT count(keyword) AS countname FROM keywords WHERE keyword='$keyword'", $mysql_link);
	while($row = mysql_fetch_object($query_keyword))
	{
		$countname = $row->countname;
	}
	if ($countname > 0) {
		echo "We already have the word \"<b>$keyword</b>\" listed!<br />\n";
		exit("<div align=\"center\"><a href=\"index.php\">back to index</a></div>");
	} else {
		header("Location: index.php");
		mysql_query("INSERT INTO keywords (keyword,added) VALUES ('$keyword', '$datestring')");
	}
} else {
	exit("Bad credentials, or Admin Area not enabled.");
}
?>
