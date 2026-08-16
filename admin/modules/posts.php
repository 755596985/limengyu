<?php
/**
 * 模块：说说 (posts)
 * 功能：save_post, delete_post
 * 双模式文件：handle=POST 处理，render=页面渲染
 */
if (($MOD_RUN ?? '') === 'handle') {
    if ($act === 'save_post') {
        $id = $_POST['id'] ?? '';
        $content = trim($_POST['content'] ?? '');
        $title   = trim($_POST['title'] ?? '');
        $tags    = trim($_POST['tags'] ?? '');
        $tagArr = $tags ? array_map('trim', explode(',', $tags)) : [];
        $custom_date = trim($_POST['custom_date'] ?? '');
        $custom_time = trim($_POST['custom_time'] ?? '');
        if ($custom_date && $custom_time) {
            $post_time = $custom_date . ' ' . $custom_time . ':00';
        } elseif ($custom_date) {
            $post_time = $custom_date . ' ' . date('H:i:s');
        } else {
            $post_time = date('Y-m-d H:i:s');
        }
        $manual_location = trim($_POST['location'] ?? '');
        if (empty($content)) { $error = '内容不能为空！'; }
        else {
            $imgs = handle_uploads_db('images', $UPLOAD_DIR);
            $image_urls_raw = trim($_POST['image_urls'] ?? '');
            if ($image_urls_raw !== '') {
                $url_list = array_filter(array_map('trim', explode(',', $image_urls_raw)));
                $imgs = array_merge($imgs, array_values($url_list));
            }
            $video_url = trim($_POST['video_url'] ?? '');
            $video = $video_url !== '' ? $video_url : handle_single_upload_db('video', $UPLOAD_DIR, $VID_EXT, $VID_MIME);
            $music_url = trim($_POST['music_url'] ?? '');
            $music = $music_url !== '' ? $music_url : handle_single_upload_db('music', $UPLOAD_DIR, $AUD_EXT, $AUD_MIME);
            $ip = client_ip();
            $location = $manual_location !== '' ? $manual_location : resolve_location($ip);
            if ($id !== '') {
                $idx = intval($id);
                $all = posts_all();
                if (isset($all[$idx])) {
                    $cur = $all[$idx];
                    $mergedImages = array_merge(json_arr($cur['images'] ?? []), $imgs);
                    $mergedVideo = $video ?: ($cur['video'] ?? '');
                    $mergedMusic = $music ?: ($cur['music'] ?? '');
                    // 替换视频/音乐时删除旧文件
                    if ($video && !empty($cur['video']) && $cur['video'] !== $video && !preg_match('#^https?://#i', $cur['video'])) {
                        safe_unlink_under($ROOT, $cur['video']);
                    }
                    if ($music && !empty($cur['music']) && $cur['music'] !== $music && !preg_match('#^https?://#i', $cur['music'])) {
                        safe_unlink_under($ROOT, $cur['music']);
                    }
                    post_update_by_index($idx, [
                        'title' => $title,
                        'tags' => $tagArr,
                        'content' => $content,
                        'author' => $_POST['author'] ?? '1',
                        'mood' => $_POST['mood'] ?? '💕',
                        'time' => $post_time,
                        'images' => $mergedImages,
                        'video' => $mergedVideo,
                        'music' => $mergedMusic,
                        'location' => $location,
                    ]);
                }
            } else {
                post_insert([
                    'id' => new_id(),
                    'title' => $title,
                    'tags' => $tagArr,
                    'content' => $content,
                    'author' => $_POST['author'] ?? '1',
                    'mood' => $_POST['mood'] ?? '💕',
                    'time' => $post_time,
                    'images' => $imgs,
                    'video' => $video,
                    'music' => $music,
                    'ip' => $ip,
                    'location' => $location,
                ]);
            }
            $message = '说说已保存！';
        }
    }
    if ($act === 'delete_post') {
        $idx = intval($_POST['id'] ?? -1);
        if (post_delete_by_index($idx, $ROOT)) {
            $message = '已删除！';
        }
    }

    return;
}
if (($MOD_RUN ?? '') === 'render') {
?>
<?php if ($tab === 'posts'): ?>
<div class="card">
<div class="card-title" id="post_form_title">✏️ 发布说说</div>
<form method="post" enctype="multipart/form-data">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="save_post"><input type="hidden" name="id" id="edit_id" value="">
<div class="post-compose">
<textarea name="content" id="edit_content" class="neo" rows="5" placeholder="写下此刻的想法... 支持 **加粗**、# 标题、`代码`、[链接](url)、> 引用、- 列表、```代码块```" required></textarea>
<div class="post-compose-bar">
<div class="mood-picker" id="edit_mood_picker"><?php $moods=['💕'=>'恋爱','😊'=>'开心','😢'=>'难过','😡'=>'生气','😴'=>'困了','🎉'=>'庆祝','🌧️'=>'忧郁','🔥'=>'热情','🥰'=>'幸福','🤔'=>'思考','😎'=>'酷','🥳'=>'嗨皮','🌹'=>'浪漫','✨'=>'奇妙'];$f=true;foreach($moods as $e=>$l):?><label title="<?php echo $l;?>"><input type="radio" name="mood" value="<?php echo $e;?>" <?php echo $f?'checked':'';?>><span><?php echo $e;?></span></label><?php $f=false;endforeach;?></div>
<select name="author" id="edit_author" class="neo post-author" title="发布身份"><option value="1">👦 <?php echo htmlspecialchars($n1); ?></option><option value="2">👧 <?php echo htmlspecialchars($n2); ?></option></select>
</div>
<div class="post-tools">
<button type="button" class="btn" onclick="togglePostField('f_title')">📌 标题</button>
<button type="button" class="btn" onclick="togglePostField('f_tags')">🏷️ 标签</button>
<button type="button" class="btn" onclick="togglePostField('f_location')">📍 地点</button>
<button type="button" class="btn" onclick="togglePostField('f_time')">🕐 时间</button>
<button type="button" class="btn" onclick="togglePostField('f_images')">📷 图片</button>
<button type="button" class="btn" onclick="insertPostImageUrl()">🖼️ 插入图片链接</button>
<button type="button" class="btn" onclick="togglePostField('f_video')">🎬 视频</button>
<button type="button" class="btn" onclick="togglePostField('f_music')">🎵 音乐</button>
</div>
<script>
// 在内容框光标处插入 [图片]url 标记（支持任意图床直链，含动态接口如 acg.php）
function insertPostImageUrl() {
    var url = prompt('请输入图片链接（支持任意图床直链，如 https://acg.yaohud.cn/dm/acg.php）：');
    if (url && url.trim()) {
        var ins = ' [图片]' + url.trim() + ' ';
        var input = document.getElementById('edit_content');
        if (input) {
            var start = input.selectionStart, end = input.selectionEnd;
            input.value = input.value.substring(0, start) + ins + input.value.substring(end);
            input.selectionStart = input.selectionEnd = start + ins.length;
        }
    }
}
</script>
</div>
<div id="f_title" class="post-extra">
<div class="fg"><label>📌 标题 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(可选，发布文章式说说)</span></label><input type="text" name="title" id="edit_title" class="neo" placeholder="如：我们的第一次旅行"></div>
</div>
<div id="f_tags" class="post-extra">
<div class="fg"><label>🏷️ 标签 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(可选，用逗号分隔)</span></label><input type="text" name="tags" id="edit_tags" class="neo" placeholder="如：旅行, 美食, 日常"></div>
</div>
<div id="f_location" class="post-extra">
<div class="fg"><label>📍 地点 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(可选，留空由IP自动识别)</span></label><input type="text" name="location" id="edit_location" class="neo" placeholder="如：北京 · 朝阳公园"></div>
</div>
<div id="f_time" class="post-extra">
<div style="display:flex;gap:12px">
<div class="fg" style="flex:1"><label>📅 日期 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(留空=当前)</span></label><input type="date" name="custom_date" id="edit_date" class="neo" value="<?php echo date('Y-m-d'); ?>"></div>
<div class="fg" style="flex:1"><label>🕐 时间 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(留空=当前)</span></label><input type="time" name="custom_time" id="edit_time" class="neo" value="<?php echo date('H:i'); ?>"></div>
</div>
</div>
<div id="f_images" class="post-extra">
<div class="fg"><label>📷 图片上传 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(可多选，编辑时重新上传将追加图片)</span></label><input type="file" name="images[]" multiple></div>
<div class="fg"><label>🔗 图片链接 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(可选，多个链接用逗号分隔)</span></label><input type="text" name="image_urls" id="edit_image_urls" class="neo" placeholder="如：https://example.com/photo1.jpg, https://example.com/photo2.jpg"></div>
</div>
<div id="f_video" class="post-extra">
<div class="fg"><label>🎬 视频上传 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(可选, 支持mp4/webm/mov等)</span></label><input type="file" name="video" accept="video/*"><div id="video_hint" style="font-size:.75em;color:var(--tl);margin-top:4px;display:none"></div></div>
<div class="fg"><label>🔗 视频链接 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(可选，直链或视频平台链接)</span></label><input type="text" name="video_url" id="edit_video_url" class="neo" placeholder="如：https://example.com/video.mp4"></div>
</div>
<div id="f_music" class="post-extra">
<div class="fg"><label>🎵 音乐上传 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(可选, 支持mp3/wav/ogg等)</span></label><input type="file" name="music" accept="audio/*"><div id="music_hint" style="font-size:.75em;color:var(--tl);margin-top:4px;display:none"></div></div>
<div class="fg"><label>🔗 音乐链接 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(可选，直链或音乐平台链接)</span></label><input type="text" name="music_url" id="edit_music_url" class="neo" placeholder="如：https://example.com/music.mp3"></div>
</div>
<div class="btn-group"><button type="submit" class="btn primary" id="submit_btn">💕 发布</button><button type="button" class="btn" id="cancel_edit_btn" style="display:none;color:var(--tl)" onclick="cancelEdit()">✕ 取消编辑</button></div>
</form>
</div>
<div class="card"><div class="card-title">📋 说说列表 (<?php echo count($posts);?>)</div>
<?php if(empty($posts)):?><p style="text-align:center;color:var(--tl);padding:30px">还没有说说~</p>
<?php else: foreach($posts as $i=>$po):?>
<div class="list-item">
<div style="font-size:1.5em"><?php echo ($po['author']??'1')==='1'?'👦':'👧';?></div>
<div class="item-info">
<div class="item-title"><?php echo htmlspecialchars($po['mood']??'💕');?> <?php if(!empty($po['title'])):?><span style="color:var(--pri)"><?php echo htmlspecialchars($po['title']);?></span> — <?php endif;?><?php echo htmlspecialchars(mb_substr($po['content'],0,40));?><?php echo mb_strlen($po['content'])>40?'…':'';?></div>
<div class="item-meta"><?php echo htmlspecialchars(($po['author']??'1')==='1'?$n1:$n2);?> · <?php echo htmlspecialchars($po['time']);?><?php if (!empty($po['location']) && $po['location'] !== '未知'): ?> · 📍 <?php echo htmlspecialchars($po['location']); ?><?php endif; ?></div>
<?php if(!empty($po['tags'])):?><div><?php foreach($po['tags'] as $t):?><span class="tag-badge">#<?php echo htmlspecialchars($t);?></span><?php endforeach;?></div><?php endif;?>
<?php if(!empty($po['images'])):?><div class="item-imgs"><?php foreach($po['images'] as $im):?><?php $is_url = preg_match('#^https?://#i', $im); $img_src = $is_url ? htmlspecialchars($im) : '../'.htmlspecialchars($im); $open_src = $is_url ? htmlspecialchars($im) : '../'.htmlspecialchars($im);?><img src="<?php echo $img_src;?>" onclick="event.stopPropagation();window.open('<?php echo $open_src;?>')" style="cursor:pointer"><?php endforeach;?></div><?php endif;?>
<?php if(!empty($po['video'])):?><?php $is_vurl = preg_match('#^https?://#i', $po['video']); if($is_vurl):?><div style="margin-top:4px"><video src="<?php echo htmlspecialchars($po['video']);?>" controls style="max-width:100%;max-height:120px;border-radius:8px"></video></div><?php else:?><div style="margin-top:4px;font-size:.78em;color:var(--tl)">🎬 含有视频</div><?php endif;?><?php endif;?>
<?php if(!empty($po['music'])):?><?php $is_murl = preg_match('#^https?://#i', $po['music']); if($is_murl):?><div style="margin-top:4px"><audio src="<?php echo htmlspecialchars($po['music']);?>" controls style="width:100%;height:32px"></audio></div><?php else:?><div style="margin-top:4px;font-size:.78em;color:var(--tl)">🎵 含有音乐</div><?php endif;?><?php endif;?>
</div>
<div style="flex-shrink:0;display:flex;gap:6px">
<button type="button" class="btn small primary" onclick='editPost(<?php echo $i;?>,<?php echo json_encode($po['mood']??'💕');?>,<?php echo json_encode($po['content']);?>,<?php echo json_encode($po['author']??'1');?>,<?php echo json_encode(substr($po['time'],0,10));?>,<?php echo json_encode(substr($po['time'],11,5));?>,<?php echo json_encode($po['location']??'');?>,<?php echo json_encode($po['title']??'');?>,<?php echo json_encode(isset($po['tags'])?implode(', ',$po['tags']):'');?>,<?php $poImages = $po['images'] ?? []; $poImageUrls = array_filter($poImages, function($i){return preg_match('#^https?://#i',$i);}); echo json_encode($poImageUrls ? implode(', ',$poImageUrls):'');?>,<?php echo json_encode(!empty($po['video'])?basename($po['video']):'');?>,<?php $poVid = $po['video'] ?? ''; echo json_encode(preg_match('#^https?://#i',$poVid) ? $poVid : '');?>,<?php echo json_encode(!empty($po['music'])?basename($po['music']):'');?>,<?php $poMus = $po['music'] ?? ''; echo json_encode(preg_match('#^https?://#i',$poMus) ? $poMus : '');?>)' title="编辑">✏️</button>
<form method="post" onsubmit="return confirm('确定删除？')" style="display:inline"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_post"><input type="hidden" name="id" value="<?php echo $i;?>"><button type="submit" class="btn small danger">删除</button></form>
</div>
</div>
<?php endforeach; endif;?>
</div>
<?php endif; /* posts */ ?>
<?php
    return;
}
