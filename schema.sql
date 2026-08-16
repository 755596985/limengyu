-- ============================================================
-- 情侣小窝 数据库结构 (MySQL 5.7+ / MariaDB 10.3+)
-- 使用方法：phpMyAdmin 或命令行导入本文件即可
-- ============================================================

SET NAMES utf8mb4;

-- 站点配置表
CREATE TABLE IF NOT EXISTS cp_config (
  id INT PRIMARY KEY,
  name1 VARCHAR(50) NOT NULL DEFAULT '男神',
  name2 VARCHAR(50) NOT NULL DEFAULT '女神',
  love_date DATE NULL,
  site_title VARCHAR(200) NOT NULL DEFAULT '',
  beian TEXT,
  avatar1 TEXT,
  avatar2 TEXT,
  background_image TEXT,
  love_title VARCHAR(50) NOT NULL DEFAULT '已经在一起',
  show_comments TINYINT NOT NULL DEFAULT 1,
  show_album TINYINT NOT NULL DEFAULT 1,
  show_places TINYINT NOT NULL DEFAULT 1,
  show_todos TINYINT NOT NULL DEFAULT 1,
  show_user_posts TINYINT NOT NULL DEFAULT 1,
  footer TEXT,
  ai_base_url TEXT,
  ai_api_key TEXT,
  ai_model TEXT,
  ai_persona_key TEXT,
  ai_persona_custom TEXT,
  ai_emotion_on TINYINT NOT NULL DEFAULT 0,
  ai_memory_on TINYINT NOT NULL DEFAULT 0,
  ai_auto_reply_on TINYINT NOT NULL DEFAULT 0,
  ai_reply_user_id VARCHAR(32) DEFAULT NULL,
  ai_memory TEXT,
  ai_cron_key VARCHAR(64) DEFAULT NULL,
  ai_cron_last DATETIME DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO cp_config (id, name1, name2, love_date, site_title)
VALUES (1, '男神', '女神', CURDATE(), '情侣小窝')
ON DUPLICATE KEY UPDATE id=id;

-- 访问统计表
CREATE TABLE IF NOT EXISTS cp_visit (
  id INT PRIMARY KEY,
  total INT NOT NULL DEFAULT 0,
  today INT NOT NULL DEFAULT 0,
  visit_date DATE NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO cp_visit (id, total, today, visit_date) VALUES (1, 0, 0, CURDATE())
ON DUPLICATE KEY UPDATE id=id;

-- 关于我们 / 版本信息表
CREATE TABLE IF NOT EXISTS cp_about (
  id INT PRIMARY KEY,
  version VARCHAR(50) NOT NULL DEFAULT '',
  version_desc TEXT,
  boy_name VARCHAR(50) NOT NULL DEFAULT '',
  boy_intro TEXT,
  girl_name VARCHAR(50) NOT NULL DEFAULT '',
  girl_intro TEXT,
  boy_avatar_url TEXT,
  girl_avatar_url TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO cp_about (id, version) VALUES (1, '情侣小窝 v1.0')
ON DUPLICATE KEY UPDATE id=id;

-- 说说（动态）表
CREATE TABLE IF NOT EXISTS cp_posts (
  id VARCHAR(32) PRIMARY KEY,
  title VARCHAR(200) NOT NULL DEFAULT '',
  tags TEXT,
  content TEXT,
  author VARCHAR(50) NOT NULL DEFAULT '1',
  mood VARCHAR(20) NOT NULL DEFAULT '💕',
  created_at DATETIME NULL,
  images TEXT,
  video TEXT,
  music TEXT,
  ip VARCHAR(50) DEFAULT NULL,
  location VARCHAR(200) DEFAULT NULL,
  user_id VARCHAR(32) DEFAULT NULL,
  user_nick VARCHAR(50) DEFAULT NULL,
  user_color VARCHAR(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 评论表
CREATE TABLE IF NOT EXISTS cp_comments (
  id VARCHAR(32) PRIMARY KEY,
  post_id VARCHAR(32) DEFAULT NULL,
  nick VARCHAR(50) NOT NULL DEFAULT '',
  text TEXT,
  voice TEXT,
  ip VARCHAR(50) DEFAULT NULL,
  user_id VARCHAR(32) DEFAULT NULL,
  parent_id VARCHAR(32) DEFAULT NULL,
  created_at DATETIME NULL,
  likes INT NOT NULL DEFAULT 0,
  reply TEXT,
  replied_at DATETIME NULL,
  KEY idx_post (post_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 评论点赞表
CREATE TABLE IF NOT EXISTS cp_comment_likes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  comment_id VARCHAR(32) NOT NULL,
  user_id VARCHAR(32) NOT NULL,
  type VARCHAR(10) NOT NULL DEFAULT 'like',
  created_at DATETIME NULL,
  UNIQUE KEY uq_comment_user (comment_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户表
CREATE TABLE IF NOT EXISTS cp_users (
  id VARCHAR(32) PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL,
  nickname VARCHAR(50) DEFAULT NULL,
  avatar TEXT,
  avatar_color VARCHAR(20) NOT NULL DEFAULT '#d4786e',
  ip VARCHAR(50) DEFAULT NULL,
  location VARCHAR(200) DEFAULT NULL,
  email VARCHAR(100) DEFAULT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at DATETIME NULL,
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 相册表
CREATE TABLE IF NOT EXISTS cp_photos (
  id VARCHAR(32) PRIMARY KEY,
  url TEXT,
  title VARCHAR(200) NOT NULL DEFAULT '',
  created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 足迹表
CREATE TABLE IF NOT EXISTS cp_places (
  id VARCHAR(32) PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  note TEXT,
  image TEXT,
  created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 清单（心愿单/待办）表
CREATE TABLE IF NOT EXISTS cp_todos (
  id VARCHAR(32) PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  note TEXT,
  done TINYINT NOT NULL DEFAULT 0,
  done_time DATETIME NULL,
  created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 自定义页面表
CREATE TABLE IF NOT EXISTS cp_pages (
  id VARCHAR(32) PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(100) DEFAULT NULL,
  icon VARCHAR(20) NOT NULL DEFAULT '📄',
  content TEXT,
  sort INT NOT NULL DEFAULT 99,
  created_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 管理员表（首次访问后台时若不存在会自动创建 admin/admin123，请登录后立即修改）
CREATE TABLE IF NOT EXISTS cp_admin (
  id INT PRIMARY KEY,
  username VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
