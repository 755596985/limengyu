-- =============================================
-- 情侣小窝 数据库结构
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 站点配置
CREATE TABLE IF NOT EXISTS `cp_config` (
  `id` int NOT NULL DEFAULT 1,
  `name1` varchar(50) NOT NULL DEFAULT '男神',
  `name2` varchar(50) NOT NULL DEFAULT '女神',
  `love_date` varchar(20) NOT NULL DEFAULT '2024-01-01',
  `site_title` varchar(100) NOT NULL DEFAULT '',
  `beian` text,
  `avatar1` text,
  `avatar2` text,
  `background_image` text,
  `love_title` varchar(100) NOT NULL DEFAULT '已经在一起',
  `show_comments` tinyint NOT NULL DEFAULT 1,
  `show_album` tinyint NOT NULL DEFAULT 1,
  `show_places` tinyint NOT NULL DEFAULT 1,
  `show_todos` tinyint NOT NULL DEFAULT 1,
  `show_user_posts` tinyint NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 管理员
CREATE TABLE IF NOT EXISTS `cp_admin` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 普通用户
CREATE TABLE IF NOT EXISTS `cp_users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nickname` varchar(50) NOT NULL DEFAULT '',
  `avatar` varchar(255) NOT NULL DEFAULT '',
  `avatar_color` varchar(7) NOT NULL DEFAULT '#d4786e',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `location` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL DEFAULT '',
  `status` tinyint NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 说说/帖子
CREATE TABLE IF NOT EXISTS `cp_posts` (
  `id` varchar(32) NOT NULL,
  `title` varchar(200) NOT NULL DEFAULT '',
  `tags` text,
  `content` text,
  `author` varchar(50) NOT NULL DEFAULT '',
  `mood` varchar(20) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `images` text,
  `video` text,
  `music` text,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `location` varchar(100) NOT NULL DEFAULT '',
  `user_id` int DEFAULT NULL,
  `user_nick` varchar(50) NOT NULL DEFAULT '',
  `user_color` varchar(7) NOT NULL DEFAULT '#d4786e',
  PRIMARY KEY (`id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 评论
CREATE TABLE IF NOT EXISTS `cp_comments` (
  `id` varchar(40) NOT NULL,
  `post_id` varchar(32) NOT NULL DEFAULT '',
  `nick` varchar(50) NOT NULL DEFAULT '',
  `text` text,
  `voice` text,
  `ip` varchar(45) NOT NULL DEFAULT '',
  `user_id` int DEFAULT NULL,
  `parent_id` varchar(40) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `likes` int NOT NULL DEFAULT 0,
  `reply` text,
  `replied_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `post_id` (`post_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 评论点赞/踩
CREATE TABLE IF NOT EXISTS `cp_comment_likes` (
  `comment_id` varchar(40) NOT NULL,
  `user_id` varchar(80) NOT NULL,
  `type` varchar(10) NOT NULL DEFAULT 'like',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`comment_id`,`user_id`),
  KEY `comment_id` (`comment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 相册
CREATE TABLE IF NOT EXISTS `cp_photos` (
  `id` varchar(32) NOT NULL,
  `url` varchar(500) NOT NULL,
  `title` varchar(200) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 足迹
CREATE TABLE IF NOT EXISTS `cp_places` (
  `id` varchar(32) NOT NULL,
  `name` varchar(200) NOT NULL,
  `note` text,
  `image` varchar(500) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 待办清单
CREATE TABLE IF NOT EXISTS `cp_todos` (
  `id` varchar(32) NOT NULL,
  `title` varchar(200) NOT NULL,
  `note` text,
  `done` tinyint NOT NULL DEFAULT 0,
  `done_time` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 自定义页面
CREATE TABLE IF NOT EXISTS `cp_pages` (
  `id` varchar(32) NOT NULL,
  `title` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `icon` varchar(10) NOT NULL DEFAULT '📄',
  `content` text,
  `sort` int NOT NULL DEFAULT 99,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 关于我们
CREATE TABLE IF NOT EXISTS `cp_about` (
  `id` int NOT NULL DEFAULT 1,
  `version` varchar(20) NOT NULL DEFAULT '1.0',
  `version_desc` text,
  `boy_name` varchar(50) NOT NULL DEFAULT '',
  `boy_intro` text,
  `girl_name` varchar(50) NOT NULL DEFAULT '',
  `girl_intro` text,
  `boy_avatar_url` text,
  `girl_avatar_url` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 访问统计
CREATE TABLE IF NOT EXISTS `cp_visit` (
  `id` int NOT NULL DEFAULT 1,
  `total` int NOT NULL DEFAULT 0,
  `today` int NOT NULL DEFAULT 0,
  `visit_date` date DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 初始数据
INSERT IGNORE INTO `cp_config` (`id`) VALUES (1);
INSERT IGNORE INTO `cp_about` (`id`) VALUES (1);
INSERT IGNORE INTO `cp_visit` (`id`, `total`, `today`, `visit_date`) VALUES (1, 0, 0, CURDATE());

SET FOREIGN_KEY_CHECKS = 1;
