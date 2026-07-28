<?php
/**
 * 情侣小窝 - 安装向导
 * 请运行前确保 PHP 7.4+
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$step = $_GET['step'] ?? 1;
$step = (int)$step;
$error = '';
$success = '';

// 颜色变量
$bg = '#f2ede9'; $pri = '#d4786e'; $tx = '#5a4e4a'; $tl = '#8c7e78';
$sd = 'rgba(166,156,148,0.5)'; $sl = 'rgba(255,255,255,0.8)';

function check_php_version() {
    return version_compare(PHP_VERSION, '7.4.0', '>=');
}
function check_ext($name) {
    return extension_loaded($name);
}
function is_writable_check($dir) {
    $root = __DIR__;
    $full = $root . '/' . ltrim($dir, '/');
    if (!is_dir($full)) {
        return @mkdir($full, 0755, true);
    }
    return is_writable($full);
}

$checks = [
    ['PHP >= 7.4', check_php_version(), PHP_VERSION],
    ['mbstring 扩展', check_ext('mbstring'), ''],
    ['json 扩展', check_ext('json'), ''],
    ['fileinfo 扩展', check_ext('fileinfo'), ''],
    ['gd 扩展', check_ext('gd'), ''],
];

$dir_checks = [
    ['data/', is_writable_check('data')],
    ['uploads/', is_writable_check('uploads')],
    ['admin/', is_writable_check('admin')],
];

$all_pass = true;
foreach ($checks as $c) if (!$c[1]) $all_pass = false;
foreach ($dir_checks as $d) if (!$d[1]) $all_pass = false;

if ($step == 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? 'admin');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    $name1 = trim($_POST['name1'] ?? '男神');
    $name2 = trim($_POST['name2'] ?? '女神');
    $love_date = trim($_POST['love_date'] ?? date('Y-m-d'));
    $site_title = trim($_POST['site_title'] ?? '');

    if (empty($username) || strlen($username) < 2) {
        $error = '账号至少2个字符！';
    } elseif (empty($password) || strlen($password) < 4) {
        $error = '密码至少4位！';
    } elseif ($password !== $confirm) {
        $error = '两次密码不一致！';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // 确保目录存在
        $dirs = ['data', 'uploads'];
        foreach ($dirs as $d) {
            if (!is_dir(__DIR__ . '/' . $d)) mkdir(__DIR__ . '/' . $d, 0755, true);
        }

        // 管理员账号
        $admin_data = json_encode([
            'username' => $username,
            'password' => $hash,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        file_put_contents(__DIR__ . '/data/admin.json', $admin_data);

        // 站点配置
        $config = [
            'name1' => $name1,
            'name2' => $name2,
            'love_date' => $love_date,
            'site_title' => $site_title ?: ($name1 . ' ❤ ' . $name2),
            'beian' => '',
            'avatar1' => '',
            'avatar2' => '',
            'background_image' => '',
        ];
        file_put_contents(__DIR__ . '/data/config.json', json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        // 初始空数据
        file_put_contents(__DIR__ . '/data/posts.json', '[]');
        file_put_contents(__DIR__ . '/data/places.json', '[]');
        file_put_contents(__DIR__ . '/data/todos.json', '[]');
        file_put_contents(__DIR__ . '/data/photos.json', '[]');
        file_put_contents(__DIR__ . '/data/pages.json', '[]');
        file_put_contents(__DIR__ . '/data/comments.json', '[]');
        file_put_contents(__DIR__ . '/data/users.json', '[]');
        file_put_contents(__DIR__ . '/data/about.json', json_encode([
            'version' => '情侣小窝 v1.0',
            'version_desc' => 'v1.0 - 初始版本',
            'boy_name' => '',
            'boy_intro' => '',
            'girl_name' => '',
            'girl_intro' => '',
            'boy_avatar_url' => '',
            'girl_avatar_url' => '',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        file_put_contents(__DIR__ . '/data/filter_words.json', '[]');
        file_put_contents(__DIR__ . '/data/visitors.json', '[]');

        $success = '安装成功！';
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>安装向导 · 情侣小窝</title>
<style>
:root{--bg:<?php echo $bg; ?>;--sd:<?php echo $sd; ?>;--sl:<?php echo $sl; ?>;--pri:<?php echo $pri; ?>;--tx:<?php echo $tx; ?>;--tl:<?php echo $tl; ?>}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei',sans-serif;background:var(--bg);color:var(--tx);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{background:var(--bg);border-radius:24px;box-shadow:8px 8px 24px var(--sd),-8px -8px 24px var(--sl);padding:40px 36px;width:480px;max-width:95vw}
.box .icon{width:80px;height:80px;border-radius:50%;box-shadow:6px 6px 16px var(--sd),-6px -6px 16px var(--sl);display:inline-flex;align-items:center;justify-content:center;font-size:2.2em;margin-bottom:20px}
.box h2{font-size:1.4em;color:var(--tx);letter-spacing:2px;margin-bottom:6px;text-align:center}
.box .sub{font-size:.85em;color:var(--tl);margin-bottom:28px;text-align:center}
.steps{display:flex;justify-content:center;gap:40px;margin-bottom:24px}
.step{display:flex;flex-direction:column;align-items:center;gap:6px}
.step-num{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9em;box-shadow:4px 4px 10px var(--sd),-4px -4px 10px var(--sl)}
.step-num.active{box-shadow:inset 4px 4px 8px var(--sd),inset -4px -4px 8px var(--sl);color:var(--pri)}
.step-num.done{color:#5cb85c}
.step-label{font-size:.72em;color:var(--tl)}
.fg{margin-bottom:16px;text-align:left}
.fg label{display:block;font-size:.82em;color:var(--tl);margin-bottom:8px;font-weight:600;letter-spacing:1px}
.fg input{width:100%;padding:13px 16px;background:var(--bg);border:none;border-radius:12px;font-size:1em;color:var(--tx);box-shadow:inset 4px 4px 10px var(--sd),inset -4px -4px 10px var(--sl);outline:none;font-family:inherit}
.btn{width:100%;padding:13px;background:var(--bg);border:none;border-radius:12px;font-size:1em;font-weight:700;color:var(--pri);box-shadow:4px 4px 12px var(--sd),-4px -4px 12px var(--sl);cursor:pointer;transition:all .2s;letter-spacing:2px;text-align:center}
.btn:active{box-shadow:inset 4px 4px 10px var(--sd),inset -4px -4px 10px var(--sl);transform:scale(.97)}
.btn-secondary{color:var(--tx);margin-top:12px}
.check-list{list-style:none;margin-bottom:20px}
.check-list li{display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:10px;margin-bottom:6px;font-size:.88em;box-shadow:2px 2px 6px var(--sd),-2px -2px 6px var(--sl)}
.check-list li .pass{color:#5cb85c;font-weight:700}
.check-list li .fail{color:#c0392b;font-weight:700}
.check-list li .info{flex:1}
.check-list li .ver{font-size:.75em;color:var(--tl)}
.success-box{text-align:center;padding:20px 0}
.success-box .big-icon{font-size:3em;margin-bottom:16px}
.success-box p{font-size:.9em;color:var(--tl);line-height:1.8}
.success-box strong{color:var(--pri)}
.error-box{background:#ffebee;color:#c62828;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:.85em;text-align:center}
.msg-box{background:#e8f5e9;color:#2e7d32;padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:.85em;text-align:center}
</style>
</head>
<body>
<div class="box">
<div class="icon">💕</div>
<h2>情侣小窝 · 安装向导</h2>

<?php if ($step == 1): ?>
<p class="sub">第 1 步：环境检测</p>
<ul class="check-list">
<?php foreach ($checks as $c): ?>
<li>
<span><?php echo $c[1] ? '<span class="pass">✓</span>' : '<span class="fail">✗</span>'; ?></span>
<span class="info"><?php echo htmlspecialchars($c[0]); ?></span>
<?php if ($c[2]): ?><span class="ver"><?php echo htmlspecialchars($c[2]); ?></span><?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
<p class="sub" style="margin-bottom:10px">目录权限检测</p>
<ul class="check-list">
<?php foreach ($dir_checks as $d): ?>
<li>
<span><?php echo $d[1] ? '<span class="pass">✓</span>' : '<span class="fail">✗</span>'; ?></span>
<span class="info"><?php echo htmlspecialchars($d[0]); ?> <?php echo $d[1] ? '可写' : '不可写'; ?></span>
</li>
<?php endforeach; ?>
</ul>
<?php if ($all_pass): ?>
<div class="msg-box">✅ 环境检测通过，可以继续安装！</div>
<a href="?step=2" class="btn" style="display:block;text-decoration:none">开始安装 →</a>
<?php else: ?>
<div class="error-box">❌ 部分检测未通过，请先修复以上问题后再继续。</div>
<?php endif; ?>

<?php elseif ($step == 2 && !$success): ?>
<p class="sub">第 2 步：站点设置</p>
<?php if ($error): ?><div class="error-box"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<form method="post">
<div class="fg"><label>👤 管理员账号</label><input type="text" name="username" value="admin" placeholder="管理员登录账号" required autocomplete="off"></div>
<div class="fg"><label>🔑 管理员密码</label><input type="password" name="password" placeholder="至少4位" required autocomplete="off"></div>
<div class="fg"><label>🔄 确认密码</label><input type="password" name="confirm" placeholder="再次输入密码" required autocomplete="off"></div>
<div class="fg"><label>👦 你的名字</label><input type="text" name="name1" placeholder="如：小鱼" required></div>
<div class="fg"><label>👧 TA 的名字</label><input type="text" name="name2" placeholder="如：小可爱" required></div>
<div class="fg"><label>📅 纪念日</label><input type="date" name="love_date" value="<?php echo date('Y-m-d'); ?>"></div>
<div class="fg"><label>🏷️ 网站标题 (可选)</label><input type="text" name="site_title" placeholder="默认：名字 ❤ 名字"></div>
<button type="submit" class="btn">💕 开始安装</button>
</form>

<?php elseif ($success): ?>
<p class="sub">安装完成！</p>
<div class="success-box">
<div class="big-icon">🎉</div>
<p><strong>情侣小窝</strong> 安装成功！</p>
<p style="margin-top:12px">
🏠 <strong>前台地址：</strong><a href="index.php" style="color:var(--pri)">/index.php</a><br>
⚙️ <strong>后台地址：</strong><a href="admin/index.php" style="color:var(--pri)">/admin/index.php</a>
</p>
<p style="margin-top:16px;font-size:.78em;color:var(--tl)">
🔐 请牢记管理员账号密码<br>
⚠️ 建议安装完成后<strong>删除 install.php</strong> 以提高安全性
</p>
</div>
<?php endif; ?>
</div>
</body>
</html>