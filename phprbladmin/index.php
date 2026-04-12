<?php

#
# PHPrbl Admin Area
# Released under GNU GPL License version 2, see LICENSE for more info
#
# (c) Eelco Wesemann (eelco@init1.nl)
# (Version 1.0)
#
# This single file handles all admin functionality:
# - Keywords: list, add, delete
# - Blocked IPs: list, unblock
# - Whitelist: list, add, delete
#
# Old setkeyword.php and delete.php are no longer needed and can be removed.
#

require("config.php");

if ($enable_admin_area != 1) {
	exit("Admin Area is not enabled. Set \$enable_admin_area to 1 in config.php");
}

if ($admin_user == "PHPRBLADMINUSER" || $admin_pass == "PHPRBLADMINPASS") {
	exit("Please change the default username and password in config.php");
}

# ── Database connection ──────────────────────────────────────────────────────

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
	exit("Unable to connect to database.");
}

# ── Session and authentication ───────────────────────────────────────────────

session_start();

# Generate or retrieve CSRF token
if (empty($_SESSION['phprbl_csrf_token'])) {
	$_SESSION['phprbl_csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['phprbl_csrf_token'];

function phprbl_check_csrf(): void {
	if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['phprbl_csrf_token'], $_POST['csrf_token'])) {
		exit("Invalid request. Please go back and try again.");
	}
}

# Handle login
if (isset($_POST['action']) && $_POST['action'] === 'login') {
	phprbl_check_csrf();
	$given_user = $_POST['username'] ?? '';
	$given_pass = $_POST['password'] ?? '';

	global $admin_user, $admin_pass;
	if (hash_equals($admin_user, $given_user) && hash_equals($admin_pass, $given_pass)) {
		$_SESSION['phprbl_logged_in'] = true;
		# regenerate session ID to prevent fixation
		session_regenerate_id(true);
		header("Location: index.php");
		exit;
	} else {
		$login_error = "Bad credentials.";
	}
}

# Handle logout
if (isset($_POST['action']) && $_POST['action'] === 'logout') {
	phprbl_check_csrf();
	$_SESSION = array();
	session_destroy();
	header("Location: index.php");
	exit;
}

# If not logged in, show login form and stop
if (empty($_SESSION['phprbl_logged_in'])) {
	phprbl_render_login($csrf_token, $login_error ?? null);
	exit;
}

# ── POST action handling (all require CSRF) ──────────────────────────────────

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
	phprbl_check_csrf();

	switch ($_POST['action']) {

		case 'add_keyword':
			$keyword = trim($_POST['keyword'] ?? '');
			if (strlen($keyword) > 0) {
				$stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM keywords WHERE keyword = :keyword");
				$stmt->execute(array('keyword' => $keyword));
				$row = $stmt->fetch();
				if ($row->cnt > 0) {
					$message = "Keyword \"" . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . "\" already exists.";
				} else {
					$stmt = $db->prepare("INSERT INTO keywords (keyword, added) VALUES (:keyword, :added)");
					$stmt->execute(array('keyword' => $keyword, 'added' => time()));
					$message = "Keyword \"" . htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') . "\" added.";
				}
			}
			break;

		case 'delete_keyword':
			$id = (int) ($_POST['id'] ?? 0);
			if ($id > 0) {
				$stmt = $db->prepare("DELETE FROM keywords WHERE id = :id");
				$stmt->execute(array('id' => $id));
				$message = "Keyword deleted.";
			}
			break;

		case 'unblock_ip':
			$id = (int) ($_POST['id'] ?? 0);
			if ($id > 0) {
				$stmt = $db->prepare("DELETE FROM blocked WHERE id = :id");
				$stmt->execute(array('id' => $id));
				$message = "IP unblocked.";
			}
			break;

		case 'add_whitelist':
			$ip = trim($_POST['ip'] ?? '');
			$note = trim($_POST['note'] ?? '');
			if (strlen($ip) > 0) {
				$stmt = $db->prepare("SELECT COUNT(*) AS cnt FROM whitelist WHERE ip = :ip");
				$stmt->execute(array('ip' => $ip));
				$row = $stmt->fetch();
				if ($row->cnt > 0) {
					$message = "IP " . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . " is already whitelisted.";
				} else {
					$stmt = $db->prepare("INSERT INTO whitelist (ip, added, note) VALUES (:ip, :added, :note)");
					$stmt->execute(array('ip' => $ip, 'added' => time(), 'note' => $note));
					$message = "IP " . htmlspecialchars($ip, ENT_QUOTES, 'UTF-8') . " added to whitelist.";
				}
			}
			break;

		case 'delete_whitelist':
			$id = (int) ($_POST['id'] ?? 0);
			if ($id > 0) {
				$stmt = $db->prepare("DELETE FROM whitelist WHERE id = :id");
				$stmt->execute(array('id' => $id));
				$message = "IP removed from whitelist.";
			}
			break;
	}
}

# ── Page routing ─────────────────────────────────────────────────────────────

$page = $_GET['page'] ?? 'keywords';

switch ($page) {
	case 'blocked':
		$stmt = $db->prepare("SELECT id, ip, visits, lastseen, service, referer FROM blocked ORDER BY lastseen DESC LIMIT 200");
		$stmt->execute();
		$rows = $stmt->fetchAll();
		phprbl_render_page($page, $rows, $csrf_token, $message);
		break;

	case 'whitelist':
		try {
			$stmt = $db->prepare("SELECT id, ip, added, note FROM whitelist ORDER BY added DESC");
			$stmt->execute();
			$rows = $stmt->fetchAll();
		} catch (PDOException $e) {
			$rows = array();
			$message = "Whitelist table not found. See the README for the CREATE TABLE statement.";
		}
		phprbl_render_page($page, $rows, $csrf_token, $message);
		break;

	case 'keywords':
	default:
		$stmt = $db->prepare("SELECT id, keyword, occurances FROM keywords ORDER BY occurances DESC");
		$stmt->execute();
		$rows = $stmt->fetchAll();
		phprbl_render_page($page, $rows, $csrf_token, $message);
		break;
}

# ── Rendering functions ─────────────────────────────────────────────────────

function phprbl_render_login(string $csrf_token, ?string $error): void {
	$err_html = $error ? '<p style="color:#c00">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>' : '';
	echo <<<HTML
	<!DOCTYPE html>
	<html lang="en">
	<head>
		<meta charset="utf-8">
		<title>PHPrbl Admin — Login</title>
		<style>
			body { font-family: sans-serif; max-width: 400px; margin: 80px auto; }
			input { display: block; width: 100%; padding: 6px; margin: 6px 0 12px; box-sizing: border-box; }
			button { padding: 8px 20px; }
		</style>
	</head>
	<body>
		<h2>PHPrbl Admin</h2>
		{$err_html}
		<form method="post" action="index.php">
			<input type="hidden" name="action" value="login">
			<input type="hidden" name="csrf_token" value="{$csrf_token}">
			<label>Username<input type="text" name="username" required></label>
			<label>Password<input type="password" name="password" required></label>
			<button type="submit">Log in</button>
		</form>
	</body>
	</html>
	HTML;
}

function phprbl_render_page(string $page, array $rows, string $csrf_token, string $message): void {
	$esc = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
	$msg_html = $message ? '<p style="background:#eef;padding:8px;border:1px solid #ccf">' . $esc($message) . '</p>' : '';

	$nav = '';
	foreach (array('keywords' => 'Keywords', 'blocked' => 'Blocked IPs', 'whitelist' => 'Whitelist') as $key => $label) {
		$active = ($page === $key) ? ' style="font-weight:bold"' : '';
		$nav .= " <a href=\"index.php?page={$key}\"{$active}>{$label}</a> &middot; ";
	}

	echo <<<HTML
	<!DOCTYPE html>
	<html lang="en">
	<head>
		<meta charset="utf-8">
		<title>PHPrbl Admin — {$esc(ucfirst($page))}</title>
		<style>
			body { font-family: sans-serif; max-width: 900px; margin: 20px auto; padding: 0 10px; }
			table { border-collapse: collapse; width: 100%; margin: 12px 0; }
			th, td { border: 1px solid #ccc; padding: 6px 10px; text-align: left; }
			th { background: #f5f5f5; }
			input[type=text] { padding: 5px; }
			button { padding: 5px 14px; cursor: pointer; }
			.del-btn { background: #fdd; border: 1px solid #c99; }
			nav { margin-bottom: 16px; }
			.topbar { display: flex; justify-content: space-between; align-items: center; }
		</style>
	</head>
	<body>
		<div class="topbar">
			<h2>PHPrbl Admin</h2>
			<form method="post" action="index.php" style="margin:0">
				<input type="hidden" name="action" value="logout">
				<input type="hidden" name="csrf_token" value="{$csrf_token}">
				<button type="submit">Log out</button>
			</form>
		</div>
		<nav>{$nav}</nav>
		{$msg_html}
	HTML;

	match ($page) {
		'keywords' => phprbl_render_keywords($rows, $csrf_token, $esc),
		'blocked'  => phprbl_render_blocked($rows, $csrf_token, $esc),
		'whitelist'=> phprbl_render_whitelist($rows, $csrf_token, $esc),
	};

	echo "</body></html>";
}

function phprbl_render_keywords(array $rows, string $csrf_token, callable $esc): void {
	echo <<<HTML
		<h3>Blocked keywords</h3>
		<table>
			<tr><th>Hits</th><th>Keyword</th><th></th></tr>
	HTML;

	foreach ($rows as $row) {
		$id = (int) $row->id;
		$keyword = $esc($row->keyword);
		$hits = (int) $row->occurances;
		echo <<<HTML
			<tr>
				<td style="width:60px;text-align:center">{$hits}</td>
				<td>{$keyword}</td>
				<td style="width:80px;text-align:center">
					<form method="post" action="index.php?page=keywords" style="margin:0"
						onsubmit="return confirm('Delete keyword {$keyword}?')">
						<input type="hidden" name="action" value="delete_keyword">
						<input type="hidden" name="id" value="{$id}">
						<input type="hidden" name="csrf_token" value="{$csrf_token}">
						<button type="submit" class="del-btn">del</button>
					</form>
				</td>
			</tr>
		HTML;
	}

	echo <<<HTML
		</table>
		<form method="post" action="index.php?page=keywords">
			<input type="hidden" name="action" value="add_keyword">
			<input type="hidden" name="csrf_token" value="{$csrf_token}">
			Add keyword: <input type="text" name="keyword" required>
			<button type="submit">Block</button>
		</form>
	HTML;
}

function phprbl_render_blocked(array $rows, string $csrf_token, callable $esc): void {
	echo <<<HTML
		<h3>Blocked IPs</h3>
		<p>Showing the 200 most recently seen entries.</p>
		<table>
			<tr><th>IP</th><th>Visits</th><th>Last seen</th><th>Service</th><th>Referer</th><th></th></tr>
	HTML;

	foreach ($rows as $row) {
		$id = (int) $row->id;
		$ip = $esc($row->ip);
		$visits = (int) $row->visits;
		$lastseen = $esc((string) $row->lastseen);
		$service = $esc($row->service);
		$referer = $esc((string) ($row->referer ?? ''));
		echo <<<HTML
			<tr>
				<td>{$ip}</td>
				<td style="text-align:center">{$visits}</td>
				<td>{$lastseen}</td>
				<td>{$service}</td>
				<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{$referer}</td>
				<td style="width:80px;text-align:center">
					<form method="post" action="index.php?page=blocked" style="margin:0"
						onsubmit="return confirm('Unblock {$ip}?')">
						<input type="hidden" name="action" value="unblock_ip">
						<input type="hidden" name="id" value="{$id}">
						<input type="hidden" name="csrf_token" value="{$csrf_token}">
						<button type="submit" class="del-btn">unblock</button>
					</form>
				</td>
			</tr>
		HTML;
	}

	echo "</table>";
}

function phprbl_render_whitelist(array $rows, string $csrf_token, callable $esc): void {
	echo <<<HTML
		<h3>Whitelisted IPs</h3>
		<table>
			<tr><th>IP</th><th>Added</th><th>Note</th><th></th></tr>
	HTML;

	foreach ($rows as $row) {
		$id = (int) $row->id;
		$ip = $esc($row->ip);
		$added = $esc((string) $row->added);
		$note = $esc((string) ($row->note ?? ''));
		echo <<<HTML
			<tr>
				<td>{$ip}</td>
				<td>{$added}</td>
				<td>{$note}</td>
				<td style="width:80px;text-align:center">
					<form method="post" action="index.php?page=whitelist" style="margin:0"
						onsubmit="return confirm('Remove {$ip} from whitelist?')">
						<input type="hidden" name="action" value="delete_whitelist">
						<input type="hidden" name="id" value="{$id}">
						<input type="hidden" name="csrf_token" value="{$csrf_token}">
						<button type="submit" class="del-btn">del</button>
					</form>
				</td>
			</tr>
		HTML;
	}

	echo <<<HTML
		</table>
		<form method="post" action="index.php?page=whitelist">
			<input type="hidden" name="action" value="add_whitelist">
			<input type="hidden" name="csrf_token" value="{$csrf_token}">
			IP: <input type="text" name="ip" required placeholder="1.2.3.4 or 2001:db8::1">
			Note: <input type="text" name="note" placeholder="optional">
			<button type="submit">Add to whitelist</button>
		</form>
	HTML;
}
