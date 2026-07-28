<?php
/**
 * 一言 API - 随机情话/句子
 * 返回 JSON: {"code":1,"data":{"text":"...","author":"...","source":"..."}}
 */
header('Content-Type: application/json; charset=utf-8');

$dataFile = __DIR__ . '/data/yiyan.php';

if (!file_exists($dataFile)) {
    echo json_encode(['code' => 0, 'msg' => '数据文件不存在'], JSON_UNESCAPED_UNICODE);
    exit;
}

// data/yiyan.php 以 PHP exit 开头防止直接访问
$lines = file($dataFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
array_shift($lines);
$content = implode("\n", $lines);

$quotes = json_decode($content, true);

if (!is_array($quotes) || empty($quotes)) {
    echo json_encode(['code' => 0, 'msg' => '数据为空'], JSON_UNESCAPED_UNICODE);
    exit;
}

$quote = $quotes[array_rand($quotes)];

echo json_encode([
    'code' => 1,
    'data' => [
        'text'   => $quote['text'] ?? '',
        'author' => $quote['author'] ?? '',
        'source' => $quote['source'] ?? '',
    ]
], JSON_UNESCAPED_UNICODE);