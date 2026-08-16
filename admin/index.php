<?php
require __DIR__ . '/../include/bootstrap.php';
require_csrf();

if (isset($_SESSION['cp_admin'])) {
    header('Location: manage.php');
    exit;
}

$saved = admin_get();

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input_user = trim($_POST['username'] ?? '');
    $input_pass = $_POST['password'] ?? '';

    if (empty($input_user) || empty($input_pass)) {
        $error = '请输入账号和密码！';
    } else {
        $saved = admin_get();
        if (($saved['username'] ?? 'admin') === $input_user && password_verify($input_pass, $saved['password'] ?? '')) {
            session_regenerate_id(true);
            $_SESSION['cp_admin'] = true; $_SESSION['user'] = ['id' => 'admin', 'username' => 'admin', 'nickname' => '管理员', 'avatar_color' => '#4a90d9'];
            header('Location: manage.php');
            exit;
        }
        $error = '账号或密码错误！';
    }
}
$config = get_config();
$st = ($config['site_title'] ?? '') ?: '情侣小窝';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>后台 · <?php echo htmlspecialchars($st); ?></title>
<style>
:root{--bg:#f7f0e6;--sd:rgba(166,156,148,0.5);--sl:rgba(255,255,255,0.8);--pri:#d4786e;--tx:#5a4e4a;--tl:#8c7e78;--card:#f7f0e6;--err:#ffeaea;--errtx:#c0392b}
[data-theme="dark"]{--bg:#1f1b18;--sd:rgba(0,0,0,0.55);--sl:rgba(255,255,255,0.05);--pri:#ec9d94;--tx:#ece5df;--tl:#a89a92;--card:#2b2522;--err:#45272b;--errtx:#ee8d9b}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei',sans-serif;background:var(--bg);min-height:100vh;display:flex;align-items:center;justify-content:center}
.login-box{background:var(--card);border-radius:24px;box-shadow:8px 8px 24px var(--sd),-8px -8px 24px var(--sl);padding:40px 36px;width:360px;max-width:90vw;text-align:center}
.login-box .lock{width:80px;height:80px;border-radius:50%;box-shadow:6px 6px 16px var(--sd),-6px -6px 16px var(--sl);display:inline-flex;align-items:center;justify-content:center;font-size:2.2em;margin-bottom:20px}
.login-box h2{font-size:1.4em;color:var(--tx);letter-spacing:2px;margin-bottom:6px}
.login-box .sub{font-size:.85em;color:var(--tl);margin-bottom:28px}
.fg{margin-bottom:18px;text-align:left}
.fg label{display:block;font-size:.82em;color:var(--tl);margin-bottom:8px;font-weight:600;letter-spacing:1px}
.fg input{width:100%;padding:13px 16px;background:var(--card);border:none;border-radius:12px;font-size:1em;color:var(--tx);box-shadow:inset 4px 4px 10px var(--sd),inset -4px -4px 10px var(--sl);outline:none;font-family:inherit}
.btn{width:100%;padding:13px;background:var(--card);border:none;border-radius:12px;font-size:1em;font-weight:700;color:var(--pri);box-shadow:4px 4px 12px var(--sd),-4px -4px 12px var(--sl);cursor:pointer;transition:all .2s;letter-spacing:2px}
.btn:active{box-shadow:inset 4px 4px 10px var(--sd),inset -4px -4px 10px var(--sl);transform:scale(.97)}
.error{background:var(--err);color:var(--errtx);padding:10px;border-radius:10px;margin-bottom:14px;font-size:.85em}
.back{display:block;margin-top:18px;color:var(--tl);text-decoration:none;font-size:.85em}
</style>
</head>
<body>
<button id="themeToggle" onclick="toggleTheme()" title="切换奶白/黑夜模式" style="position:fixed;top:14px;right:14px;z-index:300;width:34px;height:34px;border-radius:50%;border:none;cursor:pointer;background:var(--card);box-shadow:0 2px 8px rgba(0,0,0,.12);font-size:1.05em;display:flex;align-items:center;justify-content:center;transition:transform .2s">🌙</button>
<div class="login-box">
<div class="lock">🔐</div><h2>后台管理</h2><p class="sub"><?php echo htmlspecialchars($st); ?> · 管理后台</p>
<?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<form method="post" autocomplete="off">
<?php echo csrf_field(); ?>
<div class="fg"><label>👤 管理账号</label><input type="text" name="username" placeholder="请输入账号" required autofocus autocomplete="off"></div>
<div class="fg"><label>🔑 管理密码</label><input type="password" name="password" placeholder="请输入密码" required autocomplete="off"></div>
<button type="submit" class="btn">登 录</button></form>
<a href="../" class="back">← 返回首页</a>
<p style="margin-top:16px;font-size:.72em;color:var(--tl)">首次登录后请及时修改默认密码</p>
</div>
<script>
// 主题切换（奶白/黑夜，与前台共用 localStorage）
(function(){
    var KEY='site_theme';
    function apply(t){
        document.documentElement.setAttribute('data-theme',t);
        var b=document.getElementById('themeToggle');
        if(b) b.textContent = (t==='dark') ? '☀️' : '🌙';
    }
    var saved=localStorage.getItem(KEY);
    apply(saved==='dark' ? 'dark' : 'milk');
    window.toggleTheme=function(){
        var cur=document.documentElement.getAttribute('data-theme');
        var next=(cur==='dark') ? 'milk' : 'dark';
        localStorage.setItem(KEY,next);
        apply(next);
    };
})();
</script>
</body></html>