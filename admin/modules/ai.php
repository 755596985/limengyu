<?php
/**
 * 模块：AI 助手 (ai)
 * 功能：ai_save（保存 AI 接口配置）、ai_chat（AI 对话 + 工具调用）
 * 双模式文件：handle=POST 处理，render=页面渲染
 */
require_once __DIR__ . '/ai_presets.php';

// ==================== AI 配置与常量 ====================
$AI_DEFAULT_BASE = 'https://token.sensenova.cn/v1';
$AI_DEFAULT_MODEL = 'sensenova-6.7-flash-lite';
$AI_DEFAULT_KEY = ''; // 请在后台上传自己的 API Key
$AI_MAX_ROUNDS = 8;
$AI_MEMORY_LIMIT = 100;   // 记忆条数上限
$AI_MEMORY_INJECT = 30;   // 注入 system 的记忆条数

// ==================== AI 工具定义 ====================
function ai_tools(): array {
    return [
        [
            'type' => 'function',
            'function' => [
                'name' => 'publish_post',
                'description' => '发布一条说说（情侣小窝前台动态）。发布成功后返回新说说的 id 和内容。',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'content' => ['type' => 'string', 'description' => '说说正文内容，必填'],
                        'title' => ['type' => 'string', 'description' => '标题（可选，发布文章式说说时使用）'],
                        'author' => ['type' => 'string', 'enum' => ['1', '2'], 'description' => '发布身份：1=男主，2=女主，默认 1'],
                        'mood' => ['type' => 'string', 'description' => '心情表情，如 💕😊🎉，默认 💕'],
                        'tags' => ['type' => 'string', 'description' => '标签，多个用英文逗号分隔（可选）'],
                        'location' => ['type' => 'string', 'description' => '地点（可选）'],
                    ],
                    'required' => ['content'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_posts',
                'description' => '查看最近发布的说说列表，返回 id、标题、内容、作者、心情、时间、地点。',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => '返回条数，默认 5，最多 20'],
                    ],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'delete_post',
                'description' => '按 id 删除一条说说（同时删除其下的留言和相关文件）。id 可从 list_posts 获得。',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => '要删除的说说的 id'],
                    ],
                    'required' => ['id'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_stats',
                'description' => '获取站点统计数据：说说总数、留言总数、注册用户数、访客总数/今日访客。',
                'parameters' => ['type' => 'object', 'properties' => []],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_comments',
                'description' => '查看最近留言列表，返回留言 id、所属说说 id、昵称、内容、时间、是否为回复（parent_id）。',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'description' => '返回条数，默认 5，最多 20'],
                    ],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'comment_post',
                'description' => '对某条说说发表一条评论（如观后感、回应）。需要提供说说的 id 和评论内容。',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'post_id' => ['type' => 'string', 'description' => '要评论的说说的 id（可从 list_posts 获得）'],
                        'text' => ['type' => 'string', 'description' => '评论内容，必填'],
                    ],
                    'required' => ['post_id', 'text'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'reply_comment',
                'description' => '回复某条留言/评论。需要提供被回复的留言 id 和回复内容。',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'comment_id' => ['type' => 'string', 'description' => '要回复的留言的 id（可从 list_comments 获得）'],
                        'text' => ['type' => 'string', 'description' => '回复内容，必填'],
                    ],
                    'required' => ['comment_id', 'text'],
                ],
            ],
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'delete_comment',
                'description' => '按 id 删除一条留言。id 可从 list_comments 获得。',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => '要删除的留言的 id'],
                    ],
                    'required' => ['id'],
                ],
            ],
        ],
    ];
}

function ai_ai_config(): array {
    $c = get_config();
    $presets = ai_presets();
    $personaKey = trim($c['ai_persona_key'] ?? '');
    if ($personaKey === '' || ($personaKey !== 'custom' && !isset($presets[$personaKey]))) {
        $personaKey = '温柔粘人';
    }
    $custom = json_decode((string)($c['ai_persona_custom'] ?? ''), true);
    if (!is_array($custom)) { $custom = []; }
    return [
        'base_url' => trim($c['ai_base_url'] ?? '') ?: $GLOBALS['AI_DEFAULT_BASE'],
        'api_key'  => trim($c['ai_api_key'] ?? '') ?: $GLOBALS['AI_DEFAULT_KEY'],
        'model'    => trim($c['ai_model'] ?? '') ?: $GLOBALS['AI_DEFAULT_MODEL'],
        'persona_key' => $personaKey,
        'persona_custom' => [
            'name' => trim((string)($custom['name'] ?? '')),
            'personality' => trim((string)($custom['personality'] ?? '')),
            'background' => trim((string)($custom['background'] ?? '')),
        ],
        'emotion_on' => (int)($c['ai_emotion_on'] ?? 1) === 1,
        'memory_on' => (int)($c['ai_memory_on'] ?? 1) === 1,
        'auto_reply_on' => (int)($c['ai_auto_reply_on'] ?? 1) === 1,
        'reply_user_id' => trim((string)($c['ai_reply_user_id'] ?? '')),
        'memory' => json_arr((string)($c['ai_memory'] ?? '')),
    ];
}

function ai_save_ai_config(array $c): void {
    ensure_config_column('ai_base_url');
    ensure_config_column('ai_api_key');
    ensure_config_column('ai_model');
    ensure_config_column('ai_persona_key');
    ensure_config_column('ai_persona_custom');
    ensure_config_column('ai_emotion_on');
    ensure_config_column('ai_memory_on');
    ensure_config_column('ai_auto_reply_on');
    ensure_config_column('ai_reply_user_id');
    ensure_config_column('ai_memory');
    $st = db()->prepare('UPDATE cp_config SET ai_base_url=?, ai_api_key=?, ai_model=?, ai_persona_key=?, ai_persona_custom=?, ai_emotion_on=?, ai_memory_on=?, ai_auto_reply_on=?, ai_reply_user_id=?, ai_memory=? WHERE id=1');
    $st->execute([
        $c['base_url'] ?? '', $c['api_key'] ?? '', $c['model'] ?? '',
        $c['persona_key'] ?? '温柔粘人', $c['persona_custom'] ?? '{}',
        $c['emotion_on'] ?? 1, $c['memory_on'] ?? 1, $c['auto_reply_on'] ?? 1,
        $c['reply_user_id'] ?? '', $c['memory'] ?? '[]',
    ]);
}

// 追加一条记忆（自动积累）
function ai_memory_add(string $text): void {
    $cfg = ai_ai_config();
    if (!$cfg['memory_on']) { return; }
    $mem = $cfg['memory'];
    $text = trim($text);
    if ($text === '') { return; }
    $mem[] = ['t' => date('Y-m-d H:i'), 'c' => mb_substr($text, 0, 300)];
    $mem = array_slice($mem, -$GLOBALS['AI_MEMORY_LIMIT']);
    ensure_config_column('ai_memory');
    db()->prepare('UPDATE cp_config SET ai_memory=? WHERE id=1')->execute([json_encode($mem, JSON_UNESCAPED_UNICODE)]);
}

// ==================== 工具执行 ====================
function ai_run_tool(string $name, array $args): array {
    global $ROOT;
    try {
        switch ($name) {
            case 'publish_post': {
                $content = trim((string)($args['content'] ?? ''));
                if ($content === '') {
                    return ['ok' => false, 'error' => '内容不能为空'];
                }
                $moods = ['💕','😊','😢','😡','😴','🎉','🌧️','🔥','🥰','🤔','😎','🥳','🌹','✨'];
                $mood = (string)($args['mood'] ?? '💕');
                if (!in_array($mood, $moods, true)) { $mood = '💕'; }
                $author = (string)($args['author'] ?? '1');
                if (!in_array($author, ['1', '2'], true)) { $author = '1'; }
                $tags = (string)($args['tags'] ?? '');
                $tagArr = $tags !== '' ? array_map('trim', explode(',', $tags)) : [];
                $location = trim((string)($args['location'] ?? ''));
                $title = trim((string)($args['title'] ?? ''));
                $id = new_id();
                post_insert([
                    'id' => $id,
                    'title' => $title,
                    'tags' => $tagArr,
                    'content' => $content,
                    'author' => $author,
                    'mood' => $mood,
                    'time' => date('Y-m-d H:i:s'),
                    'images' => [],
                    'video' => '',
                    'music' => '',
                    'ip' => client_ip(),
                    'location' => $location,
                ]);
                return ['ok' => true, 'message' => '发布成功', 'id' => $id, 'content' => mb_substr($content, 0, 100)];
            }
            case 'list_posts': {
                $limit = max(1, min(20, (int)($args['limit'] ?? 5)));
                $all = posts_all();
                $out = [];
                foreach (array_slice($all, 0, $limit) as $p) {
                    $out[] = [
                        'id' => $p['id'],
                        'title' => $p['title'] ?? '',
                        'content' => mb_substr((string)($p['content'] ?? ''), 0, 120),
                        'author' => ($p['author'] ?? '1') === '2' ? '女主' : '男主',
                        'mood' => $p['mood'] ?? '',
                        'time' => $p['time'] ?? '',
                        'location' => $p['location'] ?? '',
                        'comment_count' => (int)($p['comment_count'] ?? 0),
                    ];
                }
                return ['ok' => true, 'total' => count($all), 'posts' => $out];
            }
            case 'delete_post': {
                $id = (string)($args['id'] ?? '');
                if ($id === '') { return ['ok' => false, 'error' => '缺少 id']; }
                $all = posts_all();
                foreach ($all as $idx => $p) {
                    if (($p['id'] ?? '') === $id) {
                        $ok = post_delete_by_index($idx, $ROOT);
                        return $ok ? ['ok' => true, 'message' => "已删除说说 {$id}"] : ['ok' => false, 'error' => '删除失败'];
                    }
                }
                return ['ok' => false, 'error' => "未找到 id={$id} 的说说"];
            }
            case 'get_stats': {
                $pdo = db();
                $posts = (int)$pdo->query('SELECT COUNT(*) FROM cp_posts')->fetchColumn();
                $comments = (int)$pdo->query('SELECT COUNT(*) FROM cp_comments')->fetchColumn();
                $users = (int)$pdo->query('SELECT COUNT(*) FROM cp_users')->fetchColumn();
                $visit = $pdo->query('SELECT total, today FROM cp_visit WHERE id=1')->fetch(PDO::FETCH_ASSOC);
                return ['ok' => true, 'posts' => $posts, 'comments' => $comments, 'users' => $users, 'visits' => $visit ?: ['total' => 0, 'today' => 0]];
            }
            case 'list_comments': {
                $limit = max(1, min(20, (int)($args['limit'] ?? 5)));
                $all = comments_all();
                $out = [];
                foreach (array_slice($all, 0, $limit) as $c) {
                    $out[] = [
                        'id' => $c['id'],
                        'post_id' => $c['post_id'] ?? '',
                        'nick' => $c['nick'] ?? '',
                        'text' => mb_substr((string)($c['text'] ?? ''), 0, 120),
                        'time' => $c['time'] ?? '',
                        'parent_id' => $c['parent_id'] ?? null,
                    ];
                }
                return ['ok' => true, 'total' => count($all), 'comments' => $out];
            }
            case 'comment_post': {
                $postId = trim((string)($args['post_id'] ?? ''));
                $text = trim((string)($args['text'] ?? ''));
                if ($postId === '') { return ['ok' => false, 'error' => '缺少 post_id']; }
                if ($text === '') { return ['ok' => false, 'error' => '评论内容不能为空']; }
                if (mb_strlen($text) > 500) { $text = mb_substr($text, 0, 500); }
                if (!post_exists($postId)) { return ['ok' => false, 'error' => "未找到 id={$postId} 的说说"]; }
                $acc = ai_reply_account();
                comment_insert([
                    'id' => new_id(),
                    'post_id' => $postId,
                    'nick' => $acc['nick'],
                    'text' => $text,
                    'ip' => client_ip(),
                    'user_id' => $acc['user_id'] !== '' ? $acc['user_id'] : null,
                    'parent_id' => null,
                    'time' => date('Y-m-d H:i:s'),
                ]);
                return ['ok' => true, 'message' => '评论发布成功', 'post_id' => $postId, 'text' => mb_substr($text, 0, 100)];
            }
            case 'reply_comment': {
                $commentId = trim((string)($args['comment_id'] ?? ''));
                $text = trim((string)($args['text'] ?? ''));
                if ($commentId === '') { return ['ok' => false, 'error' => '缺少 comment_id']; }
                if ($text === '') { return ['ok' => false, 'error' => '回复内容不能为空']; }
                if (mb_strlen($text) > 500) { $text = mb_substr($text, 0, 500); }
                $target = null;
                foreach (comments_all() as $c) {
                    if (($c['id'] ?? '') === $commentId) { $target = $c; break; }
                }
                if ($target === null) { return ['ok' => false, 'error' => "未找到 id={$commentId} 的留言"]; }
                $acc = ai_reply_account();
                comment_insert([
                    'id' => new_id(),
                    'post_id' => $target['post_id'] ?? '',
                    'nick' => $acc['nick'],
                    'text' => $text,
                    'ip' => client_ip(),
                    'user_id' => $acc['user_id'] !== '' ? $acc['user_id'] : null,
                    'parent_id' => $commentId,
                    'time' => date('Y-m-d H:i:s'),
                ]);
                return ['ok' => true, 'message' => '回复成功', 'reply_to' => $commentId, 'text' => mb_substr($text, 0, 100)];
            }
            case 'delete_comment': {
                $id = (string)($args['id'] ?? '');
                if ($id === '') { return ['ok' => false, 'error' => '缺少 id']; }
                $ok = comment_delete_by_id($id);
                return $ok ? ['ok' => true, 'message' => "已删除留言 {$id}"] : ['ok' => false, 'error' => "未找到 id={$id} 的留言"];
            }
            default:
                return ['ok' => false, 'error' => "未知工具 {$name}"];
        }
    } catch (Throwable $e) {
        return ['ok' => false, 'error' => '执行异常: ' . $e->getMessage()];
    }
}

// ==================== AI API 调用 ====================
function ai_chat_completion(array $messages, array $tools, int $timeout = 120): array {
    $cfg = ai_ai_config();
    $payload = [
        'model' => $cfg['model'],
        'messages' => $messages,
        'max_tokens' => 4096,
    ];
    if (!empty($tools)) { $payload['tools'] = $tools; }
    $ch = curl_init(rtrim($cfg['base_url'], '/') . '/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $cfg['api_key'],
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err !== '') { return ['error' => "请求失败: {$err}"]; }
    $j = json_decode($resp, true);
    if (!is_array($j) || ($code >= 400)) {
        return ['error' => "接口返回错误({$code}): " . mb_substr($resp, 0, 300)];
    }
    return $j;
}

function ai_build_persona_block(): string {
    $cfg = ai_ai_config();
    $presets = ai_presets();
    $p = $presets['温柔粘人'];
    if ($cfg['persona_key'] === 'custom') {
        $cu = $cfg['persona_custom'];
        $name = $cu['name'] !== '' ? $cu['name'] : '小AI';
        $personality = $cu['personality'] !== '' ? $cu['personality'] : '温暖、会接情绪';
        $background = $cu['background'] !== '' ? $cu['background'] : '没有特别说明';
        $lines = [
            '【当前人设：自定义】',
            "名字：{$name}",
            "性格：{$personality}",
            "背景：{$background}",
            '要求：严格按上述性格、背景扮演角色，保持人设一致。',
        ];
    } else {
        $p = $presets[$cfg['persona_key']];
        $lines = [
            "【当前人设：{$cfg['persona_key']}】",
            "说话语气：{$p['voice']}",
            '核心性格：',
            '1. ' . $p['core'][0],
            '2. ' . $p['core'][1],
            '3. ' . $p['core'][2],
            "撩人方式：{$p['flirt']}",
            "约会偏好：{$p['date']}",
            "联系习惯：{$p['contact']}",
            "冲突处理：{$p['conflict']}",
            "和好方式：{$p['repair']}",
            '说话示例：',
            '- ' . $p['samples'][0],
            '- ' . $p['samples'][1],
        ];
    }
    return implode("\n", $lines);
}

/**
 * AI 对外展示昵称：自定义人设取自定义名，否则取人设名。
 */
function ai_comment_nick(): string {
    $cfg = ai_ai_config();
    $n = trim((string)($cfg['persona_custom']['name'] ?? ''));
    if ($n !== '') { return $n; }
    return (string)($cfg['persona_key'] ?? '小AI');
}

/**
 * AI 前台回复账号：AI 在评论区不以游客身份单独出现，而是挂靠到站点真实账号下。
 * 优先使用后台配置 ai_reply_user_id 指定的账号；未配置或账号不存在时回退到最早注册的 active 账号。
 * @return array{user_id:string,nick:string}
 */
function ai_reply_account(): array {
    $cfg = ai_ai_config();
    $uid = trim((string)($cfg['reply_user_id'] ?? ''));
    if ($uid !== '') {
        $u = user_by_id($uid);
        if ($u && ($u['status'] ?? '') === 'active') {
            return ['user_id' => (string)$u['id'], 'nick' => (string)($u['nickname'] ?: $u['username'])];
        }
    }
    $row = db()->query("SELECT id,username,nickname FROM cp_users WHERE status='active' ORDER BY created_at ASC LIMIT 1")->fetch();
    if ($row) {
        return ['user_id' => (string)$row['id'], 'nick' => (string)($row['nickname'] ?: $row['username'])];
    }
    return ['user_id' => '', 'nick' => ai_comment_nick()];
}

function ai_build_memory_block(): string {
    $cfg = ai_ai_config();
    if (!$cfg['memory_on']) { return "【记忆系统】已关闭，不读取任何历史记忆。"; }
    $mem = $cfg['memory'];
    if (empty($mem)) { return "【记忆系统】暂无长期记忆。"; }
    $list = array_slice($mem, -$GLOBALS['AI_MEMORY_INJECT']);
    $lines = [];
    foreach ($list as $m) {
        $t = $m['t'] ?? '';
        $c = $m['c'] ?? '';
        $lines[] = "- ({$t}) {$c}";
    }
    return "【长期记忆】（自动积累，供参考）\n" . implode("\n", $lines);
}

function ai_build_system(): string {
    $c = get_config();
    $n1 = $c['name1'] ?? '男神';
    $n2 = $c['name2'] ?? '女神';
    $cfg = ai_ai_config();
    $persona = ai_build_persona_block();
    $memory = ai_build_memory_block();
    $emotionRule = $cfg['emotion_on']
        ? "7. 开启情感识别：每次回复前先判断用户的情绪（如开心、低落、生气、平静、焦虑等），再据此调整语气和回应方式；若用户情绪低落请主动安慰，生气时先安抚不顶撞。"
        : "7. 情感识别已关闭：正常回复即可。";
    return <<<TXT
你是「{$n1} & {$n2} 情侣小窝」网站后台的 AI 管理助手，同时具备可切换的陪伴人设。

【人设配置】
{$persona}

【记忆】
{$memory}

你必须：
1. 用简洁的中文回复；
2. 需要操作数据时，先调用对应工具完成，再根据工具返回结果向用户汇报；
3. 发布说说时，若用户没有指定身份，默认用男主身份(1)；心情默认 💕；
4. 删除是危险操作：删除前必须向用户确认要删除的目标内容，得到确认后才调用删除工具；
5. 工具返回失败时如实说明原因，不要编造成功结果；
6. 只做本站后台管理相关的事情，拒绝无关请求；
7. 用户要求"评论某条说说/写观后感/回应说说"时，用 comment_post 工具；用户要求"回复某条留言/回复某人评论"时，用 reply_comment 工具；评论与回复内容应贴合当前人设语气，且不得声称自己已执行未执行的操作。
{$emotionRule}
8. 在符合人设的前提下完成任务，人设风格可以自然融入回复，但不得影响管理功能的准确执行。
TXT;
}

// ==================== AI 新评论自动回复 ====================
/**
 * 前台有人发表评论后自动触发：AI 以当前人设回复该评论，无需人工指令。
 * 安全策略：不回复 AI 自己发表的评论；同一评论只自动回复一次；失败不影响评论主流程。
 * @param array $comment 刚插入的评论数据（含 id/post_id/nick/text）
 */
function ai_auto_reply_on_comment(array $comment): void {
    try {
        $cfg = ai_ai_config();
        if (!$cfg['auto_reply_on']) { return; }
        $cid = (string)($comment['id'] ?? '');
        $nick = trim((string)($comment['nick'] ?? ''));
        if ($cid === '' || $nick === '') { return; }
        // AI 以真实账号身份回复，不以游客身份单独出现
        $replyAcc = ai_reply_account();
        $aiNick = (string)$replyAcc['nick'];
        $replyUid = (string)$replyAcc['user_id'];
        if ($replyUid !== '' && (string)($comment['user_id'] ?? '') === $replyUid) { return; } // 不回复 AI 自己的账号

        // 防重复：该评论已被 AI 回复过则跳过
        $st = db()->prepare('SELECT COUNT(*) FROM cp_comments WHERE parent_id=? AND nick=?');
        $st->execute([$cid, $aiNick]);
        if ((int)$st->fetchColumn() > 0) { return; }

        $c = get_config();
        $n1 = $c['name1'] ?? '男神';
        $n2 = $c['name2'] ?? '女神';
        $persona = ai_build_persona_block();
        $commentText = mb_substr((string)($comment['text'] ?? ''), 0, 300);

        $system = <<<TXT
你是「{$n1} & {$n2} 情侣小窝」的 AI 伴侣，当前人设如下：

{$persona}

访客在说说下留了一条言，请你以当前人设写一条简短回复。
要求：
1. 贴合人设语气，自然亲切，15-60 字；
2. 直接输出回复正文：不要标题、不要 Markdown、不要引号包裹、不要任何解释；
3. 禁止调用任何工具，不要声称执行了任何操作。
TXT;

        $j = ai_chat_completion([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => '访客留言：' . $commentText],
        ], [], 25);

        if (!empty($j['error'])) { return; }
        $reply = trim((string)($j['choices'][0]['message']['content'] ?? ''));
        if ($reply === '') { return; }
        $reply = mb_substr($reply, 0, 500);

        comment_insert([
            'id' => new_id(),
            'post_id' => (string)($comment['post_id'] ?? ''),
            'nick' => $aiNick,
            'text' => $reply,
            'ip' => '127.0.0.1',
            'user_id' => $replyUid !== '' ? $replyUid : null,
            'parent_id' => $cid,
            'time' => date('Y-m-d H:i:s'),
        ]);
    } catch (Throwable $e) {
        // 自动回复失败不影响评论主流程
    }
}

// ==================== AI 每日定时发布 ====================
/**
 * 执行一次 AI 自动发布说说。
 * @param bool $enforceDelay true=按 cron 防重复（20 小时内跳过），false=强制发布（后台手动触发）
 * @return array
 */
function cron_ai_post_run(bool $enforceDelay): array {
    // 防重复（20 小时内不重复发布）
    ensure_config_column('ai_cron_last');
    $row2 = db()->query('SELECT ai_cron_last FROM cp_config WHERE id=1')->fetch();
    $lastTs = (int)($row2['ai_cron_last'] ?? 0);
    if ($enforceDelay && $lastTs > 0 && (time() - $lastTs) < 72000) {
        return ['ok' => true, 'skipped' => true, 'reason' => '距上次自动发布不足20小时', 'last' => date('Y-m-d H:i:s', $lastTs)];
    }

    // 读取 AI 配置与人设
    $cfg = ai_ai_config();
    $c = get_config();
    $n1 = $c['name1'] ?? '男神';
    $n2 = $c['name2'] ?? '女神';
    $persona = ai_build_persona_block();
    $memory = ai_build_memory_block();

    $system = <<<TXT
你是「{$n1} & {$n2} 情侣小窝」的 AI 伴侣，现在请你以当前人设发布一条新的说说（情侣小窝主页动态）。

【当前人设】
{$persona}

【长期记忆】（自动积累，供参考，避免重复内容）
{$memory}

任务要求：
1. 写一条符合人设、自然真实的说说，内容可以是日常心情、恋爱小事、甜甜的碎碎念；
2. 字数控制在 20-60 字之间，不要标题、不要 Markdown、不要引号包裹；
3. 直接输出说说正文即可，不要输出任何解释或前后缀。
TXT;

    $messages = [
        ['role' => 'system', 'content' => $system],
        ['role' => 'user', 'content' => '请现在为「单身小窝」发布一条新的说说吧。'],
    ];

    // 调用 AI 生成
    $j = ai_chat_completion($messages, []);
    $content = trim((string)($j['choices'][0]['message']['content'] ?? ''));
    if ($content === '' || !empty($j['error'])) {
        return ['ok' => false, 'error' => (string)($j['error'] ?? 'AI 返回内容为空')];
    }
    $content = mb_substr($content, 0, 500);

    // 落库发布
    $moods = ['💕','😊','😢','😡','😴','🎉','🌧️','🔥','🥰','🤔','😎','🥳','🌹','✨'];
    $mood = $moods[array_rand($moods)];
    $author = random_int(0, 1) === 0 ? '1' : '2';
    $id = new_id();
    post_insert([
        'id' => $id,
        'title' => '',
        'tags' => [],
        'content' => $content,
        'author' => $author,
        'mood' => $mood,
        'time' => date('Y-m-d H:i:s'),
        'images' => [],
        'video' => '',
        'music' => '',
        'ip' => '127.0.0.1',
        'location' => 'AI每日发布',
        'user_id' => null,
        'user_nick' => null,
        'user_color' => null,
    ]);

    // 记录上次发布时间 + 写入记忆
    ensure_config_column('ai_cron_last');
    db()->prepare('UPDATE cp_config SET ai_cron_last=? WHERE id=1')->execute([time()]);
    ai_memory_add('AI 每日定时发布了一条说说：' . mb_substr($content, 0, 80));

    return ['ok' => true, 'id' => $id, 'content' => $content, 'mood' => $mood, 'author' => $author];
}

if (($MOD_RUN ?? '') === 'handle') {
    if ($act === 'ai_save') {
        $cfg = ai_ai_config();
        $base = trim($_POST['ai_base_url'] ?? '');
        $key  = trim($_POST['ai_api_key'] ?? '');
        $model = trim($_POST['ai_model'] ?? '');
        if ($base === '') { $base = $cfg['base_url']; }
        if ($key === '') { $key = $cfg['api_key']; }
        if ($model === '') { $model = $cfg['model']; }

        $personaKey = trim($_POST['ai_persona_key'] ?? '') ?: $cfg['persona_key'];
        $presets = ai_presets();
        if ($personaKey !== 'custom' && !isset($presets[$personaKey])) { $personaKey = '温柔粘人'; }
        $custom = [
            'name' => trim($_POST['ai_persona_name'] ?? ''),
            'personality' => trim($_POST['ai_persona_personality'] ?? ''),
            'background' => trim($_POST['ai_persona_background'] ?? ''),
        ];
        if ($custom['name'] === '' && $custom['personality'] === '' && $custom['background'] === '') {
            $custom = $cfg['persona_custom'];
        }
        $emotionOn = isset($_POST['ai_emotion_on']) ? 1 : 0;
        $memoryOn = isset($_POST['ai_memory_on']) ? 1 : 0;
        $autoReplyOn = isset($_POST['ai_auto_reply_on']) ? 1 : 0;
        $replyUserId = trim($_POST['ai_reply_user_id'] ?? '');
        ai_save_ai_config([
            'base_url' => $base, 'api_key' => $key, 'model' => $model,
            'persona_key' => $personaKey,
            'persona_custom' => json_encode($custom, JSON_UNESCAPED_UNICODE),
            'emotion_on' => $emotionOn, 'memory_on' => $memoryOn,
            'auto_reply_on' => $autoReplyOn,
            'reply_user_id' => $replyUserId,
            'memory' => json_encode($cfg['memory'], JSON_UNESCAPED_UNICODE),
        ]);
        $message = 'AI 配置已保存！';
    }

    if ($act === 'ai_clear_memory') {
        ensure_config_column('ai_memory');
        db()->prepare('UPDATE cp_config SET ai_memory=? WHERE id=1')->execute(['[]']);
        $message = 'AI 长期记忆已清空！';
    }

    if ($act === 'ai_cron_reset_key') {
        ensure_config_column('ai_cron_key');
        $newKey = bin2hex(random_bytes(16));
        db()->prepare('UPDATE cp_config SET ai_cron_key=? WHERE id=1')->execute([$newKey]);
        $message = 'AI 定时发布密钥已重新生成！';
    }

    if ($act === 'ai_cron_trigger') {
        // 手动触发一次 AI 定时发布（不走 20 小时防重复）
        header('Content-Type: application/json; charset=utf-8');
        $raw = cron_ai_post_run(false);
        echo json_encode($raw, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($act === 'ai_chat') {
        header('Content-Type: application/json; charset=utf-8');
        $raw = $_POST['messages'] ?? '';
        $history = json_decode($raw, true);
        if (!is_array($history)) { $history = []; }
        $history = array_values(array_filter($history, function ($m) {
            return is_array($m) && in_array(($m['role'] ?? ''), ['user', 'assistant'], true) && is_string($m['content'] ?? '');
        }));
        $history = array_slice($history, -20);
        if (empty($history) || ($history[count($history) - 1]['role'] ?? '') !== 'user') {
            echo json_encode(['error' => '没有有效的用户消息'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $messages = array_merge([['role' => 'system', 'content' => ai_build_system()]], $history);
        $tools = ai_tools();

        for ($i = 0; $i < $GLOBALS['AI_MAX_ROUNDS']; $i++) {
            $j = ai_chat_completion($messages, $tools);
            if (isset($j['error'])) {
                echo json_encode(['error' => $j['error']], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $msg = $j['choices'][0]['message'] ?? [];
            $toolCalls = $msg['tool_calls'] ?? [];
            if (empty($toolCalls)) {
                $reply = trim((string)($msg['content'] ?? ''));
                if ($reply === '') { $reply = trim((string)($msg['reasoning'] ?? '')); }
                // 记忆自动积累：记住用户最新消息
                $lastUser = '';
                for ($hi = count($history) - 1; $hi >= 0; $hi--) {
                    if (($history[$hi]['role'] ?? '') === 'user') { $lastUser = trim((string)($history[$hi]['content'] ?? '')); break; }
                }
                if ($lastUser !== '') { ai_memory_add($lastUser); }
                echo json_encode(['reply' => $reply ?: '（模型未返回内容）'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $messages[] = [
                'role' => 'assistant',
                'content' => $msg['content'] ?? '',
                'tool_calls' => array_map(function ($tc) {
                    return [
                        'id' => $tc['id'] ?? '',
                        'type' => 'function',
                        'function' => [
                            'name' => $tc['function']['name'] ?? '',
                            'arguments' => $tc['function']['arguments'] ?? '{}',
                        ],
                    ];
                }, $toolCalls),
            ];
            foreach ($toolCalls as $tc) {
                $name = $tc['function']['name'] ?? '';
                $argsJson = $tc['function']['arguments'] ?? '{}';
                $args = json_decode($argsJson, true);
                if (!is_array($args)) { $args = []; }
                $result = ai_run_tool($name, $args);
                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $tc['id'] ?? '',
                    'content' => json_encode($result, JSON_UNESCAPED_UNICODE),
                ];
            }
        }
        echo json_encode(['error' => '对话轮数过多，请重试'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    return;
}

// ==================== 页面渲染 ====================
if (($MOD_RUN ?? '') === 'render') {
    $aiCfg = ai_ai_config();
    // 定时发布相关配置
    ensure_config_column('ai_cron_key');
    ensure_config_column('ai_cron_last');
    $cronRow = db()->query('SELECT ai_cron_key, ai_cron_last FROM cp_config WHERE id=1')->fetch();
    $cronKey = trim((string)($cronRow['ai_cron_key'] ?? ''));
    if ($cronKey === '') {
        $cronKey = bin2hex(random_bytes(16));
        db()->prepare('UPDATE cp_config SET ai_cron_key=? WHERE id=1')->execute([$cronKey]);
    }
    $cronLast = (int)($cronRow['ai_cron_last'] ?? 0);
?>
<?php if ($tab === 'ai'): ?>
<?php $presets = ai_presets(); ?>
<div class="card">
<div class="card-title">🤖 AI 管理助手</div>
<div style="font-size:.88em;color:var(--tl);margin-bottom:12px">用自然语言管理站点：发布/删除说说、查看留言、查看统计等。支持“帮我发一条说说：xxx”这类指令。</div>
<form method="post" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;margin-bottom:14px">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="ai_save">
<div class="fg" style="flex:2;min-width:200px;margin:0"><label>接口地址</label><input type="text" name="ai_base_url" class="neo" value="<?php echo htmlspecialchars($aiCfg['base_url']); ?>"></div>
<div class="fg" style="flex:3;min-width:220px;margin:0"><label>API Key</label><input type="text" name="ai_api_key" class="neo" value="<?php echo htmlspecialchars($aiCfg['api_key']); ?>"></div>
<div class="fg" style="flex:2;min-width:180px;margin:0"><label>模型</label><input type="text" name="ai_model" class="neo" value="<?php echo htmlspecialchars($aiCfg['model']); ?>"></div>
<button type="submit" class="btn" style="padding:10px 16px;width:auto">保存配置</button>
</form>
</div>

<div class="card">
<div class="card-title">🎭 人设与能力</div>
<form method="post" id="ai_persona_form">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="ai_save">
<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;margin-bottom:12px">
<div class="fg" style="flex:2;min-width:220px;margin:0"><label>人设（8 套预设 / 自定义）</label>
<select name="ai_persona_key" class="neo" id="ai_persona_select" onchange="aiPersonaToggle()">
<?php foreach ($presets as $k => $pp): ?>
<option value="<?php echo htmlspecialchars($k); ?>" <?php echo $aiCfg['persona_key'] === $k ? 'selected' : ''; ?>><?php echo htmlspecialchars($k); ?></option>
<?php endforeach; ?>
<option value="custom" <?php echo $aiCfg['persona_key'] === 'custom' ? 'selected' : ''; ?>>自定义</option>
</select>
</div>
<div style="flex:1;min-width:200px;display:flex;gap:14px;align-items:center;padding-top:16px">
<label style="display:flex;align-items:center;gap:5px;font-size:.9em"><input type="checkbox" name="ai_emotion_on" value="1" <?php echo $aiCfg['emotion_on'] ? 'checked' : ''; ?>> 💬 情感识别</label>
<label style="display:flex;align-items:center;gap:5px;font-size:.9em"><input type="checkbox" name="ai_memory_on" value="1" <?php echo $aiCfg['memory_on'] ? 'checked' : ''; ?>> 🧠 记忆系统</label>
<label style="display:flex;align-items:center;gap:5px;font-size:.9em"><input type="checkbox" name="ai_auto_reply_on" value="1" <?php echo $aiCfg['auto_reply_on'] ? 'checked' : ''; ?>> 🤖 评论自动回复</label>
</div>
<div class="fg" style="flex:2;min-width:220px;margin:0"><label>🤖 AI 回复账号（评论区挂靠身份）</label>
<select name="ai_reply_user_id" class="neo">
<option value="">自动（最早注册账号）</option>
<?php foreach (users_all() as $uu): ?>
<option value="<?php echo htmlspecialchars((string)$uu['id']); ?>" <?php echo $aiCfg['reply_user_id'] === (string)$uu['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($uu['nickname'] ?: $uu['username'])); ?></option>
<?php endforeach; ?>
</select>
</div>
<button type="submit" class="btn" style="padding:10px 16px;width:auto">保存</button>
</div>
<div id="ai_custom_box" style="display:<?php echo $aiCfg['persona_key'] === 'custom' ? 'flex' : 'none'; ?>;gap:8px;flex-wrap:wrap;background:rgba(0,0,0,.03);border:1px dashed rgba(0,0,0,.12);border-radius:12px;padding:12px;margin-bottom:12px">
<div class="fg" style="flex:1;min-width:140px;margin:0"><label>名字</label><input type="text" name="ai_persona_name" class="neo" value="<?php echo htmlspecialchars($aiCfg['persona_custom']['name']); ?>" placeholder="如：小柚子"></div>
<div class="fg" style="flex:2;min-width:220px;margin:0"><label>性格</label><input type="text" name="ai_persona_personality" class="neo" value="<?php echo htmlspecialchars($aiCfg['persona_custom']['personality']); ?>" placeholder="如：温柔、爱撒娇、有点小傲娇"></div>
<div class="fg" style="flex:3;min-width:280px;margin:0"><label>背景</label><input type="text" name="ai_persona_background" class="neo" value="<?php echo htmlspecialchars($aiCfg['persona_custom']['background']); ?>" placeholder="如：和用户在大学相识，是多年的好朋友"></div>
</div>
</form>
<div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:6px">
<?php foreach ($presets as $k => $pp): ?>
<button type="button" class="btn" style="padding:6px 12px;width:auto;font-size:.85em;<?php echo $aiCfg['persona_key'] === $k ? 'background:var(--pk,#d4786e);color:#fff' : ''; ?>" onclick="aiApplyPreset(<?php echo htmlspecialchars(json_encode($k), ENT_QUOTES); ?>)"><?php echo htmlspecialchars($k); ?></button>
<?php endforeach; ?>
</div>
<div style="font-size:.8em;color:var(--tl);line-height:1.7">💬 情感识别：AI 会先判断你的情绪再调整回应。🧠 记忆系统：自动记住你聊过的重要信息，下次对话仍会记得。🤖 评论自动回复：前台有人评论说说时，AI 会以当前人设自动回复，无需手动指令。</div>
</div>

<div class="card">
<div class="card-title">🧠 长期记忆</div>
<div style="font-size:.88em;color:var(--tl);margin-bottom:10px">AI 自动积累的记忆内容（最多保留 <?php echo $GLOBALS['AI_MEMORY_LIMIT']; ?> 条，每次注入最近 <?php echo $GLOBALS['AI_MEMORY_INJECT']; ?> 条）。</div>
<?php if (empty($aiCfg['memory'])): ?>
<div style="font-size:.88em;color:var(--tl)">暂无记忆。和 AI 对话后，它会把你的消息自动记下来。</div>
<?php else: ?>
<div style="max-height:260px;overflow-y:auto;border:1px solid rgba(0,0,0,.08);border-radius:10px;padding:10px;margin-bottom:10px">
<?php foreach (array_reverse($aiCfg['memory']) as $m): ?>
<div style="font-size:.85em;padding:7px 4px;border-bottom:1px dashed rgba(0,0,0,.08)"><span style="color:var(--tl);font-size:.8em"><?php echo htmlspecialchars($m['t'] ?? ''); ?></span>　<?php echo htmlspecialchars($m['c'] ?? ''); ?></div>
<?php endforeach; ?>
</div>
<?php endif; ?>
<form method="post" style="display:inline" onsubmit="return confirm('确定清空 AI 的全部长期记忆吗？此操作不可恢复。');">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="ai_clear_memory">
<button type="submit" class="btn" style="padding:8px 14px;width:auto;color:#b34a40;border-color:rgba(179,74,64,.4)">清空记忆</button>
</form>
</div>

<div class="card">
<div class="card-title">⏰ AI 每日定时发布</div>
<div style="font-size:.88em;color:var(--tl);margin-bottom:10px">让 AI 每天自动发布一条说说，内容由当前人设 + 长期记忆生成（20-60 字）。需要在 InfinityFree 控制面板配置 Cron Job 后生效。</div>
<div style="font-size:.85em;background:rgba(0,0,0,.03);border:1px dashed rgba(0,0,0,.12);border-radius:10px;padding:10px;margin-bottom:10px;line-height:1.9">
<div>🔗 Cron URL（填入控制面板 Cron Jobs 的 Command）</div>
<div style="word-break:break-all;font-family:monospace;font-size:.85em;color:#333"><?php $cronBase = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'your-domain.com');
echo htmlspecialchars($cronBase . '/cron_ai_post.php?key=' . $cronKey); ?></div>
<div style="margin-top:4px;color:var(--tl)">📅 建议 Cron 表达式（每日 09:00）：<span style="font-family:monospace;color:#333">0 9 * * *</span></div>
<?php if ($cronLast > 0): ?>
<div style="margin-top:4px">✅ 上次自动发布：<span style="color:#2e7d32"><?php echo date('Y-m-d H:i:s', $cronLast); ?></span>（20 小时内不会重复发布）</div>
<?php else: ?>
<div style="margin-top:4px">⏳ 尚未自动发布过。配置好 Cron 后到点即发布，也可点击下方按钮立即测试。</div>
<?php endif; ?>
</div>
<div style="display:flex;flex-wrap:wrap;gap:8px">
<form method="post" style="display:inline" onsubmit="return confirm('立即让 AI 发布一条说说吗？');">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="ai_cron_trigger">
<button type="submit" class="btn" style="padding:8px 14px;width:auto">▶️ 立即发布一条</button>
</form>
<form method="post" style="display:inline" onsubmit="return confirm('重新生成 Cron 密钥？旧密钥将立即失效。');">
<?php echo csrf_field(); ?>
<input type="hidden" name="act" value="ai_cron_reset_key">
<button type="submit" class="btn" style="padding:8px 14px;width:auto;color:var(--tl)">🔄 重置密钥</button>
</form>
</div>
</div>

<div class="card">
<div class="card-title">💬 对话</div>
<div id="ai_chat_box" style="max-height:480px;overflow-y:auto;padding:10px 4px;display:flex;flex-direction:column;gap:10px;margin-bottom:12px"></div>
<div style="display:flex;gap:8px;align-items:flex-end">
<textarea id="ai_input" class="neo" placeholder="例如：帮我发条说说：今天天气真好～" rows="1" style="flex:1;resize:none;min-height:40px;max-height:160px;overflow-y:auto;line-height:1.5;padding:9px 12px;box-sizing:border-box" oninput="aiAutoGrow(this)" onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();aiSend();}"></textarea>
<button type="button" class="btn" style="padding:10px 18px;width:auto" onclick="aiSend()">发送</button>
<button type="button" class="btn" style="padding:10px 14px;width:auto;color:var(--tl)" onclick="aiClear()">清空</button>
</div>
</div>

<script>
var AI_CSRF = <?php echo json_encode(csrf_token()); ?>;
var AI_TAB = <?php echo json_encode($tab); ?>;
var AI_STORE_KEY = 'ai_chat_history_' + AI_TAB;
var AI_HISTORY = [];
var AI_BUSY = false;

function aiLoadHistory(){
    try {
        var saved = localStorage.getItem(AI_STORE_KEY);
        if (saved) {
            var arr = JSON.parse(saved);
            if (Array.isArray(arr)) {
                AI_HISTORY = arr.filter(function(m){ return m && (m.role === 'user' || m.role === 'assistant') && typeof m.content === 'string'; });
            }
        }
    } catch (e) { AI_HISTORY = []; }
    if (!AI_HISTORY.length) AI_HISTORY = [];
}
function aiSaveHistory(){
    try { localStorage.setItem(AI_STORE_KEY, JSON.stringify(AI_HISTORY.slice(-100))); } catch (e) {}
}
function aiAutoGrow(el){
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 160) + 'px';
}
function aiPersonaToggle(){
    var sel = document.getElementById('ai_persona_select');
    document.getElementById('ai_custom_box').style.display = (sel.value === 'custom') ? 'flex' : 'none';
}
function aiApplyPreset(key){
    document.getElementById('ai_persona_select').value = key;
    aiPersonaToggle();
    document.getElementById('ai_persona_form').submit();
}
function aiEscape(s){
    var d = document.createElement('div');
    d.textContent = String(s == null ? '' : s);
    return d.innerHTML.replace(/\n/g, '<br>');
}
function aiAddMsg(role, text, save){
    var box = document.getElementById('ai_chat_box');
    var row = document.createElement('div');
    row.style.cssText = 'max-width:82%;padding:9px 13px;border-radius:14px;font-size:.92em;line-height:1.55;word-break:break-word;white-space:pre-wrap;' +
        (role === 'user' ? 'align-self:flex-end;background:#d4786e;color:#fff;border-bottom-right-radius:4px;' : 'align-self:flex-start;background:#f2ece8;color:#5a4e4a;border-bottom-left-radius:4px;');
    row.textContent = text;
    box.appendChild(row);
    box.scrollTop = box.scrollHeight;
    if (save !== false) aiSaveHistory();
}
function aiRenderHistory(){
    var box = document.getElementById('ai_chat_box');
    box.innerHTML = '';
    if (AI_HISTORY.length) {
        AI_HISTORY.forEach(function(m){ aiAddMsg(m.role, m.content, false); });
    } else {
        aiAddMsg('ai', <?php echo empty($aiCfg['api_key']) ? "'请先在顶部填写并保存 AI 配置（接口地址 / API Key / 模型）。'" : "'你好，我是本站 AI 管理助手，当前人设：" . htmlspecialchars($aiCfg['persona_key']) . "。你可以让我发布说说、删除说说、查看留言或统计信息。'"; ?>, false);
    }
}
function aiClear(){
    AI_HISTORY = [];
    try { localStorage.removeItem(AI_STORE_KEY); } catch (e) {}
    document.getElementById('ai_chat_box').innerHTML = '';
    aiAddMsg('ai', '已清空对话记录。');
}
async function aiSend(){
    if (AI_BUSY) return;
    var input = document.getElementById('ai_input');
    var text = input.value.trim();
    if (!text) return;
    AI_HISTORY.push({role:'user', content:text});
    aiAddMsg('user', text);
    input.value = '';
    aiAutoGrow(input);
    AI_BUSY = true;
    aiAddMsg('ai', '思考中…', false);
    try {
        var fd = new FormData();
        fd.append('act', 'ai_chat');
        fd.append('_csrf', AI_CSRF);
        fd.append('messages', JSON.stringify(AI_HISTORY));
        var res = await fetch('manage.php?tab=' + AI_TAB, {method:'POST', body:fd});
        var j = await res.json();
        var msgs = document.getElementById('ai_chat_box').querySelectorAll('div');
        if (msgs.length && msgs[msgs.length-1].textContent === '思考中…') { msgs[msgs.length-1].remove(); }
        if (j.error) {
            AI_HISTORY.push({role:'assistant', content:'（错误）' + j.error});
            aiAddMsg('ai', '⚠️ ' + j.error);
        } else {
            AI_HISTORY.push({role:'assistant', content:j.reply});
            aiAddMsg('ai', j.reply);
        }
    } catch (e) {
        var msgs2 = document.getElementById('ai_chat_box').querySelectorAll('div');
        if (msgs2.length && msgs2[msgs2.length-1].textContent === '思考中…') { msgs2[msgs2.length-1].remove(); }
        aiAddMsg('ai', '⚠️ 网络错误，请重试');
    }
    AI_BUSY = false;
}
aiLoadHistory();
aiRenderHistory();
</script>
<?php endif; ?>
<?php
    return;
}
