-- MySQL dump 10.13  Distrib 5.7.44, for Linux (x86_64)
--
-- Host: localhost    Database: laz
-- ------------------------------------------------------
-- Server version	5.7.44-log



CREATE TABLE `classes` (
  `fid` int(11) NOT NULL AUTO_INCREMENT,
  `fname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '会员等级名称',
  `foff` decimal(10,2) DEFAULT NULL COMMENT '等级折扣',
  PRIMARY KEY (`fid`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='会员等级表';
/*!40101 SET character_set_client = @saved_cs_client */;



CREATE TABLE `member` (
  `fid` int(11) NOT NULL AUTO_INCREMENT,
  `fnumber` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '会员编码',
  `fname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '会员名称',
  `fclassesid` int(11) DEFAULT NULL COMMENT '会员等级id，对应classes表fid',
  `fclassesname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '会员等级名称',
  `faccruedamount` decimal(10,2) DEFAULT '0.00' COMMENT '累计充值金额',
  `fbalance` decimal(10,2) DEFAULT '0.00' COMMENT '余额',
  `fmark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '备注',
  PRIMARY KEY (`fid`) USING BTREE,
  UNIQUE KEY `idx_fnumber` (`fnumber`) USING BTREE COMMENT '会员编码唯一',
  KEY `idx_fname` (`fname`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=106 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='会员信息表';

CREATE TABLE `menmberdetail` (
  `fid` int(11) NOT NULL AUTO_INCREMENT,
  `fdate` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '日期',
  `fmode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作：新增、修改、删除、充值、消费',
  `fmemberid` int(11) DEFAULT NULL COMMENT '会员id，对应member表fid',
  `fmembername` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '会员名称',
  `fclassesid` int(11) DEFAULT NULL COMMENT '会员等级id，对应classes表fid',
  `fclassesname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '会员等级名称',
  `fgoods` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '商品名称',
  `famount` decimal(10,2) DEFAULT NULL COMMENT '调整金额',
  `fbalance` decimal(10,2) DEFAULT NULL COMMENT '调整后余额',
  `fmark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '调整备注',
  PRIMARY KEY (`fid`) USING BTREE,
  KEY `idx_fmemberid` (`fmemberid`) USING BTREE,
  KEY `idx_fdate` (`fdate`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=672 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='会员明细表';

CREATE TABLE `menmberdetail_log` (
  `fid` int(11) NOT NULL AUTO_INCREMENT,
  `fdate` datetime DEFAULT CURRENT_TIMESTAMP COMMENT '日期',
  `fmode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作：新增、修改、删除、充值、消费',
  `fmemberid` int(11) DEFAULT NULL COMMENT '会员id，对应member表fid',
  `fmembername` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '会员名称',
  `fclassesid` int(11) DEFAULT NULL COMMENT '会员等级id，对应classes表fid',
  `fclassesname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '会员等级名称',
  `fgoods` varchar(1024) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '商品名称',
  `famount` decimal(10,2) DEFAULT NULL COMMENT '调整金额',
  `fbalance` decimal(10,2) DEFAULT NULL COMMENT '调整后余额',
  `fmark` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '调整备注',
  PRIMARY KEY (`fid`) USING BTREE,
  KEY `idx_fmemberid` (`fmemberid`) USING BTREE,
  KEY `idx_fdate` (`fdate`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=705 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='会员明细日志表';
