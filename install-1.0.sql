--
-- PHPrbl 1.0 — Fresh install schema
-- Requires MySQL 5.7+ / MariaDB 10.2+
--

CREATE TABLE `blocked` (
  `id` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `visits` int(8) unsigned NOT NULL DEFAULT 0,
  `lastseen` varchar(11) NOT NULL DEFAULT '',
  `service` varchar(255) NOT NULL DEFAULT '',
  `referer` varchar(2048) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `keywords` (
  `id` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `keyword` varchar(255) NOT NULL DEFAULT '',
  `occurrences` int(8) unsigned NOT NULL DEFAULT 0,
  `added` varchar(11) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `keyword` (`keyword`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `whitelist` (
  `id` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `added` varchar(11) NOT NULL DEFAULT '',
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
