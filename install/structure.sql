#
# QuickFox Setup SQL template [structure section]. 
#


# Table definition for {DBKEY}blog_entries 
DROP TABLE IF EXISTS `{DBKEY}blog_entries` ;
CREATE TABLE `{DBKEY}blog_entries` ( 
    `id` char(8) COLLATE ascii_general_ci NOT NULL, 
    `author` char(16) NOT NULL, 
    `author_id` int(10) unsigned NOT NULL DEFAULT '0', 
    `caption` varchar(128) NOT NULL, 
    `time` int(11) NOT NULL DEFAULT '0', 
    `r_level` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `mu_acc_r` binary(255) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0', 
    `mu_acc_w` binary(255) NOT NULL DEFAULT '\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0\0', 
    `pt_root` int(10) unsigned NOT NULL DEFAULT '0', 
    PRIMARY KEY (`id`) , 
    UNIQUE `pt_root` (`pt_root`) , 
    INDEX `author_id` (`author_id`) , 
    INDEX `r_level` (`r_level`) , 
    INDEX `time` (`time`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}blog_texts 
DROP TABLE IF EXISTS `{DBKEY}blog_texts` ;
CREATE TABLE `{DBKEY}blog_texts` ( 
    `id` char(8) COLLATE ascii_general_ci NOT NULL, 
    `o_text` text NOT NULL, 
    `hash` char(32) COLLATE ascii_general_ci NOT NULL, 
    `p_text` text NOT NULL, 
    `is_raw` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `preparsed` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    PRIMARY KEY (`id`) , 
    INDEX `hash` (`hash`) , 
    INDEX `preparsed` (`preparsed`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}cms_pgs 
DROP TABLE IF EXISTS `{DBKEY}cms_pgs` ;
CREATE TABLE `{DBKEY}cms_pgs` ( 
    `id` varchar(16) COLLATE ascii_general_ci NOT NULL, 
    `parent` varchar(16) COLLATE ascii_general_ci DEFAULT NULL, 
    `caption` varchar(255) NOT NULL, 
    `file_id` varchar(32) COLLATE ascii_general_ci NOT NULL, 
    `file_type` varchar(5) COLLATE ascii_general_ci NOT NULL, 
    `links_to` text COLLATE ascii_general_ci DEFAULT NULL, 
    `author_id` int(10) unsigned NOT NULL DEFAULT '0', 
    `mod_date` int(11) NOT NULL DEFAULT '0', 
    `is_section` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `order_id` smallint(5) unsigned NOT NULL DEFAULT '0', 
    `r_level` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    PRIMARY KEY (`id`) , 
    INDEX `author_id` (`author_id`) , 
    INDEX `is_section` (`is_section`) , 
    INDEX `mod_date` (`mod_date`) , 
    INDEX `order_id` (`order_id`) , 
    INDEX `parent` (`parent`) , 
    INDEX `r_level` (`r_level`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}cms_stats 
DROP TABLE IF EXISTS `{DBKEY}cms_stats` ;
CREATE TABLE `{DBKEY}cms_stats` ( 
    `id` char(16) COLLATE ascii_general_ci NOT NULL, 
    `views` int(10) unsigned NOT NULL DEFAULT '0', 
    `v_by_refer` int(10) unsigned NOT NULL DEFAULT '0', 
    `last_view` int(11) NOT NULL DEFAULT '0', 
    PRIMARY KEY (`id`) , 
    INDEX `last_view` (`last_view`) , 
    INDEX `views` (`views`, `v_by_refer`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}config 
DROP TABLE IF EXISTS `{DBKEY}config` ;
CREATE TABLE `{DBKEY}config` ( 
    `parent` varchar(10) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `name` varchar(32) COLLATE ascii_general_ci NOT NULL, 
    `scheme` varchar(10) COLLATE ascii_general_ci NOT NULL DEFAULT 'root', 
    `value` blob NOT NULL, 
    `scalar` tinyint(1) unsigned NOT NULL DEFAULT '1', 
    PRIMARY KEY (`parent`, `name`, `scheme`) , 
    INDEX `scalar` (`scalar`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}config_sets 
DROP TABLE IF EXISTS `{DBKEY}config_sets` ;
CREATE TABLE `{DBKEY}config_sets` ( 
    `set_id` varchar(64) COLLATE ascii_general_ci NOT NULL, 
    `sec_set_id` varchar(64) COLLATE ascii_general_ci DEFAULT NULL, 
    `package` varchar(64) COLLATE ascii_general_ci NOT NULL DEFAULT 'qf2', 
    `capt` varchar(64) NOT NULL, 
    `cfg_parent` varchar(10) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `cfg_name` varchar(32) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `cfg_type` varchar(32) COLLATE ascii_general_ci NOT NULL, 
    `src_data` blob DEFAULT NULL, 
    `drops_cache` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `drops_confs` text COLLATE ascii_general_ci DEFAULT NULL, 
    `schemable` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `order_id` smallint(5) NOT NULL DEFAULT '0', 
    PRIMARY KEY (`cfg_name`, `cfg_parent`) , 
    INDEX `cfg_type` (`cfg_type`) , 
    INDEX `drops_cache` (`drops_cache`) , 
    INDEX `order_id` (`order_id`) , 
    INDEX `package` (`package`) , 
    INDEX `schemable` (`schemable`) , 
    INDEX `set_id` (`set_id`, `sec_set_id`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}datasets 
DROP TABLE IF EXISTS `{DBKEY}datasets` ;
CREATE TABLE `{DBKEY}datasets` ( 
    `set_id` varchar(64) COLLATE ascii_general_ci NOT NULL, 
    `package` varchar(64) COLLATE ascii_general_ci NOT NULL DEFAULT 'qf2', 
    `data_id` varchar(64) COLLATE ascii_general_ci NOT NULL, 
    `data` blob NOT NULL, 
    `scalar` tinyint(1) unsigned NOT NULL DEFAULT '1', 
    `lparse_sufx` varchar(10) COLLATE ascii_general_ci NOT NULL, 
    PRIMARY KEY (`set_id`, `data_id`) , 
    INDEX `package` (`package`) , 
    INDEX `scalar` (`scalar`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}file_dloads 
DROP TABLE IF EXISTS `{DBKEY}file_dloads` ;
CREATE TABLE `{DBKEY}file_dloads` ( 
    `dl_id` char(32) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `file_id` char(8) COLLATE ascii_general_ci NOT NULL, 
    `sid` char(32) COLLATE ascii_general_ci NOT NULL, 
    `gid` char(32) COLLATE ascii_general_ci DEFAULT NULL, 
    `client_ip` int(10) unsigned NOT NULL DEFAULT '0', 
    `active_till` int(11) NOT NULL DEFAULT '0', 
    PRIMARY KEY (`dl_id`) , 
    UNIQUE `sid_uniq` (`sid`, `file_id`) , 
    UNIQUE `gid_uniq` (`gid`, `file_id`) , 
    INDEX `active_till` (`active_till`) , 
    INDEX `client_ip` (`client_ip`) , 
    INDEX `file_id` (`file_id`) , 
    INDEX `gid` (`gid`) , 
    INDEX `sid` (`sid`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}file_folders 
DROP TABLE IF EXISTS `{DBKEY}file_folders` ;
CREATE TABLE `{DBKEY}file_folders` ( 
    `id` smallint(5) unsigned NOT NULL auto_increment, 
    `t_id` char(64) COLLATE ascii_general_ci DEFAULT NULL, 
    `parent` smallint(5) unsigned NOT NULL DEFAULT '0', 
    `name` char(128) NOT NULL, 
    `type` char(8) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `r_level` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `w_level` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `acc_gr` int(5) unsigned NOT NULL DEFAULT '0', 
    `mtime` int(11) NOT NULL DEFAULT '0', 
    `files` int(10) unsigned NOT NULL DEFAULT '0', 
    `size` int(10) unsigned NOT NULL DEFAULT '0', 
    `is_sys` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `sort_type` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    PRIMARY KEY (`id`) , 
    UNIQUE `t_id` (`t_id`) , 
    INDEX `acc_gr` (`acc_gr`) , 
    INDEX `files` (`files`) , 
    INDEX `is_sys` (`is_sys`) , 
    INDEX `levels` (`r_level`, `w_level`) , 
    INDEX `mtime` (`mtime`) , 
    INDEX `parent` (`parent`) , 
    INDEX `size` (`size`) , 
    INDEX `type` (`type`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}files 
DROP TABLE IF EXISTS `{DBKEY}files` ;
CREATE TABLE `{DBKEY}files` ( 
    `id` char(8) COLLATE ascii_general_ci NOT NULL, 
    `folder` smallint(5) unsigned NOT NULL DEFAULT '0', 
    `is_temp` tinyint(1) unsigned NOT NULL DEFAULT '1', 
    `author` char(16) NOT NULL, 
    `author_id` int(10) unsigned NOT NULL DEFAULT '0', 
    `author_ip` int(10) unsigned NOT NULL DEFAULT '0', 
    `time` int(11) NOT NULL DEFAULT '0', 
    `r_level` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `caption` char(255) NOT NULL, 
    `filename` char(255) NOT NULL, 
    `file_id` char(32) COLLATE ascii_general_ci NOT NULL, 
    `file_md5` char(32) COLLATE ascii_general_ci NOT NULL, 
    `file_gzip` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `file_size` int(10) unsigned NOT NULL DEFAULT '0', 
    `mime` char(64) COLLATE ascii_general_ci NOT NULL, 
    `is_arch` char(5) COLLATE ascii_general_ci DEFAULT NULL, 
    `force_save` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `has_pics` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `pics_mime` char(64) COLLATE ascii_general_ci DEFAULT NULL, 
    `pics_name` char(128) DEFAULT NULL, 
    `image_dims` char(11) COLLATE ascii_general_ci DEFAULT NULL, 
    `aspect_ratio` float NOT NULL DEFAULT '0', 
    `dloads` mediumint(8) unsigned NOT NULL DEFAULT '0', 
    `last_dload` int(11) NOT NULL DEFAULT '0', 
    PRIMARY KEY (`id`) , 
    UNIQUE `file_id` (`file_id`) , 
    INDEX `author_id` (`author_id`) , 
    INDEX `author_ip` (`author_ip`) , 
    INDEX `dloads` (`dloads`) , 
    INDEX `file_md5` (`file_md5`) , 
    INDEX `folder` (`folder`) , 
    INDEX `has_pics` (`has_pics`) , 
    INDEX `is_temp` (`is_temp`) , 
    INDEX `last_dload` (`last_dload`) , 
    INDEX `r_level` (`r_level`) , 
    INDEX `time` (`time`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}fox_logs 
DROP TABLE IF EXISTS `{DBKEY}fox_logs` ;
CREATE TABLE `{DBKEY}fox_logs` ( 
    `ev_id` int(10) unsigned NOT NULL auto_increment, 
    `log_id` char(32) COLLATE ascii_general_ci NOT NULL DEFAULT 'common', 
    `time` int(11) NOT NULL DEFAULT '0', 
    `event` char(255) NOT NULL, 
    `cl_ip` int(11) NOT NULL DEFAULT '0', 
    `cl_req` char(255) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `cl_uagent` char(255) NOT NULL DEFAULT '', 
    PRIMARY KEY (`ev_id`) , 
    INDEX `cl_ip` (`cl_ip`) , 
    INDEX `log_id` (`log_id`) , 
    INDEX `time` (`time`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}gal_al_itms 
DROP TABLE IF EXISTS `{DBKEY}gal_al_itms` ;
CREATE TABLE `{DBKEY}gal_al_itms` ( 
    `album_id` int(10) unsigned NOT NULL DEFAULT '0', 
    `item_id` char(8) COLLATE ascii_general_ci NOT NULL, 
    `put_by` int(10) unsigned NOT NULL DEFAULT '0', 
    `put_at` int(11) NOT NULL DEFAULT '0', 
    PRIMARY KEY (`album_id`, `item_id`) , 
    INDEX `item_id` (`item_id`) , 
    INDEX `put_at` (`put_at`) , 
    INDEX `put_by` (`put_by`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}gal_albums 
DROP TABLE IF EXISTS `{DBKEY}gal_albums` ;
CREATE TABLE `{DBKEY}gal_albums` ( 
    `id` int(10) unsigned NOT NULL auto_increment, 
    `t_id` char(128) COLLATE ascii_general_ci NOT NULL, 
    `caption` char(128) NOT NULL, 
    `owner_id` int(10) unsigned NOT NULL DEFAULT '0', 
    `lasttime` int(11) NOT NULL DEFAULT '0', 
    `lastthree` char(26) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `r_level` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `w_level` tinyint(1) unsigned NOT NULL DEFAULT '1', 
    `acc_gr` int(5) unsigned NOT NULL DEFAULT '0', 
    PRIMARY KEY (`id`) , 
    UNIQUE `t_id` (`t_id`) , 
    INDEX `acc_gr` (`acc_gr`) , 
    INDEX `lasttime` (`lasttime`) , 
    INDEX `owner_id` (`owner_id`) , 
    INDEX `r_level` (`r_level`) , 
    INDEX `w_level` (`w_level`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}gal_items 
DROP TABLE IF EXISTS `{DBKEY}gal_items` ;
CREATE TABLE `{DBKEY}gal_items` ( 
    `id` char(8) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `file_id` char(8) COLLATE ascii_general_ci NOT NULL, 
    `author` varchar(16) NOT NULL, 
    `author_id` int(10) unsigned NOT NULL DEFAULT '0', 
    `caption` varchar(128) NOT NULL, 
    `time` int(11) NOT NULL DEFAULT '0', 
    `r_level` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `description` text NOT NULL, 
    `pt_root` int(10) unsigned DEFAULT NULL, 
    PRIMARY KEY (`id`) , 
    UNIQUE `file_id` (`file_id`) , 
    UNIQUE `pt_root` (`pt_root`) , 
    INDEX `author_id` (`author_id`) , 
    INDEX `r_level` (`r_level`) , 
    INDEX `time` (`time`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}guests 
DROP TABLE IF EXISTS `{DBKEY}guests` ;
CREATE TABLE `{DBKEY}guests` ( 
    `gid` char(32) COLLATE ascii_general_ci NOT NULL, 
    `nick` varchar(15) NOT NULL DEFAULT '', 
    `lastseen` int(11) NOT NULL DEFAULT '0', 
    `last_url` varchar(255) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `last_uagent` varchar(255) NOT NULL DEFAULT '', 
    `last_ip` int(10) unsigned NOT NULL DEFAULT '0', 
    `long_reg` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `reg_till` int(11) NOT NULL DEFAULT '0', 
    `views` int(10) unsigned NOT NULL DEFAULT '0', 
    `g_sets` blob DEFAULT NULL, 
    PRIMARY KEY (`gid`) , 
    INDEX `gcode` (`long_reg`) , 
    INDEX `last_ip` (`last_ip`) , 
    INDEX `lastseen` (`lastseen`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}pt_peruser 
DROP TABLE IF EXISTS `{DBKEY}pt_peruser` ;
CREATE TABLE `{DBKEY}pt_peruser` ( 
    `root_id` int(10) unsigned NOT NULL, 
    `user_id` int(10) unsigned NOT NULL, 
    `c_key` char(128) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `d_key_1` char(128) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `d_key_2` char(128) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `d_key_3` char(128) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    PRIMARY KEY (`root_id`, `user_id`) , 
    INDEX `c_key` (`c_key`) , 
    INDEX `d_key` (`d_key_1`, `d_key_2`, `d_key_3`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}pt_posts 
DROP TABLE IF EXISTS `{DBKEY}pt_posts` ;
CREATE TABLE `{DBKEY}pt_posts` ( 
    `post_id` bigint(20) unsigned NOT NULL auto_increment, 
    `root_id` int(10) unsigned NOT NULL DEFAULT '0', 
    `parent` bigint(20) unsigned NOT NULL DEFAULT '0', 
    `tree_idx` binary(255) DEFAULT NULL, 
    `author_ip` int(10) unsigned NOT NULL DEFAULT '0', 
    `author` char(16) NOT NULL, 
    `author_id` int(10) unsigned NOT NULL DEFAULT '0', 
    `time` int(11) NOT NULL DEFAULT '0', 
    `ch_user` char(16) DEFAULT NULL, 
    `ch_user_id` int(10) unsigned DEFAULT NULL, 
    `ch_user_ip` int(10) unsigned DEFAULT NULL, 
    `ch_time` int(11) DEFAULT NULL, 
    `locked` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `marked` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    PRIMARY KEY (`post_id`) , 
    INDEX `author_id` (`author_id`) , 
    INDEX `ch_time` (`ch_time`) , 
    INDEX `ch_user_id` (`ch_user_id`) , 
    INDEX `change_ip` (`ch_user_ip`) , 
    INDEX `flags` (`locked`, `deleted`, `marked`) , 
    INDEX `parents` (`root_id`, `parent`) , 
    INDEX `post_ip` (`author_ip`) , 
    INDEX `time` (`time`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}pt_ptext 
DROP TABLE IF EXISTS `{DBKEY}pt_ptext` ;
CREATE TABLE `{DBKEY}pt_ptext` ( 
    `post_id` bigint(20) unsigned NOT NULL, 
    `o_text` text NOT NULL, 
    `hash` char(32) COLLATE ascii_general_ci NOT NULL, 
    `p_text` text NOT NULL, 
    `preparsed` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    PRIMARY KEY (`post_id`) , 
    INDEX `hash` (`hash`) , 
    INDEX `preparsed` (`preparsed`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}pt_roots 
DROP TABLE IF EXISTS `{DBKEY}pt_roots` ;
CREATE TABLE `{DBKEY}pt_roots` ( 
    `root_id` int(10) unsigned NOT NULL auto_increment, 
    `class` char(8) COLLATE ascii_general_ci NOT NULL, 
    `a_key` char(128) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `b_key1` char(128) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `b_key2` char(128) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `b_key3` char(128) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `author` varchar(16) NOT NULL, 
    `author_id` int(10) unsigned NOT NULL DEFAULT '0', 
    `caption` varchar(255) NOT NULL, 
    `data` blob NOT NULL, 
    `hash` char(32) COLLATE ascii_general_ci NOT NULL, 
    `time` int(11) NOT NULL DEFAULT '0', 
    `l_author` varchar(16) NOT NULL, 
    `l_author_id` int(10) unsigned NOT NULL DEFAULT '0', 
    `l_time` int(11) NOT NULL DEFAULT '0', 
    `l_post_id` bigint(20) unsigned NOT NULL DEFAULT '0', 
    `posts` int(10) unsigned NOT NULL DEFAULT '0', 
    `totalposts` int(10) unsigned NOT NULL DEFAULT '0', 
    `r_level` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `w_level` tinyint(1) unsigned NOT NULL DEFAULT '1', 
    `locked` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `marked` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    PRIMARY KEY (`root_id`) , 
    UNIQUE `ident` (`class`, `hash`) , 
    INDEX `a_key` (`a_key`) , 
    INDEX `author_id` (`author_id`) , 
    INDEX `b_key` (`b_key1`, `b_key2`, `b_key3`) , 
    INDEX `flags` (`locked`, `deleted`, `marked`) , 
    INDEX `l_author_id` (`l_author_id`) , 
    INDEX `l_time` (`l_time`) , 
    INDEX `levels` (`r_level`, `w_level`) , 
    INDEX `posts` (`posts`) , 
    INDEX `time` (`time`) , 
    INDEX `totalposts` (`totalposts`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}results 
DROP TABLE IF EXISTS `{DBKEY}results` ;
CREATE TABLE `{DBKEY}results` ( 
    `res_id` char(8) COLLATE ascii_general_ci NOT NULL, 
    `code` char(16) COLLATE ascii_general_ci NOT NULL, 
    `text` text NOT NULL, 
    `is_err` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `tr_errs` varchar(255) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `time` int(11) NOT NULL DEFAULT '0', 
    `got_at` varchar(255) NOT NULL DEFAULT '', 
    `redir_to` varchar(255) NOT NULL DEFAULT '', 
    `u_sid` char(32) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `u_id` int(10) unsigned NOT NULL DEFAULT '0', 
    PRIMARY KEY (`res_id`) , 
    INDEX `code` (`code`, `is_err`) , 
    INDEX `user` (`u_sid`, `u_id`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}services 
DROP TABLE IF EXISTS `{DBKEY}services` ;
CREATE TABLE `{DBKEY}services` ( 
    `name` char(64) COLLATE ascii_general_ci NOT NULL, 
    `descr` char(255) NOT NULL, 
    `run_period` mediumint(8) unsigned NOT NULL DEFAULT '3600', 
    `run_php` char(255) COLLATE ascii_general_ci NOT NULL, 
    `next_run` int(11) NOT NULL DEFAULT '0', 
    `active` tinyint(1) unsigned NOT NULL DEFAULT '1', 
    PRIMARY KEY (`name`) , 
    INDEX `active` (`active`) , 
    INDEX `next_run` (`next_run`, `active`) , 
    INDEX `run_period` (`run_period`) , 
    INDEX `run_php` (`run_php`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}sess_cache 
DROP TABLE IF EXISTS `{DBKEY}sess_cache` ;
CREATE TABLE `{DBKEY}sess_cache` ( 
    `sid` char(32) COLLATE ascii_general_ci NOT NULL, 
    `ch_name` char(32) COLLATE ascii_general_ci NOT NULL, 
    `ch_data` mediumblob NOT NULL, 
    `ch_stored` int(11) NOT NULL DEFAULT '0', 
    PRIMARY KEY (`sid`, `ch_name`) , 
    INDEX `ch_stored` (`ch_stored`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}sessions 
DROP TABLE IF EXISTS `{DBKEY}sessions` ;
CREATE TABLE `{DBKEY}sessions` ( 
    `sid` char(32) COLLATE ascii_general_ci NOT NULL, 
    `ip` int(10) unsigned NOT NULL DEFAULT '0', 
    `vars` blob DEFAULT NULL, 
    `starttime` int(11) NOT NULL DEFAULT '0', 
    `lastused` int(11) NOT NULL DEFAULT '0', 
    `clicks` int(5) unsigned NOT NULL DEFAULT '0', 
    PRIMARY KEY (`sid`) , 
    INDEX `ip` (`ip`) , 
    INDEX `lastused` (`lastused`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}spiders_stats 
DROP TABLE IF EXISTS `{DBKEY}spiders_stats` ;
CREATE TABLE `{DBKEY}spiders_stats` ( 
    `sp_name` char(64) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    `visits` int(10) unsigned NOT NULL DEFAULT '0', 
    `last_time` int(11) NOT NULL DEFAULT '0', 
    `last_ip` int(11) NOT NULL DEFAULT '0', 
    `last_uagent` char(255) NOT NULL, 
    PRIMARY KEY (`sp_name`) , 
    INDEX `last_ip` (`last_ip`) , 
    INDEX `last_time` (`last_time`) , 
    INDEX `visits` (`visits`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}users 
DROP TABLE IF EXISTS `{DBKEY}users` ;
CREATE TABLE `{DBKEY}users` ( 
    `uid` int(10) unsigned NOT NULL auto_increment, 
    `nick` varchar(16) NOT NULL, 
    `level` tinyint(1) unsigned NOT NULL DEFAULT '1', 
    `mod_lvl` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `adm_lvl` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `frozen` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `readonly` tinyint(1) unsigned NOT NULL DEFAULT '0', 
    `acc_group` int(5) unsigned NOT NULL DEFAULT '0', 
    `avatar` varchar(64) COLLATE ascii_general_ci DEFAULT NULL, 
    `av_dims` varchar(11) COLLATE ascii_general_ci DEFAULT NULL, 
    `av_sig` varchar(64) DEFAULT NULL, 
    `signature` varchar(255) NOT NULL DEFAULT '', 
    `regtime` int(11) NOT NULL DEFAULT '0', 
    `lastseen` int(11) DEFAULT NULL, 
    `last_url` varchar(255) COLLATE ascii_general_ci DEFAULT NULL, 
    `last_uagent` varchar(255) DEFAULT NULL, 
    `last_ip` int(10) unsigned DEFAULT NULL, 
    `sess_id` char(32) COLLATE ascii_general_ci DEFAULT NULL, 
    `us_info` mediumblob DEFAULT NULL, 
    `us_sets` mediumblob DEFAULT NULL, 
    `us_flags` blob DEFAULT NULL, 
    PRIMARY KEY (`uid`) , 
    UNIQUE `nick` (`nick`) , 
    INDEX `acc_group` (`acc_group`) , 
    INDEX `acc_state` (`frozen`, `readonly`) , 
    INDEX `last_ip` (`last_ip`) , 
    INDEX `lastseen` (`lastseen`) , 
    INDEX `level` (`level`, `mod_lvl`, `adm_lvl`) , 
    INDEX `sess_id` (`sess_id`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}users_al 
DROP TABLE IF EXISTS `{DBKEY}users_al` ;
CREATE TABLE `{DBKEY}users_al` ( 
    `al_id` char(32) COLLATE ascii_general_ci NOT NULL, 
    `al_sig` char(32) COLLATE ascii_general_ci NOT NULL, 
    `uid` int(10) unsigned NOT NULL DEFAULT '0', 
    `al_started` int(11) NOT NULL DEFAULT '0', 
    `al_lastuse` int(11) NOT NULL DEFAULT '0', 
    PRIMARY KEY (`al_id`) , 
    INDEX `al_sig` (`al_sig`) , 
    INDEX `times` (`al_started`, `al_lastuse`) , 
    INDEX `uid` (`uid`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}users_auth 
DROP TABLE IF EXISTS `{DBKEY}users_auth` ;
CREATE TABLE `{DBKEY}users_auth` ( 
    `uid` int(10) unsigned NOT NULL, 
    `login` char(16) COLLATE ascii_general_ci NOT NULL, 
    `pass_hash` char(32) COLLATE ascii_general_ci NOT NULL, 
    `pass_salt` char(32) COLLATE ascii_general_ci NOT NULL, 
    `sys_email` char(32) COLLATE ascii_general_ci NOT NULL, 
    `pass_dropcode` char(32) COLLATE ascii_general_ci DEFAULT NULL, 
    `lastauth` int(11) DEFAULT NULL, 
    PRIMARY KEY (`uid`) , 
    UNIQUE `login` (`login`) , 
    INDEX `pass_dropcode` (`pass_dropcode`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

# Table definition for {DBKEY}users_rel 
DROP TABLE IF EXISTS `{DBKEY}users_rel` ;
CREATE TABLE `{DBKEY}users_rel` ( 
    `uid` int(10) unsigned NOT NULL, 
    `rel_uid` int(10) unsigned NOT NULL, 
    `relation` tinyint(1) NOT NULL DEFAULT '0', 
    `group` char(15) COLLATE ascii_general_ci NOT NULL DEFAULT '', 
    PRIMARY KEY (`uid`, `rel_uid`) , 
    INDEX `group` (`group`) , 
    INDEX `relation` (`relation`)  
) ENGINE = MyISAM COLLATE utf8_general_ci; 

