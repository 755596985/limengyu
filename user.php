<?php
require __DIR__ . '/include/bootstrap.php';
require_csrf();

$me = $_SESSION['user'] ?? null;
if (($_GET['act'] ?? '') === 'logout') { session_destroy(); header('Location: index.php'); exit; }
if (!$me) { header('Location: login.php'); exit; }

$ROOT = __DIR__;
$UPLOAD_DIR = $ROOT . '/uploads/';

$u = user_by_id($me['id']);
if ($u) {
    $me['created_at'] = $u['created_at'] ?? ($me['created_at'] ?? '');
    $me['avatar'] = $u['avatar'] ?? ($me['avatar'] ?? '');
    $me['nickname'] = $u['nickname'] ?? ($me['nickname'] ?? '');
    $me['avatar_color'] = $u['avatar_color'] ?? ($me['avatar_color'] ?? '#d4786e');
    $_SESSION['user'] = array_merge($_SESSION['user'], [
        'created_at' => $me['created_at'],
        'avatar' => $me['avatar'],
        'nickname' => $me['nickname'],
        'avatar_color' => $me['avatar_color'],
    ]);
}

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'update_nickname') {
        $nn = trim($_POST['nickname'] ?? '');
        if (empty($nn) || mb_strlen($nn) > 20) { $msg = '昵称不能为空且不超过20字！'; }
        else {
            user_update($me['id'], ['nickname' => $nn]);
            $me['nickname'] = $nn;
            $_SESSION['user']['nickname'] = $nn;
            $msg = '昵称更新成功！';
        }
    }
    if ($act === 'upload_avatar') {
        $url = safe_upload_one('avatar', $UPLOAD_DIR, ['jpg','jpeg','png','gif','webp'], ['image/jpeg','image/png','image/gif','image/webp']);
        if ($url) {
            $old = $me['avatar'] ?? '';
            user_update($me['id'], ['avatar' => $url]);
            if ($old && $old !== $url) {
                safe_unlink_under($ROOT, $old);
            }
            $me['avatar'] = $url;
            $_SESSION['user']['avatar'] = $url;
            $msg = '头像更新成功！';
        } else {
            $msg = '头像上传失败，请检查图片格式和大小！';
        }
    }
    if ($act === 'delete_posts') {
        $ids = array_values(array_filter((array)($_POST['post_ids'] ?? [])));
        $deleted = 0;
        foreach ($ids as $pid) {
            if (post_delete_by_id($pid, $me['id'], $ROOT)) $deleted++;
        }
        $msg = $deleted > 0 ? ('已删除 ' . $deleted . ' 条说说！') : '未找到可删除的说说！';
    }
}

$myPosts = posts_by_user($me['id']);
$config = get_config();
$st = ($config['site_title'] ?? '') ?: '情侣小窝';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title>个人中心 · <?php echo htmlspecialchars($st); ?></title>
<link rel="icon" href="data:image/svg+xml,💕">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--pri:#d4786e;--pl:#f0b4ac;--ac:#c7a98c;--tx:#5a4e4a;--tl:#8c7e78;--bg:#f2ede9}
body{font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei',sans-serif;background:var(--bg);color:var(--tx);min-height:100vh;padding-bottom:90px}
.main{max-width:520px;margin:0 auto;padding:16px}
.nc{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.06);padding:20px;margin-bottom:16px}
.nc h2{font-size:1.1em;font-weight:700;color:var(--tx);margin-bottom:16px}
.profile{display:flex;align-items:center;gap:16px;padding:20px;background:linear-gradient(135deg,var(--pl),var(--pri));border-radius:16px;color:#fff;margin-bottom:20px}
.avatar{width:70px;height:70px;border-radius:50%;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,0.2);display:flex;align-items:center;justify-content:center;font-size:2em;background:rgba(255,255,255,0.2);flex-shrink:0}
.avatar img{width:100%;height:100%;object-fit:cover}
.info{flex:1}
.info h3{font-size:1.3em;margin-bottom:4px}
.info p{font-size:.85em;opacity:.9}
.fg{margin-bottom:16px}
.fg label{display:block;font-size:.82em;color:var(--tl);font-weight:600;margin-bottom:6px}
.inp{width:100%;padding:11px 15px;background:var(--bg);border:none;border-radius:10px;font-size:.92em;color:var(--tx);outline:none}
.btn{padding:10px 22px;border:none;border-radius:10px;font-size:.9em;font-weight:700;cursor:pointer;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.06);color:var(--pri);transition:all .2s}
.btn:hover{opacity:.85}
.post-item{display:flex;align-items:flex-start;gap:12px;padding:14px 0;border-bottom:1px solid rgba(0,0,0,.04)}
.post-item:last-child{border-bottom:none}
.post-content{flex:1}
.post-meta{font-size:.75em;color:var(--tl);margin-top:4px}
.check-box{margin-top:6px}
.check-box input{margin-right:6px}
.msg{padding:10px 14px;border-radius:10px;margin-bottom:16px;font-size:.85em;text-align:center}
.ok{background:#e8f5e9;color:#2e7d32}
.back{text-align:center;margin-top:20px}
.back a{color:var(--pri);text-decoration:none;font-size:.9em}
</style>
</head>
<body>
<div class="main">
<div class="profile">
<div class="avatar"><?php if($me['avatar']):?><img src="<?php echo htmlspecialchars($me['avatar']);?>"><?php else:?>👤<?php endif;?></div>
<div class="info">
<h3><?php echo htmlspecialchars($me['nickname']); ?></h3>
<p>@<?php echo htmlspecialchars($me['username']); ?> · 注册于 <?php echo htmlspecialchars(substr($me['created_at']??'',0,10)); ?></p>
</div>
</div>

<?php if ($msg): ?><div class="msg <?php echo strpos($msg,'成功')!==false?'ok':'' ?>"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>

<div class="nc">
<h2>👤 编辑资料</h2>
<form method="post">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="update_nickname">
<div class="fg"><label>昵称</label><input type="text" name="nickname" class="inp" value="<?php echo htmlspecialchars($me['nickname']??''); ?>" maxlength="20"></div>
<button type="submit" class="btn">💾 保存昵称</button>
</form>
</div>

<div class="nc">
<h2>🖼️ 上传头像</h2>
<form method="post" enctype="multipart/form-data">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="upload_avatar">
<div class="fg"><input type="file" name="avatar" accept="image/*"></div>
<button type="submit" class="btn">📷 上传头像</button>
</form>
</div>

<div class="nc">
<h2>💬 我的说说 (<?php echo count($myPosts);?>)</h2>
<?php if (empty($myPosts)): ?><p style="text-align:center;color:var(--tl);padding:30px">还没有发表过说说~</p>
<?php else: ?>
<form method="post" onsubmit="return confirm('确定删除选中的说说？')">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="delete_posts">
<?php foreach($myPosts as $po): ?>
<div class="post-item">
<div class="check-box"><input type="checkbox" name="post_ids[]" value="<?php echo htmlspecialchars($po['id']??''); ?>"></div>
<div class="post-content"><div style="font-weight:600;color:var(--tx)"><?php echo htmlspecialchars($po['mood']??'💕');?> <?php echo htmlspecialchars(mb_substr($po['content'],0,50));?><?php echo mb_strlen($po['content'])>50?'…':'';?></div><div class="post-meta"><?php echo htmlspecialchars($po['time']??''); ?></div></div>
</div>
<?php endforeach; ?>
<button type="submit" class="btn" style="background:#ffebee;color:#c0392b">🗑️ 删除选中</button>
</form>
<?php endif; ?>
</div>

<div class="back"><a href="index.php">← 返回首页</a> · <a href="?act=logout">退出登录</a></div>
</div>
</body>
</html>
