<?php
/**
 * 模块：关于 (about)
 * 功能：save_about
 * 双模式文件：handle=POST 处理，render=页面渲染
 */
if (($MOD_RUN ?? '') === 'handle') {
    if ($act === 'save_about') {
        $about['version'] = trim($_POST['version'] ?? '');
        $about['version_desc'] = trim($_POST['version_desc'] ?? '');
        $about['boy_name'] = trim($_POST['boy_name'] ?? '');
        $about['boy_intro'] = trim($_POST['boy_intro'] ?? '');
        $about['girl_name'] = trim($_POST['girl_name'] ?? '');
        $about['girl_intro'] = trim($_POST['girl_intro'] ?? '');
        $av = handle_uploads_db('boy_avatar', $UPLOAD_DIR); if (!empty($av)) $about['boy_avatar_url'] = $av[0];
        $av = handle_uploads_db('girl_avatar', $UPLOAD_DIR); if (!empty($av)) $about['girl_avatar_url'] = $av[0];
        save_about($about);
        $message = '关于页面已保存！';
    }

    return;
}
if (($MOD_RUN ?? '') === 'render') {
?>
<?php if ($tab === 'about'): ?>
<div class="card"><div class="card-title">📖 版本介绍</div>
<form method="post" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="act" value="save_about">
<div class="fg"><label>🏷️ 项目名称及版本</label><input type="text" name="version" class="neo" value="<?php echo htmlspecialchars($about['version']??'');?>" placeholder="如：情侣小窝 v2.0.0"></div>
<div class="fg"><label>📝 更新日志 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(支持换行，可写多条更新)</span></label><textarea name="version_desc" class="neo" rows="8" placeholder="v2.0.0 - 2024-01-01&#10;  - 新增文件管理功能&#10;  - 优化留言敏感词过滤"><?php echo htmlspecialchars($about['version_desc']??'');?></textarea></div>
<div style="margin:8px 0;height:1px;background:rgba(0,0,0,.05)"></div>
<div style="font-size:.82em;color:var(--tl);margin-bottom:12px">💡 关于我们（可选，展示在前台关于页面）</div>
<div style="display:flex;gap:16px" class="about-flex">
<div style="flex:1">
<div class="fg"><label>👦 他的称呼</label><input type="text" name="boy_name" class="neo" value="<?php echo htmlspecialchars($about['boy_name']??'');?>" placeholder="如：大笨蛋"></div>
<div class="fg"><label>📝 他的介绍</label><textarea name="boy_intro" class="neo" rows="3" placeholder="介绍他..."><?php echo htmlspecialchars($about['boy_intro']??'');?></textarea></div>
<div class="fg"><label>🖼️ 他的照片</label><input type="file" name="boy_avatar[]" accept="image/*"><?php if(!empty($about['boy_avatar_url'])):?><div style="margin-top:4px"><img src="../<?php echo htmlspecialchars($about['boy_avatar_url']);?>" style="max-width:120px;max-height:120px;border-radius:8px"></div><?php endif;?></div>
</div>
<div style="flex:1">
<div class="fg"><label>👧 她的称呼</label><input type="text" name="girl_name" class="neo" value="<?php echo htmlspecialchars($about['girl_name']??'');?>" placeholder="如：小可爱"></div>
<div class="fg"><label>📝 她的介绍</label><textarea name="girl_intro" class="neo" rows="3" placeholder="介绍她..."><?php echo htmlspecialchars($about['girl_intro']??'');?></textarea></div>
<div class="fg"><label>🖼️ 她的照片</label><input type="file" name="girl_avatar[]" accept="image/*"><?php if(!empty($about['girl_avatar_url'])):?><div style="margin-top:4px"><img src="../<?php echo htmlspecialchars($about['girl_avatar_url']);?>" style="max-width:120px;max-height:120px;border-radius:8px"></div><?php endif;?></div>
</div></div>
<div class="btn-group"><button type="submit" class="btn primary">💾 保存</button></div></form></div>
<?php endif; /* about */ ?>
<?php
    return;
}
