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
        $config['loc1_addr'] = trim($_POST['loc1_addr'] ?? '');
        $config['loc1_lat'] = trim($_POST['loc1_lat'] ?? '');
        $config['loc1_lng'] = trim($_POST['loc1_lng'] ?? '');
        $config['loc2_addr'] = trim($_POST['loc2_addr'] ?? '');
        $config['loc2_lat'] = trim($_POST['loc2_lat'] ?? '');
        $config['loc2_lng'] = trim($_POST['loc2_lng'] ?? '');
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
        // 赞赏收款码：优先使用上传图片，否则使用填写的图片 URL
        $rwx = handle_uploads_db('reward_wx_img', $UPLOAD_DIR);
        if (!empty($rwx)) { $config['reward_wx_img'] = $rwx[0]; }
        elseif (isset($_POST['reward_wx_url'])) { $config['reward_wx_img'] = trim($_POST['reward_wx_url']); }
        if (isset($_POST['delete_reward_wx']) && $_POST['delete_reward_wx'] == '1') $config['reward_wx_img'] = '';
        $rali = handle_uploads_db('reward_alipay_img', $UPLOAD_DIR);
        if (!empty($rali)) { $config['reward_alipay_img'] = $rali[0]; }
        elseif (isset($_POST['reward_alipay_url'])) { $config['reward_alipay_img'] = trim($_POST['reward_alipay_url']); }
        if (isset($_POST['delete_reward_alipay']) && $_POST['delete_reward_alipay'] == '1') $config['reward_alipay_img'] = '';
        save_config($config);
        $n1 = $config['name1']; $n2 = $config['name2']; $av1 = $config['avatar1']??''; $av2 = $config['avatar2']??'';
        $message = '设置已保存！';
    }

    return;
}
if (($MOD_RUN ?? '') === 'render') {
?>
<?php if ($tab === 'config'): ?>
<div class="card"><div class="card-title"><?php echo m_ico_badge('user'); ?>头像 & 设置</div>
<div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap">
<div style="text-align:center;min-width:100px;flex:1">
<div style="width:70px;height:70px;border-radius:50%;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);display:inline-flex;align-items:center;justify-content:center;background:#fff"><?php echo $av1?'<img src="../'.htmlspecialchars($av1).'" style="width:100%;height:100%;object-fit:cover">':'<span style="font-size:2em">👦</span>';?></div>
<div style="font-size:.85em;margin-top:4px"><?php echo htmlspecialchars($n1);?></div></div>
<div style="text-align:center;min-width:100px;flex:1">
<div style="width:70px;height:70px;border-radius:50%;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);display:inline-flex;align-items:center;justify-content:center;background:#fff"><?php echo $av2?'<img src="../'.htmlspecialchars($av2).'" style="width:100%;height:100%;object-fit:cover">':'<span style="font-size:2em">👧</span>';?></div>
<div style="font-size:.85em;margin-top:4px"><?php echo htmlspecialchars($n2);?></div></div></div>
<form method="post" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="act" value="save_config">
<div class="fg"><label><span class="lbl-ico"><?php echo m_ico("user", 15); ?></span> 你的名字</label><input type="text" name="name1" class="neo" value="<?php echo htmlspecialchars($n1);?>" required></div>
<div class="fg"><label><span class="lbl-ico"><?php echo m_ico("image", 15); ?></span> 你的头像</label><input type="file" name="avatar1[]" accept="image/*"><div style="font-size:.7em;color:var(--tl)">不选则保持原头像</div></div>
<div class="fg"><label><span class="lbl-ico"><?php echo m_ico("user", 15); ?></span> TA的名字</label><input type="text" name="name2" class="neo" value="<?php echo htmlspecialchars($n2);?>" required></div>
<div class="fg"><label><span class="lbl-ico"><?php echo m_ico("image", 15); ?></span> TA的头像</label><input type="file" name="avatar2[]" accept="image/*"><div style="font-size:.7em;color:var(--tl)">不选则保持原头像</div></div>
<div class="fg"><label><span class="lbl-ico"><?php echo m_ico("calendar", 15); ?></span> 纪念日</label><input type="date" name="love_date" class="neo" value="<?php echo htmlspecialchars($config['love_date']??'2024-01-01');?>"></div>
<div class="fg"><label><span class="lbl-ico"><?php echo m_ico("tag", 15); ?></span> 网站标题</label><input type="text" name="site_title" class="neo" value="<?php echo htmlspecialchars($config['site_title']??'');?>" placeholder="默认：名字 ❤ 名字"></div>
<div class="fg"><label><span class="lbl-ico"><?php echo m_ico("list", 15); ?></span> 底部备案</label><input type="text" name="beian" class="neo" value="<?php echo htmlspecialchars($config['beian']??'');?>" placeholder="备案文字，自动识别 ICP备/公网安备 并跳转官网，也支持 [文字](链接)"></div>
<div class="fg"><label><span class="lbl-ico"><?php echo m_ico("file", 15); ?></span> 页脚自定义内容</label><textarea name="footer" class="neo" rows="3" style="resize:vertical" placeholder="显示在底部最下方的独立内容，支持 Markdown：加粗、[文字](链接)、自动识别网址等"><?php echo htmlspecialchars($config['footer']??'');?></textarea></div>
 <div class="fg"><label><span class="lbl-ico"><?php echo m_ico("image", 15); ?></span> 首页背景图</label>
<?php if(!empty($config['background_image'])): ?>
<div style="margin-bottom:8px;"><img src="../<?php echo htmlspecialchars($config['background_image']); ?>" style="max-width:200px;border-radius:8px;"></div>
<label><input type="checkbox" name="delete_background" value="1"> 删除当前背景图</label>
<?php endif; ?>
<input type="file" name="background_image[]" accept="image/*"><div style="font-size:.7em;color:var(--tl)">不上传则保持原背景，勾选删除可重置为默认</div></div>
<div class="btn-group"><button type="submit" class="btn primary"><span class="lbl-ico"><?php echo m_ico("save", 15); ?></span> 保存</button></div><hr style="margin:16px 0;border-color:var(--sd)">
<div class="card-title" style="margin-top:8px"><span class="lbl-ico"><?php echo m_ico("config", 15); ?></span> 功能开关</div>
<div class="fg"><label><span class="lbl-ico"><?php echo m_ico("heart", 15); ?></span> 恋爱计时标题</label><input type="text" name="love_title" class="neo" value="<?php echo htmlspecialchars($config['love_title']??'已经在一起');?>" placeholder="已经在一起"></div>
<div class="fg" style="margin-top:14px;border-top:1px dashed var(--sd);padding-top:10px"><label style="font-weight:800"><span class="lbl-ico"><?php echo m_ico("map", 15); ?></span> 两人位置共享</label><div style="font-size:.7em;color:var(--tl)">前台首页计时卡左上角「位置」按钮可查看两人所在地。经纬度可到 <a href="https://lbs.amap.com/tools/picker" target="_blank" rel="noopener">高德坐标拾取器</a> 点选后复制填入（先纬度后经度）；位置名留空则不显示该点。</div></div>
<div class="fg"><label><span class="lbl-ico"><?php echo m_ico("user", 15); ?></span> <?php echo htmlspecialchars($config['name1'] ?? '我'); ?> 的位置名</label><input type="text" name="loc1_addr" class="neo" value="<?php echo htmlspecialchars($config['loc1_addr']??'');?>" placeholder="如：深圳南山"></div>
<div class="fg" style="display:flex;gap:8px"><input type="text" name="loc1_lat" class="neo" value="<?php echo htmlspecialchars($config['loc1_lat']??'');?>" placeholder="<?php echo htmlspecialchars($config['name1'] ?? '我'); ?> 纬度，如 22.5402" style="flex:1"><input type="text" name="loc1_lng" class="neo" value="<?php echo htmlspecialchars($config['loc1_lng']??'');?>" placeholder="<?php echo htmlspecialchars($config['name1'] ?? '我'); ?> 经度，如 113.9330" style="flex:1"></div>
<div class="fg"><label><span class="lbl-ico"><?php echo m_ico("user", 15); ?></span> <?php echo htmlspecialchars($config['name2'] ?? 'TA'); ?> 的位置名</label><input type="text" name="loc2_addr" class="neo" value="<?php echo htmlspecialchars($config['loc2_addr']??'');?>" placeholder="如：北京朝阳"></div>
<div class="fg" style="display:flex;gap:8px"><input type="text" name="loc2_lat" class="neo" value="<?php echo htmlspecialchars($config['loc2_lat']??'');?>" placeholder="<?php echo htmlspecialchars($config['name2'] ?? 'TA'); ?> 纬度，如 39.9042" style="flex:1"><input type="text" name="loc2_lng" class="neo" value="<?php echo htmlspecialchars($config['loc2_lng']??'');?>" placeholder="<?php echo htmlspecialchars($config['name2'] ?? 'TA'); ?> 经度，如 116.4074" style="flex:1"></div>
<div class="fg" style="display:flex;align-items:center;gap:10px"><label style="flex:1"><span class="lbl-ico"><?php echo m_ico("comment", 15); ?></span> 评论功能</label><label class="switch"><input type="checkbox" name="show_comments" <?php echo ($config['show_comments']??1)?'checked':''; ?>><span class="slider"></span></label></div>
<div class="fg" style="display:flex;align-items:center;gap:10px"><label style="flex:1"><span class="lbl-ico"><?php echo m_ico("camera", 15); ?></span> 相册页面</label><label class="switch"><input type="checkbox" name="show_album" <?php echo ($config['show_album']??1)?'checked':''; ?>><span class="slider"></span></label></div>
<div class="fg" style="display:flex;align-items:center;gap:10px"><label style="flex:1"><span class="lbl-ico"><?php echo m_ico("place", 15); ?></span> 足迹页面</label><label class="switch"><input type="checkbox" name="show_places" <?php echo ($config['show_places']??1)?'checked':''; ?>><span class="slider"></span></label></div>
<div class="fg" style="display:flex;align-items:center;gap:10px"><label style="flex:1"><span class="lbl-ico"><?php echo m_ico("check", 15); ?></span> 清单页面</label><label class="switch"><input type="checkbox" name="show_todos" <?php echo ($config['show_todos']??1)?'checked':''; ?>><span class="slider"></span></label></div>
<div class="fg" style="display:flex;align-items:center;gap:10px"><label style="flex:1"><span class="lbl-ico"><?php echo m_ico("edit", 15); ?></span> 用户发说说</label><label class="switch"><input type="checkbox" name="show_user_posts" <?php echo ($config['show_user_posts']??1)?'checked':''; ?>><span class="slider"></span></label></div>
<div class="card-title" style="margin-top:16px"><span class="lbl-ico"><?php echo m_ico("gift", 15); ?></span> 赞赏收款码</div>
<div style="font-size:.75em;color:var(--tl);margin-bottom:12px">在文章详情底部显示"赞赏"按钮，访客点击后弹出收款码。可上传图片或直接填图片链接，不配置则不显示。</div>
<div class="fg"><label><span class="lbl-ico"><?php echo m_ico("heart", 15); ?></span> 微信收款码</label>
<?php if(!empty($config['reward_wx_img'])): ?>
<div style="margin-bottom:8px;"><img src="../<?php echo htmlspecialchars($config['reward_wx_img']); ?>" style="max-width:160px;border-radius:8px;border:1px solid var(--sd)"></div>
<label><input type="checkbox" name="delete_reward_wx" value="1"> 删除当前微信收款码</label>
<?php endif; ?>
<input type="file" name="reward_wx_img[]" accept="image/*"><div style="font-size:.7em;color:var(--tl)">上传图片，或填写下面的图片链接</div>
<input type="text" name="reward_wx_url" class="neo" placeholder="https://…/wx_qr.png" value="<?php echo (preg_match('#^https?://#i', (string)($config['reward_wx_img']??''))) ? htmlspecialchars($config['reward_wx_img']) : ''; ?>" style="margin-top:6px"></div>
<div class="fg"><label><span class="lbl-ico"><?php echo m_ico("heart", 15); ?></span> 支付宝收款码</label>
<?php if(!empty($config['reward_alipay_img'])): ?>
<div style="margin-bottom:8px;"><img src="../<?php echo htmlspecialchars($config['reward_alipay_img']); ?>" style="max-width:160px;border-radius:8px;border:1px solid var(--sd)"></div>
<label><input type="checkbox" name="delete_reward_alipay" value="1"> 删除当前支付宝收款码</label>
<?php endif; ?>
<input type="file" name="reward_alipay_img[]" accept="image/*"><div style="font-size:.7em;color:var(--tl)">上传图片，或填写下面的图片链接</div>
<input type="text" name="reward_alipay_url" class="neo" placeholder="https://…/ali_qr.png" value="<?php echo (preg_match('#^https?://#i', (string)($config['reward_alipay_img']??''))) ? htmlspecialchars($config['reward_alipay_img']) : ''; ?>" style="margin-top:6px"></div>
<div class="btn-group"><button type="submit" class="btn primary"><span class="lbl-ico"><?php echo m_ico("save", 15); ?></span> 保存</button></div>
</form></div>

<?php endif; /* config */ ?>
<?php
    return;
}
