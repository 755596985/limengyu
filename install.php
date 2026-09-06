<?php
/**
 * 情侣小窝 - MySQL 安装向导
 * 适用架构：v1.0（PDO + MySQL）
 * 功能：环境检测 → 连接数据库 → 自动导入 schema.sql（12 张 cp_* 表）→
 *       创建管理员账号 → 写入初始站点配置 → 可选写回 include/config.db.php
 * 要求：PHP 7.4+，扩展 pdo_mysql / mbstring / json / fileinfo / gd / curl
 * 安装完成后请立即删除本文件。
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = false;
$config_written = false;

// 奶油风配色
$bg = '#f2ede9'; $pri = '#d4786e'; $tx = '#5a4e4a'; $tl = '#8c7e78';
$sd = 'rgba(166,156,148,0.5)'; $sl = 'rgba(255,255,255,0.8)';

function check_php_version() {
    return version_compare(PHP_VERSION, '7.4.0', '>=');
}
function check_ext($name) {
    return extension_loaded($name);
}
function writable_dir($dir) {
    $full = __DIR__ . '/' . trim($dir, '/');
    if (!is_dir($full)) {
        return @mkdir($full, 0755, true);
    }
    return is_writable($full);
}

/**
 * 读取 schema.sql 并拆分为单条 SQL。
 * schema.sql 全部为幂等语句（IF NOT EXISTS / ON DUPLICATE KEY UPDATE），可安全重复执行。
 */
function schema_statements($file) {
    $raw = file_get_contents($file);
    if ($raw === false) {
        return false;
    }
    $lines = explode("\n", $raw);
    $statements = array();
    $buf = '';
    foreach ($lines as $line) {
        $t = trim($line);
        if ($t === '') {
            continue;
        }
        if (strpos($t, '--') === 0) {
            continue;
        }
        $buf .= $line . "\n";
        if (substr(rtrim($line), -1) === ';') {
            $s = trim($buf);
            if ($s !== '') {
                $statements[] = $s;
            }
            $buf = '';
        }
    }
    if (trim($buf) !== '') {
        $statements[] = trim($buf);
    }
    return $statements;
}

/**
 * 生成 include/config.db.php 文件内容（保留环境变量优先的回退结构）
 */
function config_php_src($host, $port, $dbname, $user, $pass) {
    $esc = function ($v) {
        return str_replace(array("\\", "'"), array("\\\\", "\\'"), $v);
    };
    $lines = array();
    $lines[] = '<?php';
    $lines[] = '/**';
    $lines[] = ' * 数据库配置 — 优先使用环境变量，本文件回退值由 install.php 向导写入';
    $lines[] = ' * 生产环境建议使用环境变量 CP_DB_HOST / CP_DB_PORT / CP_DB_NAME / CP_DB_USER / CP_DB_PASS 覆盖';
    $lines[] = ' */';
    $lines[] = 'return [';
    $lines[] = "    'host'    => getenv('CP_DB_HOST') ?: '" . $esc($host) . "',";
    $lines[] = "    'port'    => (int)(getenv('CP_DB_PORT') ?: " . (int)$port . "),";
    $lines[] = "    'dbname'  => getenv('CP_DB_NAME') ?: '" . $esc($dbname) . "',";
    $lines[] = "    'user'    => getenv('CP_DB_USER') ?: '" . $esc($user) . "',";
    $lines[] = "    'pass'    => getenv('CP_DB_PASS') ?: '" . $esc($pass) . "',";
    $lines[] = "    'charset' => 'utf8mb4',";
    $lines[] = '];';
    $lines[] = '';
    return implode("\n", $lines);
}

// ---------- POST：执行安装 ----------
if ($step == 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $db_host   = trim($_POST['db_host'] ?? 'localhost');
    $db_port   = (int)($_POST['db_port'] ?? 3306);
    $db_port   = $db_port > 0 ? $db_port : 3306;
    $db_name   = trim($_POST['db_name'] ?? '');
    $db_user   = trim($_POST['db_user'] ?? '');
    $db_pass   = (string)($_POST['db_pass'] ?? '');
    $username  = trim($_POST['username'] ?? 'admin');
    $password  = (string)($_POST['password'] ?? '');
    $confirm   = (string)($_POST['confirm'] ?? '');
    $name1     = trim($_POST['name1'] ?? '男神');
    $name2     = trim($_POST['name2'] ?? '女神');
    $love_date = trim($_POST['love_date'] ?? date('Y-m-d'));
    $site_title= trim($_POST['site_title'] ?? '');

    if ($db_name === '' || $db_user === '') {
        $error = '请填写数据库名与数据库用户名！';
    } elseif (strlen($username) < 2) {
        $error = '管理员账号至少 2 个字符！';
    } elseif (strlen($password) < 4) {
        $error = '管理员密码至少 4 位！';
    } elseif ($password !== $confirm) {
        $error = '两次输入的管理员密码不一致！';
    } else {
        // 1) 连接数据库
        $pdo = null;
        try {
            $dsn = 'mysql:host=' . $db_host . ';port=' . $db_port . ';dbname=' . $db_name . ';charset=utf8mb4';
            $pdo = new PDO($dsn, $db_user, $db_pass, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 8,
            ));
            $pdo->exec('SET NAMES utf8mb4');
        } catch (PDOException $ex) {
            $error = '数据库连接失败：' . $ex->getMessage();
        }

        // 2) 导入 schema.sql（幂等，可重复执行）
        if (!$error && $pdo) {
            $stmts = schema_statements(__DIR__ . '/schema.sql');
            if ($stmts === false) {
                $error = '无法读取根目录 schema.sql，请确认文件存在。';
            } else {
                foreach ($stmts as $s) {
                    try {
                        $pdo->exec($s);
                    } catch (PDOException $ex) {
                        $error = '建表失败：' . $ex->getMessage() . '（SQL: ' . mb_substr($s, 0, 80) . '…）';
                        break;
                    }
                }
            }
        }

        // 3) 创建/更新管理员与站点配置
        if (!$error && $pdo) {
            try {
                $stmt = $pdo->prepare('INSERT INTO cp_admin (id, username, password) VALUES (1, ?, ?) ON DUPLICATE KEY UPDATE username=VALUES(username), password=VALUES(password)');
                $stmt->execute(array($username, password_hash($password, PASSWORD_DEFAULT)));

                $title = $site_title !== '' ? $site_title : $name1 . ' ❤ ' . $name2;
                $stmt = $pdo->prepare('UPDATE cp_config SET name1=?, name2=?, love_date=?, site_title=? WHERE id=1');
                $stmt->execute(array($name1, $name2, $love_date !== '' ? $love_date : date('Y-m-d'), $title));
            } catch (PDOException $ex) {
                $error = '初始化站点失败：' . $ex->getMessage();
            }
        }

        // 4) 可选：写回 include/config.db.php
        if (!$error && !empty($_POST['write_config'])) {
            $cfg_dir = __DIR__ . '/include';
            $cfg_file = $cfg_dir . '/config.db.php';
            if (!is_dir($cfg_dir)) {
                @mkdir($cfg_dir, 0755, true);
            }
            if ((file_exists($cfg_file) && is_writable($cfg_file)) || (!file_exists($cfg_file) && is_writable($cfg_dir))) {
                $w = @file_put_contents($cfg_file, config_php_src($db_host, $db_port, $db_name, $db_user, $db_pass));
                if ($w !== false) {
                    $config_written = true;
                }
            }
        }

        if (!$error) {
            $success = true;
        }
    }
}

$v = function ($k, $d = '') {
    return isset($_POST[$k]) ? htmlspecialchars(trim((string)$_POST[$k])) : $d;
};
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
.box{background:var(--bg);border-radius:24px;box-shadow:8px 8px 24px var(--sd),-8px -8px 24px var(--sl);padding:40px 36px;width:520px;max-width:95vw}
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
.fg input[type=checkbox]{width:auto;box-shadow:none;accent-color:var(--pri);transform:scale(1.15);margin-right:8px;vertical-align:middle}
.section-title{font-size:.9em;font-weight:700;color:var(--pri);letter-spacing:2px;margin:22px 0 14px;padding-bottom:8px;border-bottom:1px dashed rgba(212,120,110,.35);text-align:left}
.btn{width:100%;padding:13px;background:var(--bg);border:none;border-radius:12px;font-size:1em;font-weight:700;color:var(--pri);box-shadow:4px 4px 12px var(--sd),-4px -4px 12px var(--sl);cursor:pointer;transition:all .2s;letter-spacing:2px;text-align:center;display:block;text-decoration:none}
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
.hint{font-size:.75em;color:var(--tl);line-height:1.7;margin-top:14px;text-align:left}
</style>
</head>
<body>
<div class="box">
<div class="icon">💕</div>
<h2>情侣小窝 · 安装向导</h2>
<div class="steps">
<div class="step"><div class="step-num <?php echo $step >= 1 ? ($success ? 'done' : 'active') : ''; ?>">1</div><div class="step-label">环境检测</div></div>
<div class="step"><div class="step-num <?php echo $step >= 2 ? ($success ? 'done' : 'active') : ''; ?>">2</div><div class="step-label">数据库与站点</div></div>
<div class="step"><div class="step-num <?php echo $success ? 'done' : ''; ?>">3</div><div class="step-label">完成</div></div>
</div>

<?php if ($step == 1 && !$success): ?>
<?php
$checks = [
    ['PHP >= 7.4', check_php_version(), PHP_VERSION],
    ['pdo_mysql 扩展', check_ext('pdo_mysql'), ''],
    ['mbstring 扩展', check_ext('mbstring'), ''],
    ['json 扩展', check_ext('json'), ''],
    ['fileinfo 扩展', check_ext('fileinfo'), ''],
    ['gd 扩展', check_ext('gd'), ''],
    ['curl 扩展', check_ext('curl'), ''],
];
$dir_checks = [
    ['data/', writable_dir('data')],
    ['uploads/', writable_dir('uploads')],
    ['include/', writable_dir('include')],
];
$all_pass = true;
foreach ($checks as $c) if (!$c[1]) $all_pass = false;
foreach ($dir_checks as $d) if (!$d[1]) $all_pass = false;
?>
<p class="sub">第 1 步：环境检测</p>
<ul class="check-list">
<?php foreach ($checks as $c): ?>
<li><span><?php echo $c[1] ? '<span class="pass">✓</span>' : '<span class="fail">✗</span>'; ?></span><span class="info"><?php echo htmlspecialchars($c[0]); ?></span><?php if ($c[2]): ?><span class="ver"><?php echo htmlspecialchars($c[2]); ?></span><?php endif; ?></li>
<?php endforeach; ?>
</ul>
<p class="sub" style="margin-bottom:10px">目录权限检测</p>
<ul class="check-list">
<?php foreach ($dir_checks as $d): ?>
<li><span><?php echo $d[1] ? '<span class="pass">✓</span>' : '<span class="fail">✗</span>'; ?></span><span class="info"><?php echo htmlspecialchars($d[0]); ?> <?php echo $d[1] ? '可写' : '不可写'; ?></span></li>
<?php endforeach; ?>
</ul>
<?php if ($all_pass): ?>
<div class="msg-box">✅ 环境检测通过，可以继续安装！</div>
<a href="?step=2" class="btn">开始安装 →</a>
<?php else: ?>
<div class="error-box">❌ 部分检测未通过，请先修复以上问题后再继续（PHP 需启用 pdo_mysql 等扩展）。</div>
<?php endif; ?>

<?php elseif ($step == 2 && !$success): ?>
<p class="sub">第 2 步：数据库连接与站点设置</p>
<?php if ($error): ?><div class="error-box"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<form method="post" autocomplete="off">
<div class="section-title">🗄 数据库连接（需已提前建好空数据库）</div>
<div class="fg"><label>数据库地址</label><input type="text" name="db_host" value="<?php echo $v('db_host', 'localhost'); ?>" placeholder="localhost 或 数据库主机地址"></div>
<div style="display:flex;gap:14px">
<div class="fg" style="flex:0 0 110px"><label>端口</label><input type="number" name="db_port" value="<?php echo $v('db_port', '3306'); ?>" placeholder="3306"></div>
<div class="fg" style="flex:1"><label>数据库名</label><input type="text" name="db_name" value="<?php echo $v('db_name'); ?>" placeholder="如 qinglv" required></div>
</div>
<div class="fg"><label>数据库用户名</label><input type="text" name="db_user" value="<?php echo $v('db_user'); ?>" placeholder="数据库账号" required></div>
<div class="fg"><label>数据库密码</label><input type="password" name="db_pass" value="" placeholder="数据库密码"></div>
<div class="section-title">👤 管理员与站点</div>
<div class="fg"><label>管理员账号</label><input type="text" name="username" value="<?php echo $v('username', 'admin'); ?>" placeholder="后台登录账号，至少2字符" required></div>
<div style="display:flex;gap:14px">
<div class="fg" style="flex:1"><label>管理员密码</label><input type="password" name="password" value="" placeholder="至少4位" required></div>
<div class="fg" style="flex:1"><label>确认密码</label><input type="password" name="confirm" value="" placeholder="再次输入" required></div>
</div>
<div style="display:flex;gap:14px">
<div class="fg" style="flex:1"><label>你的名字</label><input type="text" name="name1" value="<?php echo $v('name1', '男神'); ?>" placeholder="如：小鱼" required></div>
<div class="fg" style="flex:1"><label>TA 的名字</label><input type="text" name="name2" value="<?php echo $v('name2', '女神'); ?>" placeholder="如：小可爱" required></div>
</div>
<div style="display:flex;gap:14px">
<div class="fg" style="flex:1"><label>纪念日</label><input type="date" name="love_date" value="<?php echo $v('love_date', date('Y-m-d')); ?>"></div>
<div class="fg" style="flex:1"><label>网站标题（可选）</label><input type="text" name="site_title" value="<?php echo $v('site_title'); ?>" placeholder="默认：名字 ❤ 名字"></div>
</div>
<div class="fg"><label><input type="checkbox" name="write_config" value="1" checked> 安装后自动将连接信息写入 include/config.db.php（推荐）</label></div>
<button type="submit" class="btn">💕 连接数据库并安装</button>
<div class="hint">向导会自动导入根目录 <strong>schema.sql</strong>（幂等建表，12 张 cp_* 表），创建管理员账号并写入初始站点配置。重复执行不会破坏已有数据。</div>
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
<?php if (!$config_written): ?>
<p style="margin-top:12px;font-size:.85em">⚠️ 未能自动写入 <strong>include/config.db.php</strong>（目录不可写），请手动按 README 填写数据库连接信息。</p>
<?php endif; ?>
<p style="margin-top:16px;font-size:.78em;color:var(--tl)">
🔐 请牢记管理员账号密码<br>
⚠️ 建议安装完成后<strong>删除 install.php</strong> 以提高安全性
</p>
</div>
<?php endif; ?>
</div>
</body>
</html>
