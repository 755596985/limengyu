<?php
require __DIR__ . '/../include/bootstrap.php';
require_csrf();

if (!isset($_SESSION['cp_admin'])) { header('Location: index.php'); exit; }

$ROOT = dirname(__DIR__);
$UPLOAD_DIR = $ROOT . '/uploads/';

$config = get_config();
$posts  = posts_all();
$places = places_all();
$todos  = todos_all();
$photos = photos_all();
$pages    = pages_all();
$comments = comments_all();
$users    = users_all();
$about    = get_about();
$admin_saved = admin_get();
$filter_words = filter_words_get();
$visitors = visitors_get();

$n1 = $config['name1'] ?? '男神';
$n2 = $config['name2'] ?? '女神';
$av1 = $config['avatar1'] ?? '';
$av2 = $config['avatar2'] ?? '';

$tab = $_GET['tab'] ?? 'posts';
$message = $error = '';

$IMG_EXT = ['jpg','jpeg','png','gif','webp'];
$IMG_MIME = ['image/jpeg','image/png','image/gif','image/webp'];
$VID_EXT = ['mp4','webm','mov','avi','mkv'];
$VID_MIME = ['video/mp4','video/webm','video/quicktime','video/x-msvideo','video/x-matroska'];
$AUD_EXT = ['mp3','wav','ogg','m4a','aac','flac'];
$AUD_MIME = ['audio/mpeg','audio/wav','audio/ogg','audio/mp4','audio/aac','audio/flac'];

function handle_uploads_db(string $key, string $dir): array {
    return safe_upload_multi($key, $dir, ['jpg','jpeg','png','gif','webp'], ['image/jpeg','image/png','image/gif','image/webp']);
}
function handle_single_upload_db(string $key, string $dir, array $exts, array $mimes): string {
    return safe_upload_one($key, $dir, $exts, $mimes);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    if ($act === 'save_post') {
        $id = $_POST['id'] ?? '';
        $content = trim($_POST['content'] ?? '');
        $title   = trim($_POST['title'] ?? '');
        $tags    = trim($_POST['tags'] ?? '');
        $tagArr = $tags ? array_map('trim', explode(',', $tags)) : [];
        $custom_date = trim($_POST['custom_date'] ?? '');
        $custom_time = trim($_POST['custom_time'] ?? '');
        if ($custom_date && $custom_time) {
            $post_time = $custom_date . ' ' . $custom_time . ':00';
        } elseif ($custom_date) {
            $post_time = $custom_date . ' ' . date('H:i:s');
        } else {
            $post_time = date('Y-m-d H:i:s');
        }
        $manual_location = trim($_POST['location'] ?? '');
        if (empty($content)) { $error = '内容不能为空！'; }
        else {
            $imgs = handle_uploads_db('images', $UPLOAD_DIR);
            $video = handle_single_upload_db('video', $UPLOAD_DIR, $VID_EXT, $VID_MIME);
            $music = handle_single_upload_db('music', $UPLOAD_DIR, $AUD_EXT, $AUD_MIME);
            $ip = client_ip();
            $location = $manual_location !== '' ? $manual_location : resolve_location($ip);
            if ($id !== '') {
                $idx = intval($id);
                $all = posts_all();
                if (isset($all[$idx])) {
                    $cur = $all[$idx];
                    $mergedImages = array_merge(json_arr($cur['images'] ?? []), $imgs);
                    $mergedVideo = $video ?: ($cur['video'] ?? '');
                    $mergedMusic = $music ?: ($cur['music'] ?? '');
                    // 替换视频/音乐时删除旧文件
                    if ($video && !empty($cur['video']) && $cur['video'] !== $video) {
                        safe_unlink_under($ROOT, $cur['video']);
                    }
                    if ($music && !empty($cur['music']) && $cur['music'] !== $music) {
                        safe_unlink_under($ROOT, $cur['music']);
                    }
                    post_update_by_index($idx, [
                        'title' => $title,
                        'tags' => $tagArr,
                        'content' => $content,
                        'author' => $_POST['author'] ?? '1',
                        'mood' => $_POST['mood'] ?? '💕',
                        'time' => $post_time,
                        'images' => $mergedImages,
                        'video' => $mergedVideo,
                        'music' => $mergedMusic,
                        'location' => $location,
                    ]);
                }
            } else {
                post_insert([
                    'id' => new_id(),
                    'title' => $title,
                    'tags' => $tagArr,
                    'content' => $content,
                    'author' => $_POST['author'] ?? '1',
                    'mood' => $_POST['mood'] ?? '💕',
                    'time' => $post_time,
                    'images' => $imgs,
                    'video' => $video,
                    'music' => $music,
                    'ip' => $ip,
                    'location' => $location,
                ]);
            }
            $message = '说说已保存！';
        }
    }
    if ($act === 'delete_post') {
        $idx = intval($_POST['id'] ?? -1);
        if (post_delete_by_index($idx, $ROOT)) {
            $message = '已删除！';
        }
    }
    if ($act === 'save_photo') {
        $title = trim($_POST['title'] ?? '');
        $imgs = handle_uploads_db('photo', $UPLOAD_DIR);
        if (empty($imgs)) { $error = '请选择照片！'; }
        else {
            foreach ($imgs as $url) {
                photo_insert(['id' => new_id(), 'url' => $url, 'title' => $title, 'time' => date('Y-m-d H:i:s')]);
            }
            $message = '照片已添加！';
        }
    }
    if ($act === 'delete_photo') {
        $idx = intval($_POST['id'] ?? -1);
        if (photo_delete_by_index($idx, $ROOT)) {
            $message = '照片已删除！';
        }
    }
    if ($act === 'save_place') {
        $name = trim($_POST['name'] ?? '');
        if (empty($name)) { $error='请输入地点名称！'; }
        else {
            $imgs = handle_uploads_db('place_image', $UPLOAD_DIR);
            place_insert([
                'id' => new_id(), 'name' => $name,
                'note' => trim($_POST['note'] ?? ''),
                'image' => $imgs[0] ?? '',
                'time' => date('Y-m-d H:i:s'),
            ]);
            $message = '地点已记录！';
        }
    }
    if ($act === 'delete_place') {
        $idx = intval($_POST['id'] ?? -1);
        if (place_delete_by_index($idx, $ROOT)) {
            $message = '已删除！';
        }
    }
    if ($act === 'save_todo') {
        $title = trim($_POST['title'] ?? '');
        if (empty($title)) { $error='请输入事项！'; }
        else {
            todo_insert(['id' => new_id(), 'title' => $title, 'note' => trim($_POST['note'] ?? ''), 'time' => date('Y-m-d H:i:s')]);
            $message = '事项已添加！';
        }
    }
    if ($act === 'toggle_todo') {
        $idx = intval($_POST['id'] ?? -1);
        todo_toggle_by_index($idx);
    }
    if ($act === 'delete_todo') {
        $idx = intval($_POST['id'] ?? -1);
        if (todo_delete_by_index($idx)) {
            $message = '已删除！';
        }
    }
    if ($act === 'save_page') {
        $pid = $_POST['pid'] ?? '';
        $page_title = trim($_POST['page_title'] ?? '');
        $page_slug  = trim($_POST['page_slug'] ?? '');
        $page_icon  = trim($_POST['page_icon'] ?? '📄');
        $page_content = $_POST['page_content'] ?? '';
        $page_sort  = intval($_POST['page_sort'] ?? 99);
        if (empty($page_title) || empty($page_slug)) { $error = '标题和标识不能为空！'; }
        else {
            if ($pid !== '') {
                page_update_by_index(intval($pid), [
                    'title' => $page_title, 'slug' => $page_slug,
                    'icon' => $page_icon, 'content' => $page_content,
                    'sort' => $page_sort, 'time' => date('Y-m-d H:i:s'),
                ]);
            } else {
                page_insert([
                    'id' => new_id(), 'title' => $page_title, 'slug' => $page_slug,
                    'icon' => $page_icon, 'content' => $page_content,
                    'sort' => $page_sort, 'time' => date('Y-m-d H:i:s'),
                ]);
            }
            $message = '页面已保存！';
        }
    }
    if ($act === 'delete_page') {
        $idx = intval($_POST['id'] ?? -1);
        if (page_delete_by_index($idx)) {
            $message = '页面已删除！';
        }
    }
    if ($act === 'delete_comment') {
        $idx = intval($_POST['id'] ?? -1);
        if (comment_delete_by_index($idx)) {
            $message = '留言已删除！';
        }
    }
    if ($act === 'delete_user') {
        $idx = intval($_POST['id'] ?? -1);
        $allUsers = users_all();
        if (isset($allUsers[$idx])) {
            $uid = $allUsers[$idx]['id'];
            $userPosts = posts_by_user($uid);
            foreach ($userPosts as $up) {
                post_delete_by_id($up['id'], $uid, $ROOT);
            }
            if (!empty($allUsers[$idx]['avatar'])) {
                safe_unlink_under($ROOT, $allUsers[$idx]['avatar']);
            }
            if (user_delete_by_id($uid)) {
                $message = '用户及其说说已删除！';
            }
        }
    }
    if ($act === 'save_config') {
        $config['name1'] = trim($_POST['name1'] ?? '男神');
        $config['name2'] = trim($_POST['name2'] ?? '女神');
        $config['love_date'] = trim($_POST['love_date'] ?? '2024-01-01');
        $config['site_title'] = trim($_POST['site_title'] ?? '');
        $config['beian'] = trim($_POST['beian'] ?? '');
        $av = handle_uploads_db('avatar1', $UPLOAD_DIR); if (!empty($av)) $config['avatar1'] = $av[0];
        $av = handle_uploads_db('avatar2', $UPLOAD_DIR); if (!empty($av)) $config['avatar2'] = $av[0];
        $bg = handle_uploads_db('background_image', $UPLOAD_DIR); if(!empty($bg)) $config['background_image'] = $bg[0];
        if(isset($_POST['delete_background']) && $_POST['delete_background'] == '1') {
            if(!empty($config['background_image'])) {
                safe_unlink_under($ROOT, $config['background_image']);
            }
            $config['background_image'] = '';
        }
        save_config($config);
        $n1 = $config['name1']; $n2 = $config['name2']; $av1 = $config['avatar1']??''; $av2 = $config['avatar2']??'';
        $message = '设置已保存！';
    }
    if ($act === 'save_about') {
        $about['version'] = trim($_POST['version'] ?? '');
        $about['version_desc'] = trim($_POST['version_desc'] ?? '');
        $about['boy_name'] = trim($_POST['boy_name'] ?? '');
        $about['boy_intro'] = trim($_POST['boy_intro'] ?? '');
        $about['girl_name'] = trim($_POST['girl_name'] ?? '');
        $about['girl_intro'] = trim($_POST['girl_intro'] ?? '');
        $av = handle_uploads_db('boy_avatar', $UPLOAD_DIR); if (!empty($av)) $about['boy_avatar_url'] = $av[0];
        $av = handle_uploads_db('girl_avatar', $UPLOAD_DIR); if (!empty($av)) $about['girl_avatar_url'] = $av[0];
        save_about($about);
        $message = '关于页面已保存！';
    }
    if ($act === 'change_password') {
        $old = $_POST['old_password'] ?? ''; $new = $_POST['new_password'] ?? ''; $new_user = trim($_POST['new_username'] ?? '');
        $saved = admin_get();
        if (!$saved || !password_verify($old, $saved['password']??'')) $error='原密码错误！';
        elseif ($new && strlen($new)<4) $error='新密码至少4位！';
        elseif ($new && $new !== ($_POST['confirm_password']??'')) $error='两次密码不一致！';
        elseif ($new_user && strlen($new_user)<2) $error='账号至少2个字符！';
        else {
            $final_user = $new_user ?: ($saved['username'] ?? 'admin');
            $hash = $new ? password_hash($new, PASSWORD_DEFAULT) : null;
            admin_save($final_user, $hash);
            $message = '账号密码修改成功！';
        }
    }
    // === 文件管理 ===
    if ($act === 'upload_file') {
        $target_rel = trim($_POST['dir'] ?? '');
        $target = rtrim($ROOT, '/') . '/' . ltrim($target_rel, '/');
        $targetReal = realpath($target);
        $rootReal = realpath($ROOT);
        if (!$targetReal || strpos($targetReal, $rootReal) !== 0) {
            $error = '无效的目录';
        } elseif (empty($_FILES['file']['name'])) {
            $error = '请选择文件';
        } else {
            $fname = basename($_FILES['file']['name']);
            $fext = strtolower(pathinfo($fname, PATHINFO_EXTENSION));
            $blocked_exts = ['php','phtml','php3','php4','php5','php7','php8','phps','phar','shtml','cgi','pl','py','rb','sh','asp','aspx','jsp','exe','bat','cmd','com','dll','so','htaccess'];
            if (in_array($fext, $blocked_exts, true)) {
                $error = '禁止上传可执行文件 (' . $fext . ')';
            } else {
                $dest = $targetReal . '/' . $fname;
                if (move_uploaded_file($_FILES['file']['tmp_name'], $dest)) {
                    $message = '文件上传成功';
                } else {
                    $error = '上传失败';
                }
            }
        }
    }
    if ($act === 'delete_file') {
        $file_rel = trim($_POST['file'] ?? '');
        $fileReal = realpath($ROOT . '/' . ltrim($file_rel, '/'));
        $rootReal = realpath($ROOT);
        if ($fileReal && strpos($fileReal, $rootReal) === 0 && is_file($fileReal)) {
            if (unlink($fileReal)) $message = '文件已删除';
            else $error = '删除失败';
        } else {
            $error = '无效的文件';
        }
    }
    if ($act === 'delete_dir') {
        $dir_rel = trim($_POST['dir'] ?? '');
        $dirReal = realpath($ROOT . '/' . ltrim($dir_rel, '/'));
        $rootReal = realpath($ROOT);
        if ($dirReal && strpos($dirReal, $rootReal) === 0 && is_dir($dirReal)) {
            $files = array_diff(scandir($dirReal), ['.', '..']);
            if (!empty($files)) {
                $error = '目录不为空，请先删除内部文件';
            } elseif (rmdir($dirReal)) {
                $message = '目录已删除';
            } else {
                $error = '删除目录失败';
            }
        } else {
            $error = '无效的目录';
        }
    }
    if ($act === 'save_file') {
        $file_rel = trim($_POST['file'] ?? '');
        $file_content = $_POST['content'] ?? '';
        $fileReal = realpath($ROOT . '/' . ltrim($file_rel, '/'));
        $rootReal = realpath($ROOT);
        if ($fileReal && strpos($fileReal, $rootReal) === 0 && is_file($fileReal)) {
            if (file_put_contents($fileReal, $file_content) !== false) $message = '文件已保存';
            else $error = '保存失败';
        } else {
            $error = '无效的文件';
        }
    }
    if ($act === 'mkdir_file') {
        $target_rel = trim($_POST['dir'] ?? '');
        $dirname = trim($_POST['dirname'] ?? '');
        if (empty($dirname)) {
            $error = '请输入目录名';
        } else {
            $target = rtrim($ROOT, '/') . '/' . ltrim($target_rel, '/');
            $targetReal = realpath($target);
            $rootReal = realpath($ROOT);
            if ($targetReal && strpos($targetReal, $rootReal) === 0) {
                $newDir = $targetReal . '/' . basename($dirname);
                if (!is_dir($newDir)) {
                    if (mkdir($newDir, 0755, true)) $message = '目录已创建';
                    else $error = '创建目录失败';
                } else {
                    $error = '目录已存在';
                }
            } else {
                $error = '无效的目录';
            }
        }
    }

    // === 敏感词管理 ===
    if ($act === 'clear_visitors') {
        visitors_save([]);
        $message = '访客记录已清空';
    }
    if ($act === 'add_word') {
        $word = trim($_POST['word'] ?? '');
        if (empty($word)) $error = '请输入敏感词';
        else {
            $words = filter_words_get();
            if (!in_array($word, $words)) {
                $words[] = $word;
                filter_words_save($words);
                $message = '敏感词已添加';
            } else {
                $error = '该敏感词已存在';
            }
        }
    }
    if ($act === 'delete_word') {
        $idx = intval($_POST['id'] ?? -1);
        $words = filter_words_get();
        if (isset($words[$idx])) {
            array_splice($words, $idx, 1);
            filter_words_save($words);
            $message = '敏感词已删除';
        }
    }


    if ($act) {
        $redirect = 'manage.php?tab=' . $tab;
        if ($message) $redirect .= '&msg=' . urlencode($message);
        if ($error) $redirect .= '&err=' . urlencode($error);
        header('Location: ' . $redirect);
        exit;
    }
}
if (isset($_GET['msg'])) $message = $_GET['msg'];
if (isset($_GET['err'])) $error = $_GET['err'];
if (isset($_GET['logout'])) { unset($_SESSION['cp_admin']); session_regenerate_id(true); header('Location: index.php'); exit; }

$posts = posts_all();
$places = places_all();
$todos = todos_all();
$photos = photos_all();
$pages = pages_all();
$comments = comments_all();
$users = users_all();
$about = get_about();
$config = get_config();
$admin_saved = admin_get();
$filter_words = filter_words_get();
$n1 = $config['name1'] ?? '男神';
$n2 = $config['name2'] ?? '女神';
$av1 = $config['avatar1'] ?? '';
$av2 = $config['avatar2'] ?? '';
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>后台 · 情侣小窝</title>
<style>
:root{--bg:#f2ede9;--pri:#d4786e;--tx:#5a4e4a;--tl:#8c7e78}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei',sans-serif;background:#f2ede9;color:var(--tx);min-height:100vh}
.main{padding:16px 14px 90px;max-width:900px;margin:0 auto}
.card{background:#fff;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.06);padding:24px;margin-bottom:20px}
.card-title{font-size:1.05em;font-weight:700;color:var(--tx);margin-bottom:18px}
.msg{padding:12px 18px;border-radius:10px;margin-bottom:16px;font-size:.88em}
.msg.success{background:#e8f5e9;color:#2e7d32}
.msg.error{background:#ffebee;color:#c62828}
.fg{margin-bottom:16px}
.fg label{display:block;font-size:.82em;color:var(--tl);font-weight:600;margin-bottom:6px}
.neo{width:100%;padding:11px 15px;background:#f5f1ee;border:none;border-radius:10px;box-shadow:inset 2px 2px 6px rgba(0,0,0,0.04);font-size:.92em;color:var(--tx);outline:none;font-family:inherit}
.neo:focus{box-shadow:inset 1px 1px 4px rgba(0,0,0,0.06)}
textarea.neo{min-height:90px;resize:vertical}
select.neo{cursor:pointer}
.btn{padding:10px 22px;border:none;border-radius:10px;font-size:.9em;font-weight:700;cursor:pointer;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.06);color:var(--tx);transition:all .2s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.btn:active{transform:scale(.97)}
.btn.primary{color:var(--pri)}
.btn.danger{color:#c0392b}
.btn.small{padding:6px 14px;font-size:.78em;border-radius:8px}
.btn-group{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
.list-item{display:flex;align-items:flex-start;gap:14px;padding:16px 0;border-bottom:1px solid rgba(0,0,0,.04)}
.list-item:last-child{border-bottom:none}
.list-item .item-info{flex:1;min-width:0}
.list-item .item-title{font-weight:700;font-size:.93em}
.list-item .item-meta{font-size:.75em;color:var(--tl);margin-top:3px}
.list-item .item-body{font-size:.85em;color:var(--tl);margin-top:4px;word-break:break-word}
.list-item .item-imgs{display:flex;gap:4px;margin-top:6px;flex-wrap:wrap}
.list-item .item-imgs img{width:50px;height:50px;object-fit:cover;border-radius:6px}
.mood-picker{display:flex;gap:6px;flex-wrap:wrap}
.mood-picker label{cursor:pointer;padding:6px 12px;border-radius:20px;box-shadow:0 2px 6px rgba(0,0,0,0.06);font-size:1.1em;transition:all .2s}
.mood-picker input[type=radio]{display:none}
.mood-picker label:has(input:checked){box-shadow:inset 1px 1px 4px rgba(0,0,0,0.06);background:rgba(212,120,110,.08)}
.tag-badge{display:inline-block;background:rgba(212,120,110,.1);color:var(--pri);font-size:.68em;padding:2px 8px;border-radius:10px;margin-right:4px;margin-top:4px}

.bnav{position:fixed;bottom:16px;left:50%;transform:translateX(-50%);background:#fff;border-radius:26px;box-shadow:0 4px 20px rgba(0,0,0,0.1),0 2px 6px rgba(0,0,0,0.04);display:flex;padding:6px 10px;z-index:100;gap:0;overflow-x:auto;max-width:95vw;-webkit-overflow-scrolling:touch;scrollbar-width:none}
.bnav::-webkit-scrollbar{display:none}
.bnav a{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:7px 13px;border-radius:20px;text-decoration:none;color:#888;transition:all .2s;min-width:50px;font-weight:500;flex-shrink:0}
.bnav a .ni{font-size:1.35em;line-height:1;margin-bottom:2px}
.bnav a .nl{white-space:nowrap}
.bnav a.active{color:#e85d5d;font-weight:700}
.bnav a:active{transform:scale(.94)}
@media(min-width:600px){.bnav a{padding:9px 18px}}
.bnav a[href*="tab=posts"] .ni{color:#5c9ce6}
.bnav a[href*="tab=album"] .ni{color:#4da6ff}
.bnav a[href*="tab=places"] .ni{color:#e8553d}
.bnav a[href*="tab=todos"] .ni{color:#5cb85c}
.bnav a[href*="tab=config"] .ni{color:#f0ad4e}
.bnav a[href*="tab=password"] .ni{color:#888}
.bnav a[href*="tab=about"] .ni{color:#aaa}
.bnav a[href*="tab=pages"] .ni{color:#9b59b6}
.bnav a[href*="tab=comments"] .ni{color:#e67e22}
.bnav a[href="../"] .ni{color:#e85d5d}
.bnav a[href*="tab=files"] .ni{color:#3498db}
.bnav a[href*="tab=visitors"] .ni{color:#27ae60}
.bnav a[href*="tab=filter"] .ni{color:#e74c3c}

@media(max-width:768px){.main{padding:12px 10px 80px}.card{padding:16px;border-radius:12px}}
@media(max-width:600px){.about-flex{flex-direction:column;gap:12px}.about-flex .fg{margin-bottom:12px}.bnav a{min-width:44px;padding:6px 8px}.bnav a .ni{font-size:1.15em}.bnav a .nl{font-size:.7em}.card-title{font-size:.95em}.btn{font-size:.82em;padding:8px 16px}}
</style>
<script>
function editPost(idx, mood, content, author, dateStr, timeStr, location, title, tags, hasVideo, hasMusic) {
    document.getElementById('edit_id').value = idx;
    document.getElementById('edit_title').value = title || '';
    document.getElementById('edit_tags').value = tags || '';
    document.getElementById('edit_content').value = content;
    document.getElementById('edit_author').value = author;
    document.getElementById('edit_date').value = dateStr;
    document.getElementById('edit_time').value = timeStr;
    document.getElementById('edit_location').value = location || '';
    var vh = document.getElementById('video_hint');
    if (hasVideo) { vh.style.display = 'block'; vh.textContent = '已有视频: ' + hasVideo; }
    else { vh.style.display = 'none'; vh.textContent = ''; }
    var mh = document.getElementById('music_hint');
    if (hasMusic) { mh.style.display = 'block'; mh.textContent = '已有音乐: ' + hasMusic; }
    else { mh.style.display = 'none'; mh.textContent = ''; }
    var radios = document.querySelectorAll('#edit_mood_picker input[type=radio]');
    for (var i = 0; i < radios.length; i++) {
        radios[i].checked = (radios[i].value === mood);
    }
    document.getElementById('post_form_title').innerHTML = '✏️ 编辑说说 #' + (parseInt(idx)+1);
    document.getElementById('submit_btn').innerHTML = '💾 保存修改';
    document.getElementById('cancel_edit_btn').style.display = 'inline-flex';
    document.getElementById('post_form_title').scrollIntoView({behavior:'smooth'});
}
function cancelEdit() {
    document.getElementById('edit_id').value = '';
    document.getElementById('edit_title').value = '';
    document.getElementById('edit_tags').value = '';
    document.getElementById('edit_content').value = '';
    document.getElementById('edit_author').value = '1';
    document.getElementById('edit_date').value = new Date().toISOString().slice(0,10);
    var now = new Date();
    document.getElementById('edit_time').value = now.getHours().toString().padStart(2,'0') + ':' + now.getMinutes().toString().padStart(2,'0');
    document.getElementById('edit_location').value = '';
    document.getElementById('video_hint').style.display = 'none';
    document.getElementById('music_hint').style.display = 'none';
    var radios = document.querySelectorAll('#edit_mood_picker input[type=radio]');
    if (radios.length > 0) radios[0].checked = true;
    document.getElementById('post_form_title').innerHTML = '✏️ 发布说说';
    document.getElementById('submit_btn').innerHTML = '💕 发布';
    document.getElementById('cancel_edit_btn').style.display = 'none';
}
function editPage(idx, title, slug, icon, content, sortVal) {
    document.getElementById('page_pid').value = idx;
    document.getElementById('page_title').value = title;
    document.getElementById('page_slug').value = slug;
    document.getElementById('page_icon').value = icon;
    document.getElementById('page_content').value = content;
    document.getElementById('page_sort').value = sortVal;
    document.getElementById('page_form_title').innerHTML = '📄 编辑页面';
    document.getElementById('page_submit_btn').innerHTML = '💾 保存修改';
    document.getElementById('page_cancel_btn').style.display = 'inline-flex';
    document.getElementById('page_form_title').scrollIntoView({behavior:'smooth'});
}
function cancelPageEdit() {
    document.getElementById('page_pid').value = '';
    document.getElementById('page_title').value = '';
    document.getElementById('page_slug').value = '';
    document.getElementById('page_icon').value = '📄';
    document.getElementById('page_content').value = '';
    document.getElementById('page_sort').value = '99';
    document.getElementById('page_form_title').innerHTML = '📄 添加自定义页面';
    document.getElementById('page_submit_btn').innerHTML = '📄 保存页面';
    document.getElementById('page_cancel_btn').style.display = 'none';
}
</script>
</head>
<body>
<div class="main">
<?php if ($message): ?><div class="msg success">✅ <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="msg error">❌ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if ($tab === 'posts'): ?>
<div class="card">
<div class="card-title" id="post_form_title">✏️ 发布说说</div>
<form method="post" enctype="multipart/form-data">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="save_post"><input type="hidden" name="id" id="edit_id" value="">
<div class="fg"><label>📌 标题 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(可选，发布文章式说说)</span></label><input type="text" name="title" id="edit_title" class="neo" placeholder="如：我们的第一次旅行"></div>
<div class="fg"><label>🏷️ 标签 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(可选，用逗号分隔)</span></label><input type="text" name="tags" id="edit_tags" class="neo" placeholder="如：旅行, 美食, 日常"></div>
<div class="fg"><label>👤 身份</label><select name="author" id="edit_author" class="neo" style="width:auto"><option value="1"><?php echo htmlspecialchars($n1); ?> 👦</option><option value="2"><?php echo htmlspecialchars($n2); ?> 👧</option></select></div>
<div class="fg"><label>😊 心情</label><div class="mood-picker" id="edit_mood_picker"><?php $moods=['💕'=>'恋爱','😊'=>'开心','😢'=>'难过','😡'=>'生气','😴'=>'困了','🎉'=>'庆祝','🌧️'=>'忧郁','🔥'=>'热情','🥰'=>'幸福','🤔'=>'思考','😎'=>'酷','🥳'=>'嗨皮','🌹'=>'浪漫','✨'=>'奇妙'];$f=true;foreach($moods as $e=>$l):?><label><input type="radio" name="mood" value="<?php echo $e;?>" <?php echo $f?'checked':'';?>><span><?php echo $e;?></span></label><?php $f=false;endforeach;?></div></div>
<div class="fg"><label>💬 内容</label><textarea name="content" id="edit_content" class="neo" rows="3" placeholder="写下你想说的话..." required></textarea></div>
<div style="display:flex;gap:12px">
<div class="fg" style="flex:1"><label>📅 日期 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(留空=当前)</span></label><input type="date" name="custom_date" id="edit_date" class="neo" value="<?php echo date('Y-m-d'); ?>"></div>
<div class="fg" style="flex:1"><label>🕐 时间 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(留空=当前)</span></label><input type="time" name="custom_time" id="edit_time" class="neo" value="<?php echo date('H:i'); ?>"></div>
</div>
<div class="fg"><label>📍 地点 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(可选，留空由IP自动识别)</span></label><input type="text" name="location" id="edit_location" class="neo" placeholder="如：北京 · 朝阳公园"></div>
<div class="fg"><label>📷 图片 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(编辑时重新上传将追加图片)</span></label><input type="file" name="images[]" accept="image/*" multiple></div>
<div class="fg"><label>🎬 视频 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(可选, 支持mp4/webm/mov等)</span></label><input type="file" name="video" accept="video/*"><div id="video_hint" style="font-size:.75em;color:var(--tl);margin-top:4px;display:none"></div></div>
<div class="fg"><label>🎵 音乐 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(可选, 支持mp3/wav/ogg等)</span></label><input type="file" name="music" accept="audio/*"><div id="music_hint" style="font-size:.75em;color:var(--tl);margin-top:4px;display:none"></div></div>
<div class="btn-group"><button type="submit" class="btn primary" id="submit_btn">💕 发布</button><button type="button" class="btn" id="cancel_edit_btn" style="display:none;color:var(--tl)" onclick="cancelEdit()">✕ 取消编辑</button></div>
</form>
</div>
<div class="card"><div class="card-title">📋 说说列表 (<?php echo count($posts);?>)</div>
<?php if(empty($posts)):?><p style="text-align:center;color:var(--tl);padding:30px">还没有说说~</p>
<?php else: foreach($posts as $i=>$po):?>
<div class="list-item">
<div style="font-size:1.5em"><?php echo ($po['author']??'1')==='1'?'👦':'👧';?></div>
<div class="item-info">
<div class="item-title"><?php echo htmlspecialchars($po['mood']??'💕');?> <?php if(!empty($po['title'])):?><span style="color:var(--pri)"><?php echo htmlspecialchars($po['title']);?></span> — <?php endif;?><?php echo htmlspecialchars(mb_substr($po['content'],0,40));?><?php echo mb_strlen($po['content'])>40?'…':'';?></div>
<div class="item-meta"><?php echo htmlspecialchars(($po['author']??'1')==='1'?$n1:$n2);?> · <?php echo htmlspecialchars($po['time']);?><?php if (!empty($po['location']) && $po['location'] !== '未知'): ?> · 📍 <?php echo htmlspecialchars($po['location']); ?><?php endif; ?></div>
<?php if(!empty($po['tags'])):?><div><?php foreach($po['tags'] as $t):?><span class="tag-badge">#<?php echo htmlspecialchars($t);?></span><?php endforeach;?></div><?php endif;?>
<?php if(!empty($po['images'])):?><div class="item-imgs"><?php foreach($po['images'] as $im):?><img src="../<?php echo htmlspecialchars($im);?>" onclick="event.stopPropagation();window.open('../<?php echo htmlspecialchars($im);?>')" style="cursor:pointer"><?php endforeach;?></div><?php endif;?>
<?php if(!empty($po['video'])):?><div style="margin-top:4px;font-size:.78em;color:var(--tl)">🎬 含有视频</div><?php endif;?>
<?php if(!empty($po['music'])):?><div style="margin-top:4px;font-size:.78em;color:var(--tl)">🎵 含有音乐</div><?php endif;?>
</div>
<div style="flex-shrink:0;display:flex;gap:6px">
<button type="button" class="btn small primary" onclick='editPost(<?php echo $i;?>,<?php echo json_encode($po['mood']??'💕');?>,<?php echo json_encode($po['content']);?>,<?php echo json_encode($po['author']??'1');?>,<?php echo json_encode(substr($po['time'],0,10));?>,<?php echo json_encode(substr($po['time'],11,5));?>,<?php echo json_encode($po['location']??'');?>,<?php echo json_encode($po['title']??'');?>,<?php echo json_encode(isset($po['tags'])?implode(', ',$po['tags']):'');?>,<?php echo json_encode(!empty($po['video'])?basename($po['video']):'');?>,<?php echo json_encode(!empty($po['music'])?basename($po['music']):'');?>)' title="编辑">✏️</button>
<form method="post" onsubmit="return confirm('确定删除？')" style="display:inline"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_post"><input type="hidden" name="id" value="<?php echo $i;?>"><button type="submit" class="btn small danger">删除</button></form>
</div>
</div>
<?php endforeach; endif;?>
</div>
<?php endif; /* posts */ ?>

<?php if ($tab === 'album'): ?>
<div class="card"><div class="card-title">📤 上传照片</div>
<form method="post" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="act" value="save_photo">
<div class="fg"><label>🏷️ 描述</label><input type="text" name="title" class="neo" placeholder="例如：第一次约会"></div>
<div class="fg"><label>📷 照片</label><input type="file" name="photo[]" accept="image/*" multiple required></div>
<div class="btn-group"><button type="submit" class="btn primary">📷 上传</button></div></form></div>
<div class="card"><div class="card-title">🖼️ 相册 (<?php echo count($photos);?>张)</div>
<?php if(empty($photos)):?><p style="text-align:center;color:var(--tl);padding:30px">相册空的~</p>
<?php else:?><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:12px">
<?php foreach($photos as $i=>$ph):?>
<div style="position:relative;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06)">
<img src="../<?php echo htmlspecialchars($ph['url']);?>" style="width:100%;aspect-ratio:1;object-fit:cover">
<?php if(!empty($ph['title'])):?><div style="position:absolute;bottom:0;left:0;right:0;padding:6px 10px;background:linear-gradient(transparent,rgba(0,0,0,.5));color:#fff;font-size:.75em"><?php echo htmlspecialchars($ph['title']);?></div><?php endif;?>
<form method="post" onsubmit="return confirm('删除？')" style="position:absolute;top:6px;right:6px"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_photo"><input type="hidden" name="id" value="<?php echo $i;?>"><button type="submit" style="width:26px;height:26px;border-radius:50%;border:none;background:rgba(0,0,0,.5);color:#fff;cursor:pointer">✕</button></form>
</div>
<?php endforeach;?></div><?php endif;?></div>
<?php endif; /* album */ ?>

<?php if ($tab === 'places'): ?>
<div class="card"><div class="card-title">📍 记录地点</div>
<form method="post" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="act" value="save_place">
<div class="fg"><label>🗺️ 地点 *</label><input type="text" name="name" class="neo" placeholder="三亚·天涯海角" required></div>
<div class="fg"><label>📝 感想</label><textarea name="note" class="neo" rows="2" placeholder="那天..."></textarea></div>
<div class="fg"><label>📷 照片</label><input type="file" name="place_image[]" accept="image/*"></div>
<div class="btn-group"><button type="submit" class="btn primary">📍 记录</button></div></form></div>
<div class="card"><div class="card-title">🗺️ 足迹 (<?php echo count($places);?>个)</div>
<?php if(empty($places)):?><p style="text-align:center;color:var(--tl);padding:30px">还没有记录~</p>
<?php else: foreach($places as $i=>$pl):?>
<div class="list-item">
<div style="width:56px;height:56px;border-radius:12px;box-shadow:0 2px 8px rgba(0,0,0,0.06);overflow:hidden;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1.5em;background:#fff"><?php echo $pl['image']?'<img src="../'.htmlspecialchars($pl['image']).'" style="width:100%;height:100%;object-fit:cover">':'📍';?></div>
<div class="item-info"><div class="item-title"><?php echo htmlspecialchars($pl['name']);?></div><div class="item-meta">🕐 <?php echo htmlspecialchars($pl['time']);?></div><?php if(!empty($pl['note'])):?><div class="item-body"><?php echo nl2br(htmlspecialchars($pl['note']));?></div><?php endif;?></div>
<form method="post" onsubmit="return confirm('删除？')"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_place"><input type="hidden" name="id" value="<?php echo $i;?>"><button type="submit" class="btn small danger">删除</button></form>
</div>
<?php endforeach; endif;?></div>
<?php endif; /* places */ ?>

<?php if ($tab === 'todos'): ?>
<div class="card"><div class="card-title">📝 添加事项</div>
<form method="post"><?php echo csrf_field(); ?><input type="hidden" name="act" value="save_todo">
<div class="fg"><label>📋 事项 *</label><input type="text" name="title" class="neo" placeholder="一起看日出" required></div>
<div class="fg"><label>📝 备注</label><textarea name="note" class="neo" rows="2"></textarea></div>
<div class="btn-group"><button type="submit" class="btn primary">✅ 添加</button></div></form></div>
<div class="card"><div class="card-title">📋 清单 (<?php $dn=count(array_filter($todos,function($t){return !empty($t['done']);}));echo $dn.'/'.count($todos);?>)</div>
<?php if(empty($todos)):?><p style="text-align:center;color:var(--tl);padding:30px">清单空的~</p>
<?php else: foreach($todos as $i=>$t):$isd=!empty($t['done']);?>
<div class="list-item">
<form method="post" style="flex-shrink:0"><?php echo csrf_field(); ?><input type="hidden" name="act" value="toggle_todo"><input type="hidden" name="id" value="<?php echo $i;?>"><button type="submit" style="width:38px;height:38px;border-radius:50%;border:none;background:#fff;box-shadow:<?php echo $isd?'inset 1px 1px 4px rgba(0,0,0,.06)':'0 2px 8px rgba(0,0,0,.06)';?>;cursor:pointer;font-size:1.1em"><?php echo $isd?'✅':'⬜';?></button></form>
<div class="item-info"><div class="item-title" style="<?php echo $isd?'text-decoration:line-through;color:var(--tl)':'';?>"><?php echo htmlspecialchars($t['title']);?></div><div class="item-meta"><?php echo $isd?'✅ 已完成 · '.htmlspecialchars($t['done_time']):'📝 创建于 '.htmlspecialchars($t['time']);?></div><?php if(!empty($t['note'])):?><div class="item-body"><?php echo htmlspecialchars($t['note']);?></div><?php endif;?></div>
<form method="post" onsubmit="return confirm('删除？')"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_todo"><input type="hidden" name="id" value="<?php echo $i;?>"><button type="submit" class="btn small danger">删除</button></form>
</div>
<?php endforeach; endif;?></div>
<?php endif; /* todos */ ?>

<?php if ($tab === 'config'): ?>
<div class="card"><div class="card-title">👤 头像 & 设置</div>
<div style="display:flex;gap:16px;margin-bottom:16px;flex-wrap:wrap">
<div style="text-align:center;min-width:100px;flex:1">
<div style="width:70px;height:70px;border-radius:50%;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);display:inline-flex;align-items:center;justify-content:center;background:#fff"><?php echo $av1?'<img src="../'.htmlspecialchars($av1).'" style="width:100%;height:100%;object-fit:cover">':'<span style="font-size:2em">👦</span>';?></div>
<div style="font-size:.85em;margin-top:4px"><?php echo htmlspecialchars($n1);?></div></div>
<div style="text-align:center;min-width:100px;flex:1">
<div style="width:70px;height:70px;border-radius:50%;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);display:inline-flex;align-items:center;justify-content:center;background:#fff"><?php echo $av2?'<img src="../'.htmlspecialchars($av2).'" style="width:100%;height:100%;object-fit:cover">':'<span style="font-size:2em">👧</span>';?></div>
<div style="font-size:.85em;margin-top:4px"><?php echo htmlspecialchars($n2);?></div></div></div>
<form method="post" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="act" value="save_config">
<div class="fg"><label>👦 你的名字</label><input type="text" name="name1" class="neo" value="<?php echo htmlspecialchars($n1);?>" required></div>
<div class="fg"><label>🖼️ 你的头像</label><input type="file" name="avatar1[]" accept="image/*"><div style="font-size:.7em;color:var(--tl)">不选则保持原头像</div></div>
<div class="fg"><label>👧 TA的名字</label><input type="text" name="name2" class="neo" value="<?php echo htmlspecialchars($n2);?>" required></div>
<div class="fg"><label>🖼️ TA的头像</label><input type="file" name="avatar2[]" accept="image/*"><div style="font-size:.7em;color:var(--tl)">不选则保持原头像</div></div>
<div class="fg"><label>📅 纪念日</label><input type="date" name="love_date" class="neo" value="<?php echo htmlspecialchars($config['love_date']??'2024-01-01');?>"></div>
<div class="fg"><label>🏷️ 网站标题</label><input type="text" name="site_title" class="neo" value="<?php echo htmlspecialchars($config['site_title']??'');?>" placeholder="默认：名字 ❤ 名字"></div>
<div class="fg"><label>📋 底部备案</label><input type="text" name="beian" class="neo" value="<?php echo htmlspecialchars($config['beian']??'');?>" placeholder="备案文字"></div>
 <div class="fg"><label>🖼️ 首页背景图</label>
<?php if(!empty($config['background_image'])): ?>
<div style="margin-bottom:8px;"><img src="../<?php echo htmlspecialchars($config['background_image']); ?>" style="max-width:200px;border-radius:8px;"></div>
<label><input type="checkbox" name="delete_background" value="1"> 删除当前背景图</label>
<?php endif; ?>
<input type="file" name="background_image[]" accept="image/*"><div style="font-size:.7em;color:var(--tl)">不上传则保持原背景，勾选删除可重置为默认</div></div>
<div class="btn-group"><button type="submit" class="btn primary">💾 保存</button></div></form></div>
<?php endif; /* config */ ?>

<?php if ($tab === 'password'): ?>
<div class="card"><div class="card-title">🔑 管理员设置</div>
<form method="post"><?php echo csrf_field(); ?><input type="hidden" name="act" value="change_password">
<div class="fg"><label>👤 新账号（留空不修改）</label><input type="text" name="new_username" class="neo" placeholder="当前: <?php echo htmlspecialchars($admin_saved['username'] ?? 'admin');?>"></div>
<div style="margin:8px 0;height:1px;background:rgba(0,0,0,.05)"></div>
<div class="fg"><label>🔑 原密码（必填）</label><input type="password" name="old_password" class="neo" required></div>
<div class="fg"><label>🆕 新密码（留空不修改）</label><input type="password" name="new_password" class="neo" minlength="4"></div>
<div class="fg"><label>🔄 确认新密码</label><input type="password" name="confirm_password" class="neo" minlength="4"></div>
<div class="btn-group"><button type="submit" class="btn primary">💾 保存</button></div></form></div>
<?php endif; /* password */ ?>

<?php if ($tab === 'about'): ?>
<div class="card"><div class="card-title">📖 版本介绍</div>
<form method="post" enctype="multipart/form-data"><?php echo csrf_field(); ?><input type="hidden" name="act" value="save_about">
<div class="fg"><label>🏷️ 项目名称及版本</label><input type="text" name="version" class="neo" value="<?php echo htmlspecialchars($about['version']??'');?>" placeholder="如：情侣小窝 v2.0.0"></div>
<div class="fg"><label>📝 更新日志 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(支持换行，可写多条更新)</span></label><textarea name="version_desc" class="neo" rows="8" placeholder="v2.0.0 - 2024-01-01&#10;  - 新增文件管理功能&#10;  - 优化留言敏感词过滤"><?php echo htmlspecialchars($about['version_desc']??'');?></textarea></div>
<div style="margin:8px 0;height:1px;background:rgba(0,0,0,.05)"></div>
<div style="font-size:.82em;color:var(--tl);margin-bottom:12px">💡 关于我们（可选，展示在前台关于页面）</div>
<div style="display:flex;gap:16px" class="about-flex">
<div style="flex:1">
<div class="fg"><label>👦 他的称呼</label><input type="text" name="boy_name" class="neo" value="<?php echo htmlspecialchars($about['boy_name']??'');?>" placeholder="如：大笨蛋"></div>
<div class="fg"><label>📝 他的介绍</label><textarea name="boy_intro" class="neo" rows="3" placeholder="介绍他..."><?php echo htmlspecialchars($about['boy_intro']??'');?></textarea></div>
<div class="fg"><label>🖼️ 他的照片</label><input type="file" name="boy_avatar[]" accept="image/*"><?php if(!empty($about['boy_avatar_url'])):?><div style="margin-top:4px"><img src="../<?php echo htmlspecialchars($about['boy_avatar_url']);?>" style="max-width:120px;max-height:120px;border-radius:8px"></div><?php endif;?></div>
</div>
<div style="flex:1">
<div class="fg"><label>👧 她的称呼</label><input type="text" name="girl_name" class="neo" value="<?php echo htmlspecialchars($about['girl_name']??'');?>" placeholder="如：小可爱"></div>
<div class="fg"><label>📝 她的介绍</label><textarea name="girl_intro" class="neo" rows="3" placeholder="介绍她..."><?php echo htmlspecialchars($about['girl_intro']??'');?></textarea></div>
<div class="fg"><label>🖼️ 她的照片</label><input type="file" name="girl_avatar[]" accept="image/*"><?php if(!empty($about['girl_avatar_url'])):?><div style="margin-top:4px"><img src="../<?php echo htmlspecialchars($about['girl_avatar_url']);?>" style="max-width:120px;max-height:120px;border-radius:8px"></div><?php endif;?></div>
</div></div>
<div class="btn-group"><button type="submit" class="btn primary">💾 保存</button></div></form></div>
<?php endif; /* about */ ?>

<?php if ($tab === 'pages'): ?>
<div class="card">
<div class="card-title" id="page_form_title">📄 添加自定义页面</div>
<form method="post" enctype="multipart/form-data">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="save_page"><input type="hidden" name="pid" id="page_pid" value="">
<div style="display:flex;gap:12px">
<div class="fg" style="flex:2"><label>📄 页面标题 *</label><input type="text" name="page_title" id="page_title" class="neo" placeholder="如：我们的故事" required></div>
<div class="fg" style="flex:1"><label>🔗 页面标识 * <span style="font-weight:400;font-size:.85em;color:var(--tl)">(英文/数字)</span></label><input type="text" name="page_slug" id="page_slug" class="neo" placeholder="如：story" required></div>
</div>
<div style="display:flex;gap:12px">
<div class="fg" style="flex:1"><label>🎨 图标</label><input type="text" name="page_icon" id="page_icon" class="neo" placeholder="📄" value="📄"></div>
<div class="fg" style="flex:1"><label>🔢 排序</label><input type="number" name="page_sort" id="page_sort" class="neo" value="99" min="0"></div>
</div>
<div class="fg"><label>📝 页面内容 <span style="font-weight:400;font-size:.85em;color:var(--tl)">(支持HTML)</span></label><textarea name="page_content" id="page_content" class="neo" rows="8" placeholder="<h3>我们的故事</h3><p>从那天开始...</p>"></textarea></div>
<div class="btn-group"><button type="submit" class="btn primary" id="page_submit_btn">📄 保存页面</button><button type="button" class="btn" id="page_cancel_btn" style="display:none;color:var(--tl)" onclick="cancelPageEdit()">✕ 取消编辑</button></div>
</form>
</div>
<div class="card"><div class="card-title">📑 自定义页面列表 (<?php echo count($pages);?>个)</div>
<?php if(empty($pages)):?><p style="text-align:center;color:var(--tl);padding:30px">还没有自定义页面<br>创建一个吧，它会出现在前台导航中~</p>
<?php else: foreach($pages as $i=>$pg):?>
<div class="list-item">
<div style="font-size:1.8em"><?php echo htmlspecialchars($pg['icon']??'📄');?></div>
<div class="item-info">
<div class="item-title"><?php echo htmlspecialchars($pg['title']);?></div>
<div class="item-meta">🔗 ?p=<?php echo htmlspecialchars($pg['slug']);?> · 排序: <?php echo $pg['sort']??99;?> · <?php echo htmlspecialchars($pg['time']??'');?></div>
<div class="item-body"><?php echo htmlspecialchars(mb_substr(strip_tags($pg['content']??''),0,80));?>…</div>
</div>
<div style="flex-shrink:0;display:flex;gap:6px">
<button type="button" class="btn small primary" onclick='editPage(<?php echo $i;?>,<?php echo json_encode($pg['title']);?>,<?php echo json_encode($pg['slug']);?>,<?php echo json_encode($pg['icon']??'📄');?>,<?php echo json_encode($pg['content']??'');?>,<?php echo ($pg['sort']??99);?>)' title="编辑">✏️</button>
<form method="post" onsubmit="return confirm('确定删除？')" style="display:inline"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_page"><input type="hidden" name="id" value="<?php echo $i;?>"><button type="submit" class="btn small danger">删除</button></form>
</div>
</div>
<?php endforeach; endif;?></div>
<?php endif; /* pages */ ?>

<?php if ($tab === 'comments'): ?>
<div class="card"><div class="card-title">💬 留言管理 (<?php echo count($comments);?>条)</div>
<?php if(empty($comments)):?><p style="text-align:center;color:var(--tl);padding:30px">还没有留言~</p>
<?php else: foreach($comments as $i=>$cm): $pid = $cm['post_id']??''; $poContent = ''; foreach($posts as $po) if(($po['id']??'') === $pid) { $poContent = mb_substr($po['content'],0,30); break; } ?>
<div class="list-item">
<div style="font-size:1.5em">💬</div>
<div class="item-info">
<div class="item-title"><?php echo htmlspecialchars($cm['nick']);?> <span style="font-weight:400;color:var(--tl);font-size:.85em">→ <?php echo htmlspecialchars($poContent ?: '已删除的说说');?>…</span></div>
<div class="item-body"><?php echo nl2br(htmlspecialchars($cm['text']));?></div>
<div class="item-meta">🕐 <?php echo htmlspecialchars($cm['time']);?> · IP: <?php echo htmlspecialchars($cm['ip']??'');?></div>
</div>
<form method="post" onsubmit="return confirm('确定删除？')" style="flex-shrink:0"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_comment"><input type="hidden" name="id" value="<?php echo $i;?>"><button type="submit" class="btn small danger">删除</button></form>
</div>
<?php endforeach; endif;?></div>
<?php endif; /* comments */ ?>

<?php if ($tab === 'users'): ?>
<div class="card"><div class="card-title">👥 用户管理 (<?php echo count($users);?>人)</div>
<?php if(empty($users)):?><p style="text-align:center;color:var(--tl);padding:30px">暂无注册用户</p>
<?php else: foreach($users as $i=>$u): ?>
<div class="list-item">
<div style="font-size:1.5em">👤</div>
<div class="item-info">
<div class="item-title"><?php echo htmlspecialchars($u['nickname'] ?? $u['username']);?> <span style="font-weight:400;color:var(--tl);font-size:.85em">@<?php echo htmlspecialchars($u['username']);?></span></div>
<div class="item-meta">🕐 注册时间: <?php echo htmlspecialchars($u['created_at'] ?? $u['time'] ?? '未知');?></div>
</div>
<form method="post" onsubmit="return confirm('确定删除该用户吗？删除后该用户的所有数据也会丢失。')" style="flex-shrink:0"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_user"><input type="hidden" name="id" value="<?php echo $i;?>"><button type="submit" class="btn small danger">删除</button></form>
</div>
<?php endforeach; endif;?></div>
<?php endif; /* users */ ?>

<?php
// 文件管理 tab 数据
$fm_dir = $_GET['dir'] ?? '';
$fm_dir = str_replace('\\', '/', $fm_dir);
$fullDir = rtrim($ROOT, '/') . '/' . ltrim($fm_dir, '/');
$fullDir = realpath($fullDir) ?: $ROOT;
$rootReal = realpath($ROOT);
if (strpos($fullDir, $rootReal) !== 0) $fullDir = $rootReal;
$fm_items = array_diff(scandir($fullDir), ['.', '..']);
$fm_dirs = []; $fm_files = [];
foreach ($fm_items as $item) {
    $p = $fullDir . '/' . $item;
    if (is_dir($p)) $fm_dirs[] = $item;
    else $fm_files[] = $item;
}
sort($fm_dirs); sort($fm_files);
$fm_rel = ltrim(str_replace($rootReal, '', $fullDir), '/') ?: '';
$fm_parent = dirname($fm_rel);
if ($fm_parent === '.') $fm_parent = '';
$editing_file = $_GET['edit'] ?? '';
$editing_content = '';
if ($editing_file !== '') {
    $editReal = realpath($ROOT . '/' . ltrim($editing_file, '/'));
    if ($editReal && strpos($editReal, $rootReal) === 0 && is_file($editReal)) {
        $editing_content = file_get_contents($editReal);
    } else {
        $editing_file = '';
    }
}
?>

<?php if ($tab === 'files'): ?>
<div class="card">
<div class="card-title">📁 文件管理</div>
<div style="margin-bottom:16px">
<span style="font-size:.85em;color:var(--tl)">📂 当前目录：</span>
<span style="font-size:.85em;color:var(--pri);word-break:break-all">/<?php echo htmlspecialchars($fm_rel); ?></span>
<?php if ($fm_rel !== ''): ?>
<a href="?tab=files&dir=<?php echo urlencode($fm_parent); ?>" class="btn small" style="margin-left:8px">⬆ 上级目录</a>
<?php endif; ?>
<a href="?tab=files" class="btn small" style="margin-left:4px">🏠 根目录</a>
</div>
<form method="post" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="upload_file">
<input type="hidden" name="dir" value="<?php echo htmlspecialchars($fm_rel); ?>">
<input type="file" name="file" required style="font-size:.82em;flex:1;min-width:150px">
<button type="submit" class="btn primary small">📤 上传</button>
</form>
<form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="mkdir_file">
<input type="hidden" name="dir" value="<?php echo htmlspecialchars($fm_rel); ?>">
<input type="text" name="dirname" class="neo" placeholder="新建目录名" style="flex:1;min-width:120px;font-size:.82em;padding:8px 12px">
<button type="submit" class="btn small">📁 创建</button>
</form>
</div>
<?php if (!empty($fm_dirs)): ?>
<div class="card"><div class="card-title">📁 目录</div>
<?php foreach ($fm_dirs as $d): $sub = $fm_rel ? $fm_rel . '/' . $d : $d; ?>
<div class="list-item">
<div style="font-size:1.5em">📁</div>
<div class="item-info"><div class="item-title"><?php echo htmlspecialchars($d); ?>/</div></div>
<div style="flex-shrink:0;display:flex;gap:6px">
<a href="?tab=files&dir=<?php echo urlencode($sub); ?>" class="btn small">📂 打开</a>
<form method="post" onsubmit="return confirm('确定删除目录？')" style="display:inline"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_dir"><input type="hidden" name="dir" value="<?php echo htmlspecialchars($sub); ?>"><button type="submit" class="btn small danger">删除</button></form>
</div>
</div>
<?php endforeach; ?></div>
<?php endif; ?>
<div class="card"><div class="card-title">📄 文件 (<?php echo count($fm_files); ?>)</div>
<?php if (empty($fm_files)): ?>
<p style="text-align:center;color:var(--tl);padding:30px">空目录</p>
<?php else: foreach ($fm_files as $fn):
    $fp = $fm_rel ? $fm_rel . '/' . $fn : $fn;
    $fs = filesize($fullDir . '/' . $fn);
    if ($fs < 1024) $fsh = $fs . ' B';
    elseif ($fs < 1048576) $fsh = round($fs/1024, 1) . ' KB';
    else $fsh = round($fs/1048576, 2) . ' MB';
    $fext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
    $isText = in_array($fext, ['php','html','htm','css','js','json','txt','md','xml','yml','yaml','env','htaccess','ini','log','sql','sh','py','rb','java','c','cpp','h','csv']);
    $icon = in_array($fext, ['jpg','jpeg','png','gif','webp','svg','ico']) ? '🖼️' : ($isText ? '📝' : '📎');
?>
<div class="list-item">
<div style="font-size:1.5em"><?php echo $icon; ?></div>
<div class="item-info">
<div class="item-title"><?php echo htmlspecialchars($fn); ?></div>
<div class="item-meta"><?php echo $fsh; ?> · <?php echo htmlspecialchars($fext); ?></div>
</div>
<div style="flex-shrink:0;display:flex;gap:6px">
<?php if ($isText): ?><a href="?tab=files&dir=<?php echo urlencode($fm_rel); ?>&edit=<?php echo urlencode($fp); ?>" class="btn small primary">✏️ 编辑</a><?php endif; ?>
<form method="post" onsubmit="return confirm('确定删除文件？')" style="display:inline"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_file"><input type="hidden" name="file" value="<?php echo htmlspecialchars($fp); ?>"><button type="submit" class="btn small danger">删除</button></form>
</div>
</div>
<?php endforeach; endif; ?></div>
<?php if ($editing_file !== ''): ?>
<div class="card">
<div class="card-title">✏️ 编辑：<?php echo htmlspecialchars(basename($editing_file)); ?><?php if (in_array(strtolower(pathinfo($editing_file, PATHINFO_EXTENSION)), ['php','phtml','phps'])): ?> <span style="color:#c0392b;font-size:.75em">⚠️ PHP文件</span><?php endif; ?></div>
<form method="post"><?php echo csrf_field(); ?>
<input type="hidden" name="act" value="save_file">
<input type="hidden" name="file" value="<?php echo htmlspecialchars($editing_file); ?>">
<div class="fg"><textarea name="content" class="neo" rows="25" style="font-family:monospace;font-size:.82em"><?php echo htmlspecialchars($editing_content); ?></textarea></div>
<div class="btn-group">
<button type="submit" class="btn primary">💾 保存</button>
<a href="?tab=files&dir=<?php echo urlencode($fm_rel); ?>" class="btn">取消</a>
</div></form>
</div>
<?php endif; ?>
<?php endif; /* files */ ?>

<?php if ($tab === 'visitors'): ?>
<h2 class="card-title">📊 访客记录</h2>
<?php $vlist = visitors_get(); if (empty($vlist)): ?>
<p style="text-align:center;color:var(--tl);padding:30px">暂无访客记录</p>
<?php else: $vlist = array_reverse($vlist); ?>
<div style="margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
<span style="color:var(--tl);font-size:.85em">共 <?php echo count($vlist); ?> 条记录（最近 1000 条）</span>
<form method="post" onsubmit="return confirm('确定清空所有访客记录？')" style="display:inline">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="clear_visitors">
<button type="submit" class="btn danger small">🗑 清空记录</button>
</form>
</div>
<div style="overflow-x:auto">
<table style="width:100%;border-collapse:collapse;font-size:.82em">
<thead><tr style="background:#f5f5f5">
<th style="padding:10px 8px;text-align:left;border-bottom:2px solid #e0e0e0">IP 地址</th>
<th style="padding:10px 8px;text-align:left;border-bottom:2px solid #e0e0e0">归属地</th>
<th style="padding:10px 8px;text-align:left;border-bottom:2px solid #e0e0e0">访问时间</th>
<th style="padding:10px 8px;text-align:left;border-bottom:2px solid #e0e0e0">访问页面</th>
<th style="padding:10px 8px;text-align:left;border-bottom:2px solid #e0e0e0">浏览器 UA</th>
</tr></thead>
<tbody>
<?php foreach ($vlist as $v): ?>
<tr style="border-bottom:1px solid #eee">
<td style="padding:8px;word-break:break-all"><code><?php echo htmlspecialchars($v['ip'] ?? ''); ?></code></td>
<td style="padding:8px;word-break:break-all"><?php echo htmlspecialchars($v['location'] ?? ''); ?></td>
<td style="padding:8px;white-space:nowrap"><?php echo htmlspecialchars($v['time'] ?? ''); ?></td>
<td style="padding:8px;word-break:break-all;max-width:200px;overflow:hidden;text-overflow:ellipsis" title="<?php echo htmlspecialchars($v['url'] ?? ''); ?>"><?php echo htmlspecialchars($v['url'] ?? ''); ?></td>
<td style="padding:8px;font-size:.75em;word-break:break-all;color:#999"><?php echo htmlspecialchars($v['ua'] ?? ''); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php endif; ?>
<?php endif; /* visitors */ ?>

<?php if ($tab === 'filter'): ?>
<div class="card"><div class="card-title">🚫 敏感词设置</div>
<div style="font-size:.85em;color:var(--tl);margin-bottom:16px">📌 前台留言和说说内容中包含的敏感词将被自动替换为 * 号。</div>
<form method="post" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><?php echo csrf_field(); ?>
<input type="hidden" name="act" value="add_word">
<input type="text" name="word" class="neo" placeholder="输入敏感词" required style="flex:1;min-width:150px">
<button type="submit" class="btn primary small">➕ 添加</button>
</form>
</div>
<div class="card"><div class="card-title">📋 敏感词列表 (<?php echo count($filter_words); ?>个)</div>
<?php if (empty($filter_words)): ?>
<p style="text-align:center;color:var(--tl);padding:30px">暂未设置任何敏感词</p>
<?php else: ?>
<div style="display:flex;flex-wrap:wrap;gap:8px">
<?php foreach ($filter_words as $i => $w): ?>
<div style="display:flex;align-items:center;gap:6px;background:#fff;border-radius:20px;padding:6px 14px;box-shadow:0 2px 8px rgba(0,0,0,0.06);font-size:.88em">
<span style="color:#c0392b"><?php echo htmlspecialchars($w); ?></span>
<form method="post" onsubmit="return confirm('确定删除该敏感词？')" style="display:inline"><?php echo csrf_field(); ?><input type="hidden" name="act" value="delete_word"><input type="hidden" name="id" value="<?php echo $i; ?>"><button type="submit" style="background:none;border:none;color:#c0392b;cursor:pointer;font-size:1em;padding:0;line-height:1">✕</button></form>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?></div>
<?php endif; /* filter */ ?>

</div>

<div class="bnav">
<a href="?tab=posts" class="<?php echo $tab==='posts'?'active':'';?>"><span class="ni">💬</span><span class="nl">说说</span></a>
<a href="?tab=album" class="<?php echo $tab==='album'?'active':'';?>"><span class="ni">📷</span><span class="nl">相册</span></a>
<a href="?tab=places" class="<?php echo $tab==='places'?'active':'';?>"><span class="ni">📍</span><span class="nl">足迹</span></a>
<a href="?tab=todos" class="<?php echo $tab==='todos'?'active':'';?>"><span class="ni">✅</span><span class="nl">清单</span></a>
<a href="?tab=pages" class="<?php echo $tab==='pages'?'active':'';?>"><span class="ni">📑</span><span class="nl">页面</span></a>
<a href="?tab=comments" class="<?php echo $tab==='comments'?'active':'';?>"><span class="ni">💬</span><span class="nl">留言</span></a>
<a href="?tab=users" class="<?php echo $tab==='users'?'active':'';?>"><span class="ni">👥</span><span class="nl">用户</span></a>
<a href="?tab=config" class="<?php echo $tab==='config'?'active':'';?>"><span class="ni">⚙️</span><span class="nl">设置</span></a>
<a href="?tab=password" class="<?php echo $tab==='password'?'active':'';?>"><span class="ni">🔑</span><span class="nl">密码</span></a>
<a href="?tab=about" class="<?php echo $tab==='about'?'active':'';?>"><span class="ni">📖</span><span class="nl">关于</span></a>
<a href="?tab=files" class="<?php echo $tab==='files'?'active':'';?>"><span class="ni">📁</span><span class="nl">文件</span></a>
<a href="?tab=filter" class="<?php echo $tab==='filter'?'active':'';?>"><span class="ni">🚫</span><span class="nl">敏感词</span></a>
<a href="?tab=visitors" class="<?php echo $tab==='visitors'?'active':'';?>"><span class="ni">📊</span><span class="nl">访客</span></a>
<a href="../"><span class="ni">🏠</span><span class="nl">前台</span></a>
<a href="?logout=1"><span class="ni">🚪</span><span class="nl">退出</span></a>
</div>
</body>
</html>