<?php
/**
 * 模块：密码 (password)
 * 功能：change_password
 * 双模式文件：handle=POST 处理，render=页面渲染
 */
if (($MOD_RUN ?? '') === 'handle') {
    if ($act === 'change_password') {
        $old = $_POST['old_password'] ?? ''; $new = $_POST['new_password'] ?? ''; $new_user = trim($_POST['new_username'] ?? '');
        $saved = admin_get();
        if (!$saved || !password_verify($old, $saved['password']??'')) $error='原密码错误！';
        elseif ($new && strlen($new)<4) $error='新密码至少4位！';
        elseif ($new && $new !== ($_POST['confirm_password']??'')) $error='两次密码不一致！';
        elseif ($new_user && strlen($new_user)<2) $error='账号至少2个字符！';
        else {
            $final_user = $new_user ?: ($saved['username'] ?? 'admin');
            $hash = $new ? password_hash($new, PASSWORD_DEFAULT) : null;
            admin_save($final_user, $hash);
            $message = '账号密码修改成功！';
        }
    }
    // === 文件管理 ===

    return;
}
if (($MOD_RUN ?? '') === 'render') {
?>
<?php if ($tab === 'password'): ?>
<div class="card"><div class="card-title">🔑 管理员设置</div>
<form method="post"><?php echo csrf_field(); ?><input type="hidden" name="act" value="change_password">
<div class="fg"><label>👤 新账号（留空不修改）</label><input type="text" name="new_username" class="neo" placeholder="当前: <?php echo htmlspecialchars($admin_saved['username'] ?? 'admin');?>"></div>
<div style="margin:8px 0;height:1px;background:rgba(0,0,0,.05)"></div>
<div class="fg"><label>🔑 原密码（必填）</label><input type="password" name="old_password" class="neo" required></div>
<div class="fg"><label>🆕 新密码（留空不修改）</label><input type="password" name="new_password" class="neo" minlength="4"></div>
<div class="fg"><label>🔄 确认新密码</label><input type="password" name="confirm_password" class="neo" minlength="4"></div>
<div class="btn-group"><button type="submit" class="btn primary">💾 保存</button></div></form></div>
<?php endif; /* password */ ?>
<?php
    return;
}
