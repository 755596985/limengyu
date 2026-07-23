<?php
/**
 * 数据迁移脚本：从 JSON 文件迁移到 MySQL
 * 访问 migrate.php 执行，完成后建议删除
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
header('Content-Type: text/plain; charset=utf-8');

echo "=== 情侣小窝 数据迁移 ===\n\n";

try {
    require __DIR__ . '/include/bootstrap.php';
    echo "[1] bootstrap 加载成功\n";
    db()->query('SELECT 1');
    echo "[2] 数据库连接成功\n";
} catch (Throwable $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
}

function load_json_file(string $file) {
    if (!file_exists($file)) return null;
    $raw = file_get_contents($file);
    $raw = preg_replace('/^<\?php[^\n]*\n/', '', $raw);
    $raw = trim($raw);
    if ($raw === '' || $raw === 'null') return null;
    $j = json_decode($raw, true);
    if ($j === null && json_last_error() !== JSON_ERROR_NONE) {
        echo "  WARN: JSON 解析失败 $file : " . json_last_error_msg() . "\n";
        return null;
    }
    return $j;
}

$dd = __DIR__ . '/data/';

// 1. 建表
echo "[3] 执行 schema.sql ...\n";
$schemaFile = __DIR__ . '/install/schema.sql';
if (!file_exists($schemaFile)) {
    echo "FATAL: 找不到 install/schema.sql\n";
    exit(1);
}
$schema = file_get_contents($schemaFile);
$statements = preg_split('/;\s*[\r\n]+/', $schema);
foreach ($statements as $sql) {
    $sql = trim($sql);
    if ($sql === '' || preg_match('/^(--|#|\/\*)/', $sql)) continue;
    if (stripos($sql, 'SET NAMES') === 0 || stripos($sql, 'SET FOREIGN_KEY') === 0) {
        try { db()->exec($sql); } catch (Exception $e) {}
        continue;
    }
    try {
        db()->exec($sql);
        $preview = preg_replace('/\s+/', ' ', substr($sql, 0, 60));
        echo "  OK: $preview...\n";
    } catch (Exception $e) {
        echo "  WARN: " . $e->getMessage() . "\n";
        echo "    SQL: " . substr($sql, 0, 80) . "\n";
    }
}

// 2. 迁移 admin_pass
echo "[4] 迁移 admin ...\n";
$admin = load_json_file($dd . 'admin_pass.php');
if ($admin && !empty($admin['password'])) {
    $st = db()->prepare('INSERT INTO cp_admin (id,username,password) VALUES (1,?,?) ON DUPLICATE KEY UPDATE username=VALUES(username), password=VALUES(password)');
    $st->execute([$admin['username'] ?? 'admin', $admin['password']]);
    echo "  admin 已写入\n";
} else {
    admin_get();
    echo "  使用默认 admin/admin123\n";
}

// 3. 迁移 config
echo "[5] 迁移 config ...\n";
$cfg = load_json_file($dd . 'config.php');
if ($cfg) {
    save_config($cfg);
    echo "  config 已写入\n";
} else {
    echo "  跳过（无文件）\n";
}

// 4. 迁移 about
echo "[6] 迁移 about ...\n";
$ab = load_json_file($dd . 'about.php');
if ($ab) {
    save_about($ab);
    echo "  about 已写入\n";
} else {
    echo "  跳过（无文件）\n";
}

// 5. 迁移 visit
echo "[7] 迁移 visit ...\n";
$vis = load_json_file($dd . 'visit.php');
if ($vis) {
    db()->prepare('UPDATE cp_visit SET total=?, today=?, visit_date=? WHERE id=1')
        ->execute([(int)($vis['total'] ?? 0), (int)($vis['today'] ?? 0), $vis['date'] ?? date('Y-m-d')]);
    echo "  visit 已写入\n";
} else {
    echo "  跳过（无文件）\n";
}

// 6. 迁移 users
echo "[8] 迁移 users ...\n";
$users = load_json_file($dd . 'users.php');
$n = 0;
if (is_array($users)) {
    $st = db()->prepare('INSERT IGNORE INTO cp_users (id,username,password,nickname,avatar,avatar_color,created_at) VALUES (?,?,?,?,?,?,?)');
    foreach ($users as $u) {
        if (empty($u['id']) || empty($u['username'])) continue;
        $st->execute([
            $u['id'], $u['username'], $u['password'] ?? '',
            $u['nickname'] ?? $u['username'], $u['avatar'] ?? '',
            $u['avatar_color'] ?? '#d4786e', $u['created_at'] ?? date('Y-m-d H:i:s'),
        ]);
        $n++;
    }
}
echo "  users: $n 条\n";

// 7. 迁移 posts
echo "[9] 迁移 posts ...\n";
$posts = load_json_file($dd . 'posts.php');
$n = 0;
if (is_array($posts)) {
    $st = db()->prepare('INSERT IGNORE INTO cp_posts (id,title,tags,content,author,mood,created_at,images,video,music,ip,location,user_id,user_nick,user_color) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($posts as $p) {
        if (empty($p['id'])) $p['id'] = new_id();
        $st->execute([
            $p['id'], $p['title'] ?? '', json_col($p['tags'] ?? []),
            $p['content'] ?? '', $p['author'] ?? '1', $p['mood'] ?? '💕',
            $p['time'] ?? date('Y-m-d H:i:s'), json_col($p['images'] ?? []),
            $p['video'] ?? '', $p['music'] ?? '', $p['ip'] ?? '',
            $p['location'] ?? '', $p['user_id'] ?? null,
            $p['user_nick'] ?? null, $p['user_color'] ?? null,
        ]);
        $n++;
    }
}
echo "  posts: $n 条\n";

// 8. 迁移 comments
echo "[10] 迁移 comments ...\n";
$cmts = load_json_file($dd . 'comments.php');
$n = 0;
if (is_array($cmts)) {
    $st = db()->prepare('INSERT IGNORE INTO cp_comments (id,post_id,nick,text,ip,user_id,created_at) VALUES (?,?,?,?,?,?,?)');
    foreach ($cmts as $c) {
        if (empty($c['id'])) $c['id'] = new_id();
        $st->execute([
            $c['id'], $c['post_id'] ?? '', $c['nick'] ?? '', $c['text'] ?? '',
            $c['ip'] ?? '', $c['user_id'] ?? null, $c['time'] ?? date('Y-m-d H:i:s'),
        ]);
        $n++;
    }
}
echo "  comments: $n 条\n";

// 9. 迁移 photos
echo "[11] 迁移 photos ...\n";
$photos = load_json_file($dd . 'photos.php');
$n = 0;
if (is_array($photos)) {
    $st = db()->prepare('INSERT IGNORE INTO cp_photos (id,url,title,created_at) VALUES (?,?,?,?)');
    foreach ($photos as $p) {
        if (empty($p['id'])) $p['id'] = new_id();
        $st->execute([$p['id'], $p['url'] ?? '', $p['title'] ?? '', $p['time'] ?? date('Y-m-d H:i:s')]);
        $n++;
    }
}
echo "  photos: $n 条\n";

// 10. 迁移 places
echo "[12] 迁移 places ...\n";
$places = load_json_file($dd . 'places.php');
$n = 0;
if (is_array($places)) {
    $st = db()->prepare('INSERT IGNORE INTO cp_places (id,name,note,image,created_at) VALUES (?,?,?,?,?)');
    foreach ($places as $p) {
        if (empty($p['id'])) $p['id'] = new_id();
        $st->execute([$p['id'], $p['name'] ?? '', $p['note'] ?? '', $p['image'] ?? '', $p['time'] ?? date('Y-m-d H:i:s')]);
        $n++;
    }
}
echo "  places: $n 条\n";

// 11. 迁移 todos
echo "[13] 迁移 todos ...\n";
$todos = load_json_file($dd . 'todos.php');
$n = 0;
if (is_array($todos)) {
    $st = db()->prepare('INSERT IGNORE INTO cp_todos (id,title,note,done,done_time,created_at) VALUES (?,?,?,?,?,?)');
    foreach ($todos as $t) {
        if (empty($t['id'])) $t['id'] = new_id();
        $st->execute([
            $t['id'], $t['title'] ?? '', $t['note'] ?? '',
            !empty($t['done']) ? 1 : 0, $t['done_time'] ?? '',
            $t['time'] ?? date('Y-m-d H:i:s'),
        ]);
        $n++;
    }
}
echo "  todos: $n 条\n";

// 12. 迁移 pages
echo "[14] 迁移 pages ...\n";
$pages = load_json_file($dd . 'pages.php');
$n = 0;
if (is_array($pages)) {
    $st = db()->prepare('INSERT IGNORE INTO cp_pages (id,title,slug,icon,content,sort,created_at) VALUES (?,?,?,?,?,?,?)');
    foreach ($pages as $p) {
        if (empty($p['id'])) $p['id'] = new_id();
        $st->execute([
            $p['id'], $p['title'] ?? '', $p['slug'] ?? ('page' . $n), $p['icon'] ?? '📄',
            $p['content'] ?? '', (int)($p['sort'] ?? 99), $p['time'] ?? date('Y-m-d H:i:s'),
        ]);
        $n++;
    }
}
echo "  pages: $n 条\n";

// 统计
echo "\n=== 迁移完成 · 数据统计 ===\n\n";
$counts = [
    'admin' => (int)db()->query('SELECT COUNT(*) FROM cp_admin')->fetchColumn(),
    'config' => (int)db()->query('SELECT COUNT(*) FROM cp_config')->fetchColumn(),
    'users' => (int)db()->query('SELECT COUNT(*) FROM cp_users')->fetchColumn(),
    'posts' => (int)db()->query('SELECT COUNT(*) FROM cp_posts')->fetchColumn(),
    'comments' => (int)db()->query('SELECT COUNT(*) FROM cp_comments')->fetchColumn(),
    'photos' => (int)db()->query('SELECT COUNT(*) FROM cp_photos')->fetchColumn(),
    'places' => (int)db()->query('SELECT COUNT(*) FROM cp_places')->fetchColumn(),
    'todos' => (int)db()->query('SELECT COUNT(*) FROM cp_todos')->fetchColumn(),
    'pages' => (int)db()->query('SELECT COUNT(*) FROM cp_pages')->fetchColumn(),
];
foreach ($counts as $k => $v) echo sprintf("%-10s %d 条\n", $k, $v);
echo "\n请先确认首页正常，再删除 migrate.php 和 debug.php。\n";
echo "首页: /\n";
