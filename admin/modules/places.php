<?php
/**
 * 模块：足迹 (places)
 * 功能：save_place, delete_place
 * 双模式文件：handle=POST 处理，render=页面渲染
 */
if (($MOD_RUN ?? '') === 'handle') {
    if ($act === 'save_place') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) { $error='请输入地点名称！'; }
        else {
            // 兼容旧库：确保坐标列存在
            try { ensure_table_column('cp_places', 'lat', 'lat DECIMAL(10,7) NULL'); ensure_table_column('cp_places', 'lng', 'lng DECIMAL(10,7) NULL'); } catch (Throwable $e) {}
            $lat = trim($_POST['lat'] ?? ''); $lng = trim($_POST['lng'] ?? '');
            $lat = ($lat !== '' && is_numeric($lat)) ? (float)$lat : null;
            $lng = ($lng !== '' && is_numeric($lng)) ? (float)$lng : null;
            $imgs = handle_uploads_db('place_image', $UPLOAD_DIR);
            place_insert([
                'id' => new_id(), 'name' => $name,
                'note' => trim($_POST['note'] ?? ''),
                'image' => $imgs[0] ?? '',
                'lat' => $lat, 'lng' => $lng,
                'time' => date('Y-m-d H:i:s'),
            ]);
            $message = '地点已记录！';
        }
    }
    if ($act === 'delete_place') {
        $idx = intval($_POST['id'] ?? -1);
        if (place_delete_by_index($idx, $ROOT)) {
            $message = '已删除！';
        }
    }

    return;
}
if (($MOD_RUN ?? '') === 'render') {
    try { ensure_table_column('cp_places', 'lat', 'lat DECIMAL(10,7) NULL'); ensure_table_column('cp_places', 'lng', 'lng DECIMAL(10,7) NULL'); } catch (Throwable $e) {}
?>
<?php if ($tab === 'places'): ?>
<div class="card"><div class="card-title"><?php echo m_ico_badge('place'); ?>记录地点</div>
<form method="post" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="act" value="save_place">
<div class="fg"><label><span class="lbl-ico"><?php echo m_ico("place", 15); ?></span> 地点 *</label><input type="text" name="name" class="neo" placeholder="三亚·天涯海角" required></div>
<div class="fg"><label><span class="lbl-ico"><?php echo m_ico("edit", 15); ?></span> 感想</label><textarea name="note" class="neo" rows="2" placeholder="那天..."></textarea></div>
<div class="fg"><label><span class="lbl-ico"><?php echo m_ico("map", 15); ?></span> 地图坐标（可选，填写后前台会显示在地图上）</label>
<div style="display:flex;gap:8px"><input type="text" name="lat" class="neo" placeholder="纬度 lat，如 18.252847" style="flex:1"><input type="text" name="lng" class="neo" placeholder="经度 lng，如 109.511909" style="flex:1"></div></div>
<div class="fg"><label><span class="lbl-ico"><?php echo m_ico("camera", 15); ?></span> 照片</label><input type="file" name="place_image[]" accept="image/*"></div>
<div class="btn-group"><button type="submit" class="btn primary"><span class="lbl-ico"><?php echo m_ico("place", 15); ?></span> 记录</button></div></form></div>
<div class="card"><div class="card-title"><?php echo m_ico_badge('place'); ?>足迹 (<?php echo count($places);?>个)</div>
<?php if(empty($places)):?><p style="text-align:center;color:var(--tl);padding:30px">还没有记录~</p>
<?php else: foreach($places as $i=>$pl):?>
<div class="list-item">
<div style="width:56px;height:56px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1.5em;background:#fff"><?php echo $pl['image']?'<img src="../'.htmlspecialchars($pl['image']).'" style="width:100%;height:100%;object-fit:cover">':'📍';?></div>
<div class="item-info"><div class="item-title"><?php echo htmlspecialchars($pl['name']);?></div><div class="item-meta"><span class="lbl-ico"><?php echo m_ico("clock", 15); ?></span> <?php echo htmlspecialchars($pl['time']);?></div><?php if(!empty($pl['note'])):?><div class="item-body"><?php echo nl2br(htmlspecialchars($pl['note']));?></div><?php endif;?></div>
<form method="post" onsubmit="return confirm('删除？')"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_place"><input type="hidden" name="id" value="<?php echo $i;?>"><button type="submit" class="btn small danger">删除</button></form>
</div>
<?php endforeach; endif;?></div>
<?php endif; /* places */ ?>
<?php
    return;
}
