<?php
require __DIR__ . '/include/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '仅支持 POST']);
    exit;
}

$me = $_SESSION['user'] ?? (isset($_SESSION['cp_admin']) ? ['id' => 'admin', 'nickname' => '管理员', 'avatar_color' => '#4a90d9'] : null);
if (!$me) {
    http_response_code(401);
    echo json_encode(['error' => '请先登录']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$commentId = trim($input['comment_id'] ?? '');
$type = trim($input['type'] ?? 'like');
$csrf = trim($input['_csrf'] ?? '');
if (empty($csrf) || !hash_equals($_SESSION['_csrf'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF 验证失败']);
    exit;
}
if (empty($commentId)) {
    http_response_code(400);
    echo json_encode(['error' => '缺少 comment_id']);
    exit;
}

$result = comment_like_toggle($commentId, $me['id'], $type);
echo json_encode($result);
