<?php
/**
 * 情侣小窝 — 公共引导：会话、PDO、CSRF、上传、工具函数
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

$ROOT = dirname(__DIR__);
$UPLOAD_DIR = $ROOT . '/uploads/';
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
}

$dbCfg = require __DIR__ . '/config.db.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    global $dbCfg;
    $host = $dbCfg['host'] ?? 'localhost';
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $host,
        $dbCfg['port'],
        $dbCfg['dbname'],
        $dbCfg['charset']
    );
    $pdo = new PDO($dsn, $dbCfg['user'], $dbCfg['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    return $pdo;
}

function client_ip(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function resolve_location(string $ip): string {
    $location = '';
    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
    $geo = @file_get_contents('https://ip9.com.cn/get?ip=' . urlencode($ip), false, $ctx);
    if (!$geo) {
        $geo = @file_get_contents('https://ip9.com.cn/get?', false, $ctx);
    }
    if ($geo) {
        $j = json_decode($geo, true);
        if ($j && ($j['ret'] ?? 0) == 200) {
            $d = $j['data'] ?? [];
            $parts = [];
            if (!empty($d['prov'])) $parts[] = $d['prov'];
            if (!empty($d['city'])) $parts[] = $d['city'];
            if (!empty($d['isp'])) $parts[] = $d['isp'];
            $location = $parts ? implode(' ', $parts) : ($d['ip'] ?? $ip);
        }
    }
    return $location !== '' ? $location : $ip;
}

function csrf_token(): string {
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['_csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_verify(): bool {
    $t = $_POST['_csrf'] ?? '';
    return is_string($t) && isset($_SESSION['_csrf']) && hash_equals($_SESSION['_csrf'], $t);
}

function require_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_verify()) {
        http_response_code(403);
        exit('CSRF 校验失败，请刷新页面后重试');
    }
}

/**
 * 上传图片自动压缩：仅当 GD 可用且为图片时生效。
 * 超过 maxSide 的图片等比缩放；超过 minBytes 的图片统一以 quality 重编码，压缩体积。
 * 失败时静默忽略，不影响上传。
 */
function maybe_auto_compress_image(string $path, string $ext, int $maxSide = 1600, int $minBytes = 51200, int $quality = 78): void {
    $ext = strtolower($ext);
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) return;
    if (!function_exists('imagecreatefromjpeg') || !function_exists('imagecopyresampled')) return;
    if (!is_file($path)) return;
    if (filesize($path) < $minBytes) return;
    try {
        $info = @getimagesize($path);
        if (!$info) return;
        switch ($info[2]) {
            case IMAGETYPE_JPEG: $src = @imagecreatefromjpeg($path); break;
            case IMAGETYPE_PNG:  $src = @imagecreatefrompng($path);  break;
            case IMAGETYPE_WEBP:
                if (!function_exists('imagecreatefromwebp')) return;
                $src = @imagecreatefromwebp($path);
                break;
            default: return;
        }
        if (!$src) return;
        $w = imagesx($src); $h = imagesy($src);
        if ($w <= 1 || $h <= 1) { imagedestroy($src); return; }
        $nw = $w; $nh = $h;
        if (max($w, $h) > $maxSide) {
            $ratio = $maxSide / max($w, $h);
            $nw = (int)round($w * $ratio); $nh = (int)round($h * $ratio);
        }
        $dst = imagecreatetruecolor($nw, $nh);
        if ($info[2] === IMAGETYPE_PNG || $info[2] === IMAGETYPE_WEBP) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        switch ($info[2]) {
            case IMAGETYPE_JPEG: imagejpeg($dst, $path, $quality); break;
            case IMAGETYPE_PNG:  imagepng($dst, $path, 6); break;
            case IMAGETYPE_WEBP:
                if (function_exists('imagewebp')) imagewebp($dst, $path, $quality);
                else imagepng($dst, $path, 6);
                break;
        }
        imagedestroy($src); imagedestroy($dst);
    } catch (Throwable $e) {
        // 压缩失败不影响上传结果
    }
}

function safe_upload_multi(string $key, string $dir, array $allowedExt, array $allowedMime, int $maxBytes = 8388608): array {
    $urls = [];
    if (empty($_FILES[$key]['name'][0])) {
        return $urls;
    }
    foreach ($_FILES[$key]['tmp_name'] as $i => $tmp) {
        if (($_FILES[$key]['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }
        if (($_FILES[$key]['size'][$i] ?? 0) > $maxBytes) {
            continue;
        }
        $ext = strtolower(pathinfo($_FILES[$key]['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            continue;
        }
        if ($allowedMime && class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmp) ?: '';
            if ($mime && !in_array($mime, $allowedMime, true)) {
                continue;
            }
        }
        $fn = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = rtrim($dir, '/') . '/' . $fn;
        if (move_uploaded_file($tmp, $dest)) {
            maybe_auto_compress_image($dest, $ext);
            $urls[] = 'uploads/' . $fn;
        }
    }
    return $urls;
}

function safe_upload_one(string $key, string $dir, array $allowedExt, array $allowedMime, int $maxBytes = 52428800): string {
    if (empty($_FILES[$key]['name']) || ($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return '';
    }
    if (($_FILES[$key]['size'] ?? 0) > $maxBytes) {
        return '';
    }
    $ext = strtolower(pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return '';
    }
    $tmp = $_FILES[$key]['tmp_name'];
    if ($allowedMime && class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        if ($mime && !in_array($mime, $allowedMime, true)) {
            return '';
        }
    }
    $fn = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (move_uploaded_file($tmp, rtrim($dir, '/') . '/' . $fn)) {
        return 'uploads/' . $fn;
    }
    return '';
}

function h(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function json_col($v): string {
    if (is_array($v)) {
        return json_encode($v, JSON_UNESCAPED_UNICODE);
    }
    if ($v === null || $v === '') {
        return '[]';
    }
    if (is_string($v)) {
        $j = json_decode($v, true);
        if (is_array($j)) {
            return json_encode($j, JSON_UNESCAPED_UNICODE);
        }
        return json_encode([$v], JSON_UNESCAPED_UNICODE);
    }
    return json_encode([], JSON_UNESCAPED_UNICODE);
}

function safe_unlink_under(string $root, string $rel): void {
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    if ($rel === '' || strpos($rel, '..') !== false) {
        return;
    }
    $full = rtrim($root, '/') . '/' . $rel;
    $rootReal = realpath($root);
    $fileReal = realpath($full);
    if ($rootReal && $fileReal && strpos($fileReal, $rootReal) === 0 && is_file($fileReal)) {
        @unlink($fileReal);
    }
}

function json_arr($v): array {
    if (is_array($v)) {
        return $v;
    }
    if ($v === null || $v === '') {
        return [];
    }
    $j = json_decode((string)$v, true);
    return is_array($j) ? $j : [];
}

// ---------- Config / Visit / About ----------

// ===== 敏感词过滤 =====
$FILTER_WORDS_FILE = $ROOT . '/data/filter_words.php';
$VISITORS_FILE = $ROOT . '/data/visitors.php';
function filter_words_get(): array {
    global $FILTER_WORDS_FILE;
    if (!file_exists($FILTER_WORDS_FILE)) return [];
    $raw = file_get_contents($FILTER_WORDS_FILE);
    $raw = preg_replace('/^<\?php\s*exit;\?>\s*/', '', $raw);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
function filter_words_save(array $words): void {
    global $FILTER_WORDS_FILE;
    $payload = '<' . '?php exit;?>' . "\n" . json_encode(array_values($words), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents($FILTER_WORDS_FILE, $payload, LOCK_EX);
}
function filter_text(string $text): string {
    $words = filter_words_get();
    if (empty($words)) return $text;
    foreach ($words as $w) {
        $w = trim($w);
        if ($w === '') continue;
        $len = mb_strlen($w, 'UTF-8');
        $replacement = str_repeat('*', $len);
        $text = str_ireplace($w, $replacement, $text);
    }
    return $text;
}
// ===== /敏感词过滤 =====
// ===== 访客记录 =====
function visitors_get(): array {
    global $VISITORS_FILE;
    if (!file_exists($VISITORS_FILE)) return [];
    $raw = file_get_contents($VISITORS_FILE);
    $raw = preg_replace('/^<\?php\s*exit;\?>\s*/', '', $raw);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}
function visitors_save(array $list): void {
    global $VISITORS_FILE;
    $payload = '<' . '?php exit;?>' . "\n" . json_encode(array_values($list), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents($VISITORS_FILE, $payload, LOCK_EX);
}
function visitor_log(): void {
    global $VISITORS_FILE;
    $ip = client_ip();
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $url = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '/');
    $time = date('Y-m-d H:i:s');
    $entry = [
        'id' => uniqid(),
        'ip' => $ip,
        'location' => resolve_location($ip),
        'ua' => mb_substr($ua, 0, 500),
        'url' => $url,
        'time' => $time,
    ];
    $list = visitors_get();
    $list[] = $entry;
    if (count($list) > 1000) {
        $list = array_slice($list, -1000);
    }
    visitors_save($list);
}
// ===== /访客记录 =====


function get_config(): array {
    $row = db()->query('SELECT * FROM cp_config WHERE id=1')->fetch();
    if (!$row) {
        return [
            'name1' => '男神', 'name2' => '女神', 'love_date' => '2024-01-01',
            'site_title' => '', 'beian' => '本站由小兔云提供技术支持 · 仅供个人使用',
            'avatar1' => '', 'avatar2' => '', 'background_image' => '',
            'love_title' => '已经在一起',
            'show_comments' => 1, 'show_album' => 1, 'show_places' => 1,
            'show_todos' => 1, 'show_user_posts' => 1,
            'footer' => '',
        ];
    }
    return $row;
}

// 确保 cp_config 存在指定列（兼容旧库，缺失时自动 ALTER 添加）
function ensure_config_column(string $col): void {
    static $checked = [];
    if (isset($checked[$col])) return;
    $pdo = db();
    $cols = $pdo->query('SHOW COLUMNS FROM cp_config')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array($col, $cols, true)) {
        $pdo->exec('ALTER TABLE cp_config ADD COLUMN `' . $col . "` TEXT NOT NULL DEFAULT ''");
    }
    $checked[$col] = true;
}

function save_config(array $c): void {
    ensure_config_column('footer');
    $st = db()->prepare('UPDATE cp_config SET name1=?, name2=?, love_date=?, site_title=?, beian=?, avatar1=?, avatar2=?, background_image=?, love_title=?, show_comments=?, show_album=?, show_places=?, show_todos=?, show_user_posts=?, footer=? WHERE id=1');
    $st->execute([
        $c['name1'] ?? '男神', $c['name2'] ?? '女神', $c['love_date'] ?? '2024-01-01',
        $c['site_title'] ?? '', $c['beian'] ?? '',
        $c['avatar1'] ?? '', $c['avatar2'] ?? '', $c['background_image'] ?? '',
        $c['love_title'] ?? '已经在一起',
        $c['show_comments'] ?? 1, $c['show_album'] ?? 1, $c['show_places'] ?? 1,
        $c['show_todos'] ?? 1, $c['show_user_posts'] ?? 1,
        $c['footer'] ?? '',
    ]);
}

function bump_visit(): array {
    $pdo = db();
    $pdo->beginTransaction();
    $row = $pdo->query('SELECT total, today, visit_date FROM cp_visit WHERE id=1 FOR UPDATE')->fetch();
    if (!$row) {
        $pdo->exec("INSERT INTO cp_visit (id,total,today,visit_date) VALUES (1,1,1,CURDATE())");
        $pdo->commit();
        return ['total' => 1, 'today' => 1, 'date' => date('Y-m-d')];
    }
    $today = date('Y-m-d');
    if ($row['visit_date'] !== $today) {
        $total = (int)$row['total'] + 1;
        $pdo->prepare('UPDATE cp_visit SET total=?, today=1, visit_date=? WHERE id=1')->execute([$total, $today]);
        $pdo->commit();
        return ['total' => $total, 'today' => 1, 'date' => $today];
    }
    $total = (int)$row['total'] + 1;
    $t = (int)$row['today'] + 1;
    $pdo->prepare('UPDATE cp_visit SET total=?, today=? WHERE id=1')->execute([$total, $t]);
    $pdo->commit();
    return ['total' => $total, 'today' => $t, 'date' => $today];
}

function get_about(): array {
    $row = db()->query('SELECT * FROM cp_about WHERE id=1')->fetch();
    return $row ?: [
        'version' => '', 'version_desc' => '', 'boy_name' => '', 'boy_intro' => '',
        'girl_name' => '', 'girl_intro' => '', 'boy_avatar_url' => '', 'girl_avatar_url' => '',
    ];
}

function save_about(array $a): void {
    $st = db()->prepare('UPDATE cp_about SET version=?, version_desc=?, boy_name=?, boy_intro=?, girl_name=?, girl_intro=?, boy_avatar_url=?, girl_avatar_url=? WHERE id=1');
    $st->execute([
        $a['version'] ?? '', $a['version_desc'] ?? '', $a['boy_name'] ?? '', $a['boy_intro'] ?? '',
        $a['girl_name'] ?? '', $a['girl_intro'] ?? '', $a['boy_avatar_url'] ?? '', $a['girl_avatar_url'] ?? '',
    ]);
}

// ---------- Posts ----------
function posts_all(): array {
    $rows = db()->query('SELECT p.*, u.avatar AS user_avatar, u.avatar_color AS user_avatar_color
        FROM cp_posts p
        LEFT JOIN cp_users u ON p.user_id = u.id
        ORDER BY p.created_at DESC')->fetchAll();
    foreach ($rows as &$r) {
        $r['tags'] = json_arr($r['tags']);
        $r['images'] = json_arr($r['images']);
        $r['time'] = $r['created_at'];
    }
    unset($r);
    return $rows;
}

function post_insert(array $p): void {
    $st = db()->prepare('INSERT INTO cp_posts (id,title,tags,content,author,mood,created_at,images,video,music,ip,location,user_id,user_nick,user_color) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $st->execute([
        $p['id'], $p['title'] ?? '', json_col($p['tags'] ?? []), $p['content'],
        $p['author'] ?? '1', $p['mood'] ?? '💕', $p['time'] ?? date('Y-m-d H:i:s'),
        json_col($p['images'] ?? []), $p['video'] ?? '', $p['music'] ?? '',
        $p['ip'] ?? '', $p['location'] ?? '', $p['user_id'] ?? null, $p['user_nick'] ?? null, $p['user_color'] ?? null,
    ]);
}

function post_update_by_index(int $idx, array $fields): bool {
    $all = posts_all();
    if (!isset($all[$idx])) {
        return false;
    }
    $id = $all[$idx]['id'];
    $cur = $all[$idx];
    $merged = array_merge($cur, $fields);
    $st = db()->prepare('UPDATE cp_posts SET title=?, tags=?, content=?, author=?, mood=?, created_at=?, images=?, video=?, music=?, location=? WHERE id=?');
    $st->execute([
        $merged['title'] ?? '', json_col($merged['tags'] ?? []), $merged['content'] ?? '',
        $merged['author'] ?? '1', $merged['mood'] ?? '💕', $merged['time'] ?? $merged['created_at'],
        json_col($merged['images'] ?? []), $merged['video'] ?? '', $merged['music'] ?? '',
        $merged['location'] ?? '', $id,
    ]);
    return true;
}

function post_delete_by_index(int $idx, string $root): bool {
    $all = posts_all();
    if (!isset($all[$idx])) {
        return false;
    }
    $po = $all[$idx];
    foreach (json_arr($po['images'] ?? []) as $im) {
        safe_unlink_under($root, $im);
    }
    foreach (['video', 'music'] as $k) {
        if (!empty($po[$k])) {
            safe_unlink_under($root, $po[$k]);
        }
    }
    db()->prepare('DELETE FROM cp_comments WHERE post_id=?')->execute([$po['id']]);
    db()->prepare('DELETE FROM cp_posts WHERE id=?')->execute([$po['id']]);
    return true;
}

function posts_by_user(string $userId): array {
    $st = db()->prepare('SELECT p.*, u.avatar AS user_avatar, u.avatar_color AS user_avatar_color
        FROM cp_posts p
        LEFT JOIN cp_users u ON p.user_id = u.id
        WHERE p.user_id=? ORDER BY p.created_at DESC');
    $st->execute([$userId]);
    $rows = $st->fetchAll();
    foreach ($rows as &$r) {
        $r['tags'] = json_arr($r['tags']);
        $r['images'] = json_arr($r['images']);
        $r['time'] = $r['created_at'];
    }
    unset($r);
    return $rows;
}

function post_delete_by_id(string $id, string $userId, string $root): bool {
    $st = db()->prepare('SELECT * FROM cp_posts WHERE id=? AND user_id=?');
    $st->execute([$id, $userId]);
    $po = $st->fetch();
    if (!$po) {
        return false;
    }
    foreach (json_arr($po['images']) as $im) {
        safe_unlink_under($root, $im);
    }
    foreach (['video', 'music'] as $k) {
        if (!empty($po[$k])) {
            safe_unlink_under($root, $po[$k]);
        }
    }
    db()->prepare('DELETE FROM cp_comments WHERE post_id=?')->execute([$id]);
    db()->prepare('DELETE FROM cp_posts WHERE id=?')->execute([$id]);
    return true;
}

// ---------- Comments ----------
function comments_all(): array {
    $rows = db()->query('SELECT c.*, u.avatar AS user_avatar, u.avatar_color AS user_avatar_color, u.location AS user_location
        FROM cp_comments c
        LEFT JOIN cp_users u ON c.user_id = u.id
        ORDER BY c.created_at DESC')->fetchAll();
    foreach ($rows as &$r) {
        $r['time'] = $r['created_at'];
    }
    unset($r);
    return $rows;
}

function comment_like_toggle(string $commentId, string $userId, string $type = 'like'): array {
    // Ensure type column exists (migration)
    static $migrated = false;
    if (!$migrated) {
        try {
            db()->exec("ALTER TABLE cp_comment_likes ADD COLUMN type VARCHAR(10) DEFAULT 'like' AFTER user_id");
        } catch (Throwable $e) {}
        $migrated = true;
    }
    $st = db()->prepare('SELECT type FROM cp_comment_likes WHERE comment_id=? AND user_id=?');
    $st->execute([$commentId, $userId]);
    $existing = $st->fetchColumn();
    if ($existing !== false) {
        if ($existing === $type) {
            // Same type - toggle off
            db()->prepare('DELETE FROM cp_comment_likes WHERE comment_id=? AND user_id=?')->execute([$commentId, $userId]);
            db()->prepare('UPDATE cp_comments SET likes = GREATEST(0, likes - 1) WHERE id=?')->execute([$commentId]);
            $liked = false;
            $activeType = null;
        } else {
            // Different type - switch
            db()->prepare('UPDATE cp_comment_likes SET type=? WHERE comment_id=? AND user_id=?')->execute([$type, $commentId, $userId]);
            $liked = true;
            $activeType = $type;
        }
    } else {
        // New like/dislike
        db()->prepare('INSERT INTO cp_comment_likes (comment_id, user_id, type, created_at) VALUES (?,?,?,?)')->execute([$commentId, $userId, $type, date('Y-m-d H:i:s')]);
        db()->prepare('UPDATE cp_comments SET likes = likes + 1 WHERE id=?')->execute([$commentId]);
        $liked = true;
        $activeType = $type;
    }
    $newCountSt = db()->prepare('SELECT likes FROM cp_comments WHERE id=?');
    $newCountSt->execute([$commentId]);
    $newCount = $newCountSt->fetchColumn();
    return ['liked' => $liked, 'count' => (int)$newCount, 'type' => $activeType];
}function comment_likes_status(array $commentIds, string $userId): array {
    if (empty($commentIds)) return [];
    $placeholders = implode(',', array_fill(0, count($commentIds), '?'));
    $params = $commentIds;
    $params[] = $userId;
    try {
        $st = db()->prepare("SELECT comment_id, type FROM cp_comment_likes WHERE comment_id IN ($placeholders) AND user_id=?");
        $st->execute($params);
        $result = [];
        while ($row = $st->fetch()) { $result[$row['comment_id']] = $row['type'] ?? 'like'; }
        return $result;
    } catch (Throwable $e) {
        // Fallback without type column
        $st = db()->prepare("SELECT comment_id FROM cp_comment_likes WHERE comment_id IN ($placeholders) AND user_id=?");
        $st->execute($params);
        $liked = [];
        while ($row = $st->fetch()) { $liked[$row['comment_id']] = true; }
        return $liked;
    }
}
function post_exists(string $postId): bool {
    if ($postId === '') {
        return false;
    }
    $st = db()->prepare('SELECT 1 FROM cp_posts WHERE id=? LIMIT 1');
    $st->execute([$postId]);
    return (bool)$st->fetchColumn();
}

function comment_insert(array $c): void {
    $parent_id = !empty($c['parent_id']) ? $c['parent_id'] : null;
    $st = db()->prepare('INSERT INTO cp_comments (id,post_id,nick,text,voice,ip,user_id,parent_id,created_at) VALUES (?,?,?,?,?,?,?,?,?)');
    $st->execute([
        $c['id'], $c['post_id'], $c['nick'], $c['text'],
        $c['voice'] ?? '', $c['ip'] ?? '', $c['user_id'] ?? null, $parent_id, $c['time'] ?? date('Y-m-d H:i:s'),
    ]);
}

function comment_delete_by_index(int $idx): bool {
    $all = comments_all();
    if (!isset($all[$idx])) {
        return false;
    }
    db()->prepare('DELETE FROM cp_comments WHERE id=?')->execute([$all[$idx]['id']]);
    return true;
}

function comment_delete_by_id(string $id): bool {
    $st = db()->prepare('DELETE FROM cp_comments WHERE id=?');
    $st->execute([$id]);
    return $st->rowCount() > 0;
}

function comment_update(string $id, string $text): bool {
    $st = db()->prepare('UPDATE cp_comments SET text=? WHERE id=?');
    $st->execute([$text, $id]);
    return $st->rowCount() > 0;
}

// ---------- Users ----------
function users_all(): array {
    return db()->query('SELECT id,username,nickname,avatar,avatar_color,ip,location,email,status,created_at FROM cp_users ORDER BY created_at DESC')->fetchAll();
}

function user_by_username(string $username): ?array {
    $st = db()->prepare('SELECT * FROM cp_users WHERE username=? LIMIT 1');
    $st->execute([$username]);
    $r = $st->fetch();
    return $r ?: null;
}

function user_by_id(string $id): ?array {
    $st = db()->prepare('SELECT * FROM cp_users WHERE id=? LIMIT 1');
    $st->execute([$id]);
    $r = $st->fetch();
    return $r ?: null;
}

function user_insert(array $u): void {
    $st = db()->prepare('INSERT INTO cp_users (id,username,password,nickname,avatar,avatar_color,ip,location,email,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $st->execute([
        $u['id'], $u['username'], $u['password'], $u['nickname'] ?? $u['username'],
        $u['avatar'] ?? '', $u['avatar_color'] ?? '#d4786e',
        $u['ip'] ?? '', $u['location'] ?? '', $u['email'] ?? '', $u['status'] ?? 'active',
        $u['created_at'] ?? date('Y-m-d H:i:s'),
    ]);
}

function user_update(string $id, array $fields): void {
    $allowed = ['nickname', 'avatar', 'avatar_color', 'password', 'email', 'status', 'ip', 'location'];
    $sets = [];
    $vals = [];
    foreach ($allowed as $k) {
        if (array_key_exists($k, $fields)) {
            $sets[] = "$k=?";
            $vals[] = $fields[$k];
        }
    }
    if (!$sets) {
        return;
    }
    $vals[] = $id;
    db()->prepare('UPDATE cp_users SET ' . implode(',', $sets) . ' WHERE id=?')->execute($vals);
}

function user_delete_by_index(int $idx): bool {
    $all = users_all();
    if (!isset($all[$idx])) {
        return false;
    }
    return user_delete_by_id($all[$idx]['id']);
}

function user_delete_by_id(string $id): bool {
    $st = db()->prepare('DELETE FROM cp_users WHERE id=?');
    $st->execute([$id]);
    return $st->rowCount() > 0;
}

// ---------- Photos / Places / Todos / Pages ----------
function photos_all(): array {
    $rows = db()->query('SELECT * FROM cp_photos ORDER BY created_at DESC')->fetchAll();
    foreach ($rows as &$r) {
        $r['time'] = $r['created_at'];
    }
    unset($r);
    return $rows;
}
function photo_insert(array $p): void {
    db()->prepare('INSERT INTO cp_photos (id,url,title,created_at) VALUES (?,?,?,?)')
        ->execute([$p['id'], $p['url'], $p['title'] ?? '', $p['time'] ?? date('Y-m-d H:i:s')]);
}
function photo_delete_by_index(int $idx, string $root): bool {
    $all = photos_all();
    if (!isset($all[$idx])) return false;
    if (!empty($all[$idx]['url'])) {
        safe_unlink_under($root, $all[$idx]['url']);
    }
    db()->prepare('DELETE FROM cp_photos WHERE id=?')->execute([$all[$idx]['id']]);
    return true;
}

function places_all(): array {
    $rows = db()->query('SELECT * FROM cp_places ORDER BY created_at DESC')->fetchAll();
    foreach ($rows as &$r) {
        $r['time'] = $r['created_at'];
    }
    unset($r);
    return $rows;
}
function place_insert(array $p): void {
    db()->prepare('INSERT INTO cp_places (id,name,note,image,created_at) VALUES (?,?,?,?,?)')
        ->execute([$p['id'], $p['name'], $p['note'] ?? '', $p['image'] ?? '', $p['time'] ?? date('Y-m-d H:i:s')]);
}
function place_delete_by_index(int $idx, string $root): bool {
    $all = places_all();
    if (!isset($all[$idx])) return false;
    if (!empty($all[$idx]['image'])) {
        safe_unlink_under($root, $all[$idx]['image']);
    }
    db()->prepare('DELETE FROM cp_places WHERE id=?')->execute([$all[$idx]['id']]);
    return true;
}

function todos_all(): array {
    $rows = db()->query('SELECT * FROM cp_todos ORDER BY done ASC, created_at DESC')->fetchAll();
    foreach ($rows as &$r) {
        $r['done'] = (bool)$r['done'];
        $r['time'] = $r['created_at'];
    }
    unset($r);
    return $rows;
}
function todo_insert(array $t): void {
    db()->prepare('INSERT INTO cp_todos (id,title,note,done,done_time,created_at) VALUES (?,?,?,?,?,?)')
        ->execute([$t['id'], $t['title'], $t['note'] ?? '', 0, '', $t['time'] ?? date('Y-m-d H:i:s')]);
}
function todo_toggle_by_index(int $idx): bool {
    $all = todos_all();
    if (!isset($all[$idx])) return false;
    $t = $all[$idx];
    if ($t['done']) {
        db()->prepare('UPDATE cp_todos SET done=0, done_time=\'\' WHERE id=?')->execute([$t['id']]);
    } else {
        db()->prepare('UPDATE cp_todos SET done=1, done_time=? WHERE id=?')->execute([date('Y-m-d H:i:s'), $t['id']]);
    }
    return true;
}
function todo_delete_by_index(int $idx): bool {
    $all = todos_all();
    if (!isset($all[$idx])) return false;
    db()->prepare('DELETE FROM cp_todos WHERE id=?')->execute([$all[$idx]['id']]);
    return true;
}

function pages_all(): array {
    $rows = db()->query('SELECT * FROM cp_pages ORDER BY sort ASC, created_at DESC')->fetchAll();
    foreach ($rows as &$r) {
        $r['time'] = $r['created_at'];
    }
    unset($r);
    return $rows;
}
function page_insert(array $p): void {
    db()->prepare('INSERT INTO cp_pages (id,title,slug,icon,content,sort,created_at) VALUES (?,?,?,?,?,?,?)')
        ->execute([
            $p['id'], $p['title'], $p['slug'], $p['icon'] ?? '📄',
            $p['content'] ?? '', (int)($p['sort'] ?? 99), $p['time'] ?? date('Y-m-d H:i:s'),
        ]);
}
function page_update_by_index(int $idx, array $p): bool {
    $all = pages_all();
    if (!isset($all[$idx])) return false;
    db()->prepare('UPDATE cp_pages SET title=?, slug=?, icon=?, content=?, sort=?, created_at=? WHERE id=?')
        ->execute([
            $p['title'], $p['slug'], $p['icon'] ?? '📄', $p['content'] ?? '',
            (int)($p['sort'] ?? 99), date('Y-m-d H:i:s'), $all[$idx]['id'],
        ]);
    return true;
}
function page_delete_by_index(int $idx): bool {
    $all = pages_all();
    if (!isset($all[$idx])) return false;
    db()->prepare('DELETE FROM cp_pages WHERE id=?')->execute([$all[$idx]['id']]);
    return true;
}

// ---------- Admin ----------
function admin_get(): array {
    $row = db()->query('SELECT * FROM cp_admin WHERE id=1')->fetch();
    if (!$row) {
        $hash = password_hash('admin123', PASSWORD_DEFAULT);
        db()->prepare('INSERT INTO cp_admin (id,username,password) VALUES (1,?,?)')->execute(['admin', $hash]);
        return ['username' => 'admin', 'password' => $hash];
    }
    return $row;
}

function admin_save(string $username, ?string $passwordHash = null): void {
    if ($passwordHash) {
        db()->prepare('UPDATE cp_admin SET username=?, password=? WHERE id=1')->execute([$username, $passwordHash]);
    } else {
        db()->prepare('UPDATE cp_admin SET username=? WHERE id=1')->execute([$username]);
    }
}

function new_id(): string {
    return bin2hex(random_bytes(8));
}

// ---------- 轻量 Markdown ----------
function md_safe_url(string $u): ?string {
    $u = htmlspecialchars_decode($u, ENT_QUOTES);
    if (preg_match('#^https?://#i', $u) || preg_match('#^mailto:#i', $u) || str_starts_with($u, '/') || str_starts_with($u, '#')) {
        return htmlspecialchars($u, ENT_QUOTES, 'UTF-8');
    }
    return null;
}
/**
 * 安全截断：避免切断 Markdown 图片标记（![alt](url)）与 [图片]url 标记，
 * 保证列表页截断后图片仍能完整渲染。
 */
function md_truncate(string $text, int $len): string {
    if (mb_strlen($text) <= $len) return $text;
    $pattern = '/!\[[^\]]*\]\([^)\s]+\)|\[图片\]((?:https?:\/\/|\/)[^\s<>"\']+)/i';
    if (!preg_match_all($pattern, $text, $m, PREG_OFFSET_CAPTURE)) {
        return mb_substr($text, 0, $len);
    }
    $marks = $m[0];
    $lastMarkEnd = 0;
    foreach ($marks as $mm) {
        $endByte = $mm[1] + strlen($mm[0]);
        if ($endByte > $lastMarkEnd) $lastMarkEnd = $endByte;
    }
    // 目标截断字节位置：len 对应的字节偏移（近似用 mb_strcut 校准）
    $targetByte = strlen(mb_strcut($text, 0, $len, 'UTF-8'));
    $cutByte = $targetByte;
    // 截断点落在某个图片标记内部：扩展到标记结束，保留完整图片
    foreach ($marks as $mm) {
        $startByte = $mm[1];
        $endByte = $startByte + strlen($mm[0]);
        if ($startByte <= $cutByte && $endByte > $cutByte) {
            $cutByte = $endByte;
        }
    }
    $base = mb_strcut($text, 0, $cutByte, 'UTF-8');
    // 截断点之后的所有图片标记一律追加保留，保证列表页图片完整显示
    $tail = '';
    foreach ($marks as $mm) {
        if ($mm[1] >= $cutByte) {
            $tail .= "\n" . $mm[0];
        }
    }
    return $base . $tail;
}
function md_inline(string $s): string {
    $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s);
    $s = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s);
    $s = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $s);
    // [图片]url 图片标记（与评论解析一致），支持任意URL（含动态图床接口如 acg.php）
    $s = preg_replace_callback('/\[图片\]((?:https?:\/\/|\/)[^\s<>"\']+)/i', function ($m) {
        $u = md_safe_url($m[1]);
        return $u !== null ? '<img src="' . $u . '" alt="" loading="lazy" style="max-width:100%;border-radius:8px">' : $m[0];
    }, $s);
    $s = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', function ($m) {
        $u = md_safe_url($m[2]);
        return $u !== null ? '<img src="' . $u . '" alt="' . $m[1] . '" loading="lazy" style="max-width:100%;border-radius:8px">' : $m[0];
    }, $s);
    $s = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
        $u = md_safe_url($m[2]);
        return $u !== null ? '<a href="' . $u . '" target="_blank" rel="noopener">' . $m[1] . '</a>' : $m[0];
    }, $s);
    // 裸图片链接自动渲染为图片（URL 以常见图片扩展名结尾，支持带查询参数）
    $s = preg_replace_callback('~(^|[\s>(\[])(https?://[^\s<>"\')\]]+\.(?:jpg|jpeg|png|gif|webp|avif|bmp|svg|webp)(?:[?#][^\s<>"\')\]]*)?)~i', function ($m) {
        $u = md_safe_url($m[2]);
        return $u !== null ? $m[1] . '<img src="' . $u . '" alt="" loading="lazy" style="max-width:100%;border-radius:8px">' : $m[0];
    }, $s);
    $s = preg_replace_callback('#(^|[\s>(\[])(https?://[^\s<>"\')\]]+)#i', function ($m) {
        return $m[1] . '<a href="' . $m[2] . '" target="_blank" rel="noopener">' . $m[2] . '</a>';
    }, $s);
    return $s;
}
function md_render(?string $text): string {
    $text = str_replace(["\r\n", "\r"], "\n", (string)$text);
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $lines = explode("\n", $text);
    $out = '';
    $inCode = false; $inList = false; $inQuote = false;
    $i = 0; $n = count($lines);
    while ($i < $n) {
        $line = $lines[$i];
        $trim = trim($line);
        if (str_starts_with($trim, '```')) {
            if ($inCode) { $out .= "</code></pre>\n"; $inCode = false; }
            else { $out .= "<pre><code>"; $inCode = true; }
            $i++; continue;
        }
        if ($inCode) { $out .= $line . "\n"; $i++; continue; }
        if ($trim === '') { if ($inList) { $out .= "</ul>\n"; $inList = false; } if ($inQuote) { $out .= "</blockquote>\n"; $inQuote = false; } $out .= "\n"; $i++; continue; }
        if (preg_match('/^(#{1,4})\s+(.*)$/', $line, $m)) {
            if ($inList) { $out .= "</ul>\n"; $inList = false; } if ($inQuote) { $out .= "</blockquote>\n"; $inQuote = false; }
            $l = strlen($m[1]);
            $out .= "<h$l>" . md_inline($m[2]) . "</h$l>\n";
            $i++; continue;
        }
        if (preg_match('/^&gt;\s?(.*)$/', $line, $m)) {
            if ($inList) { $out .= "</ul>\n"; $inList = false; }
            if (!$inQuote) { $out .= "<blockquote>\n"; $inQuote = true; }
            $out .= md_inline($m[1]) . "<br>\n";
            $i++; continue;
        }
        if (preg_match('/^[-*]\s+(.*)$/', $line, $m)) {
            if ($inQuote) { $out .= "</blockquote>\n"; $inQuote = false; }
            if (!$inList) { $out .= "<ul>\n"; $inList = true; }
            $out .= "<li>" . md_inline($m[1]) . "</li>\n";
            $i++; continue;
        }
        if ($inList) { $out .= "</ul>\n"; $inList = false; }
        if ($inQuote) { $out .= "</blockquote>\n"; $inQuote = false; }
        $out .= md_inline($line) . "<br>\n";
        $i++;
    }
    if ($inCode) $out .= "</code></pre>";
    if ($inList) $out .= "</ul>";
    if ($inQuote) $out .= "</blockquote>";
    return $out;
}

// 底部备案渲染：先转义防 XSS，再自动给 ICP/公网安备号加跳转链接，其余支持行内 Markdown 语法
function beian_render(?string $text): string {
    $s = htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
    // 公网安备号 → 公安备案查询
    $s = preg_replace_callback('/(公网安备\s*\d+号?)/u', function ($m) {
        $code = preg_replace('/\D/', '', $m[1]);
        return '<a href="https://beian.mps.gov.cn/#/query/webSearch?code=' . $code . '" target="_blank" rel="noopener">' . $m[1] . '</a>';
    }, $s);
    // ICP 备案号 → 工信部备案查询
    $s = preg_replace_callback('/([\x{4e00}-\x{9fa5}]{0,6}ICP备\s*\d+号?)/ui', function ($m) {
        return '<a href="https://beian.miit.gov.cn/" target="_blank" rel="noopener">' . $m[1] . '</a>';
    }, $s);
    return md_inline($s);
}

// 仅前台页面记录访客，避免管理后台操作混入访客数据
if (stripos($_SERVER['SCRIPT_FILENAME'] ?? '', '/admin/') === false) {
    visitor_log();
}