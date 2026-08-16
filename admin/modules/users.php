<?php
/**
 * 模块：用户 (users)
 * 功能：delete_user, user_edit, user_refresh_ip, user_toggle_status
 * 双模式文件：handle=POST 处理，render=页面渲染
 */
if (($MOD_RUN ?? '') === 'handle') {
    if ($act === 'delete_user') {
        $idx = intval($_POST['id'] ?? -1);
        $allUsers = users_all();
        if (isset($allUsers[$idx])) {
            $uid = $allUsers[$idx]['id'];
            $userPosts = posts_by_user($uid);
            foreach ($userPosts as $up) {
                post_delete_by_id($up['id'], $uid, $ROOT);
            }
            if (!empty($allUsers[$idx]['avatar'])) {
                safe_unlink_under($ROOT, $allUsers[$idx]['avatar']);
            }
            if (user_delete_by_id($uid)) {
                $message = '用户及其说说已删除！';
            }
        }
    }
    if ($act === 'user_edit') {
        $uid = $_POST['uid'] ?? '';
        $fields = [];
        if (isset($_POST['nickname'])) $fields['nickname'] = trim($_POST['nickname']);
        if (isset($_POST['email'])) $fields['email'] = trim($_POST['email']);
        if (isset($_POST['avatar_color'])) $fields['avatar_color'] = trim($_POST['avatar_color']);
        if (isset($_POST['status'])) $fields['status'] = in_array($_POST['status'], ['active','inactive','banned']) ? $_POST['status'] : 'active';
        // 头像上传
        $avatar = handle_single_upload_db('user_avatar', $UPLOAD_DIR, $IMG_EXT, $IMG_MIME);
        if ($avatar) {
            $oldUser = user_by_id($uid);
            if ($oldUser && !empty($oldUser['avatar'])) {
                safe_unlink_under($ROOT, $oldUser['avatar']);
            }
            $fields['avatar'] = $avatar;
        }
        // 密码修改
        $newPassword = trim($_POST['password'] ?? '');
        if ($newPassword !== '') {
            $fields['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        if (!empty($fields) && $uid) {
            user_update($uid, $fields);
            $message = '用户信息已更新！';
        }
    }
    if ($act === 'user_refresh_ip') {
        $uid = $_POST['uid'] ?? '';
        $u = $uid ? user_by_id($uid) : null;
        if ($u) {
            $ip = $u['ip'];
            $loc = resolve_location($ip);
            user_update($uid, ['location' => $loc]);
            $message = 'IP归属地已刷新！';
        }
    }
    if ($act === 'user_toggle_status') {
        $uid = $_POST['uid'] ?? '';
        $u = $uid ? user_by_id($uid) : null;
        if ($u) {
            $next = ($u['status'] === 'active') ? 'inactive' : (($u['status'] === 'inactive') ? 'banned' : 'active');
            user_update($uid, ['status' => $next]);
            $statusLabel = ['active'=>'正常','inactive'=>'停用','banned'=>'封禁'];
            $message = '用户状态已切换为：' . ($statusLabel[$next] ?? $next);
        }
    }

    return;
}
if (($MOD_RUN ?? '') === 'render') {
?>
<?php if ($tab === 'users'): ?>
<div class="card"><div class="card-title">👥 用户管理 (<?php echo count($users);?>人)</div>
<?php if(empty($users)):?><p style="text-align:center;color:var(--tl);padding:30px">暂无注册用户</p>
<?php else: ?>
<div class="user-table-wrap">
<table class="user-table">
<thead><tr><th>用户信息</th><th>IP</th><th>归属地</th><th>注册时间</th><th>状态</th><th>操作</th></tr></thead>
<tbody>
<?php foreach($users as $i=>$u): 
$statusLabel = ['active'=>'正常','inactive'=>'停用','banned'=>'封禁'];
$statusClass = ['active'=>'badge-green','inactive'=>'badge-yellow','banned'=>'badge-red'];
$s = $u['status'] ?? 'active';
$avatarColor = $u['avatar_color'] ?? '#d4786e';
$nick = htmlspecialchars($u['nickname'] ?? $u['username']);
$uname = htmlspecialchars($u['username']);
$uid = $u['id'];
$ip = htmlspecialchars($u['ip'] ?? '—');
$loc = htmlspecialchars($u['location'] ?? '—');
$email = htmlspecialchars($u['email'] ?? '');
$time = htmlspecialchars($u['created_at'] ?? '—');
?>
<tr>
<td data-label="用户信息"><?php if(!empty($u['avatar'])):?><img src="../<?php echo htmlspecialchars($u['avatar']);?>" style="width:22px;height:22px;border-radius:50%;object-fit:cover;margin-right:8px;vertical-align:middle;box-shadow:0 2px 6px rgba(0,0,0,.12);flex-shrink:0"><?php else:?><span class="avatar-dot" style="background:<?php echo $avatarColor;?>"></span><?php endif;?><span><?php echo $nick;?> <span class="user-at">@<?php echo $uname;?></span></span></td>
<td data-label="IP"><?php echo $ip;?></td>
<td data-label="归属地"><?php echo $loc;?></td>
<td data-label="注册时间"><?php echo $time;?></td>
<td data-label="状态"><span class="status-badge <?php echo $statusClass[$s];?>"><?php echo $statusLabel[$s];?></span></td>
<td data-label="操作">
<div class="action-btns">
<button type="button" class="btn small" onclick="editUser('<?php echo $uid;?>')" title="编辑">✏️</button>
<form method="post" style="display:inline"><?php echo csrf_field();?><input type="hidden" name="act" value="user_refresh_ip"><input type="hidden" name="uid" value="<?php echo $uid;?>"><button type="submit" class="btn small" title="刷新IP">🔄</button></form>
<form method="post" onsubmit="return confirm('确定删除该用户吗？删除后该用户的所有数据也会丢失。')" style="display:inline"><?php echo csrf_field();?><input type="hidden" name="act" value="delete_user"><input type="hidden" name="id" value="<?php echo $i;?>"><button type="submit" class="btn small danger" title="删除">🗑️</button></form>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div><?php endif;?></div>

<!-- 用户编辑弹窗 -->
<div class="modal-overlay" id="userModal" style="display:none">
<div class="modal-box">
<div class="modal-header"><span class="modal-title">✏️ 编辑用户</span><button type="button" class="modal-close" onclick="closeUserModal()">✕</button></div>
<form method="post" id="userEditForm" enctype="multipart/form-data">
<?php echo csrf_field();?>
<input type="hidden" name="act" value="user_edit">
<input type="hidden" name="uid" id="edit_uid">
<div class="fg"><label>👤 昵称</label><input type="text" name="nickname" id="edit_nickname" class="neo" placeholder="用户昵称" required></div>
<div class="fg"><label>📧 邮箱</label><input type="email" name="email" id="edit_email" class="neo" placeholder="email@example.com"></div>
<div class="fg"><label>🖼️ 头像</label>
<div style="margin-bottom:8px"><img id="edit_avatar_preview" src="" style="width:60px;height:60px;border-radius:50%;object-fit:cover;display:none;box-shadow:0 2px 8px rgba(0,0,0,.15)"></div>
<input type="file" name="user_avatar" accept="image/*" style="font-size:.82em">
<div style="font-size:.7em;color:var(--tl);margin-top:2px">不选则保持原头像</div></div>
<div class="fg"><label>🔒 新密码</label><input type="password" name="password" id="edit_password" class="neo" placeholder="留空则不修改密码" autocomplete="new-password"></div>
<div class="fg"><label>🎨 头像颜色</label>
<div class="color-picker" id="colorPicker">
<?php $colors=['#d4786e','#e8857c','#f0a89e','#e8618c','#c06a9e','#9b6db5','#7e8cc4','#5b9ecf','#4cb8b0','#5cb85c','#9acd32','#f0ad4e','#e67e22','#95a5a6']; foreach($colors as $c):?>
<label class="color-dot" style="background:<?php echo $c;?>" data-color="<?php echo $c;?>"><input type="radio" name="avatar_color" value="<?php echo $c;?>"></label>
<?php endforeach;?>
</div></div>
<div class="fg"><label>📊 状态</label><select name="status" id="edit_status" class="neo" style="width:auto">
<option value="active">✅ 正常</option>
<option value="inactive">⚠️ 停用</option>
<option value="banned">🚫 封禁</option>
</select></div>
<div class="btn-group"><button type="submit" class="btn primary">💾 保存</button><button type="button" class="btn" onclick="closeUserModal()">取消</button></div>
</form>
</div></div>



<?php endif; /* users */ ?>
<?php
    return;
}
