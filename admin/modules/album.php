<?php
/**
 * 模块：相册 (album)
 * 功能：save_photo, delete_photo
 * 双模式文件：handle=POST 处理，render=页面渲染
 */
if (($MOD_RUN ?? '') === 'handle') {
    if ($act === 'save_photo') {
        $title = trim($_POST['title'] ?? '');
        $imgs = handle_uploads_db('photo', $UPLOAD_DIR);
        if (empty($imgs)) { $error = '请选择照片！'; }
        else {
            foreach ($imgs as $url) {
                photo_insert(['id' => new_id(), 'url' => $url, 'title' => $title, 'time' => date('Y-m-d H:i:s')]);
            }
            $message = '照片已添加！';
        }
    }
    if ($act === 'delete_photo') {
        $idx = intval($_POST['id'] ?? -1);
        if (photo_delete_by_index($idx, $ROOT)) {
            $message = '照片已删除！';
        }
    }

    return;
}
if (($MOD_RUN ?? '') === 'render') {
?>
<?php if ($tab === 'album'): ?>
<div class="card"><div class="card-title">📤 上传照片</div>
<form method="post" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="act" value="save_photo">
<div class="fg"><label>🏷️ 描述</label><input type="text" name="title" class="neo" placeholder="例如：第一次约会"></div>
<div class="fg"><label>📷 照片</label><input type="file" name="photo[]" accept="image/*" multiple required></div>
<div class="btn-group"><button type="submit" class="btn primary">📷 上传</button></div></form></div>
<div class="card"><div class="card-title">🖼️ 相册 (<?php echo count($photos);?>张)</div>
<?php if(empty($photos)):?><p style="text-align:center;color:var(--tl);padding:30px">相册空的~</p>
<?php else:?><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px">
<?php foreach($photos as $i=>$ph):?>
<div style="position:relative;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06)">
<img src="../<?php echo htmlspecialchars($ph['url']);?>" style="width:100%;aspect-ratio:1;object-fit:cover">
<?php if(!empty($ph['title'])):?><div style="position:absolute;bottom:0;left:0;right:0;padding:6px 10px;background:linear-gradient(transparent,rgba(0,0,0,.5));color:#fff;font-size:.75em"><?php echo htmlspecialchars($ph['title']);?></div><?php endif;?>
<form method="post" onsubmit="return confirm('删除？')" style="position:absolute;top:6px;right:6px"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_photo"><input type="hidden" name="id" value="<?php echo $i;?>"><button type="submit" style="width:26px;height:26px;border-radius:50%;border:none;background:rgba(0,0,0,.5);color:#fff;cursor:pointer">✕</button></form>
</div>
<?php endforeach;?></div><?php endif;?></div>
<?php endif; /* album */ ?>
<?php
    return;
}
