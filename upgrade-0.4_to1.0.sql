--
-- PHPrbl — Upgrade from 0.4 to 1.0
--
-- Back up your data before running this.
--
-- Changes:
--   blocked: widen ip for IPv6, widen referer, convert to InnoDB
--   keywords: fix typo (occurances → occurrences), add proper keys, convert to InnoDB
--   whitelist: new table
--

-- ── blocked table ───────────────────────────────────────────────────────────

ALTER TABLE `blocked`
  ENGINE = InnoDB,
  MODIFY `ip` varchar(45) NOT NULL DEFAULT '',
  MODIFY `referer` varchar(2048) DEFAULT NULL;

-- ── keywords table ──────────────────────────────────────────────────────────

ALTER TABLE `keywords`
  ENGINE = InnoDB;

-- fix the typo in the column name
ALTER TABLE `keywords`
  CHANGE `occurances` `occurrences` int(8) unsigned NOT NULL DEFAULT 0;

-- replace the old composite key with a proper primary key and unique keyword
ALTER TABLE `keywords`
  DROP KEY `id`,
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `keyword` (`keyword`);

-- ── whitelist table (new) ───────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `whitelist` (
  `id` int(8) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `added` varchar(11) NOT NULL DEFAULT '',
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
