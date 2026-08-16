<?php
/**
 * 模块：文件 (files)
 * 功能：upload_file, delete_file, delete_dir, save_file, mkdir_file
 * 双模式文件：handle=POST 处理，render=页面渲染
 */
if (($MOD_RUN ?? '') === 'handle') {
    if ($act === 'upload_file') {
        $target_rel = trim($_POST['dir'] ?? '');
        $target = rtrim($ROOT, '/') . '/' . ltrim($target_rel, '/');
        $targetReal = realpath($target);
        $rootReal = realpath($ROOT);
        if (!$targetReal || strpos($targetReal, $rootReal) !== 0) {
            $error = '无效的目录';
        } elseif (empty($_FILES['file']['name'])) {
            $error = '请选择文件';
        } else {
            $fname = basename($_FILES['file']['name']);
            $fext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
            $blocked_exts = ['php','phtml','php3','php4','php5','php7','php8','phps','phar','shtml','cgi','pl','py','rb','sh','asp','aspx','jsp','exe','bat','cmd','com','dll','so','htaccess'];
            if (in_array($fext, $blocked_exts, true)) {
                $error = '禁止上传可执行文件 (' . $fext . ')';
            } else {
                $dest = $targetReal . '/' . $fname;
                if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                    $message = '文件上传成功';
                } else {
                    $error = '上传失败';
                }
            }
        }
    }
    if ($act === 'delete_file') {
        $file_rel = trim($_POST['file'] ?? '');
        $fileReal = realpath($ROOT . '/' . ltrim($file_rel, '/'));
        $rootReal = realpath($ROOT);
        if ($fileReal && strpos($fileReal, $rootReal) === 0 && is_file($fileReal)) {
            if (unlink($fileReal)) $message = '文件已删除';
            else $error = '删除失败';
        } else {
            $error = '无效的文件';
        }
    }
    if ($act === 'delete_dir') {
        $dir_rel = trim($_POST['dir'] ?? '');
        $dirReal = realpath($ROOT . '/' . ltrim($dir_rel, '/'));
        $rootReal = realpath($ROOT);
        if ($dirReal && strpos($dirReal, $rootReal) === 0 && is_dir($dirReal)) {
            $files = array_diff(scandir($dirReal), ['.', '..']);
            if (!empty($files)) {
                $error = '目录不为空，请先删除内部文件';
            } elseif (rmdir($dirReal)) {
                $message = '目录已删除';
            } else {
                $error = '删除目录失败';
            }
        } else {
            $error = '无效的目录';
        }
    }
    if ($act === 'save_file') {
        $file_rel = trim($_POST['file'] ?? '');
        $file_content = $_POST['content'] ?? '';
        $fileReal = realpath($ROOT . '/' . ltrim($file_rel, '/'));
        $rootReal = realpath($ROOT);
        if ($fileReal && strpos($fileReal, $rootReal) === 0 && is_file($fileReal)) {
            if (file_put_contents($fileReal, $file_content) !== false) $message = '文件已保存';
            else $error = '保存失败';
        } else {
            $error = '无效的文件';
        }
    }
    if ($act === 'mkdir_file') {
        $target_rel = trim($_POST['dir'] ?? '');
        $dirname = trim($_POST['dirname'] ?? '');
        if (empty($dirname)) {
            $error = '请输入目录名';
        } else {
            $target = rtrim($ROOT, '/') . '/' . ltrim($target_rel, '/');
            $targetReal = realpath($target);
            $rootReal = realpath($ROOT);
            if ($targetReal && strpos($targetReal, $rootReal) === 0) {
                $newDir = $targetReal . '/' . basename($dirname);
                if (!is_dir($newDir)) {
                    if (mkdir($newDir, 0755, true)) $message = '目录已创建';
                    else $error = '创建目录失败';
                } else {
                    $error = '目录已存在';
                }
            } else {
                $error = '无效的目录';
            }
        }
    }

    // === 敏感词管理 ===

    return;
}
if (($MOD_RUN ?? '') === 'render') {
// ---- 渲染前数据准备 ----
// 文件管理 tab 数据
$fm_dir = $_GET['dir'] ?? '';
$fm_dir = str_replace('\\', '/', $fm_dir);
$fullDir = rtrim($ROOT, '/') . '/' . ltrim($fm_dir, '/');
$fullDir = realpath($fullDir) ?: $ROOT;
$rootReal = realpath($ROOT);
if (strpos($fullDir, $rootReal) !== 0) $fullDir = $rootReal;
$fm_items = array_diff(scandir($fullDir), ['.', '..']);
$fm_dirs = []; $fm_files = [];
foreach ($fm_items as $item) {
    $p = $fullDir . '/' . $item;
    if (is_dir($p)) $fm_dirs[] = $item;
    else $fm_files[] = $item;
}
sort($fm_dirs); sort($fm_files);
$fm_rel = ltrim(str_replace($rootReal, '', $fullDir), '/') ?: '';
$fm_parent = dirname($fm_rel);
if ($fm_parent === '.') $fm_parent = '';
$editing_file = $_GET['edit'] ?? '';
$editing_content = '';
if ($editing_file !== '') {
    $editReal = realpath($ROOT . '/' . ltrim($editing_file, '/'));
    if ($editReal && strpos($editReal, $rootReal) === 0 && is_file($editReal)) {
        $editing_content = file_get_contents($editReal);
    } else {
        $editing_file = '';
    }
}
?>
<?php if ($tab === 'files'): ?>
<div class="card">
<div class="card-title">📁 文件管理</div>
<div style="margin-bottom:16px">
<span style="font-size:.85em;color:var(--tl)">📂 当前目录：</span>
<span style="font-size:.85em;color:var(--pri);word-break:break-all">/<?php echo htmlspecialchars($fm_rel); ?></span>
<?php if ($fm_rel !== ''): ?>
<a href="?tab=files&dir=<?php echo urlencode($fm_parent); ?>" class="btn small" style="margin-left:8px">⬆ 上级目录</a>
<?php endif; ?>
<a href="?tab=files" class="btn small" style="margin-left:4px">🏠 根目录</a>
</div>
<form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="upload_file">
<input type="hidden" name="dir" value="<?php echo htmlspecialchars($fm_rel); ?>">
<input type="file" name="file" required style="font-size:.82em;flex:1;min-width:150px">
<button type="submit" class="btn primary small">📤 上传</button>
</form>
<form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="mkdir_file">
<input type="hidden" name="dir" value="<?php echo htmlspecialchars($fm_rel); ?>">
<input type="text" name="dirname" class="neo" placeholder="新建目录名" style="flex:1;min-width:120px;font-size:.82em;padding:8px 12px">
<button type="submit" class="btn small">📁 创建</button>
</form>
</div>
<?php if (!empty($fm_dirs)): ?>
<div class="card"><div class="card-title">📁 目录</div>
<?php foreach ($fm_dirs as $d): $sub = $fm_rel ? $fm_rel . '/' . $d : $d; ?>
<div class="list-item">
<div style="font-size:1.5em">📁</div>
<div class="item-info"><div class="item-title"><?php echo htmlspecialchars($d); ?>/</div></div>
<div style="flex-shrink:0;display:flex;gap:6px">
<a href="?tab=files&dir=<?php echo urlencode($sub); ?>" class="btn small">📂 打开</a>
<form method="post" onsubmit="return confirm('确定删除目录？')" style="display:inline"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_dir"><input type="hidden" name="dir" value="<?php echo htmlspecialchars($sub); ?>"><button type="submit" class="btn small danger">删除</button></form>
</div>
</div>
<?php endforeach; ?></div>
<?php endif; ?>
<div class="card"><div class="card-title">📄 文件 (<?php echo count($fm_files); ?>)</div>
<?php if (empty($fm_files)): ?>
<p style="text-align:center;color:var(--tl);padding:30px">空目录</p>
<?php else: foreach ($fm_files as $fn):
    $fp = $fm_rel ? $fm_rel . '/' . $fn : $fn;
    $fs = filesize($fullDir . '/' . $fn);
    if ($fs < 1024) $fsh = $fs . ' B';
    elseif ($fs < 1048576) $fsh = round($fs/1024, 1) . ' KB';
    else $fsh = round($fs/1048576, 2) . ' MB';
    $fext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
    $isText = in_array($fext, ['php','html','htm','css','js','json','txt','md','xml','yml','yaml','env','htaccess','ini','log','sql','sh','py','rb','java','c','cpp','h','csv']);
    $icon = in_array($fext, ['jpg','jpeg','png','gif','webp','svg','ico']) ? '🖼️' : ($isText ? '📝' : '📎');
?>
<div class="list-item">
<div style="font-size:1.5em"><?php echo $icon; ?></div>
<div class="item-info">
<div class="item-title"><?php echo htmlspecialchars($fn); ?></div>
<div class="item-meta"><?php echo $fsh; ?> · <?php echo htmlspecialchars($fext); ?></div>
</div>
<div style="flex-shrink:0;display:flex;gap:6px">
<?php if ($isText): ?><a href="?tab=files&dir=<?php echo urlencode($fm_rel); ?>&edit=<?php echo urlencode($fp); ?>" class="btn small primary">✏️ 编辑</a><?php endif; ?>
<form method="post" onsubmit="return confirm('确定删除文件？')" style="display:inline"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_file"><input type="hidden" name="file" value="<?php echo htmlspecialchars($fp); ?>"><button type="submit" class="btn small danger">删除</button></form>
</div>
</div>
<?php endforeach; endif; ?></div>
<?php if ($editing_file !== ''): ?>
<div class="card">
<div class="card-title">✏️ 编辑：<?php echo htmlspecialchars(basename($editing_file)); ?><?php if (in_array(strtolower(pathinfo($editing_file, PATHINFO_EXTENSION)), ['php','phtml','phps'])): ?> <span style="color:#c0392b;font-size:.75em">⚠️ PHP文件</span><?php endif; ?></div>
<form method="post"><?php echo csrf_field(); ?>
<input type="hidden" name="act" value="save_file">
<input type="hidden" name="file" value="<?php echo htmlspecialchars($editing_file); ?>">
<div class="fg"><textarea name="content" class="neo" rows="25" style="font-family:monospace;font-size:.82em"><?php echo htmlspecialchars($editing_content); ?></textarea></div>
<div class="btn-group">
<button type="submit" class="btn primary">💾 保存</button>
<a href="?tab=files&dir=<?php echo urlencode($fm_rel); ?>" class="btn">取消</a>
</div></form>
</div>
<?php endif; ?>
<?php endif; /* files */ ?>
<?php
    return;
}
