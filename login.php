<?php
require __DIR__ . '/include/bootstrap.php';
require_csrf();

if (isset($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$is_admin_login = isset($_GET['admin']) && $_GET['admin'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = '请填写用户名和密码！';
    } elseif ($is_admin_login) {
        $admin_saved = admin_get();
        if ($admin_saved && ($admin_saved['username'] ?? 'admin') === $username && password_verify($password, $admin_saved['password'] ?? '')) {
            session_regenerate_id(true);
            $_SESSION['cp_admin'] = true;
            header('Location: admin/manage.php');
            exit;
        } else {
            $error = '管理员账号或密码错误！';
        }
    } else {
        $found = user_by_username($username);
        if (!$found) {
            $error = '用户名不存在！';
        } elseif (!password_verify($password, $found['password'] ?? '')) {
            $error = '密码错误！';
        } else {
            session_regenerate_id(true);
            $_SESSION['user'] = [
                'id' => $found['id'],
                'username' => $found['username'],
                'nickname' => $found['nickname'] ?? $found['username'],
                'avatar' => $found['avatar'] ?? '',
                'avatar_color' => $found['avatar_color'] ?? '#d4786e',
                'created_at' => $found['created_at'] ?? '',
            ];
            header('Location: index.php');
            exit;
        }
    }
}

$config = get_config();
$st = ($config['site_title'] ?? '') ?: '情侣小窝';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>登录 · <?php echo htmlspecialchars($st); ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--pri:#d4786e;--pl:#f0b4ac;--tx:#5a4e4a;--tl:#8c7e78;--bg:#f2ede9}
body{font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei',sans-serif;background:var(--bg);color:var(--tx);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{background:#fff;border-radius:24px;box-shadow:8px 8px 24px rgba(166,156,148,0.3),-8px -8px 24px rgba(255,255,255,0.8);padding:40px 32px;width:380px;max-width:90vw}
.box h2{text-align:center;font-size:1.4em;color:var(--tx);margin-bottom:6px;letter-spacing:2px}
.box .sub{text-align:center;font-size:.85em;color:var(--tl);margin-bottom:28px}
.fg{margin-bottom:16px}
.fg label{display:block;font-size:.82em;color:var(--tl);font-weight:600;margin-bottom:6px;letter-spacing:1px}
.inp{width:100%;padding:13px 16px;background:var(--bg);border:none;border-radius:12px;font-size:1em;color:var(--tx);box-shadow:inset 4px 4px 10px rgba(166,156,148,0.25),inset -4px -4px 10px rgba(255,255,255,0.7);outline:none;font-family:inherit;transition:all .2s}
.inp:focus{box-shadow:inset 2px 2px 8px rgba(166,156,148,0.2),inset -2px -2px 8px rgba(255,255,255,0.8)}
.btn{width:100%;padding:13px;border:none;border-radius:12px;font-size:1em;font-weight:700;cursor:pointer;background:var(--bg);box-shadow:4px 4px 12px rgba(166,156,148,0.3),-4px -4px 12px rgba(255,255,255,0.7);color:var(--pri);transition:all .2s;letter-spacing:2px}
.btn:active{box-shadow:inset 4px 4px 10px rgba(166,156,148,0.25),inset -4px -4px 10px rgba(255,255,255,0.7);transform:scale(.97)}
.err{background:#ffeaea;color:#c0392b;padding:10px 14px;border-radius:10px;margin-bottom:16px;font-size:.85em;text-align:center}
.link{text-align:center;margin-top:18px}
.link a{color:var(--pri);text-decoration:none;font-size:.85em}
</style>
</head>
<body>
<div class="box">
<?php if ($is_admin_login): ?>
<h2>🔐 管理员登录</h2>
<p class="sub">后台管理系统</p>
<?php else: ?>
<h2>🔐 登录</h2>
<p class="sub">欢迎回到 <?php echo htmlspecialchars($st); ?></p>
<?php endif; ?>

<?php if ($error): ?><div class="err">❌ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<form method="post" autocomplete="off">
<?php echo csrf_field(); ?>
<div class="fg"><label>👤 <?php echo $is_admin_login ? '管理员账号' : '用户名'; ?></label><input type="text" name="username" class="inp" placeholder="<?php echo $is_admin_login ? '请输入管理员账号' : '请输入用户名'; ?>" required autofocus></div>
<div class="fg"><label>🔑 密码</label><input type="password" name="password" class="inp" placeholder="请输入密码" required></div>
<button type="submit" class="btn">登 录</button>
</form>

<?php if ($is_admin_login): ?>
<div class="link"><a href="login.php">← 返回用户登录</a></div>
<?php else: ?>
<div class="link">还没有账号？<a href="register.php">去注册 →</a></div>
<div class="link"><a href="login.php?admin=1" style="color:var(--tl);font-size:.75em">管理员登录</a></div>
<?php endif; ?>
<div class="link"><a href="index.php">← 返回首页</a></div>
</div>
</body>
</html>
