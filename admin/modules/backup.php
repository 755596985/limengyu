<?php
/**
 * 模块：数据备份 (backup)
 * 功能：download_backup —— 导出全部数据为 .sql 下载
 * 双模式文件：handle=POST 处理，render=页面渲染
 */
if (($MOD_RUN ?? '') === 'handle') {
    if ($act === 'download_backup') {
        $pdo = db();
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $lines = [];
        $lines[] = '-- 情侣小窝 数据备份';
        $lines[] = '-- 导出时间：' . date('Y-m-d H:i:s');
        $lines[] = '-- 版本：v' . LIMENGYU_VERSION;
        $lines[] = 'SET NAMES utf8mb4;';
        $lines[] = '';

        foreach ($tables as $t) {
            $create = $pdo->query('SHOW CREATE TABLE `' . $t . '`')->fetch(PDO::FETCH_NUM);
            if (!$create) continue;
            $lines[] = 'DROP TABLE IF EXISTS `' . $t . '`;';
            $lines[] = $create[1] . ';';
            $lines[] = '';

            $rows = $pdo->query('SELECT * FROM `' . $t . '`')->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) continue;
            foreach ($rows as $row) {
                $cols = array_map(function ($c) { return '`' . $c . '`'; }, array_keys($row));
                $vals = array_map(function ($v) use ($pdo) {
                    if ($v === null) return 'NULL';
                    return $pdo->quote((string)$v);
                }, array_values($row));
                $lines[] = 'INSERT INTO `' . $t . '` (' . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ');';
            }
            $lines[] = '';
        }

        $sql = implode("\n", $lines);
        $fname = 'limengyu-backup-' . date('Ymd-His') . '.sql';
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fname . '"');
        header('Content-Length: ' . strlen($sql));
        echo $sql;
        exit;
    }

    return;
}
if (($MOD_RUN ?? '') === 'render') {
?>
<?php if ($tab === 'backup'): ?>
<div class="card"><div class="card-title"><?php echo m_ico_badge('save'); ?>数据备份</div>
<p style="font-size:.86em;color:var(--tl);line-height:1.8;margin-bottom:10px">
点击下方按钮将把当前站点<b>全部数据</b>（说说、留言、相册、足迹、清单、页面、用户、设置等）导出为一份 <b>.sql</b> 文件下载到本地，可用于迁移或灾难恢复。<br>
<span style="color:var(--warntx,#f57f17)">提示：恢复时可在 phpMyAdmin 中导入该文件；上传的照片、视频等媒体文件不会包含在 SQL 内，如需完整备份请同时备份 uploads/ 目录。</span>
</p>
<form method="post" target="_blank"><?php echo csrf_field(); ?><input type="hidden" name="act" value="download_backup">
<button type="submit" class="btn primary"><span class="lbl-ico"><?php echo m_ico("download", 15); ?></span> 导出 SQL 备份</button>
</form></div>
<?php endif; /* backup */ ?>
<?php
    return;
}
