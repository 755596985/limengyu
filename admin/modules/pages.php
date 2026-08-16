<?php
/**
 * 模块：页面 (pages)
 * 功能：save_page, delete_page
 * 双模式文件：handle=POST 处理，render=页面渲染
 */
if (($MOD_RUN ?? '') === 'handle') {
    if ($act === 'save_page') {
        $pid = $_POST['pid'] ?? '';
        $page_title = trim($_POST['page_title'] ?? '');
        $page_slug  = trim($_POST['page_slug'] ?? '');
        $page_icon  = trim($_POST['page_icon'] ?? '📄');
        $page_content = $_POST['page_content'] ?? '';
        $page_sort  = intval($_POST['page_sort'] ?? 99);
        if (empty($page_title) || empty($page_slug)) { $error = '标题和标识不能为空！'; }
        else {
            if ($pid !== '') {
                page_update_by_index(intval($pid), [
                    'title' => $page_title, 'slug' => $page_slug,
                    'icon' => $page_icon, 'content' => $page_content,
                    'sort' => $page_sort, 'time' => date('Y-m-d H:i:s'),
                ]);
            } else {
                page_insert([
                    'id' => new_id(), 'title' => $page_title, 'slug' => $page_slug,
                    'icon' => $page_icon, 'content' => $page_content,
                    'sort' => $page_sort, 'time' => date('Y-m-d H:i:s'),
                ]);
            }
            $message = '页面已保存！';
        }
    }
    if ($act === 'delete_page') {
        $idx = intval($_POST['id'] ?? -1);
        if (page_delete_by_index($idx)) {
            $message = '页面已删除！';
        }
    }

    return;
}
if (($MOD_RUN ?? '') === 'render') {
?>
<?php if ($tab === 'pages'): ?>
<div class="card">
<div class="card-title" id="page_form_title">📄 添加自定义页面</div>
<form method="post" enctype="multipart/form-data">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="save_page"><input type="hidden" name="pid" id="page_pid" value="">
<div class="form-row" style="display:flex;gap:12px">
<div class="fg" style="flex:2"><label>📄 页面标题 *</label><input type="text" name="page_title" id="page_title" class="neo" placeholder="如：我们的故事" required></div>
<div class="fg" style="flex:1"><label>🔗 页面标识 * <span style="font-weight:400;font-size:.85em;color:var(--tl)">(英文/数字)</span></label><input type="text" name="page_slug" id="page_slug" class="neo" placeholder="如：story" required></div>
</div>
<div class="form-row" style="display:flex;gap:12px">
<div class="fg" style="flex:1"><label>🎨 图标</label><input type="text" name="page_icon" id="page_icon" class="neo" placeholder="📄" value="📄"></div>
<div class="fg" style="flex:1"><label>🔢 排序</label><input type="number" name="page_sort" id="page_sort" class="neo" value="99" min="0"></div>
</div>
<div class="fg"><label>📝 页面内容 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(支持HTML)</span></label><textarea name="page_content" id="page_content" class="neo" rows="8" placeholder="<h3>我们的故事</h3><p>从那天开始...</p>"></textarea></div>
<div class="btn-group"><button type="submit" class="btn primary" id="page_submit_btn">📄 保存页面</button><button type="button" class="btn" id="page_cancel_btn" style="display:none;color:var(--tl)" onclick="cancelPageEdit()">✕ 取消编辑</button></div>
</form>
</div>
<div class="card"><div class="card-title">📑 自定义页面列表 (<?php echo count($pages);?>个)</div>
<?php if(empty($pages)):?><p style="text-align:center;color:var(--tl);padding:30px">还没有自定义页面<br>创建一个吧，它会出现在前台导航中~</p>
<?php else: foreach($pages as $i=>$pg):?>
<div class="list-item">
<div style="font-size:1.8em"><?php echo htmlspecialchars($pg['icon']??'📄');?></div>
<div class="item-info">
<div class="item-title"><?php echo htmlspecialchars($pg['title']);?></div>
<div class="item-meta">🔗 ?p=<?php echo htmlspecialchars($pg['slug']);?> · 排序: <?php echo $pg['sort']??99;?> · <?php echo htmlspecialchars($pg['time']??'');?></div>
<div class="item-body"><?php echo htmlspecialchars(mb_substr(strip_tags($pg['content']??''),0,80));?>…</div>
</div>
<div style="flex-shrink:0;display:flex;gap:6px">
<button type="button" class="btn small primary" onclick='editPage(<?php echo $i;?>,<?php echo json_encode($pg['title']);?>,<?php echo json_encode($pg['slug']);?>,<?php echo json_encode($pg['icon']??'📄');?>,<?php echo json_encode($pg['content']??'');?>,<?php echo ($pg['sort']??99);?>)' title="编辑">✏️</button>
<form method="post" onsubmit="return confirm('确定删除？')" style="display:inline"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_page"><input type="hidden" name="id" value="<?php echo $i;?>"><button type="submit" class="btn small danger">删除</button></form>
</div>
</div>
<?php endforeach; endif;?></div>
<?php endif; /* pages */ ?>
<?php
    return;
}
