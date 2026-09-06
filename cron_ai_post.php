<?php
/**
 * 定时任务端点：AI 每日自动发布一条说说
 * 访问方式（备用触发）：
 *   https://yu.wuaze.com/cron_ai_post.php?key=你的密钥
 * 主要触发方式为站内访问自动触发（include/bootstrap.php 的 ai_site_cron_tick），
 * 本端点保留给第三方定时服务或手动验证使用。
 * 防重复：当天已自动发布过则跳过，避免重复触发。
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

// ---------- 定时发布开关：由后台 AI 对话控制（set_ai_cron） ----------
ensure_config_column('ai_cron_enabled');
$enRow = db()->query('SELECT ai_cron_enabled FROM cp_config WHERE id=1')->fetch();
if ((int)($enRow['ai_cron_enabled'] ?? 0) !== 1) {
    cron_fail('定时发布未开启（可在后台 AI 对话中说“每天定时发一篇说说”开启）');
}

// ---------- 执行发布（含当天防重复） ----------
echo json_encode(cron_ai_post_run(true), JSON_UNESCAPED_UNICODE);
