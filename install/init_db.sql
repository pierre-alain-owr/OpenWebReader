/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `news`
--

DROP TABLE IF EXISTS `news`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `rssid` int(11) NOT NULL,
  `lastupd` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `title` varchar(255) NOT NULL,
  `link` varchar(350) NOT NULL,
  `pubDate` int(10) unsigned NOT NULL,
  `hash` varchar(32) NOT NULL,
  `author` text NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `hash` (`hash`),
  KEY `uid` (`rssid`),
  KEY `upd` (`lastupd`),
  KEY `pubDate` (`pubDate`),
  CONSTRAINT `news_ibfk_1` FOREIGN KEY (`id`) REFERENCES `objects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `news_ibfk_3` FOREIGN KEY (`rssid`) REFERENCES `streams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `news_contents`
--

DROP TABLE IF EXISTS `news_contents`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `news_contents` (
  `id` int(11) NOT NULL,
  `contents` longtext NOT NULL,
  PRIMARY KEY  (`id`),
  FULLTEXT KEY `text` (`contents`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `news_relations`
--

DROP TABLE IF EXISTS `news_relations`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `news_relations` (
  `newsid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `status` tinyint(1) NOT NULL default '1',
  `rssid` int(11) NOT NULL,
  UNIQUE KEY `uniq_key` (`newsid`,`uid`),
  KEY `uid` (`uid`),
  KEY `relations_ibfk_4` (`rssid`),
  KEY `newsid` (`newsid`),
  KEY `status` (`status`),
  CONSTRAINT `news_relations_ibfk_1` FOREIGN KEY (`uid`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `news_relations_ibfk_2` FOREIGN KEY (`newsid`) REFERENCES `news` (`id`) ON DELETE CASCADE,
  CONSTRAINT `news_relations_ibfk_4` FOREIGN KEY (`rssid`) REFERENCES `streams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `news_relations_tags`
--

DROP TABLE IF EXISTS `news_relations_tags`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `news_relations_tags` (
  `newsid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `tid` int(11) NOT NULL,
  UNIQUE KEY `uniq_key` (`newsid`,`tid`),
  KEY `uid` (`uid`),
  KEY `tid` (`tid`),
  CONSTRAINT `news_relations_tags_ibfk_1` FOREIGN KEY (`uid`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `news_relations_tags_ibfk_2` FOREIGN KEY (`newsid`) REFERENCES `news` (`id`) ON DELETE CASCADE,
  CONSTRAINT `news_relations_tags_ibfk_3` FOREIGN KEY (`tid`) REFERENCES `news_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `news_tags`
--

DROP TABLE IF EXISTS `news_tags`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `news_tags` (
  `id` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  UNIQUE KEY `uniq_key` (`uid`,`name`),
  KEY `uid` (`uid`),
  KEY `id` (`id`),
  CONSTRAINT `news_tags_ibfk_1` FOREIGN KEY (`uid`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `news_tags_ibfk_2` FOREIGN KEY (`id`) REFERENCES `objects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `objects`
--

DROP TABLE IF EXISTS `objects`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `objects` (
  `id` int(11) NOT NULL auto_increment,
  `type` varchar(255) collate utf8_unicode_ci NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `sessions` (
  `id` varchar(32) collate utf8_unicode_ci NOT NULL,
  `access` int(10) unsigned default NULL,
  `ip` varchar(16) collate utf8_unicode_ci default NULL,
  `data` text collate utf8_unicode_ci,
  PRIMARY KEY  (`id`),
  KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `streams`
--

DROP TABLE IF EXISTS `streams`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `streams` (
  `id` int(11) NOT NULL,
  `url` varchar(350) collate utf8_unicode_ci NOT NULL,
  `ttl` int(11) NOT NULL default '0',
  `lastupd` int(10) unsigned NOT NULL,
  `favicon` varchar(350) collate utf8_unicode_ci NOT NULL default '',
  `status` int(10) unsigned NOT NULL default '0',
  `hash` varchar(32) collate utf8_unicode_ci NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `hash` (`hash`),
  KEY `ttl` (`ttl`),
  KEY `lastupd` (`lastupd`),
  KEY `status` (`status`),
  CONSTRAINT `streams_ibfk_1` FOREIGN KEY (`id`) REFERENCES `objects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `streams_contents`
--

DROP TABLE IF EXISTS `streams_contents`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `streams_contents` (
  `rssid` int(11) NOT NULL,
  `src` longtext collate utf8_unicode_ci NOT NULL,
  `contents` longtext collate utf8_unicode_ci NOT NULL,
  PRIMARY KEY  (`rssid`),
  CONSTRAINT `streams_contents_ibfk_1` FOREIGN KEY (`rssid`) REFERENCES `streams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `streams_groups`
--

DROP TABLE IF EXISTS `streams_groups`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `streams_groups` (
  `id` int(11) NOT NULL,
  `name` varchar(255) collate utf8_unicode_ci default NULL,
  `uid` int(11) NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `uniq_key` (`name`,`uid`),
  KEY `name` (`name`),
  KEY `uid` (`uid`),
  CONSTRAINT `streams_groups_ibfk_1` FOREIGN KEY (`id`) REFERENCES `objects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `streams_groups_ibfk_2` FOREIGN KEY (`uid`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `streams_relations`
--

DROP TABLE IF EXISTS `streams_relations`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `streams_relations` (
  `rssid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `gid` int(11) NOT NULL,
  UNIQUE KEY `uniq_key` (`rssid`,`uid`),
  KEY `rss_relations_ibfk_1` (`uid`),
  KEY `rss_relations_ibfk_3` (`gid`),
  KEY `rss_relations_ibfk_4` (`rssid`),
  CONSTRAINT `streams_relations_ibfk_1` FOREIGN KEY (`uid`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `streams_relations_ibfk_3` FOREIGN KEY (`gid`) REFERENCES `streams_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `streams_relations_ibfk_4` FOREIGN KEY (`rssid`) REFERENCES `streams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `streams_relations_name`
--

DROP TABLE IF EXISTS `streams_relations_name`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `streams_relations_name` (
  `rssid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `name` text NOT NULL,
  UNIQUE KEY `uniq_key` (`uid`,`rssid`),
  KEY `rss_relations_name_ibfk_1` (`uid`),
  KEY `rss_relations_name_ibfk_4` (`rssid`),
  CONSTRAINT `streams_relations_name_ibfk_1` FOREIGN KEY (`uid`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `streams_relations_name_ibfk_4` FOREIGN KEY (`rssid`) REFERENCES `streams` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `login` varchar(55) collate utf8_unicode_ci NOT NULL,
  `passwd` varchar(32) collate utf8_unicode_ci NOT NULL default '',
  `rights` tinyint(4) NOT NULL,
  `lang` varchar(7) collate utf8_unicode_ci NOT NULL default 'fr_FR',
  `email` varchar(255) collate utf8_unicode_ci NOT NULL default '',
  `timezone` varchar(255) collate utf8_unicode_ci NOT NULL default 'Europe/Paris',
  `config` longtext collate utf8_unicode_ci NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `name` (`login`),
  UNIQUE KEY `email` (`email`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`id`) REFERENCES `objects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `users_tokens`
--

DROP TABLE IF EXISTS `users_tokens`;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
CREATE TABLE `users_tokens` (
  `uid` int(11) NOT NULL,
  `token` varchar(96) collate utf8_unicode_ci NOT NULL,
  `action` varchar(55) collate utf8_unicode_ci NOT NULL,
  `token_key` varchar(5) collate utf8_unicode_ci NOT NULL,
  UNIQUE KEY `uniq_key` (`uid`,`action`),
  KEY `users_tokens_ibfk_1` (`uid`),
  KEY `action` (`action`),
  CONSTRAINT `users_tokens_ibfk_1` FOREIGN KEY (`uid`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
SET character_set_client = @saved_cs_client;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2010-02-09 17:56:00
