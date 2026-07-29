<?php
/**
 * 情侣小窝 - 一键初始化
 * 上传此文件到网站根目录，浏览器访问一次即可完成建表和管理员创建。
 * 完成后请立即删除此文件！
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

// 加载数据库配置
$dbCfg = require __DIR__ . '/include/config.db.php';

try {
    $hosts = array_unique(array_filter([$dbCfg['host'] ?? 'localhost', '127.0.0.1', 'localhost']));
    $pdo = null;
    $lastErr = null;
    foreach ($hosts as $h) {
        try {
            $pdo = new PDO(
                "mysql:host=$h;port={$dbCfg['port']};dbname={$dbCfg['dbname']};charset={$dbCfg['charset']}",
                $dbCfg['user'], $dbCfg['pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            break;
        } catch (Throwable $e) { $lastErr = $e; }
    }
    if (!$pdo) throw $lastErr ?: new Exception('数据库连接失败');
} catch (Throwable $e) {
    die("❌ 数据库连接失败：" . $e->getMessage());
}

$msg = [];

// ===== 建表 =====
$tables = [
    "cp_config" => "CREATE TABLE IF NOT EXISTS cp_config (
        id INT NOT NULL DEFAULT 1,
        name1 VARCHAR(50) NOT NULL DEFAULT '男神',
        name2 VARCHAR(50) NOT NULL DEFAULT '女神',
        love_date VARCHAR(20) NOT NULL DEFAULT '2024-01-01',
        site_title VARCHAR(100) NOT NULL DEFAULT '',
        beian TEXT,
        avatar1 TEXT,
        avatar2 TEXT,
        background_image TEXT,
        love_title VARCHAR(100) NOT NULL DEFAULT '已经在一起',
        show_comments TINYINT NOT NULL DEFAULT 1,
        show_album TINYINT NOT NULL DEFAULT 1,
        show_places TINYINT NOT NULL DEFAULT 1,
        show_todos TINYINT NOT NULL DEFAULT 1,
        show_user_posts TINYINT NOT NULL DEFAULT 1,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "cp_admin" => "CREATE TABLE IF NOT EXISTS cp_admin (
        id INT NOT NULL AUTO_INCREMENT,
        username VARCHAR(50) NOT NULL,
        password VARCHAR(255) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "cp_users" => "CREATE TABLE IF NOT EXISTS cp_users (
        id INT NOT NULL AUTO_INCREMENT,
        username VARCHAR(50) NOT NULL,
        password VARCHAR(255) NOT NULL,
        nickname VARCHAR(50) NOT NULL DEFAULT '',
        avatar VARCHAR(255) NOT NULL DEFAULT '',
        avatar_color VARCHAR(7) NOT NULL DEFAULT '#d4786e',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        location VARCHAR(100) NOT NULL DEFAULT '',
        email VARCHAR(100) NOT NULL DEFAULT '',
        status TINYINT NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY username (username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "cp_posts" => "CREATE TABLE IF NOT EXISTS cp_posts (
        id VARCHAR(32) NOT NULL,
        title VARCHAR(200) NOT NULL DEFAULT '',
        tags TEXT,
        content TEXT,
        author VARCHAR(50) NOT NULL DEFAULT '',
        mood VARCHAR(20) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        images TEXT,
        video TEXT,
        music TEXT,
        ip VARCHAR(45) NOT NULL DEFAULT '',
        location VARCHAR(100) NOT NULL DEFAULT '',
        user_id INT DEFAULT NULL,
        user_nick VARCHAR(50) NOT NULL DEFAULT '',
        user_color VARCHAR(7) NOT NULL DEFAULT '#d4786e',
        PRIMARY KEY (id),
        KEY created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "cp_comments" => "CREATE TABLE IF NOT EXISTS cp_comments (
        id VARCHAR(40) NOT NULL,
        post_id VARCHAR(32) NOT NULL DEFAULT '',
        nick VARCHAR(50) NOT NULL DEFAULT '',
        text TEXT,
        voice TEXT,
        ip VARCHAR(45) NOT NULL DEFAULT '',
        user_id INT DEFAULT NULL,
        parent_id VARCHAR(40) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        likes INT NOT NULL DEFAULT 0,
        reply TEXT,
        replied_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY post_id (post_id),
        KEY created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "cp_comment_likes" => "CREATE TABLE IF NOT EXISTS cp_comment_likes (
        comment_id VARCHAR(40) NOT NULL,
        user_id VARCHAR(80) NOT NULL,
        type VARCHAR(10) NOT NULL DEFAULT 'like',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (comment_id, user_id),
        KEY comment_id (comment_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "cp_photos" => "CREATE TABLE IF NOT EXISTS cp_photos (
        id VARCHAR(32) NOT NULL,
        url VARCHAR(500) NOT NULL,
        title VARCHAR(200) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "cp_places" => "CREATE TABLE IF NOT EXISTS cp_places (
        id VARCHAR(32) NOT NULL,
        name VARCHAR(200) NOT NULL,
        note TEXT,
        image VARCHAR(500) NOT NULL DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "cp_todos" => "CREATE TABLE IF NOT EXISTS cp_todos (
        id VARCHAR(32) NOT NULL,
        title VARCHAR(200) NOT NULL,
        note TEXT,
        done TINYINT NOT NULL DEFAULT 0,
        done_time DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "cp_pages" => "CREATE TABLE IF NOT EXISTS cp_pages (
        id VARCHAR(32) NOT NULL,
        title VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL,
        icon VARCHAR(10) NOT NULL DEFAULT '📄',
        content TEXT,
        sort INT NOT NULL DEFAULT 99,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "cp_about" => "CREATE TABLE IF NOT EXISTS cp_about (
        id INT NOT NULL DEFAULT 1,
        version VARCHAR(20) NOT NULL DEFAULT '1.0',
        version_desc TEXT,
        boy_name VARCHAR(50) NOT NULL DEFAULT '',
        boy_intro TEXT,
        girl_name VARCHAR(50) NOT NULL DEFAULT '',
        girl_intro TEXT,
        boy_avatar_url TEXT,
        girl_avatar_url TEXT,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    "cp_visit" => "CREATE TABLE IF NOT EXISTS cp_visit (
        id INT NOT NULL DEFAULT 1,
        total INT NOT NULL DEFAULT 0,
        today INT NOT NULL DEFAULT 0,
        visit_date DATE DEFAULT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
];

foreach ($tables as $name => $sql) {
    try {
        $pdo->exec($sql);
        $msg[] = "✓ 表 $name 就绪";
    } catch (Throwable $e) {
        $msg[] = "✗ 表 $name 失败：" . $e->getMessage();
    }
}

// ===== 初始数据 =====
$pdo->exec("INSERT IGNORE INTO cp_config (id) VALUES (1)");
$pdo->exec("INSERT IGNORE INTO cp_about (id) VALUES (1)");
$pdo->exec("INSERT IGNORE INTO cp_visit (id, total, today, visit_date) VALUES (1, 0, 0, CURDATE())");
$msg[] = "✓ 初始数据已写入";

// ===== 表单处理 =====
$done = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';
    $name1    = trim($_POST['name1'] ?? '男神');
    $name2    = trim($_POST['name2'] ?? '女神');
    $loveDate = trim($_POST['love_date'] ?? date('Y-m-d'));
    $siteTitle = trim($_POST['site_title'] ?? '');

    $err = '';
    if (strlen($username) < 2)   $err = '账号至少2位';
    elseif (strlen($password) < 4) $err = '密码至少4位';
    elseif ($password !== $confirm) $err = '两次密码不一致';

    if (!$err) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->exec("DELETE FROM cp_admin");
        $pdo->prepare("INSERT INTO cp_admin (username, password) VALUES (?, ?)")->execute([$username, $hash]);

        $st = $pdo->prepare("UPDATE cp_config SET name1=?, name2=?, love_date=?, site_title=? WHERE id=1");
        $st->execute([$name1, $name2, $loveDate, $siteTitle ?: "$name1 ❤ $name2"]);

        $msg[] = "✓ 管理员 $username 已创建";
        $done = true;
    }
}

// ===== 输出页面 =====
?><!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>情侣小窝 - 安装向导</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f2ede9;color:#5a4e4a;line-height:1.6;padding:20px}
.card{max-width:440px;margin:20px auto;background:#fff;border-radius:16px;padding:24px;box-shadow:0 2px 16px rgba(166,156,148,0.2)}
h2{color:#d4786e;margin-bottom:8px;font-size:20px}
.sub{color:#8c7e78;font-size:13px;margin-bottom:20px}
.msg{font-size:13px;margin-bottom:16px;line-height:1.8}
.msg .ok{color:#4caf50}
.msg .err{color:#f44336}
label{display:block;font-size:14px;margin:12px 0 4px;color:#8c7e78}
input{width:100%;padding:10px 14px;border:1px solid #e0d8d2;border-radius:10px;font-size:15px;background:#faf8f6;outline:none}
input:focus{border-color:#d4786e}
.btn{width:100%;padding:12px;margin-top:20px;border:none;border-radius:12px;background:#d4786e;color:#fff;font-size:16px;cursor:pointer;font-weight:600}
.btn:hover{background:#c0685e}
.err-text{color:#f44336;font-size:13px;margin-top:8px}
.done{text-align:center;padding:20px 0}
.done .icon{font-size:48px;margin-bottom:12px}
.done p{margin:8px 0;font-size:14px}
.done a{color:#d4786e;text-decoration:none;font-weight:600}
.warn{background:#fff3e0;color:#e65100;padding:8px 12px;border-radius:8px;font-size:13px;margin-top:16px}
</style>
</head>
<body>

<div class="card">
<?php if ($done): ?>
<div class="done">
<div class="icon">🎉</div>
<h2>安装完成！</h2>
<p>管理员账号：<b><?php echo htmlspecialchars($username); ?></b></p>
<p><a href="index.php">进入网站首页 →</a></p>
<p style="margin-top:4px"><a href="admin/">进入后台管理 →</a></p>
<div class="warn">⚠️ 请立即删除 setup.php 文件</div>
</div>
<?php else: ?>
<h2>📦 数据库初始化</h2>
<div class="msg">
<?php foreach ($msg as $m): ?>
<div class="<?php echo str_starts_with($m, '✓') ? 'ok' : 'err'; ?>"><?php echo htmlspecialchars($m); ?></div>
<?php endforeach; ?>
</div>

<h2 style="margin-top:20px">🔐 创建管理员</h2>
<div class="sub">设置管理员账号和情侣昵称</div>

<?php if (!empty($err)): ?><div class="err-text"><?php echo $err; ?></div><?php endif; ?>

<form method="post">
<label>管理员账号</label>
<input type="text" name="username" value="<?php echo htmlspecialchars($username ?? 'admin'); ?>" required minlength="2">

<label>管理员密码</label>
<input type="password" name="password" required minlength="4" placeholder="至少4位">

<label>确认密码</label>
<input type="password" name="confirm" required minlength="4">

<label>男生昵称</label>
<input type="text" name="name1" value="<?php echo htmlspecialchars($name1 ?? '男神'); ?>">

<label>女生昵称</label>
<input type="text" name="name2" value="<?php echo htmlspecialchars($name2 ?? '女神'); ?>">

<label>在一起的日子</label>
<input type="date" name="love_date" value="<?php echo htmlspecialchars($loveDate ?? date('Y-m-d')); ?>">

<label>网站标题 (可选)</label>
<input type="text" name="site_title" placeholder="默认：男神 ❤ 女神">

<button type="submit" class="btn">完成安装</button>
</form>
<?php endif; ?>
</div>

</body>
</html>
