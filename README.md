# 💕 情侣小窝（Qinglv XiaoWo）

一个**适合两个人私藏的小站**：写说说、传相册、记足迹、列心愿清单，还有带 AI 自动回复的评论区——部署一次，就是属于你们的"爱情小窝"。

> 单用户自定义 PHP 应用，无框架依赖，轻量、好部署、可玩性高。
> 演示站：https://yu.wuaze.com （InfinityFree 免费主机部署）

---

## ✨ 功能一览

| 模块 | 说明 |
|------|------|
| 💬 说说 | 发布带图片/视频/音乐/标签的动态，支持心情 emoji |
| 📷 相册 | 上传照片自动压缩，瀑布流展示甜蜜瞬间 |
| 🗺 足迹 | 记录一起到过的地方（可配图） |
| ✅ 清单 | 心愿单 / 待办事项，勾选完成有仪式感 |
| 💌 评论区 | 时间+地区自动合并展示，支持回复、点赞（👍/👎）、语音留言 |
| 🤖 AI 小窝 | 接入大模型 API：AI 自动回复评论、AI 定时发布说说、自定义人设与记忆 |
| 🎨 双主题 | 奶白 / 黑夜主题一键切换 |
| 📄 自定义页 | 后台可自由添加页面（支持轻量 Markdown） |
| 👥 用户系统 | 支持注册/登录，多用户共同维护 |
| 🛡 安全防护 | CSRF 防护、敏感词过滤、访客记录、图片上传压缩与校验 |
| 📊 访问统计 | 总访问量 / 今日访问量 |
| ⚙️ 管理后台 | 模块化管理：内容、文件、配置、AI、用户一应俱全 |

## 🧰 技术栈

- 后端：原生 PHP 7.4+（推荐 8.x），PDO + MySQL 5.7+
- 前端：原生 HTML/CSS/JS，无前端框架
- 部署：支持任意支持 PHP + MySQL 的虚拟主机 / VPS / 容器
- 外部依赖（可选）：大模型 API（默认兼容商汤日日新 OpenAI 格式）、一言 API、背景图 API

## 🚀 快速部署（三步）

### 第 0 步：准备环境

- PHP >= 7.4，已启用扩展：`mbstring`、`json`、`fileinfo`、`gd`、`curl`、`pdo_mysql`
- MySQL >= 5.7 或 MariaDB >= 10.3
- 支持上传/写入权限的网站根目录

### 第 1 步：导入数据库

用 phpMyAdmin（或命令行）新建数据库（如 `qinglv`），然后导入根目录的 [`schema.sql`](schema.sql)：

```bash
mysql -u你的用户名 -p 你的数据库名 < schema.sql
```

### 第 2 步：填写数据库配置

编辑 `include/config.db.php`，填入你的数据库信息：

```php
return [
    'host'    => getenv('CP_DB_HOST') ?: 'localhost',   // 数据库地址
    'port'    => (int)(getenv('CP_DB_PORT') ?: 3306),
    'dbname'  => getenv('CP_DB_NAME') ?: '你的数据库名',
    'user'    => getenv('CP_DB_USER') ?: '你的数据库用户名',
    'pass'    => getenv('CP_DB_PASS') ?: '你的数据库密码',
    'charset' => 'utf8mb4',
];
```

> 生产环境建议直接使用环境变量 `CP_DB_HOST / CP_DB_PORT / CP_DB_NAME / CP_DB_USER / CP_DB_PASS`，避免把密码写进文件。

### 第 3 步：上传源码并安装

1. 将全部源码上传到网站根目录（确保 `data/`、`uploads/` 目录可写）
2. 浏览器访问 `https://你的域名/install.php`
3. 按向导设置：管理员账号密码、你们的名字、纪念日、网站标题
4. 安装完成后 **立即删除 `install.php`**，然后访问后台 `/admin/index.php` 开始装扮你们的小窝

默认后台账号：`admin`（首次访问若表为空会自动创建，密码 `admin123`，**请登录后立即修改**）

## ⚙️ 可选功能配置

### 🤖 AI 自动评论 / 自动发布

1. 登录后台 → **AI 设置**，填写：
   - API Base URL：`https://token.sensenova.cn/v1`（兼容 OpenAI 格式的任意服务）
   - API Key：你的密钥（如商汤日日新）
   - 模型名：如 `sensenova-6.7-flash-lite`
2. 开启「AI 自动回复评论」开关，选择 AI 使用的账号
3. 定时发布说说：在主机控制面板（如 InfinityFree → Cron Jobs）添加定时任务：

```
https://你的域名/cron_ai_post.php?key=你的密钥
```

密钥可在后台 AI 设置页查看/重置；建议每天执行一次（如 `0 9 * * *`），代码内置 20 小时防重复。

### 🎨 一言 API

前台 `api.php` 会从 `data/yiyan.php` 随机返回一句情话，默认内置 10 条，可在该文件自行增删。

### 🛡 安全建议

- 安装后删除 `install.php`
- 修改默认后台密码 `admin123`
- 定期备份数据库与 `uploads/` 目录
- 若不需要注册功能，可在后台关闭用户注册入口

## 📁 目录结构

```
├── index.php              # 前台首页（说说流 + 评论区）
├── install.php            # 安装向导（部署后请删除）
├── api.php                # 一言 API
├── login.php / register.php / user.php   # 用户登录/注册/主页
├── like.php               # 评论点赞接口
├── cron_ai_post.php       # AI 定时发布 Cron 端点
├── include/
│   ├── bootstrap.php      # 公共引导：PDO、CSRF、上传、工具函数
│   └── config.db.php      # 数据库配置
├── admin/
│   ├── index.php          # 后台入口
│   ├── manage.php         # 后台分发器
│   └── modules/           # 后台功能模块（manifest.php 声明）
├── data/                  # 运行时数据（一言、敏感词、访客等）
├── uploads/               # 上传文件目录（需可写）
└── schema.sql             # 数据库结构（导入即可用）
```

## 📜 License

[Mozilla Public License 2.0](LICENSE)

---

*愿每一个小窝，都住着两个人。* 💕
