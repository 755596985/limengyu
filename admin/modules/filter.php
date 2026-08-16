<?php
/**
 * 模块：敏感词 (filter)
 * 功能：add_word, delete_word
 * 双模式文件：handle=POST 处理，render=页面渲染
 */
if (($MOD_RUN ?? '') === 'handle') {
    if ($act === 'add_word') {
        $word = trim($_POST['word'] ?? '');
        if (empty($word)) $error = '请输入敏感词';
        else {
            $words = filter_words_get();
            if (!in_array($word, $words)) {
                $words[] = $word;
                filter_words_save($words);
                $message = '敏感词已添加';
            } else {
                $error = '该敏感词已存在';
            }
        }
    }
    if ($act === 'delete_word') {
        $idx = intval($_POST['id'] ?? -1);
        $words = filter_words_get();
        if (isset($words[$idx])) {
            array_splice($words, $idx, 1);
            filter_words_save($words);
            $message = '敏感词已删除';
        }
    }

    return;
}
if (($MOD_RUN ?? '') === 'render') {
?>
<?php if ($tab === 'filter'): ?>
<div class="card"><div class="card-title">🚫 敏感词设置</div>
<div style="font-size:.85em;color:var(--tl);margin-bottom:16px">📌 前台留言和说说内容中包含的敏感词将被自动替换为 * 号。</div>
<form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><?php echo csrf_field(); ?>
<input type="hidden" name="act" value="add_word">
<input type="text" name="word" class="neo" placeholder="输入敏感词" required style="flex:1;min-width:150px">
<button type="submit" class="btn primary small">➕ 添加</button>
</form>
</div>
<div class="card"><div class="card-title">📋 敏感词列表 (<?php echo count($filter_words); ?>个)</div>
<?php if (empty($filter_words)): ?>
<p style="text-align:center;color:var(--tl);padding:30px">暂未设置任何敏感词</p>
<?php else: ?>
<div style="display:flex;flex-wrap:wrap;gap:8px">
<?php foreach ($filter_words as $i => $w): ?>
<div style="display:flex;align-items:center;gap:6px;background:#fff;border-radius:20px;padding:6px 14px;box-shadow:0 2px 8px rgba(0,0,0,0.06);font-size:.88em">
<span style="color:#c0392b"><?php echo htmlspecialchars($w); ?></span>
<form method="post" onsubmit="return confirm('确定删除该敏感词？')" style="display:inline"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_word"><input type="hidden" name="id" value="<?php echo $i; ?>"><button type="submit" style="background:none;border:none;color:#c0392b;cursor:pointer;font-size:1em;padding:0;line-height:1">✕</button></form>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?></div>
<?php endif; /* filter */ ?>
<?php
    return;
}
