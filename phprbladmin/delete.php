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
		$keyword_id = $_POST['keyword_id'];
	} elseif ($_GET) {
		$keyword_id = $_GET['keyword_id'];
	} else {
		echo "Whatchoo doing?\n";
		exit("<div align=\"center\"><a href=\"index.php\">back to index</a></div>");
	}
	
	$mysql_link = mysql_connect("$mysql_host", "$mysql_user", "$mysql_pass") or die("Unable to connect to database");
	mysql_select_db($mysql_data, $mysql_link) or die ("Unable to select database");

	$mysql_query = mysql_query("SELECT id,keyword FROM keywords WHERE id='$keyword_id' LIMIT 1");
	while ($row = mysql_fetch_object($mysql_query)) {
		echo "Deleting keyword: <b>". $row->keyword ."</b>\n";
		mysql_query("DELETE FROM keywords WHERE id='$keyword_id'");
		echo "<div align=\"center\"><a href=\"index.php\">back to index</a></div>\n";
	}


} else {
	exit("Bad credentials, or Admin Area not enabled.");
}
?>
