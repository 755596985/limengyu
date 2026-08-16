<?php
/**
 * 模块：清单 (todos)
 * 功能：save_todo, toggle_todo, delete_todo
 * 双模式文件：handle=POST 处理，render=页面渲染
 */
if (($MOD_RUN ?? '') === 'handle') {
    if ($act === 'save_todo') {
        $title = trim($_POST['title'] ?? '');
        if (empty($title)) { $error='请输入事项！'; }
        else {
            todo_insert(['id' => new_id(), 'title' => $title, 'note' => trim($_POST['note'] ?? ''), 'time' => date('Y-m-d H:i:s')]);
            $message = '事项已添加！';
        }
    }
    if ($act === 'toggle_todo') {
        $idx = intval($_POST['id'] ?? -1);
        todo_toggle_by_index($idx);
    }
    if ($act === 'delete_todo') {
        $idx = intval($_POST['id'] ?? -1);
        if (todo_delete_by_index($idx)) {
            $message = '已删除！';
        }
    }

    return;
}
if (($MOD_RUN ?? '') === 'render') {
?>
<?php if ($tab === 'todos'): ?>
<div class="card"><div class="card-title">📝 添加事项</div>
<form method="post"><?php echo csrf_field(); ?><input type="hidden" name="act" value="save_todo">
<div class="fg"><label>📋 事项 *</label><input type="text" name="title" class="neo" placeholder="一起看日出" required></div>
<div class="fg"><label>📝 备注</label><textarea name="note" class="neo" rows="2"></textarea></div>
<div class="btn-group"><button type="submit" class="btn primary">✅ 添加</button></div></form></div>
<div class="card"><div class="card-title">📋 清单 (<?php $dn=count(array_filter($todos,function($t){return !empty($t['done']);}));echo $dn.'/'.count($todos);?>)</div>
<?php if(empty($todos)):?><p style="text-align:center;color:var(--tl);padding:30px">清单空的~</p>
<?php else: foreach($todos as $i=>$t):$isd=!empty($t['done']);?>
<div class="list-item">
<form method="post" style="flex-shrink:0"><?php echo csrf_field(); ?><input type="hidden" name="act" value="toggle_todo"><input type="hidden" name="id" value="<?php echo $i;?>"><button type="submit" style="width:38px;height:38px;border-radius:50%;border:none;background:#fff;box-shadow:<?php echo $isd?'inset 1px 1px 4px rgba(0,0,0,.06)':'0 2px 8px rgba(0,0,0,.06)';?>;cursor:pointer;font-size:1.1em"><?php echo $isd?'✅':'⬜';?></button></form>
<div class="item-info"><div class="item-title" style="<?php echo $isd?'text-decoration:line-through;color:var(--tl)':'';?>"><?php echo htmlspecialchars($t['title']);?></div><div class="item-meta"><?php echo $isd?'✅ 已完成 · '.htmlspecialchars($t['done_time']):'📝 创建于 '.htmlspecialchars($t['time']);?></div><?php if(!empty($t['note'])):?><div class="item-body"><?php echo htmlspecialchars($t['note']);?></div><?php endif;?></div>
<form method="post" onsubmit="return confirm('删除？')"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_todo"><input type="hidden" name="id" value="<?php echo $i;?>"><button type="submit" class="btn small danger">删除</button></form>
</div>
<?php endforeach; endif;?></div>
<?php endif; /* todos */ ?>
<?php
    return;
}
