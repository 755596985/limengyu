<?php
set_time_limit(300);
$zip = new ZipArchive();
$dest = '/www/wwwroot/mnbt.205620724c/backup_' . date('Ymd_His') . '.zip';
if ($zip->open($dest, ZipArchive::CREATE) != TRUE) { die('ZIP FAIL'); }
$root = '/www/wwwroot/mnbt.205620724c/couple_site';
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS));
foreach ($files as $file) {
    $local = str_replace($root.'/', '', $file->getRealPath());
    $zip->addFile($file->getRealPath(), $local);
}
$zip->close();
echo 'OK: ' . basename($dest);
