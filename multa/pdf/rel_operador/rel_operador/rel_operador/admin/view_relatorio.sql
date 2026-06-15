-- MySQL Administrator dump 1.4
--
-- ------------------------------------------------------
-- Server version	5.0.22


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;


--
-- Create schema asteriskcdrdb
--

CREATE DATABASE IF NOT EXISTS asteriskcdrdb;
USE asteriskcdrdb;

--
-- Temporary table structure for view `vwcdr`
--
DROP TABLE IF EXISTS `vwcdr`;
DROP VIEW IF EXISTS `vwcdr`;
CREATE TABLE `vwcdr` (
  `calldate` datetime,
  `clid` varchar(80),
  `src` varchar(80),
  `dst` varchar(80),
  `dcontext` varchar(80),
  `channel` varchar(80),
  `dstchannel` varchar(80),
  `lastapp` varchar(80),
  `lastdata` varchar(80),
  `duration` int(11),
  `billsec` int(11),
  `disposition` varchar(45),
  `amaflags` int(11),
  `accountcode` varchar(20),
  `userfield` varchar(255),
  `hora` varbinary(8)
);

--
-- Definition of view `vwcdr`
--

DROP TABLE IF EXISTS `vwcdr`;
DROP VIEW IF EXISTS `vwcdr`;
CREATE ALGORITHM=UNDEFINED DEFINER=`blvp`@`%` SQL SECURITY DEFINER VIEW `vwcdr` AS select `cdr`.`calldate` AS `calldate`,`cdr`.`clid` AS `clid`,`cdr`.`src` AS `src`,`cdr`.`dst` AS `dst`,`cdr`.`dcontext` AS `dcontext`,`cdr`.`channel` AS `channel`,`cdr`.`dstchannel` AS `dstchannel`,`cdr`.`lastapp` AS `lastapp`,`cdr`.`lastdata` AS `lastdata`,`cdr`.`duration` AS `duration`,`cdr`.`billsec` AS `billsec`,`cdr`.`disposition` AS `disposition`,`cdr`.`amaflags` AS `amaflags`,`cdr`.`accountcode` AS `accountcode`,`cdr`.`userfield` AS `userfield`,substr(`cdr`.`calldate`,12) AS `hora` from `cdr`;



/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
