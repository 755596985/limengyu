<?php
require __DIR__ . '/include/bootstrap.php';
require_csrf();

$me = $_SESSION['user'] ?? (isset($_SESSION['cp_admin']) ? ['id' => 'admin', 'nickname' => '管理员', 'avatar_color' => '#4a90d9'] : null);
if (($_GET['act'] ?? '') === 'logout') { session_destroy(); header('Location: index.php'); exit; }
if (!$me) { header('Location: login.php'); exit; }

$ROOT = __DIR__;
$UPLOAD_DIR = $ROOT . '/uploads/';

// Sync current user session data
$u = user_by_id($me['id']);
if ($u) {
    $me['created_at'] = $u['created_at'] ?? ($me['created_at'] ?? '');
    $me['avatar'] = $u['avatar'] ?? ($me['avatar'] ?? '');
    $me['nickname'] = $u['nickname'] ?? ($me['nickname'] ?? '');
    $me['avatar_color'] = $u['avatar_color'] ?? ($me['avatar_color'] ?? '#d4786e');
    $me['username'] = $u['username'] ?? ($me['username'] ?? '');
    $_SESSION['user'] = array_merge($_SESSION['user'] ?? [], [
        'id' => $me['id'],
        'created_at' => $me['created_at'],
        'avatar' => $me['avatar'],
        'nickname' => $me['nickname'],
        'username' => $me['username'],
        'avatar_color' => $me['avatar_color'],
    ]);
}

// --- Determine which user we're viewing ---
$targetId = $_GET['id'] ?? $me['id'];
$profileUser = user_by_id($targetId);
if (!$profileUser) {
    header('Location: index.php');
    exit;
}
$isOwner = ($profileUser['id'] === $me['id']);

// --- Handle POST actions ---
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'update_nickname' && $isOwner) {
        $nn = trim($_POST['nickname'] ?? '');
        if (empty($nn) || mb_strlen($nn) > 20) { $msg = '昵称不能为空且不超过20字！'; }
        else {
            user_update($me['id'], ['nickname' => $nn]);
            $me['nickname'] = $nn;
            $profileUser['nickname'] = $nn;
            $_SESSION['user']['nickname'] = $nn;
            $msg = '昵称更新成功！';
        }
    }

    if ($act === 'upload_avatar' && $isOwner) {
        $url = safe_upload_one('avatar', $UPLOAD_DIR, ['jpg','jpeg','png','gif','webp'], ['image/jpeg','image/png','image/gif','image/webp']);
        if ($url) {
            $old = $me['avatar'] ?? '';
            user_update($me['id'], ['avatar' => $url]);
            if ($old && $old !== $url) {
                safe_unlink_under($ROOT, $old);
            }
            $me['avatar'] = $url;
            $profileUser['avatar'] = $url;
            $_SESSION['user']['avatar'] = $url;
            $msg = '头像更新成功！';
        } else {
            $msg = '头像上传失败，请检查图片格式和大小！';
        }
    }

    if ($act === 'change_password' && $isOwner) {
        $oldPwd = $_POST['old_password'] ?? '';
        $newPwd = $_POST['new_password'] ?? '';
        $newPwd2 = $_POST['new_password2'] ?? '';
        if (empty($oldPwd) || empty($newPwd)) {
            $msg = '请填写所有密码字段！';
        } elseif (!password_verify($oldPwd, $u['password'] ?? '')) {
            $msg = '旧密码不正确！';
        } elseif ($newPwd !== $newPwd2) {
            $msg = '两次新密码不一致！';
        } elseif (mb_strlen($newPwd) < 6) {
            $msg = '新密码至少6位！';
        } else {
            user_update($me['id'], ['password' => password_hash($newPwd, PASSWORD_DEFAULT)]);
            $msg = '密码修改成功！';
        }
    }

    if ($act === 'delete_posts' && $isOwner) {
        $ids = array_values(array_filter((array)($_POST['post_ids'] ?? [])));
        $deleted = 0;
        foreach ($ids as $pid) {
            if (post_delete_by_id($pid, $me['id'], $ROOT)) $deleted++;
        }
        $msg = $deleted > 0 ? ('已删除 ' . $deleted . ' 条说说！') : '未找到可删除的说说！';
    }
}

$profilePosts = posts_by_user($profileUser['id']);
$config = get_config();
$st = ($config['site_title'] ?? '') ?: '情侣小窝';

// Prepare profile display variables
$pAv = $profileUser['avatar'] ?? '';
$pNick = htmlspecialchars($profileUser['nickname'] ?? $profileUser['username']);
$pUname = htmlspecialchars($profileUser['username'] ?? '');
$pTime = htmlspecialchars(substr($profileUser['created_at'] ?? '', 0, 10));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title><?php echo $pNick; ?> · <?php echo htmlspecialchars($st); ?></title>
<link rel="icon" href="data:image/svg+xml,💕">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--pri:#d4786e;--pl:#f0b4ac;--ac:#c7a98c;--tx:#5a4e4a;--tl:#8c7e78;--bg:#f2ede9;--err:#e74c3c;--ok:#27ae60}
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
.inp:focus{box-shadow:0 0 0 2px var(--pri)}
.btn{padding:10px 22px;border:none;border-radius:10px;font-size:.9em;font-weight:700;cursor:pointer;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.06);color:var(--pri);transition:all .2s}
.btn:hover{opacity:.85}
.btn-danger{color:#fff;background:var(--err)}
.btn-ok{color:#fff;background:var(--ok)}
.post-item{display:flex;align-items:flex-start;gap:12px;padding:14px 0;border-bottom:1px solid rgba(0,0,0,.04)}
.post-item:last-child{border-bottom:none}
.post-content{flex:1}
.post-meta{font-size:.75em;color:var(--tl);margin-top:4px}
.check-box{margin-top:6px}
.check-box input{margin-right:6px}
.msg{padding:10px 14px;border-radius:10px;margin-bottom:16px;font-size:.85em;text-align:center}
.ok-msg{background:#e8f5e9;color:var(--ok)}
.err-msg{background:#ffebee;color:var(--err)}
.pwd-box{display:none;margin-top:12px}
.pwd-box.show{display:block}
.back{text-align:center;margin-top:20px}
.back a{color:var(--pri);text-decoration:none;font-size:.9em}
.toggle-link{cursor:pointer;color:var(--pri);font-size:.9em;text-decoration:underline;margin-top:8px;display:inline-block}
</style>
</head>
<body>
<div class="main">
<div class="profile">
<div class="avatar"><?php if($pAv):?><img src="<?php echo htmlspecialchars($pAv);?>"><?php else:?>👤<?php endif;?></div>
<div class="info">
<h3><?php echo $pNick; ?></h3>
<p>@<?php echo $pUname; ?> · 注册于 <?php echo $pTime; ?></p>
</div>
</div>

<?php if ($msg): $msgClass = (strpos($msg,'成功')!==false) ? 'ok-msg' : 'err-msg'; ?>
<div class="msg <?php echo $msgClass; ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<?php if ($isOwner): ?>
<!-- === OWN PROFILE === -->
<div class="nc">
<h2>👤 编辑资料</h2>
<form method="post">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="update_nickname">
<div class="fg"><label>昵称</label><input type="text" name="nickname" class="inp" value="<?php echo htmlspecialchars($me['nickname']??''); ?>" maxlength="20"></div>
<button type="submit" class="btn">保存昵称</button>
</form>
</div>

<div class="nc">
<h2>🖼️ 上传头像</h2>
<form method="post" enctype="multipart/form-data">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="upload_avatar">
<div class="fg"><input type="file" name="avatar" accept="image/*"></div>
<button type="submit" class="btn">上传头像</button>
</form>
</div>

<div class="nc">
<h2>🔒 修改密码</h2>
<span class="toggle-link" onclick="document.getElementById('pwdForm').classList.toggle('show')">展开修改</span>
<div class="pwd-box" id="pwdForm">
<form method="post">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="change_password">
<div class="fg"><label>旧密码</label><input type="password" name="old_password" class="inp" required></div>
<div class="fg"><label>新密码（至少6位）</label><input type="password" name="new_password" class="inp" required minlength="6"></div>
<div class="fg"><label>确认新密码</label><input type="password" name="new_password2" class="inp" required minlength="6"></div>
<button type="submit" class="btn btn-ok">修改密码</button>
</form>
</div>
</div>

<div class="nc">
<h2>💬 我的说说 (<?php echo count($profilePosts);?>)</h2>
<?php if (empty($profilePosts)): ?><p style="text-align:center;color:var(--tl);padding:30px">还没有发表过说说~</p>
<?php else: ?>
<form method="post" onsubmit="return confirm('确定删除选中的说说？')">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="delete_posts">
<?php foreach($profilePosts as $po): ?>
<div class="post-item">
<div class="check-box"><input type="checkbox" name="post_ids[]" value="<?php echo htmlspecialchars($po['id']??''); ?>"></div>
<div class="post-content"><div style="font-weight:600;color:var(--tx)"><?php echo htmlspecialchars($po['mood']??'💕');?> <?php echo htmlspecialchars(mb_substr($po['content'],0,50));?><?php echo mb_strlen($po['content'])>50?'…':'';?></div><div class="post-meta"><?php echo htmlspecialchars($po['time']??''); ?></div></div>
</div>
<?php endforeach; ?>
<button type="submit" class="btn btn-danger">删除选中</button>
</form>
<?php endif; ?>
</div>

<?php else: ?>
<!-- === OTHER USER'S PROFILE === -->
<div class="nc">
<h2>💬 <?php echo $pNick; ?> 的说说 (<?php echo count($profilePosts);?>)</h2>
<?php if (empty($profilePosts)): ?><p style="text-align:center;color:var(--tl);padding:30px">TA还没有发表过说说~</p>
<?php else: ?>
<?php foreach($profilePosts as $po): ?>
<div class="post-item">
<div style="font-size:1.2em;margin-right:8px"><?php echo htmlspecialchars($po['mood']??'💕');?></div>
<div class="post-content">
<div style="color:var(--tx)"><?php echo htmlspecialchars(mb_substr($po['content'],0,80));?><?php echo mb_strlen($po['content'])>80?'…':'';?></div>
<div class="post-meta"><?php echo htmlspecialchars($po['time']??''); ?></div>
</div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>
<?php endif; ?>

<div class="back"><a href="index.php">← 返回首页</a><?php if($isOwner): ?> · <a href="?act=logout">退出登录</a><?php endif; ?></div>
</div>
</body>
</html>