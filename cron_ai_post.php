<?php
/**
 * 定时任务端点：AI 每日自动发布一条说说
 * 访问方式（Cron Job URL）：
 *   https://你的域名/cron_ai_post.php?key=你的密钥
 * 说明：InfinityFree 控制面板 -> Cron Jobs -> 新增每天执行的 URL 任务。
 * 防重复：距上次自动发布不足 20 小时则跳过，避免重复触发。
 */
require_once __DIR__ . '/include/bootstrap.php';
require_once __DIR__ . '/admin/modules/ai.php';

header('Content-Type: application/json; charset=utf-8');

function cron_fail(string $msg): void {
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

// ---------- 密钥校验 ----------
ensure_config_column('ai_cron_key');
$row = db()->query('SELECT ai_cron_key FROM cp_config WHERE id=1')->fetch();
$storedKey = trim((string)($row['ai_cron_key'] ?? ''));
if ($storedKey === '') {
    $storedKey = bin2hex(random_bytes(16));
    db()->prepare('UPDATE cp_config SET ai_cron_key=? WHERE id=1')->execute([$storedKey]);
}
$givenKey = trim((string)($_GET['key'] ?? ''));
if ($givenKey === '' || !hash_equals($storedKey, $givenKey)) {
    cron_fail('密钥无效');
}

// ---------- 执行发布（含 20 小时防重复） ----------
echo json_encode(cron_ai_post_run(true), JSON_UNESCAPED_UNICODE);
