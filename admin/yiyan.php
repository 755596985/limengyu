<?php
require __DIR__ . '/../include/bootstrap.php';
if (empty($_SESSION['cp_admin'])) { header('Location: index.php'); exit; }

$configFile = __DIR__ . '/../data/yiyan_config.json';
$message = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $apiUrl = trim($_POST['api_url'] ?? '');
    if (empty($apiUrl)) {
        $apiUrl = '/api.php';
    }
    file_put_contents($configFile, json_encode(['api_url' => $apiUrl], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $message = 'API 地址已保存！';
}

$currentApiUrl = '/api.php';
if (file_exists($configFile)) {
    $cfg = json_decode(file_get_contents($configFile), true) ?: [];
    $currentApiUrl = $cfg['api_url'] ?? '/api.php';
}

$config = get_config();
$st = ($config['site_title'] ?? '') ?: '情侣小窝';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>一言管理 · <?php echo htmlspecialchars($st); ?></title>
<style>
:root{--bg:#f2ede9;--pri:#d4786e;--tx:#5a4e4a;--tl:#8c7e78}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei',sans-serif;background:#f2ede9;color:var(--tx);min-height:100vh}
.main{padding:16px 14px 90px;max-width:900px;margin:0 auto}
.card{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.06);padding:24px;margin-bottom:20px}
.card-title{font-size:1.05em;font-weight:700;color:var(--tx);margin-bottom:18px}
.msg{padding:12px 18px;border-radius:10px;margin-bottom:16px;font-size:.88em}
.msg.success{background:#e8f5e9;color:#2e7d32}
.msg.error{background:#ffebee;color:#c62828}
.fg{margin-bottom:16px}
.fg label{display:block;font-size:.82em;color:var(--tl);font-weight:600;margin-bottom:6px}
.neo{width:100%;padding:11px 15px;background:#f5f1ee;border:none;border-radius:10px;box-shadow:inset 2px 2px 6px rgba(0,0,0,0.04);font-size:.92em;color:var(--tx);outline:none;font-family:inherit}
.neo:focus{box-shadow:inset 1px 1px 4px rgba(0,0,0,0.06)}
.btn{padding:10px 22px;border:none;border-radius:10px;font-size:.9em;font-weight:700;cursor:pointer;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.06);color:var(--tx);transition:all .2s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.btn:active{transform:scale(.97)}
.btn.primary{color:var(--pri)}
.btn-group{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}


@media(max-width:768px){.main{padding:12px 10px 80px}.card{padding:16px;border-radius:12px}}
@media(max-width:600px){.card-title{font-size:.95em}.btn{font-size:.82em;padding:8px 16px}}
</style>
</head>
<body>
<div class="main">
<?php if ($message): ?><div class="msg success">✅ <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="msg error">❌ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<div class="card">
<div class="card-title">💭 一言管理</div>
<form method="post">
<?php echo csrf_field(); ?>
<div class="fg">
<label>🔗 API 地址</label>
<input type="text" name="api_url" class="neo" value="<?php echo htmlspecialchars($currentApiUrl); ?>" placeholder="/api.php">
</div>
<div class="fg" style="font-size:.82em;color:var(--tl)">默认使用内置的 /api.php，也可以填入任意兼容的一言 API 地址。</div>
<div class="btn-group">
<button type="submit" class="btn primary">💾 保存设置</button>
<a href="manage.php" class="btn">← 返回后台</a>
</div>
</form>
</div>
</div>

</body>
</html>
