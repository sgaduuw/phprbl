# PHPrbl

**PHP Realtime Blacklist** — a single-file tool that blocks visitors from open proxies and abusive hosts, reducing referrer spam on your site.

PHPrbl checks each visitor's IP address against DNS-based blacklist (DNSBL) services. If the IP is listed, the visitor gets a 403 Forbidden page. Optionally, it can also block visitors based on referrer keywords and maintain a local blocklist in MySQL.

## Features

- **DNSBL lookups** against configurable blacklist services (Spamhaus, etc.)
- **IPv4 and IPv6** support
- **Local blocklist** via MySQL with precheck (skip DNS when an IP is already known bad)
- **Keyword blocking** — block visitors whose referrer URL contains banned words
- **Whitelist** — never block known-good IPs
- **Log-only mode** — record what would be blocked without actually blocking (for testing)
- **Zero dependencies** — single PHP file, no Composer, no frameworks
- **Auto-prepend** — works with `auto_prepend_file` so you don't need to modify your site's code

## Requirements

- PHP 8.1 or later
- MySQL/MariaDB (optional, for local blocklist, keywords, and whitelist features)

## Quick Start

1. Copy `rbl.php` to your server
2. Edit the configuration section at the top of `rbl.php`
3. Set up auto-prepend so PHPrbl runs before your site's code:

**Apache** (`.htaccess`):
```
php_value auto_prepend_file /path/to/rbl.php
```

**PHP-FPM** (`.user.ini` in your site's document root):
```
auto_prepend_file = /path/to/rbl.php
```

**Or** include it directly at the top of your `index.php`:
```php
require_once('/path/to/rbl.php');
```

That's it. PHPrbl will check every visitor against the configured DNSBL services and block listed IPs with a 403 page.

## MySQL Setup

If you want to use the local blocklist, keyword blocking, or whitelist features:

**Fresh install:**
```bash
mysql -u youruser -p yourdatabase < install-1.0.sql
```

**Upgrading from version 0.4:**
```bash
mysql -u youruser -p yourdatabase < upgrade-0.4_to1.0.sql
```

Then set `$mysql_enable = 1` in `rbl.php` and fill in the database credentials.

## Configuration

All configuration is done by editing the variables at the top of `rbl.php`:

| Variable | Default | Description |
|----------|---------|-------------|
| `$rbl_services` | Spamhaus | Array of DNSBL services to query |
| `$mysql_enable` | 0 | Enable MySQL features |
| `$mysql_precheck` | 0 | Check local DB before DNS lookups |
| `$check_keywords` | 0 | Block based on referrer keywords |
| `$keywords_autoblock_mysql` | 0 | Auto-add keyword-matched IPs to local blocklist |
| `$whitelist_enable` | 0 | Check whitelist before blocking |
| `$log_only` | 0 | Log blocks without enforcing (test mode) |
| `$mysql_host` | — | MySQL hostname |
| `$mysql_user` | — | MySQL username |
| `$mysql_pass` | — | MySQL password |
| `$mysql_data` | — | MySQL database name |

## Admin Area

PHPrbl includes a web-based admin area for managing keywords, blocked IPs, and the whitelist. To set it up:

1. Edit `phprbladmin/config.php`
2. Set `$enable_admin_area = 1`
3. Change `$admin_user` and `$admin_pass` from the defaults
4. Enter your database credentials
5. Visit `phprbladmin/index.php` in your browser

The admin area provides:
- **Keywords** — add and remove blocked referrer keywords, see hit counts
- **Blocked IPs** — view blocked IPs with visit counts and referrers, unblock IPs
- **Whitelist** — add and remove whitelisted IPs with notes

## How It Works

When a request comes in, PHPrbl runs through these checks in order:

1. **Whitelist** — if the IP is whitelisted, skip everything and let the request through
2. **Local precheck** — if enabled, check the local MySQL blocklist first (faster than DNS)
3. **Keyword check** — if the referrer contains a banned keyword, block the request
4. **DNSBL lookup** — reverse the IP and query each configured DNSBL service via DNS
5. **Block or pass** — if any check triggers, serve a 403 page (or just log in log-only mode)

For IPv4, the IP octets are reversed (e.g. `1.2.3.4` → `4.3.2.1.dnsbl.example.com`).
For IPv6, the address is expanded and each nibble is reversed.

If the database is unreachable, PHPrbl **fails open** — it lets traffic through rather than taking down your site.

## Version History

See [CHANGELOG](CHANGELOG) for the full history.

**1.0** — Modernized for PHP 8.1+. PDO with prepared statements, IPv6 support, whitelist, log-only mode, admin area rewrite with session auth and CSRF protection.

**0.4** — Added keyword-based referrer blocking and first admin area.

**0.3** — Added MySQL precheck for faster repeat blocking.

**0.2** — Added MySQL logging with referrer tracking.

**0.1** — Initial release.

## License

PHPrbl is released under the [GNU General Public License v2](LICENSE).
