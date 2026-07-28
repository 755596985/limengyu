<?php
// Syntax check
$ok = true;
$files = [
    __DIR__ . '/index.php',
    __DIR__ . '/include/bootstrap.php',
];

foreach ($files as $f) {
    $cmd = sprintf('php -l %s 2>&1', escapeshellarg($f));
    exec($cmd, $output, $rc);
    echo "CHECK: " . basename($f) . " => " . ($rc === 0 ? "OK" : "ERROR") . "\n";
    echo implode("\n", $output) . "\n\n";
    if ($rc !== 0) $ok = false;
}

echo $ok ? "ALL_OK" : "HAS_ERRORS";
