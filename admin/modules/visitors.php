<?php
/**
 * 模块：访客 (visitors)
 * 功能：clear_visitors
 * 双模式文件：handle=POST 处理，render=页面渲染
 */
if (($MOD_RUN ?? '') === 'handle') {
    if ($act === 'clear_visitors') {
        visitors_save([]);
        $message = '访客记录已清空';
    }

    return;
}
if (($MOD_RUN ?? '') === 'render') {
?>
<?php if ($tab === 'visitors'): ?>
<h2 class="card-title">📊 访客记录</h2>
<?php $vlist = visitors_get(); if (empty($vlist)): ?>
<p style="text-align:center;color:var(--tl);padding:30px">暂无访客记录</p>
<?php else: $vlist = array_reverse($vlist); $total = count($vlist); $perpage = 20; $page = max(1, intval($_GET['page'] ?? 1)); $total_pages = ceil($total / $perpage); if ($page > $total_pages) $page = $total_pages; $offset = ($page - 1) * $perpage; $vlist_page = array_slice($vlist, $offset, $perpage); ?>
<div style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
<span style="color:var(--tl);font-size:.85em">共 <?php echo $total; ?> 条记录，第 <?php echo $page; ?>/<?php echo $total_pages; ?> 页（每页 <?php echo $perpage; ?> 条）</span>
<form method="post" onsubmit="return confirm('确定清空所有访客记录？')" style="display:inline">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="clear_visitors">
<button type="submit" class="btn danger small">🗑 清空记录</button>
</form>
</div>
<div class="visitor-table-wrap" style="overflow-x:auto">
<table class="visitor-table" style="width:100%;border-collapse:collapse;font-size:.82em">
<thead><tr style="background:#f5f5f5">
<th style="padding:10px 8px;text-align:left;border-bottom:2px solid #e0e0e0">IP 地址</th>
<th style="padding:10px 8px;text-align:left;border-bottom:2px solid #e0e0e0">归属地</th>
<th style="padding:10px 8px;text-align:left;border-bottom:2px solid #e0e0e0">访问时间</th>
<th style="padding:10px 8px;text-align:left;border-bottom:2px solid #e0e0e0">访问页面</th>
<th style="padding:10px 8px;text-align:left;border-bottom:2px solid #e0e0e0">浏览器 UA</th>
</tr></thead>
<tbody>
<?php foreach ($vlist_page as $v): ?>
<tr style="border-bottom:1px solid #eee">
<td data-label="IP 地址" style="padding:8px;word-break:break-all"><code><?php echo htmlspecialchars($v['ip'] ?? ''); ?></code></td>
<td data-label="归属地" style="padding:8px;word-break:break-all"><?php echo htmlspecialchars($v['location'] ?? ''); ?></td>
<td data-label="访问时间" style="padding:8px;white-space:nowrap"><?php echo htmlspecialchars($v['time'] ?? ''); ?></td>
<td data-label="访问页面" style="padding:8px;word-break:break-all;max-width:200px;overflow:hidden;text-overflow:ellipsis" title="<td style="padding:8px;word-break:break-all;max-width:200px;overflow:hidden;text-overflow:ellipsis" title="">"></td>
<td data-label="浏览器" style="padding:8px;font-size:.75em;word-break:break-all;color:#999"><td style="padding:8px;font-size:.75em;word-break:break-all;color:#999"></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php if ($total_pages > 1): ?>
<div style="text-align:center;margin-top:16px;display:flex;justify-content:center;gap:6px;flex-wrap:wrap">
<?php if ($page > 1): ?><a href="?tab=visitors&amp;page=<?php echo $page-1; ?>" class="btn small">&laquo; 上一页</a><?php endif; ?>
<?php
$start_page = max(1, $page - 2);
$end_page = min($total_pages, $page + 2);
if ($start_page > 1) echo '<a href="?tab=visitors&amp;page=1" class="btn small">1</a><span style="padding:8px 4px;color:var(--tl)">...</span>';
for ($i = $start_page; $i <= $end_page; $i++):
?>
<a href="?tab=visitors&amp;page=<?php echo $i; ?>" class="btn small<?php echo $i === $page ? ' primary' : ''; ?>"><?php echo $i; ?></a>
<?php endfor; ?>
<?php if ($end_page < $total_pages) echo '<span style="padding:8px 4px;color:var(--tl)">...</span><a href="?tab=visitors&amp;page=' . $total_pages . '" class="btn small">' . $total_pages . '</a>'; ?>
<?php if ($page < $total_pages): ?><a href="?tab=visitors&amp;page=<?php echo $page+1; ?>" class="btn small">下一页 &raquo;</a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>
<?php endif; /* visitors */ ?>
<?php
    return;
}
