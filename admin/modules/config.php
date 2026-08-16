<?php
/**
 * 模块：设置 (config)
 * 功能：save_config
 * 双模式文件：handle=POST 处理，render=页面渲染
 */
if (($MOD_RUN ?? '') === 'handle') {
    if ($act === 'save_config') {
        $config['name1'] = trim($_POST['name1'] ?? '男神');
        $config['name2'] = trim($_POST['name2'] ?? '女神');
        $config['love_date'] = trim($_POST['love_date'] ?? '2024-01-01');
        $config['site_title'] = trim($_POST['site_title'] ?? '');
        $config['beian'] = trim($_POST['beian'] ?? '');
        $config['footer'] = trim($_POST['footer'] ?? '');
        $config['love_title'] = trim($_POST['love_title'] ?? '已经在一起');
        $config['show_comments'] = isset($_POST['show_comments']) ? 1 : 0;
        $config['show_album'] = isset($_POST['show_album']) ? 1 : 0;
        $config['show_places'] = isset($_POST['show_places']) ? 1 : 0;
        $config['show_todos'] = isset($_POST['show_todos']) ? 1 : 0;
        $config['show_user_posts'] = isset($_POST['show_user_posts']) ? 1 : 0;
        $av = handle_uploads_db('avatar1', $UPLOAD_DIR); if (!empty($av)) $config['avatar1'] = $av[0];
        $av = handle_uploads_db('avatar2', $UPLOAD_DIR); if (!empty($av)) $config['avatar2'] = $av[0];
        $bg = handle_uploads_db('background_image', $UPLOAD_DIR); if(!empty($bg)) $config['background_image'] = $bg[0];
        if(isset($_POST['delete_background']) && $_POST['delete_background'] == '1') {
            if(!empty($config['background_image'])) {
                safe_unlink_under($ROOT, $config['background_image']);
            }
            $config['background_image'] = '';
        }
        save_config($config);
        $n1 = $config['name1']; $n2 = $config['name2']; $av1 = $config['avatar1']??''; $av2 = $config['avatar2']??'';
        $message = '设置已保存！';
    }

    return;
}
if (($MOD_RUN ?? '') === 'render') {
?>
<?php if ($tab === 'config'): ?>
<div class="card"><div class="card-title">👤 头像 & 设置</div>
<div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap">
<div style="text-align:center;min-width:100px;flex:1">
<div style="width:70px;height:70px;border-radius:50%;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);display:inline-flex;align-items:center;justify-content:center;background:#fff"><?php echo $av1?'<img src="../'.htmlspecialchars($av1).'" style="width:100%;height:100%;object-fit:cover">':'<span style="font-size:2em">👦</span>';?></div>
<div style="font-size:.85em;margin-top:4px"><?php echo htmlspecialchars($n1);?></div></div>
<div style="text-align:center;min-width:100px;flex:1">
<div style="width:70px;height:70px;border-radius:50%;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);display:inline-flex;align-items:center;justify-content:center;background:#fff"><?php echo $av2?'<img src="../'.htmlspecialchars($av2).'" style="width:100%;height:100%;object-fit:cover">':'<span style="font-size:2em">👧</span>';?></div>
<div style="font-size:.85em;margin-top:4px"><?php echo htmlspecialchars($n2);?></div></div></div>
<form method="post" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="act" value="save_config">
<div class="fg"><label>👦 你的名字</label><input type="text" name="name1" class="neo" value="<?php echo htmlspecialchars($n1);?>" required></div>
<div class="fg"><label>🖼️ 你的头像</label><input type="file" name="avatar1[]" accept="image/*"><div style="font-size:.7em;color:var(--tl)">不选则保持原头像</div></div>
<div class="fg"><label>👧 TA的名字</label><input type="text" name="name2" class="neo" value="<?php echo htmlspecialchars($n2);?>" required></div>
<div class="fg"><label>🖼️ TA的头像</label><input type="file" name="avatar2[]" accept="image/*"><div style="font-size:.7em;color:var(--tl)">不选则保持原头像</div></div>
<div class="fg"><label>📅 纪念日</label><input type="date" name="love_date" class="neo" value="<?php echo htmlspecialchars($config['love_date']??'2024-01-01');?>"></div>
<div class="fg"><label>🏷️ 网站标题</label><input type="text" name="site_title" class="neo" value="<?php echo htmlspecialchars($config['site_title']??'');?>" placeholder="默认：名字 ❤ 名字"></div>
<div class="fg"><label>📋 底部备案</label><input type="text" name="beian" class="neo" value="<?php echo htmlspecialchars($config['beian']??'');?>" placeholder="备案文字，自动识别 ICP备/公网安备 并跳转官网，也支持 [文字](链接)"></div>
<div class="fg"><label>📄 页脚自定义内容</label><textarea name="footer" class="neo" rows="3" style="resize:vertical" placeholder="显示在底部最下方的独立内容，支持 Markdown：加粗、[文字](链接)、自动识别网址等"><?php echo htmlspecialchars($config['footer']??'');?></textarea></div>
 <div class="fg"><label>🖼️ 首页背景图</label>
<?php if(!empty($config['background_image'])): ?>
<div style="margin-bottom:8px;"><img src="../<?php echo htmlspecialchars($config['background_image']); ?>" style="max-width:200px;border-radius:8px;"></div>
<label><input type="checkbox" name="delete_background" value="1"> 删除当前背景图</label>
<?php endif; ?>
<input type="file" name="background_image[]" accept="image/*"><div style="font-size:.7em;color:var(--tl)">不上传则保持原背景，勾选删除可重置为默认</div></div>
<div class="btn-group"><button type="submit" class="btn primary">💾 保存</button></div><hr style="margin:16px 0;border-color:var(--sd)">
<div class="card-title" style="margin-top:8px">🔧 功能开关</div>
<div class="fg"><label>💕 恋爱计时标题</label><input type="text" name="love_title" class="neo" value="<?php echo htmlspecialchars($config['love_title']??'已经在一起');?>" placeholder="已经在一起"></div>
<div class="fg" style="display:flex;align-items:center;gap:10px"><label style="flex:1">💬 评论功能</label><label class="switch"><input type="checkbox" name="show_comments" <?php echo ($config['show_comments']??1)?'checked':''; ?>><span class="slider"></span></label></div>
<div class="fg" style="display:flex;align-items:center;gap:10px"><label style="flex:1">📷 相册页面</label><label class="switch"><input type="checkbox" name="show_album" <?php echo ($config['show_album']??1)?'checked':''; ?>><span class="slider"></span></label></div>
<div class="fg" style="display:flex;align-items:center;gap:10px"><label style="flex:1">📍 足迹页面</label><label class="switch"><input type="checkbox" name="show_places" <?php echo ($config['show_places']??1)?'checked':''; ?>><span class="slider"></span></label></div>
<div class="fg" style="display:flex;align-items:center;gap:10px"><label style="flex:1">✅ 清单页面</label><label class="switch"><input type="checkbox" name="show_todos" <?php echo ($config['show_todos']??1)?'checked':''; ?>><span class="slider"></span></label></div>
<div class="fg" style="display:flex;align-items:center;gap:10px"><label style="flex:1">✏️ 用户发说说</label><label class="switch"><input type="checkbox" name="show_user_posts" <?php echo ($config['show_user_posts']??1)?'checked':''; ?>><span class="slider"></span></label></div>
<div class="btn-group"><button type="submit" class="btn primary">💾 保存</button></div>
</form></div>

<?php endif; /* config */ ?>
<?php
    return;
}
