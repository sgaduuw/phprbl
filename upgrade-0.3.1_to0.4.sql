--
-- fixes table structure to not use reserved table names
--
ALTER TABLE `blocked` CHANGE `count` `visits` INT( 8 ) NOT NULL DEFAULT '0';


-- 
-- Table structure for table `keywords`
-- 

CREATE TABLE `keywords` (
  `id` int(8) NOT NULL auto_increment,
  `keyword` varchar(255) NOT NULL default '',
  `occurances` int(8) NOT NULL default '0',
  `added` varchar(11) NOT NULL default '',
  KEY `id` (`id`,`keyword`)
) TYPE=MyISAM;

-- 
-- Dumping data for table `keywords`
-- 

INSERT INTO `keywords` VALUES (1, 'viagra', 0, '1130320374');
INSERT INTO `keywords` VALUES (2, 'phentermine', 0, '1130320371');
INSERT INTO `keywords` VALUES (3, 'cialis', 0, '1130320381');
INSERT INTO `keywords` VALUES (4, 'adipex', 0, '1130320387');
INSERT INTO `keywords` VALUES (5, 'hydrocodone', 0, '1130320395');
INSERT INTO `keywords` VALUES (6, 'xanax', 0, '1130320400');
INSERT INTO `keywords` VALUES (7, 'vicodin', 0, '1130320405');
INSERT INTO `keywords` VALUES (8, 'fioricet', 0, '1130320412');
INSERT INTO `keywords` VALUES (9, 'valium', 0, '1130320418');
INSERT INTO `keywords` VALUES (10, 'poker', 0, '1130320424');
INSERT INTO `keywords` VALUES (11, 'holdem', 0, '1130320440');

