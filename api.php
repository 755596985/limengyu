<?php
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(120);

$base = '/www/wwwroot/mnbt.205620724c';
$src = "$base/couple_site";
$ts = date('Ymd_His');
$zipname = "backup_{$ts}.zip";
$zipfile = "$base/$zipname";

$out = [];

$zip = new ZipArchive();
if ($zip->open($zipfile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    die("Cannot open zip: $zipfile");
}

$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

$count = 0;
foreach ($files as $file) {
    $filePath = $file->getRealPath();
    $relativePath = 'couple_site/' . substr($filePath, strlen($src) + 1);
    $zip->addFile($filePath, $relativePath);
    $count++;
}

$zip->close();

if (file_exists($zipfile)) {
    $size = filesize($zipfile);
    echo "OK: $zipname\n";
    echo "Size: $size bytes (" . round($size/1024, 1) . " KB)\n";
    echo "Files: $count\n";
} else {
    echo "FAILED: zip not created\n";
}
