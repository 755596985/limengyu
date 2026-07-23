<?php
require __DIR__ . '/include/bootstrap.php';
require_csrf();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $nickname = trim($_POST['nickname'] ?? '');

    if (empty($username) || empty($password) || empty($password2)) {
        $error = '请填写完整信息！';
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = '用户名需要 3-20 个字符！';
    } elseif (!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]+$/u', $username)) {
        $error = '用户名只能包含字母、数字、下划线和中文！';
    } elseif (strlen($password) < 6) {
        $error = '密码至少 6 位！';
    } elseif ($password !== $password2) {
        $error = '两次密码不一致！';
    } elseif (user_by_username($username)) {
        $error = '该用户名已被注册！';
    } else {
        $avatar_colors = ['#d4786e','#5c9ce6','#4da6ff','#e8553d','#5cb85c','#9b59b6','#e67e22','#1abc9c','#3498db','#e74c3c'];
        user_insert([
            'id' => new_id(),
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'nickname' => $nickname ?: $username,
            'avatar' => '',
            'avatar_color' => $avatar_colors[array_rand($avatar_colors)],
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $success = '注册成功！正在跳转到登录页...';
        header('refresh:2;url=login.php');
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
<title>注册 · <?php echo htmlspecialchars($st); ?></title>
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
.ok{background:#e8f5e9;color:#2e7d32;padding:10px 14px;border-radius:10px;margin-bottom:16px;font-size:.85em;text-align:center}
.link{text-align:center;margin-top:18px}
.link a{color:var(--pri);text-decoration:none;font-size:.85em}
.tips{text-align:center;margin-top:12px;font-size:.72em;color:var(--tl)}
</style>
</head>
<body>
<div class="box">
<h2>📝 注册账号</h2>
<p class="sub">加入 <?php echo htmlspecialchars($st); ?></p>

<?php if ($error): ?><div class="err">❌ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($success): ?><div class="ok">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>

<form method="post" autocomplete="off">
<?php echo csrf_field(); ?>
<div class="fg"><label>👤 用户名</label><input type="text" name="username" class="inp" placeholder="3-20位，支持中英文" required maxlength="20" value="<?php echo htmlspecialchars($_POST['username']??''); ?>"></div>
<div class="fg"><label>🔑 密码</label><input type="password" name="password" class="inp" placeholder="至少6位" required minlength="6"></div>
<div class="fg"><label>🔑 确认密码</label><input type="password" name="password2" class="inp" placeholder="再次输入密码" required minlength="6"></div>
<div class="fg"><label>😊 昵称 <span style="font-weight:400;color:var(--tl)">(可选)</span></label><input type="text" name="nickname" class="inp" placeholder="展示名称" maxlength="20" value="<?php echo htmlspecialchars($_POST['nickname']??''); ?>"></div>
<button type="submit" class="btn">注 册</button>
</form>

<div class="link">已有账号？<a href="login.php">去登录 →</a></div>
<div class="tips">注册即表示同意遵守社区规范</div>
</div>
</body>
</html>
