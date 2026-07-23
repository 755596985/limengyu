<?php
require __DIR__ . '/include/bootstrap.php';
$ROOT = __DIR__;
$UPLOAD_DIR = $ROOT . '/uploads/';
if (!is_dir($UPLOAD_DIR)) mkdir($UPLOAD_DIR, 0755, true);

// 表未建好时引导去迁移
try {
    db()->query('SELECT 1 FROM cp_config LIMIT 1');
} catch (Throwable $e) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>需要初始化</title></head><body style="font-family:sans-serif;padding:40px;max-width:640px;margin:auto">';
    echo '<h2>数据库尚未初始化</h2>';
    echo '<p>请先打开：<a href="migrate.php">migrate.php</a> 完成建表与数据迁移。</p>';
    echo '<p>然后打开：<a href="dbtest.php">dbtest.php</a> 检查连接。</p>';
    echo '<pre style="background:#f5f5f5;padding:12px;border-radius:8px;white-space:pre-wrap">' . htmlspecialchars($e->getMessage()) . '</pre>';
    echo '</body></html>';
    exit;
}

require_csrf();

$me = $_SESSION['user'] ?? null;
$clientIp = client_ip();
$commentMsg = $commentErr = '';
$userPostMsg = $userPostErr = '';

// 先处理 POST，成功后 PRG 跳转，避免重复提交并防止访问量误计
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'comment') {
    if (!$me) {
        $commentErr = '请先登录后再留言';
    } else {
        $postId = trim($_POST['post_id'] ?? '');
        $text = trim($_POST['text'] ?? '');
    $text = filter_text($text);
        if (empty($text)) {
            $commentErr = '请填写留言内容';
        } elseif (mb_strlen($text) > 500) {
            $commentErr = '留言过长（最多500字）';
        } elseif (!post_exists($postId)) {
            $commentErr = '说说不存在或已删除';
        } else {
            comment_insert([
                'id' => new_id(), 'post_id' => $postId, 'nick' => $me['nickname'],
                'text' => $text, 'ip' => $clientIp,
                'user_id' => $me['id'], 'time' => date('Y-m-d H:i:s'),
            ]);
            header('Location: index.php?p=posts&cmt=1');
            exit;
        }
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['act'] ?? '') === 'user_post') {
    if (!$me) {
        $userPostErr = '请先登录！';
    } else {
        $content = trim($_POST['content'] ?? '');
    $content = filter_text($content);
        if (empty($content)) {
            $userPostErr = '说说内容不能为空！';
        } elseif (mb_strlen($content) > 2000) {
            $userPostErr = '内容过长（最多2000字）';
        } else {
            $imgs = safe_upload_multi('images', $UPLOAD_DIR, ['jpg','jpeg','png','gif','webp'],
                ['image/jpeg','image/png','image/gif','image/webp']);
            $video = safe_upload_one('video', $UPLOAD_DIR, ['mp4','webm','mov','avi','mkv'],
                ['video/mp4','video/webm','video/quicktime']);
            $music = safe_upload_one('music', $UPLOAD_DIR, ['mp3','wav','ogg','m4a','aac','flac'],
                ['audio/mpeg','audio/wav','audio/ogg','audio/mp4','audio/aac','audio/flac']);
            post_insert([
                'id' => new_id(), 'title' => '', 'tags' => [], 'content' => $content,
                'author' => '1', 'mood' => '💕', 'time' => date('Y-m-d H:i:s'),
                'images' => $imgs, 'video' => $video, 'music' => $music,
                'ip' => $clientIp, 'location' => resolve_location($clientIp),
                'user_id' => $me['id'], 'user_nick' => $me['nickname'],
                'user_color' => $me['avatar_color'] ?? '#d4786e',
            ]);
            header('Location: index.php?p=posts&posted=1');
            exit;
        }
    }
}

if (isset($_GET['cmt'])) $commentMsg = '留言成功！💕';
if (isset($_GET['posted'])) $userPostMsg = '发布成功！💕';

$C = get_config();
$P = posts_all();
$PL = places_all();
$T = todos_all();
$PH = photos_all();
$PG = pages_all();
$CM = comments_all();
$V = bump_visit();



$n1 = $C['name1'] ?? '男神';
$n2 = $C['name2'] ?? '女神';
$a1 = !empty($C['avatar1']) ? $C['avatar1'] : '';
$a2 = !empty($C['avatar2']) ? $C['avatar2'] : '';
$ld = $C['love_date'] ?? '2024-01-01';
$bn = $C['beian'] ?? '本站由小兔云提供技术支持 · 仅供个人使用';
$st = ($C['site_title'] ?? '') ?: "$n1 ❤ $n2";
$ds = floor((time() - strtotime($ld)) / 86400);
$y = floor($ds / 365); $m = floor(($ds % 365) / 30); $d = ($ds % 365) % 30;

function TA($dt) {
    $df = time() - strtotime($dt);
    if ($df < 60) return '刚刚';
    if ($df < 3600) return floor($df/60).'分钟前';
    if ($df < 86400) return floor($df/3600).'小时前';
    if ($df < 2592000) return floor($df/86400).'天前';
    return date('Y-m-d', strtotime($dt));
}
function AV($u, $e) {
    if ($u) return '<img src="'.htmlspecialchars($u, ENT_QUOTES).'" alt="avatar" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">';
    return htmlspecialchars($e);
}

$pg = $_GET['p'] ?? 'home';
if (($_GET['act'] ?? '') === 'logout') { session_destroy(); header('Location: index.php'); exit; }
$validPages = ['home','posts','album','places','todos'];
$customSlugs = [];
foreach ($PG as $cp) {
    if (!empty($cp['slug'])) { $validPages[] = $cp['slug']; $customSlugs[$cp['slug']] = $cp; }
}
if (!in_array($pg, $validPages)) $pg = 'home';
$isCustomPage = isset($customSlugs[$pg]);
$cp = $isCustomPage ? $customSlugs[$pg] : null;

function NI($pg, $cur, $i) {
    $a = ($pg === $cur) ? ' class="active"' : '';
    $lb = ['home'=>'首页','posts'=>'说说','album'=>'相册','places'=>'足迹','todos'=>'清单'];
    $l = isset($lb[$pg]) ? $lb[$pg] : $pg;
    return '<a href="?p='.htmlspecialchars($pg).'"'.$a.'><span class="ni">'.$i.'</span><span class="nl">'.$l.'</span></a>';
}
$DN = count(array_filter($T, function($t){return !empty($t['done']);}));

function renderPostCard($po, $CM, $n1, $n2, $a1, $a2, $me) {
    $pid = $po['id'] ?? '';
    $isUserPost = !empty($po['user_id']);
    if ($isUserPost) {
        $pav = '';
        $pem = '👤';
        $pname = htmlspecialchars($po['user_nick'] ?? '用户');
        $pcolor = '#888';
    } else {
        $pav = ($po['author']??'1')==='1'?$a1:$a2;
        $pem = ($po['author']??'1')==='1'?'👦':'👧';
        $pname = htmlspecialchars(($po['author']??'1')==='1'?$n1:$n2);
        $pcolor = '';
    }
    $postComments = [];
    foreach ($CM as $c) { if (($c['post_id'] ?? '') === $pid) $postComments[] = $c; }
    $cc = count($postComments);
    $o = '<div class="ncs pc">';
    $o .= '<div class="ph"><div class="pa"'.($isUserPost?' style="background:'.htmlspecialchars($po['user_color']??'#d4786e').'"':'').'>'.AV($pav,$pem).'</div><div class="pi"><div class="name"'.($pcolor?' style="color:'.$pcolor.'"':'').'>'.$pname.'</div><div class="time">'.htmlspecialchars($po['time']).(!empty($po['location'])&&$po['location']!=='未知'?' · 📍 '.htmlspecialchars($po['location']):'').($isUserPost?' · 👤 用户':'').'</div></div><div class="pm">'.htmlspecialchars($po['mood']??'💕').'</div></div>';
    if(!empty($po['title'])) $o .= '<div class="ptitle">'.htmlspecialchars($po['title']).'</div>';
    $o .= '<div class="pb">'.nl2br(htmlspecialchars($po['content'])).'</div>';
    if(!empty($po['tags'])) { $o .= '<div class="ptags">'; foreach($po['tags'] as $t) $o .= '<span class="tag">#'.htmlspecialchars($t).'</span>'; $o .= '</div>'; }
    if(!empty($po['images'])) { $o .= '<div class="pimgs '.((count($po['images'])===1)?'c1':((count($po['images'])===2)?'c2':'')).'">'; foreach($po['images'] as $im) $o .= '<img src="'.htmlspecialchars($im).'" onclick="l(\''.htmlspecialchars($im,ENT_QUOTES).'\')" loading="lazy">'; $o .= '</div>'; }
    if(!empty($po['video'])) { $o .= '<div class="pvideo"><video src="'.htmlspecialchars($po['video']).'" controls preload="metadata" style="width:100%;max-height:400px;border-radius:var(--rx)">您的浏览器不支持视频播放</video></div>'; }
    if(!empty($po['music'])) { $o .= '<div class="pmusic"><audio src="'.htmlspecialchars($po['music']).'" controls preload="metadata" style="width:100%">您的浏览器不支持音频播放</audio></div>'; }
    // Comments section
    $o .= '<div class="cmts">';
    $o .= '<div class="cmt-toggle" onclick="this.nextElementSibling.classList.toggle(\'show\')"><span>💬 '.$cc.' 条留言</span><span class="cmt-arr">▾</span></div>';
    $o .= '<div class="cmt-body">';
    if (!empty($postComments)) {
        foreach ($postComments as $ct) {
            $o .= '<div class="cmt"><div class="cmt-nick">'.htmlspecialchars($ct['nick']).'</div><div class="cmt-text">'.nl2br(htmlspecialchars($ct['text'])).'</div><div class="cmt-time">'.TA($ct['time']).'</div></div>';
        }
    }
    if ($me) {
        $o .= '<form method="post" class="cmt-form">'.csrf_field().'<input type="hidden" name="act" value="comment"><input type="hidden" name="post_id" value="'.htmlspecialchars($pid).'"><textarea name="text" placeholder="说点什么…" required maxlength="500" rows="2"></textarea><button type="submit">💬 留言</button></form>';
    } else {
        $o .= '<div class="cmt-login" style="padding:10px;text-align:center;font-size:.85em;color:var(--tl)"><a href="login.php" style="color:var(--pri)">登录</a> 后才能留言</div>';
    }
    $o .= '</div></div>';
    $o .= '</div>';
    return $o;
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title><?php echo htmlspecialchars($st); ?></title>
<link rel="icon" href="data:image/svg+xml,💕">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--pri:#d4786e;--pl:#f0b4ac;--ac:#c7a98c;--tx:#5a4e4a;--tl:#8c7e78;--r:18px;--rs:12px;--rx:8px}
body{font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei',sans-serif;background:#f2ede9;color:var(--tx);min-height:100vh;overflow-x:hidden;line-height:1.6}
.main-container{max-width:520px;margin:0 auto;padding:16px 16px 110px;position:relative;z-index:1}
.nc{background:#fff;border-radius:var(--r);box-shadow:0 2px 12px rgba(0,0,0,0.06);padding:24px;margin-bottom:16px}
.ncs{padding:16px;border-radius:var(--rs);box-shadow:0 1px 8px rgba(0,0,0,0.04);background:#fff;margin-bottom:12px}
.hero{text-align:center;padding:30px 0 20px}
.avd{display:flex;justify-content:center;align-items:center;gap:12px;margin-bottom:16px}
.avd .av{width:70px;height:70px;border-radius:50%;box-shadow:0 2px 12px rgba(0,0,0,0.1);display:flex;align-items:center;justify-content:center;font-size:2em;background:#fff;overflow:hidden;transition:transform .2s}
.avd .av:hover{transform:scale(1.08)}
.avd .hi{font-size:1.8em;animation:pulse 1.5s ease infinite}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.25)}}
.hero h1{font-size:1.5em;font-weight:800;letter-spacing:2px;background:linear-gradient(135deg,var(--pri),var(--ac));-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:4px}
.hero .sub{font-size:.85em;color:var(--tl);letter-spacing:1px}
.tc{text-align:center;position:relative;overflow:hidden}
.tc .tl{font-size:.82em;color:var(--tl);letter-spacing:3px;margin-bottom:8px}
.tc .tn{font-size:4em;font-weight:900;letter-spacing:4px;background:linear-gradient(180deg,var(--pri),var(--ac));-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;margin:8px 0}
.tc .td{font-size:.9em;color:var(--tl);margin-top:4px}
.tc .tdt{font-size:.78em;color:var(--tl);margin-top:12px;padding-top:12px;border-top:1px solid rgba(0,0,0,0.05)}
.sr{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.ss{text-align:center;padding:14px 8px}
.ss .n{font-size:1.5em;font-weight:800;color:var(--pri);line-height:1;margin-bottom:4px}
.ss .l{font-size:.7em;color:var(--tl);letter-spacing:1px}
.bn{position:fixed;bottom:16px;left:50%;transform:translateX(-50%);background:#fff;border-radius:26px;box-shadow:0 4px 20px rgba(0,0,0,0.1),0 2px 6px rgba(0,0,0,0.04);display:flex;padding:6px 10px;z-index:100;gap:0;overflow-x:auto;max-width:95vw}
.bn a{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:7px 13px;border-radius:20px;text-decoration:none;color:#888;transition:all .2s;min-width:50px;font-weight:500;flex-shrink:0}
.bn a .ni{font-size:1.35em;line-height:1;margin-bottom:2px}
.bn a .nl{font-size:.6em}
.bn a.active{color:#e85d5d;font-weight:700}
.bn a:active{transform:scale(.94)}
@media(min-width:600px){.bn a{padding:9px 18px}}
.sh{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.sh .si{font-size:1.3em}
.sh .st{font-size:1.05em;font-weight:700;color:var(--tx);letter-spacing:1px}
.sh .sl{flex:1;height:2px;border-radius:2px;background:linear-gradient(to right,var(--pl),transparent)}
.sh .sc{font-size:.75em;color:var(--tl);background:#fff;padding:3px 10px;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,0.06)}
.pc{margin-bottom:12px}
.pc .ph{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.pc .pa{width:40px;height:40px;border-radius:50%;box-shadow:0 1px 6px rgba(0,0,0,0.08);overflow:hidden;display:flex;align-items:center;justify-content:center;font-size:1.2em;background:#fff;flex-shrink:0}
.pc .pa img{width:100%;height:100%;object-fit:cover;border-radius:50%}
.pc .pi .name{font-weight:700;font-size:.9em}
.pc .pi .time{font-size:.72em;color:var(--tl)}
.pc .pm{font-size:1.4em;margin-left:auto}
.pc .pb{font-size:.93em;line-height:1.7;color:var(--tx);white-space:pre-wrap;word-break:break-word}
.pc .ptitle{font-size:1.1em;font-weight:700;color:var(--pri);margin-bottom:8px}
.pc .ptags{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
.pc .ptags .tag{font-size:.7em;color:var(--pri);background:rgba(212,120,110,.08);padding:3px 10px;border-radius:12px}
.pc .pimgs{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-top:12px}
.pc .pimgs.c2{grid-template-columns:repeat(2,1fr)}
.pc .pimgs.c1{grid-template-columns:1fr}
.pc .pimgs img{width:100%;aspect-ratio:1;object-fit:cover;border-radius:var(--rx);cursor:pointer;box-shadow:0 1px 4px rgba(0,0,0,0.08);transition:transform .2s}
.pc .pimgs img:hover{transform:scale(1.03)}
.pc .pvideo{margin-top:12px}
.pc .pvideo video{width:100%;border-radius:var(--rx);box-shadow:0 1px 4px rgba(0,0,0,0.08);background:#000}
.pc .pmusic{margin-top:12px}
.pc .pmusic audio{width:100%;border-radius:var(--rx);box-shadow:0 1px 4px rgba(0,0,0,0.06)}
/* Comments */
.cmts{margin-top:12px;border-top:1px solid rgba(0,0,0,.05);padding-top:10px}
.cmt-toggle{cursor:pointer;display:flex;align-items:center;justify-content:space-between;font-size:.8em;color:var(--tl);padding:6px 0}
.cmt-toggle:hover{color:var(--tx)}
.cmt-arr{font-size:.8em;transition:transform .2s}
.cmt-body{display:none}
.cmt-body.show{display:block}
.cmt{background:rgba(0,0,0,.02);border-radius:10px;padding:10px 14px;margin-bottom:8px}
.cmt .cmt-nick{font-weight:700;font-size:.82em;color:var(--pri);margin-bottom:3px}
.cmt .cmt-text{font-size:.82em;color:var(--tx);line-height:1.5;word-break:break-word}
.cmt .cmt-time{font-size:.68em;color:var(--tl);margin-top:4px}
.cmt-form{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.cmt-form input[type=text]{flex:1;min-width:80px;padding:8px 12px;border:1px solid rgba(0,0,0,.08);border-radius:10px;font-size:.82em;outline:none;background:#fff;font-family:inherit}
.cmt-form textarea{width:100%;padding:8px 12px;border:1px solid rgba(0,0,0,.08);border-radius:10px;font-size:.82em;outline:none;resize:vertical;min-height:36px;font-family:inherit}
.cmt-form button{padding:8px 18px;background:var(--pri);color:#fff;border:none;border-radius:10px;font-size:.82em;cursor:pointer;transition:opacity .2s;white-space:nowrap}
.cmt-form button:hover{opacity:.85}
.cmt-msg{padding:8px 12px;border-radius:8px;margin-bottom:8px;font-size:.8em}
.cmt-msg.ok{background:#e8f5e9;color:#2e7d32}
.cmt-msg.err{background:#ffebee;color:#c62828}
.ag{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}
.ai{border-radius:var(--rs);overflow:hidden;cursor:pointer;box-shadow:0 1px 6px rgba(0,0,0,0.06);aspect-ratio:1;position:relative;transition:transform .2s}
.ai:hover{transform:translateY(-2px)}
.ai img{width:100%;height:100%;object-fit:cover}
.ai .cap{position:absolute;bottom:0;left:0;right:0;padding:8px 12px;background:linear-gradient(transparent,rgba(0,0,0,0.5));color:#fff;font-size:.78em;font-weight:600}
.plc{display:flex;gap:14px;align-items:flex-start}
.plc .pimg{width:70px;height:70px;border-radius:var(--rs);box-shadow:0 1px 6px rgba(0,0,0,0.06);object-fit:cover;flex-shrink:0;background:#fff;display:flex;align-items:center;justify-content:center;font-size:2em}
.plc .pimg.ni{box-shadow:inset 0 1px 4px rgba(0,0,0,0.06)}
.plc .pin{flex:1}
.plc .pn{font-weight:700;font-size:.95em;margin-bottom:2px}
.plc .pd{font-size:.72em;color:var(--tl);margin-bottom:4px}
.plc .pnote{font-size:.82em;color:var(--tl);line-height:1.5}
.ti{display:flex;align-items:center;gap:12px;padding:14px 0;border-bottom:1px solid rgba(0,0,0,0.04)}
.ti:last-child{border-bottom:none}
.tc2{width:38px;height:38px;border-radius:50%;box-shadow:0 1px 6px rgba(0,0,0,0.08);display:flex;align-items:center;justify-content:center;font-size:1.2em;flex-shrink:0;cursor:pointer}
.tc2.done{box-shadow:inset 0 1px 4px rgba(0,0,0,0.08);color:var(--pri)}
.tcnt{flex:1}
.tcnt .tt{font-weight:600;font-size:.93em}
.tcnt .tt.dt{text-decoration:line-through;color:var(--tl)}
.tcnt .tm{font-size:.7em;color:var(--tl);margin-top:2px}
.tcnt .tnote{font-size:.8em;color:var(--tl)}
.empty{text-align:center;padding:40px 20px;color:var(--tl)}
.empty .ei{font-size:3em;margin-bottom:10px;opacity:.6}
.empty .et{font-size:.9em}
.pr{display:flex;align-items:center;justify-content:space-between;padding:14px 18px;cursor:pointer;text-decoration:none;color:var(--tx)}
.pr .pl{display:flex;align-items:center;gap:10px}
.pr .pv{font-size:1.3em}
.pr .pt{font-weight:600;font-size:.93em}
.pr .pc2{font-size:.78em;color:var(--tl);background:#f5f5f5;padding:2px 10px;border-radius:10px}
.pr .ar{color:var(--tl);font-size:.9em}
.lb{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.92);z-index:9999;align-items:center;justify-content:center;cursor:pointer}
.lb.show{display:flex}
.lb img{max-width:92vw;max-height:85vh;border-radius:8px}
.lb .lcl{position:absolute;top:20px;right:24px;color:#fff;font-size:2em;cursor:pointer;width:44px;height:44px;display:flex;align-items:center;justify-content:center;border-radius:50%;background:rgba(255,255,255,0.1)}
.pts{position:fixed;inset:0;pointer-events:none;z-index:0}
.pt{position:absolute;animation:floatUp 5s ease-in infinite;opacity:0}
@keyframes floatUp{0%{transform:translateY(105vh)scale(0);opacity:0}10%{opacity:.5}90%{opacity:.15}100%{transform:translateY(-5vh)scale(1.2);opacity:0}}
.ft{text-align:center;padding:20px 12px 8px;color:var(--tl);font-size:.7em;line-height:1.8}
@media(min-width:600px){.main-container{padding:24px 24px 110px}.ag{grid-template-columns:repeat(3,1fr)}}
.bn a[href="?p=home"] .ni{color:#e85d5d}
.bn a[href="?p=posts"] .ni{color:#5c9ce6}
.bn a[href="?p=album"] .ni{color:#4da6ff}
.bn a[href="?p=places"] .ni{color:#e8553d}
.bn a[href="?p=todos"] .ni{color:#5cb85c}
.bn a.active[href="?p=home"] .ni{color:#e85d5d}
.bn a.active[href="?p=posts"] .ni{color:#4a8ed4}
.bn a.active[href="?p=album"] .ni{color:#3a94e8}
.bn a.active[href="?p=places"] .ni{color:#d44a33}
.bn a.active[href="?p=todos"] .ni{color:#4aaa4e}
.cp-content{font-size:.93em;line-height:1.8;color:var(--tx);word-break:break-word}
.cp-content img{max-width:100%;border-radius:8px;margin:8px 0}
.cp-content h3,.cp-content h4{color:var(--pri);margin:16px 0 8px}
.cp-content p{margin:0 0 12px}
</style>
</head>
<body<?php if(!empty($C['background_image'])): ?> style="background-image:url('<?php echo htmlspecialchars($C['background_image']); ?>');background-size:cover;background-position:center;background-attachment:fixed;"<?php endif; ?>>
<div class="pts" id="pcs"></div>
<div class="main-container">

<div class="hero">
<div class="avd">
<div class="av"><?php echo AV($a1, '👦'); ?></div>
<div class="hi">💕</div>
<div class="av"><?php echo AV($a2, '👧'); ?></div>
</div>
<h1><?php echo htmlspecialchars($st); ?></h1>
<div class="sub">✦ <?php echo htmlspecialchars($n1); ?> & <?php echo htmlspecialchars($n2); ?> ✦</div>
</div>

<?php if ($commentMsg): ?><div class="cmt-msg ok">✅ <?php echo htmlspecialchars($commentMsg); ?></div><?php endif; ?>
<?php if ($commentErr): ?><div class="cmt-msg err">❌ <?php echo htmlspecialchars($commentErr); ?></div><?php endif; ?>

<?php if ($pg === 'home'): ?>
<div class="nc tc">
<div class="tl">已 经 在 一 起</div>
<div class="tn" id="dc"><?php echo $ds; ?></div>
<div class="td"><?php echo $y; ?>年 <?php echo $m; ?>个月 <?php echo $d; ?>天</div>
<div class="tdt">📅 <?php echo date('Y/m/d', strtotime($ld)); ?> → ∞</div>
</div>

<div class="sr">
<div class="ncs ss"><div class="n"><?php echo count($P); ?></div><div class="l">💬 说说</div></div>
<div class="ncs ss"><div class="n"><?php echo count($PH); ?></div><div class="l">📷 相册</div></div>
<div class="ncs ss"><div class="n"><?php echo count($PL); ?></div><div class="l">📍 足迹</div></div>
<div class="ncs ss"><div class="n"><?php echo $DN.'/'.count($T); ?></div><div class="l">✅ 清单</div></div>
</div>

<a href="?p=posts" style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.04);margin-bottom:10px;text-decoration:none;color:#5a4e4a;font-size:.93em;font-weight:600"><span style="display:flex;align-items:center;gap:10px"><span style="font-size:1.3em">💬</span><span>甜蜜说说</span></span><span style="font-size:.78em;color:#8c7e78;background:#f0f0f0;padding:2px 10px;border-radius:10px"><?php echo count($P); ?>条</span><span class="ar">›</span></a>
<a href="?p=album" style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.04);margin-bottom:10px;text-decoration:none;color:#5a4e4a;font-size:.93em;font-weight:600"><span style="display:flex;align-items:center;gap:10px"><span style="font-size:1.3em">📷</span><span>我们的相册</span></span><span style="font-size:.78em;color:#8c7e78;background:#f0f0f0;padding:2px 10px;border-radius:10px"><?php echo count($PH); ?>张</span><span class="ar">›</span></a>
<a href="?p=places" style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.04);margin-bottom:10px;text-decoration:none;color:#5a4e4a;font-size:.93em;font-weight:600"><span style="display:flex;align-items:center;gap:10px"><span style="font-size:1.3em">📍</span><span>去过的地方</span></span><span style="font-size:.78em;color:#8c7e78;background:#f0f0f0;padding:2px 10px;border-radius:10px"><?php echo count($PL); ?>个</span><span class="ar">›</span></a>
<a href="?p=todos" style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.04);margin-bottom:10px;text-decoration:none;color:#5a4e4a;font-size:.93em;font-weight:600"><span style="display:flex;align-items:center;gap:10px"><span style="font-size:1.3em">✅</span><span>一起完成的事</span></span><span style="font-size:.78em;color:#8c7e78;background:#f0f0f0;padding:2px 10px;border-radius:10px"><?php echo $DN.'/'.count($T); ?></span><span class="ar">›</span></a>

<?php if (!empty($P)): ?>
<div class="sh" style="margin-top:8px"><span class="si">💬</span><span class="st">最新说说</span><span class="sl"></span></div>
<?php foreach (array_slice($P,0,3) as $po) echo renderPostCard($po, $CM, $n1, $n2, $a1, $a2, $me); endif; ?>

<?php if (!empty($PG)): ?>
<div class="sh" style="margin-top:8px"><span class="si">📑</span><span class="st">更多精彩</span><span class="sl"></span></div>
<?php foreach($PG as $cpg):?>
<a href="?p=<?php echo htmlspecialchars($cpg['slug']);?>" style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-radius:12px;background:#fff;box-shadow:0 1px 8px rgba(0,0,0,0.04);margin-bottom:10px;text-decoration:none;color:#5a4e4a;font-size:.93em;font-weight:600"><span style="display:flex;align-items:center;gap:10px"><span style="font-size:1.3em"><?php echo htmlspecialchars($cpg['icon']??'📄');?></span><span><?php echo htmlspecialchars($cpg['title']);?></span></span><span class="ar">›</span></a>
<?php endforeach; endif; ?>

<?php if (!empty($PH)): $lp = array_slice($PH,0,4); ?>
<div class="sh" style="margin-top:8px"><span class="si">📷</span><span class="st">最新照片</span><span class="sl"></span></div>
<div class="ag"><?php foreach($lp as $ph): ?><div class="ai" onclick="l('<?php echo htmlspecialchars($ph['url'],ENT_QUOTES); ?>')"><img src="<?php echo htmlspecialchars($ph['url']); ?>" loading="lazy"><?php if(!empty($ph['title'])):?><div class="cap"><?php echo htmlspecialchars($ph['title']); ?></div><?php endif; ?></div><?php endforeach; ?></div>
<?php endif; endif; /* end home */ ?>

<?php if ($pg === 'posts'): ?>
<div class="sh"><span class="si">💬</span><span class="st">甜蜜说说</span><span class="sc"><?php echo count($P); ?></span></div>
<?php if (empty($P)): ?><div class="ncs empty"><div class="ei">💭</div><div class="et">还没有说说<br>去后台发布第一条吧~</div></div>
<?php else: foreach($P as $po) echo renderPostCard($po, $CM, $n1, $n2, $a1, $a2, $me); endif; endif; ?>

<?php if ($pg === 'album'): ?>
<div class="sh"><span class="si">📷</span><span class="st">我们的相册</span><span class="sc"><?php echo count($PH); ?>张</span></div>
<?php if (empty($PH)): ?><div class="ncs empty"><div class="ei">🖼️</div><div class="et">相册还是空的<br>去后台添加照片吧~</div></div>
<?php else: ?><div class="ag"><?php foreach($PH as $ph): ?><div class="ai" onclick="l('<?php echo htmlspecialchars($ph['url'],ENT_QUOTES); ?>')"><img src="<?php echo htmlspecialchars($ph['url']); ?>" loading="lazy"><?php if(!empty($ph['title'])):?><div class="cap"><?php echo htmlspecialchars($ph['title']); ?></div><?php endif; ?></div><?php endforeach; ?></div><?php endif; endif; ?>

<?php if ($pg === 'places'): ?>
<div class="sh"><span class="si">📍</span><span class="st">去过的地方</span><span class="sc"><?php echo count($PL); ?>个</span></div>
<?php if (empty($PL)): ?><div class="ncs empty"><div class="ei">🗺️</div><div class="et">还没有记录一起去过的地方</div></div>
<?php else: foreach($PL as $pl): ?><div class="ncs plc"><?php if (!empty($pl['image'])): ?><img class="pimg" src="<?php echo htmlspecialchars($pl['image']); ?>" onclick="l('<?php echo htmlspecialchars($pl['image'],ENT_QUOTES); ?>')" loading="lazy"><?php else: ?><div class="pimg ni">📍</div><?php endif; ?><div class="pin"><div class="pn"><?php echo htmlspecialchars($pl['name']??'未知地点'); ?></div><div class="pd">🕐 <?php echo htmlspecialchars($pl['time']??''); ?></div><?php if (!empty($pl['note'])): ?><div class="pnote"><?php echo nl2br(htmlspecialchars($pl['note'])); ?></div><?php endif; ?></div></div><?php endforeach; endif; endif; ?>

<?php if ($pg === 'todos'): ?>
<div class="sh"><span class="si">✅</span><span class="st">一起完成的事</span><span class="sc"><?php echo $DN.'/'.count($T); ?></span></div>
<?php if (empty($T)): ?><div class="ncs empty"><div class="ei">📋</div><div class="et">清单还是空的<br>去后台添加想一起做的事吧~</div></div>
<?php else: usort($T,function($a,$b){return ($a['done']??0)-($b['done']??0)?:strtotime($b['time'])-strtotime($a['time']);}); foreach($T as $td): $isd=!empty($td['done']); ?>
<div class="ti"><div class="tc2 <?php echo $isd?'done':''; ?>"><?php echo $isd?'✅':'⬜'; ?></div><div class="tcnt"><div class="tt <?php echo $isd?'dt':''; ?>"><?php echo htmlspecialchars($td['title']); ?></div><div class="tm"><?php echo $isd?'✅ 已完成 · '.htmlspecialchars($td['done_time']??''):'📝 创建于 '.htmlspecialchars($td['time']??''); ?></div><?php if (!empty($td['note'])): ?><div class="tnote"><?php echo htmlspecialchars($td['note']); ?></div><?php endif; ?></div></div>
<?php endforeach; endif; endif; ?>

<?php if ($isCustomPage): ?>
<div class="sh"><span class="si"><?php echo htmlspecialchars($cp['icon']??'📄');?></span><span class="st"><?php echo htmlspecialchars($cp['title']);?></span></div>
<div class="nc cp-content"><?php echo $cp['content'] ?? '<p>暂无内容</p>'; ?></div>
<?php endif; ?>

<?php if ($me): ?>
<div class="nc" id="post_box" style="display:none">
<div class="card-title" style="font-size:1.05em;font-weight:700;color:var(--tx);margin-bottom:14px">✏️ 发说说</div>
<?php if ($userPostMsg): ?><div style="padding:10px 14px;border-radius:10px;margin-bottom:12px;font-size:.85em;background:#e8f5e9;color:#2e7d32">✅ <?php echo htmlspecialchars($userPostMsg); ?></div><?php endif; ?>
<?php if ($userPostErr): ?><div style="padding:10px 14px;border-radius:10px;margin-bottom:12px;font-size:.85em;background:#ffebee;color:#c0392b">❌ <?php echo htmlspecialchars($userPostErr); ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="user_post">
<div class="fg"><label>💬 说点什么</label><textarea name="content" rows="3" placeholder="分享你的想法..." required maxlength="2000" style="width:100%;padding:11px 15px;background:#f5f1ee;border:none;border-radius:10px;box-shadow:inset 2px 2px 6px rgba(0,0,0,0.04);font-size:.92em;color:var(--tx);outline:none;font-family:-apple-system,BlinkMacSystemFont,'PingFang SC','Microsoft YaHei',sans-serif;resize:vertical"></textarea></div>
<div style="display:flex;gap:10px;flex-wrap:wrap">
<label style="flex:1;min-width:120px"><span style="font-size:.78em;color:var(--tl)">📷 图片</span><input type="file" name="images[]" accept="image/*" multiple style="width:100%;margin-top:4px;font-size:.8em"></label>
<label style="flex:1;min-width:120px"><span style="font-size:.78em;color:var(--tl)">🎬 视频</span><input type="file" name="video" accept="video/*" style="width:100%;margin-top:4px;font-size:.8em"></label>
<label style="flex:1;min-width:120px"><span style="font-size:.78em;color:var(--tl)">🎵 音乐</span><input type="file" name="music" accept="audio/*" style="width:100%;margin-top:4px;font-size:.8em"></label>
</div>
<button type="submit" style="margin-top:14px;padding:10px 24px;border:none;border-radius:10px;font-size:.9em;font-weight:700;cursor:pointer;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.06);color:var(--pri)">💕 发布</button>
</form>
</div>
<?php endif; ?>

<div class="ft">
<p>💕 <?php echo htmlspecialchars($n1); ?> & <?php echo htmlspecialchars($n2); ?></p>
<p><?php echo htmlspecialchars($bn); ?></p>
</div>
</div>

<nav class="bn">
<?php echo NI('home',$pg,'🏠'); ?>
<?php echo NI('posts',$pg,'💬'); ?>
<?php echo NI('album',$pg,'📷'); ?>
<?php echo NI('places',$pg,'📍'); ?>
<?php echo NI('todos',$pg,'✅'); ?>
<?php foreach($PG as $cpg): ?>
<a href="?p=<?php echo htmlspecialchars($cpg['slug']);?>"<?php echo $pg===$cpg['slug']?' class="active"':'';?>><span class="ni"><?php echo htmlspecialchars($cpg['icon']??'📄');?></span><span class="nl"><?php echo htmlspecialchars($cpg['title']);?></span></a>
<?php endforeach; ?>
<?php if ($me): ?>
<a href="#" onclick="document.getElementById('post_box').style.display='block';document.getElementById('post_box').scrollIntoView({behavior:'smooth'})" style="color:#2e7d32"><span class="ni">✏️</span><span class="nl">发说说</span></a>
<a href="user.php"><span class="ni">👤</span><span class="nl"><?php echo htmlspecialchars($me['nickname']); ?></span></a>
<a href="?act=logout"><span class="ni">🚪</span><span class="nl">退出</span></a>
<?php else: ?>
<a href="login.php"><span class="ni">🔑</span><span class="nl">登录</span></a>
<?php endif; ?>
<?php if (isset($_SESSION['cp_admin'])): ?>
<a href="admin/index.php"><span class="ni">⚙️</span><span class="nl">管理</span></a>
<?php endif; ?>
</nav>

<div class="lb" id="lbx" onclick="this.classList.remove('show')"><span class="lcl">&times;</span><img id="lbi" src=""></div>
<script>
function l(s){event.stopPropagation();document.getElementById('lbi').src=s;document.getElementById('lbx').classList.add('show')}
!function(){var c=document.getElementById('pcs'),e=['❤️','💕','💖','💗','💝','✨','🌸','💫','🕊️'];setInterval(function(){var p=document.createElement('span');p.className='pt';p.textContent=e[Math.floor(Math.random()*e.length)];p.style.left=Math.random()*100+'%';p.style.animationDuration=(4+Math.random()*6)+'s';p.style.fontSize=(14+Math.random()*22)+'px';c.appendChild(p);setTimeout(function(){p.remove()},8000)},500)}();
setInterval(function(){var el=document.getElementById('dc');if(el){var ld=new Date('<?php echo htmlspecialchars($ld); ?>'),df=Math.floor((Date.now()-ld)/86400000);if(el.textContent!=df){el.style.transform='scale(1.15)';el.textContent=df;setTimeout(function(){el.style.transform='scale(1)'},300)}}},60000);
</script>
</body>
</html>