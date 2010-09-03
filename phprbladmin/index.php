<?php
#
# PHPrbl: PHP realtime black listing
# Released under GNU GPL License version 2, see LICENSE for more info
#
# (c) Eelco Wesemann (eelco@init1.nl)
# http://phprbl.init1.nl || http://eol.init1.nl
# (Version 0.4, Oct 26 2005)


require("config.php");

if ($admin_user == "PHPRBLADMINUSER" || $admin_pass == "PHPRBLADMINPASS") {
	exit("Please, please don't use the default username and password.");
}

while (!isset($_SERVER["PHP_AUTH_USER"])) {
	header("WWW-Authenticate: Basic realm=\"PHPrbl Admin Area\"");
	header("HTTP/1.0 401 Unauthorized");
	echo "<h1>401 Unauthorized</h1><br />";
	echo "Try a little harder";
	exit();
}
if ($_SERVER["PHP_AUTH_USER"] == $admin_user && $_SERVER["PHP_AUTH_PW"] == $admin_pass && $enable_admin_area == 1) {

$mysql_link = mysql_connect("$mysql_host", "$mysql_user", "$mysql_pass") or die("Unable to connect to database");
mysql_select_db($mysql_data, $mysql_link) or die ("Unable to select database");

?>
<html>
 <head>
  <title>PHPrbl Admin Area</title>
 </head>
 <body>
  <h3>Welcome to PHPrbl's Admin Area</h3><br />
  Bad keywords:
  <table border="1" width="700">
   <tr>
    <td align="center" width="50">hits</td>
    <td align="center">keyword</td>
    <td align="center" width="50">del</td>    
   </tr>
<?php
$mysql_query = mysql_query("SELECT id,keyword,occurances FROM keywords ORDER BY occurances DESC");
while ($row = mysql_fetch_object($mysql_query)) {
	echo " <tr>\n";
	echo "  <td align=\"center\">". $row->occurances ."</td>\n";
	echo "  <td align=\"left\">". $row->keyword ."</td>\n";
	echo "  <td align=\"center\"><a href=\"delete.php?keyword_id=". $row->id ."\">del</a></td>\n";
	echo " </tr>\n";
}

?>
  </table>
  <form name="blockkeyword" action="setkeyword.php" method="post">
   Block keyword: <input type="text" name="keyword"> <input type="submit" value="Block!">
  </form>
 </body>
</html>
<?php

mysql_close($mysql_link);

} else {
	exit("Bad credentials, or Admin Area not enabled.");
}
?>


