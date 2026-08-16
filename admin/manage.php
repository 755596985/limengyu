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

// ===== 模块化：加载模块清单 =====
$MODULE_DIR = __DIR__ . '/modules';
$MODULES = require $MODULE_DIR . '/manifest.php';
$MODULES = array_values(array_filter($MODULES, function ($m) { return !empty($m['enabled']); }));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    // ===== 模块化：POST 处理分发 =====
    $MOD_RUN = 'handle';
    foreach ($MODULES as $mod) {
        if (in_array($act, $mod['acts'], true)) {
            require $MODULE_DIR . '/' . $mod['file'];
            break;
        }
    }
    unset($MOD_RUN);



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
:root{--bg:#faf4ec;--pri:#d4786e;--tx:#5a4e4a;--tl:#8c7e78;--card:#fff;--soft:#f5f5f5;--input:#f5f1ee;--line:rgba(0,0,0,.05);--ok:#e8f5e9;--oktx:#2e7d32;--err:#ffebee;--errtx:#c62828;--warn:#fff8e1;--warntx:#f57f17;--prisoft:rgba(212,120,110,.08)}
[data-theme="dark"]{--bg:#1f1b18;--pri:#ec9d94;--tx:#ece5df;--tl:#a89a92;--card:#2b2522;--soft:#332c28;--input:#362e2a;--line:rgba(255,255,255,.08);--ok:#1e3a28;--oktx:#8fd6a8;--err:#45272b;--errtx:#ee8d9b;--warn:#3a3120;--warntx:#e8b45a;--prisoft:rgba(236,157,148,.12)}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei',sans-serif;background:var(--bg);color:var(--tx);min-height:100vh}
.main{padding:16px 14px 90px;max-width:900px;margin:0 auto}
.card{background:var(--card);border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,0.06);padding:24px;margin-bottom:20px}
.card-title{font-size:1.05em;font-weight:700;color:var(--tx);margin-bottom:18px}
.msg{padding:12px 18px;border-radius:10px;margin-bottom:16px;font-size:.88em}
.msg.success{background:var(--ok);color:var(--oktx)}
.msg.error{background:var(--err);color:var(--errtx)}
.fg{margin-bottom:16px}
.fg label{display:block;font-size:.82em;color:var(--tl);font-weight:600;margin-bottom:6px}
.neo{width:100%;padding:11px 15px;background:var(--input);border:none;border-radius:10px;box-shadow:inset 2px 2px 6px rgba(0,0,0,0.04);font-size:.92em;color:var(--tx);outline:none;font-family:inherit}
.neo:focus{box-shadow:inset 1px 1px 4px rgba(0,0,0,0.06)}
textarea.neo{min-height:90px;resize:vertical}
select.neo{cursor:pointer}
.btn{padding:10px 22px;border:none;border-radius:10px;font-size:.9em;font-weight:700;cursor:pointer;background:var(--card);box-shadow:0 2px 8px rgba(0,0,0,0.06);color:var(--tx);transition:all .2s;display:inline-flex;align-items:center;gap:6px;text-decoration:none}
.btn:active{transform:scale(.97)}
.btn.primary{color:var(--pri)}
.btn.danger{color:var(--errtx)}
.btn.small{padding:6px 14px;font-size:.78em;border-radius:8px}
.btn-group{display:flex;gap:8px;flex-wrap:wrap;margin-top:16px}
.list-item{display:flex;align-items:flex-start;gap:14px;padding:16px 0;border-bottom:1px solid var(--line)}
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
.post-compose{border:2px dashed rgba(212,120,110,.28);border-radius:14px;padding:12px 12px 10px;margin-bottom:12px;background:var(--prisoft)}
.post-compose textarea.neo{min-height:120px;border:none;background:transparent;box-shadow:none;padding:6px 4px;font-size:.95em}
.post-compose-bar{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;padding:8px 4px 0;border-top:1px solid var(--line)}
.post-compose-bar .mood-picker{gap:2px}
.post-compose-bar .mood-picker label{padding:4px 8px;font-size:1em;box-shadow:none;background:transparent;border-radius:14px}
.post-compose-bar .mood-picker label:has(input:checked){background:rgba(212,120,110,.12)}
.post-author{width:auto;padding:6px 10px;font-size:.82em;border-radius:8px}
.post-tools{display:flex;flex-wrap:wrap;gap:6px;padding:8px 4px 0;border-top:1px solid var(--line)}
.post-tools .btn{font-size:.76em;padding:4px 12px;border-radius:16px;background:var(--input);box-shadow:none;font-weight:600}
.post-tools .btn:active{transform:scale(.96)}
.post-extra{display:none;padding:14px 14px 2px;margin-bottom:10px;background:var(--soft);border-radius:12px}
.post-extra .fg{margin-bottom:12px}
.tag-badge{display:inline-block;background:rgba(212,120,110,.1);color:var(--pri);font-size:.68em;padding:2px 8px;border-radius:10px;margin-right:4px;margin-top:4px}

.bnav{position:fixed;bottom:16px;left:50%;transform:translateX(-50%);background:var(--card);border-radius:26px;box-shadow:0 4px 20px rgba(0,0,0,0.1),0 2px 6px rgba(0,0,0,0.04);display:flex;padding:6px 10px;z-index:100;gap:0;overflow-x:auto;max-width:95vw;-webkit-overflow-scrolling:touch;scrollbar-width:none}
.bnav::-webkit-scrollbar{display:none}
.bnav a{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:7px 13px;border-radius:20px;text-decoration:none;color:var(--tl);transition:all .2s;min-width:50px;font-weight:500;flex-shrink:0}
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
.bnav a[href*="tab=password"] .ni{color:var(--tl)}
.bnav a[href*="tab=about"] .ni{color:#aaa}
.bnav a[href*="tab=pages"] .ni{color:#9b59b6}
.bnav a[href*="tab=comments"] .ni{color:#e67e22}
.bnav a[href="../"] .ni{color:#e85d5d}
.bnav a[href*="tab=files"] .ni{color:#3498db}
.bnav a[href*="tab=visitors"] .ni{color:#27ae60}
.bnav a[href*="tab=filter"] .ni{color:#e74c3c}

@media(max-width:768px){.main{padding:12px 10px 80px}.card{padding:16px;border-radius:12px}.visitor-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}.visitor-table{font-size:.78em}.visitor-table td,.visitor-table th{padding:8px 6px}}
@media(max-width:600px){.about-flex{flex-direction:column;gap:12px}.about-flex .fg{margin-bottom:12px}.bnav a{min-width:44px;padding:6px 8px}.bnav a .ni{font-size:1.15em}.bnav a .nl{font-size:.7em}.card-title{font-size:.95em}.btn{font-size:.82em;padding:8px 16px}.btn.small{min-height:44px;padding:8px 12px;font-size:.8em;display:inline-flex;align-items:center;justify-content:center}.visitor-table thead{display:none}.visitor-table tbody tr{display:block;margin-bottom:8px;background:var(--card);border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,.04);padding:8px 10px}.visitor-table tbody td{display:block;padding:3px 0;text-align:left;border:none;font-size:.8em;white-space:normal;word-break:break-all}.visitor-table tbody td::before{content:attr(data-label);font-weight:700;color:var(--tl);font-size:.72em;display:block;margin-bottom:1px}.form-row{flex-direction:column!important;gap:10px}.neo{font-size:.9em;padding:10px 12px}.list-item{flex-wrap:wrap;gap:10px}.list-item .item-info{flex-basis:100%}}
.bnav a[href*="yiyan.php"] .ni{color:#9b59b6}


/* 用户表格 */
.user-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch}
.user-table{width:100%;border-collapse:collapse;font-size:.85em}
.user-table thead{background:rgba(212,120,110,.06)}
.user-table th{padding:10px 8px;text-align:left;font-weight:700;color:var(--tl);font-size:.78em;white-space:nowrap;border-bottom:2px solid var(--line)}
.user-table td{padding:10px 8px;border-bottom:1px solid var(--line);vertical-align:middle;white-space:nowrap}
.user-table tbody tr:hover{background:var(--prisoft)}
.avatar-dot{display:inline-block;width:22px;height:22px;border-radius:50%;margin-right:8px;vertical-align:middle;box-shadow:0 2px 6px rgba(0,0,0,.12);flex-shrink:0}
.user-at{font-weight:400;color:var(--tl);font-size:.85em}
.status-badge{display:inline-block;padding:3px 10px;border-radius:12px;font-size:.75em;font-weight:700}
.badge-green{background:var(--ok);color:var(--oktx)}
.badge-yellow{background:var(--warn);color:var(--warntx)}
.badge-red{background:var(--err);color:var(--errtx)}
.action-btns{display:flex;gap:4px;flex-wrap:wrap}
/* 编辑弹窗 */
.modal-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.3);z-index:200;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(2px)}
.modal-box{background:var(--card);border-radius:20px;box-shadow:0 8px 40px rgba(0,0,0,.15);width:480px;max-width:92vw;max-height:90vh;overflow-y:auto;padding:24px}
.modal-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px}
.modal-title{font-size:1.05em;font-weight:700;color:var(--tx)}
.modal-close{background:none;border:none;font-size:1.3em;color:var(--tl);cursor:pointer;padding:4px 8px;border-radius:8px;transition:all .2s}
.modal-close:hover{background:rgba(0,0,0,.04)}
/* 颜色选择器 */
.color-picker{display:flex;gap:8px;flex-wrap:wrap}
.color-dot{width:32px;height:32px;border-radius:50%;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.12);transition:all .2s;display:flex;align-items:center;justify-content:center;position:relative}
.color-dot input[type=radio]{display:none}
.color-dot:hover{transform:scale(1.15)}
.color-dot:has(input:checked){box-shadow:0 0 0 3px var(--pri),0 2px 8px rgba(0,0,0,.15);transform:scale(1.1)}
.color-dot:has(input:checked)::after{content:"✓";color:#fff;font-size:.8em;font-weight:700;text-shadow:0 1px 2px rgba(0,0,0,.3)}
/* 手机端用户表格卡片化 */
@media(max-width:600px){
.user-table thead{display:none}
.user-table tbody tr{display:block;margin-bottom:10px;background:var(--card);border-radius:10px;box-shadow:0 1px 6px rgba(0,0,0,.04);padding:10px 12px}
.user-table tbody td{display:block;padding:5px 0;border:none;white-space:normal;word-break:break-all;font-size:.82em}
.user-table tbody td::before{content:attr(data-label);font-weight:700;color:var(--tl);font-size:.7em;display:block;margin-bottom:2px}
.user-table tbody tr:hover{background:var(--card)}
.action-btns{justify-content:flex-end;margin-top:4px}
.modal-box{padding:18px;border-radius:16px;max-width:95vw}
.modal-box .fg{margin-bottom:12px}
}

.switch{position:relative;display:inline-block;width:44px;height:24px}
.switch input{opacity:0;width:0;height:0}
.slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;transition:.3s;border-radius:24px}
.slider:before{position:absolute;content:"";height:18px;width:18px;left:3px;bottom:3px;background:var(--card);transition:.3s;border-radius:50%}
input:checked+.slider{background:#4a90d9}
input:checked+.slider:before{transform:translateX(20px)}
</style>
<script>
function togglePostField(id) {
    var el = document.getElementById(id);
    if (!el) return;
    el.style.display = (el.style.display === 'block') ? 'none' : 'block';
}
function showPostField(id) {
    var el = document.getElementById(id);
    if (el) el.style.display = 'block';
}
function hidePostFields() {
    var ids = ['f_title','f_tags','f_location','f_time','f_images','f_video','f_music'];
    for (var i = 0; i < ids.length; i++) {
        var el = document.getElementById(ids[i]);
        if (el) el.style.display = 'none';
    }
}
function editPost(idx, mood, content, author, dateStr, timeStr, location, title, tags, imageUrls, hasVideo, videoUrl, hasMusic, musicUrl) {
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
    document.getElementById('edit_image_urls').value = imageUrls || '';
    document.getElementById('edit_video_url').value = videoUrl || '';
    document.getElementById('edit_music_url').value = musicUrl || '';
    var radios = document.querySelectorAll('#edit_mood_picker input[type=radio]');
    for (var i = 0; i < radios.length; i++) {
        radios[i].checked = (radios[i].value === mood);
    }
    document.getElementById('post_form_title').innerHTML = '✏️ 编辑说说 #' + (parseInt(idx)+1);
    document.getElementById('submit_btn').innerHTML = '💾 保存修改';
    document.getElementById('cancel_edit_btn').style.display = 'inline-flex';
    if (title || tags || location || dateStr || timeStr) { showPostField('f_title'); showPostField('f_tags'); showPostField('f_location'); showPostField('f_time'); }
    if (imageUrls) showPostField('f_images');
    if (hasVideo || videoUrl) showPostField('f_video');
    if (hasMusic || musicUrl) showPostField('f_music');
    document.getElementById('post_form_title').scrollIntoView({behavior:'smooth'});
}
function cancelEdit() {
    hidePostFields();
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
    document.getElementById('edit_image_urls').value = '';
    document.getElementById('edit_video_url').value = '';
    document.getElementById('edit_music_url').value = '';
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

function editUser(uid) {
    // Find user data from the page
    var row = document.querySelector('button[onclick*="editUser(\''+uid+'\')"]');
    // Actually, fetch user data from PHP-serialized users
    // Since we have the users array in PHP, we embed it as JSON
    var usersData = <?php echo json_encode($users, JSON_UNESCAPED_UNICODE); ?>;
    var user = null;
    for (var i = 0; i < usersData.length; i++) {
        if (usersData[i].id === uid) { user = usersData[i]; break; }
    }
    if (!user) { alert('未找到用户数据'); return; }
    
    document.getElementById('edit_uid').value = uid;
    document.getElementById('edit_nickname').value = user.nickname || '';
    document.getElementById('edit_email').value = user.email || '';
    document.getElementById('edit_status').value = user.status || 'active';
    
    // 头像预览
    var avatarPreview = document.getElementById('edit_avatar_preview');
    if (user.avatar) {
        avatarPreview.src = '../' + user.avatar;
        avatarPreview.style.display = 'inline-block';
    } else {
        avatarPreview.style.display = 'none';
    }
    // 清空密码字段
    document.getElementById('edit_password').value = '';
    
    // Set color picker
    var color = user.avatar_color || '#d4786e';
    var dots = document.querySelectorAll('#colorPicker input[type=radio]');
    for (var j = 0; j < dots.length; j++) {
        dots[j].checked = (dots[j].value === color);
    }
    
    document.getElementById('userModal').style.display = 'flex';
}
function closeUserModal() {
    document.getElementById('userModal').style.display = 'none';
}
// Close modal on overlay click
document.getElementById('userModal') && document.getElementById('userModal').addEventListener('click', function(e) {
    if (e.target === this) closeUserModal();
});

</script>
</head>
<body>
<button id="themeToggle" onclick="toggleTheme()" title="切换奶白/黑夜模式" style="position:fixed;top:14px;right:14px;z-index:300;width:34px;height:34px;border-radius:50%;border:none;cursor:pointer;background:var(--card);box-shadow:0 2px 8px rgba(0,0,0,.12);font-size:1.05em;display:flex;align-items:center;justify-content:center;transition:transform .2s">🌙</button>
<div class="main">
<?php if ($message): ?><div class="msg success">✅ <?php echo htmlspecialchars($message); ?></div><?php endif; ?>
<?php if ($error): ?><div class="msg error">❌ <?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php
// ===== 模块化：页面渲染分发 =====
$MOD_RUN = 'render';
foreach ($MODULES as $mod) {
    if ($tab === $mod['key']) {
        require $MODULE_DIR . '/' . $mod['file'];
        break;
    }
}
unset($MOD_RUN);
?>

</div>

<div class="bnav">
<?php foreach ($MODULES as $mod): ?>
<a href="?tab=<?php echo $mod['key']; ?>" class="<?php echo $tab === $mod['key'] ? 'active' : ''; ?>"><span class="ni"><?php echo $mod['icon']; ?></span><span class="nl"><?php echo $mod['label']; ?></span></a>
<?php endforeach; ?>
<a href="yiyan.php"><span class="ni">💭</span><span class="nl">一言</span></a>
<a href="../"><span class="ni">🏠</span><span class="nl">前台</span></a>
<a href="?logout=1"><span class="ni">🚪</span><span class="nl">退出</span></a>
</div>

<script>
// 主题切换（奶白/黑夜，与前台共用 localStorage）
(function(){
    var KEY='site_theme';
    function apply(t){
        document.documentElement.setAttribute('data-theme',t);
        var b=document.getElementById('themeToggle');
        if(b) b.textContent = (t==='dark') ? '☀️' : '🌙';
    }
    var saved=localStorage.getItem(KEY);
    apply(saved==='dark' ? 'dark' : 'milk');
    window.toggleTheme=function(){
        var cur=document.documentElement.getAttribute('data-theme');
        var next=(cur==='dark') ? 'milk' : 'dark';
        localStorage.setItem(KEY,next);
        apply(next);
    };
})();
</script>
</body>
</html>