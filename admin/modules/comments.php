<?php
/**
 * 模块：留言 (comments)
 * 功能：delete_comment, reply_comment
 * 双模式文件：handle=POST 处理，render=页面渲染
 */
if (($MOD_RUN ?? '') === 'handle') {
    if ($act === 'delete_comment') {
        $idx = intval($_POST['id'] ?? -1);
        if (comment_delete_by_index($idx)) {
            $message = '留言已删除！';
        }
    }

    if ($act === 'reply_comment') {
        $idx = intval($_POST['id'] ?? -1);
        $reply_text = trim($_POST['reply'] ?? '');
        if (empty($reply_text)) { $error = '回复内容不能为空！'; }
        else {
            $all = comments_all();
            if (isset($all[$idx])) {
                $cid = $all[$idx]['id'];
                db()->prepare('UPDATE cp_comments SET reply=?, replied_at=? WHERE id=?')->execute([$reply_text, date('Y-m-d H:i:s'), $cid]);
                $message = '回复已保存！';
            } else {
                $error = '留言不存在！';
            }
        }
    }

    return;
}
if (($MOD_RUN ?? '') === 'render') {
?>
<?php if ($tab === 'comments'): ?>
<div class="card"><div class="card-title">💬 留言管理 (<?php echo count($comments);?>条)</div>
<?php if(empty($comments)):?><p style="text-align:center;color:var(--tl);padding:30px">还没有留言~</p>
<?php else: foreach($comments as $i=>$cm): $pid = $cm['post_id']??''; $poContent = ''; foreach($posts as $po) if(($po['id']??'') === $pid) { $poContent = mb_substr($po['content'],0,30); break; } ?>
<div class="list-item">
<div style="font-size:1.5em">💬</div>
<div class="item-info">
<div class="item-title"><?php echo htmlspecialchars($cm['nick']);?> <span style="font-weight:400;color:var(--tl);font-size:.85em">→ <?php echo htmlspecialchars($poContent ?: '已删除的说说');?>…</span></div>
<div class="item-body"><?php echo nl2br(htmlspecialchars($cm['text']));?></div>
<div class="item-meta">🕐 <?php echo htmlspecialchars($cm['time']);?> · IP: <?php echo htmlspecialchars($cm['ip']??'');?></div>
<?php if (!empty($cm['reply'])): ?>
<div style="margin-top:8px;padding:10px 14px;background:#f0f8ff;border-radius:8px;border-left:3px solid #3498db">
<div style="font-size:.78em;font-weight:700;color:#3498db;margin-bottom:4px">👤 管理员回复 · <?php echo htmlspecialchars($cm['replied_at']??'');?></div>
<div style="font-size:.85em;color:#2c3e50"><?php echo nl2br(htmlspecialchars($cm['reply']));?></div>
</div>
<?php endif; ?>
<div style="margin-top:10px">
<form method="post" style="display:flex;gap:8px;align-items:flex-start">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="reply_comment">
<input type="hidden" name="id" value="<?php echo $i;?>">
<textarea name="reply" class="neo" rows="2" style="flex:1;min-height:36px;font-size:.82em" placeholder="输入回复内容..."><?php echo htmlspecialchars($cm['reply']??'');?></textarea>
<button type="submit" class="btn primary small" style="white-space:nowrap"><?php echo empty($cm['reply'])?'回复':'更新回复';?></button>
</form>
</div>
</div>
<form method="post" onsubmit="return confirm('确定删除？')" style="flex-shrink:0"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_comment"><input type="hidden" name="id" value="<?php echo $i;?>"><button type="submit" class="btn small danger">删除</button></form>
</div>
<?php endforeach; endif;?></div>
<?php endif; /* comments */ ?>
<?php
    return;
}
