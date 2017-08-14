--
-- Database: `cacti`
--

-- --------------------------------------------------------

--
-- Table structure for table `plugin_debug`
--

CREATE TABLE IF NOT EXISTS `plugin_debug` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `started` int(11) NOT NULL,
  `done` int(11) NOT NULL DEFAULT '0',
  `user` int(11) NOT NULL,
  `datasource` int(11) NOT NULL,
  `info` text NOT NULL,
  `issue` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user` (`user`),
  KEY `done` (`done`),
  KEY `datasource` (`datasource`),
  KEY `started` (`started`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1;
