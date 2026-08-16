<?php
/**
 * 模块清单（manifest）——后台模块化配置中心
 *
 * 修改方式：
 *   1. 调整顺序 = 调整后台底部导航顺序（数组从上到下）
 *   2. enabled=false = 停用该模块（导航隐藏、路由不再加载）
 *   3. 新增模块：新建 modules/xxx.php（参照现有模块双模式结构）后在数组中注册
 */
return [
    ['key' => 'ai', 'file' => 'ai.php', 'label' => 'AI', 'icon' => '🤖', 'acts' => ["ai_save", "ai_chat", "ai_clear_memory"], 'enabled' => true],
    ['key' => 'posts', 'file' => 'posts.php', 'label' => '说说', 'icon' => '💬', 'acts' => ["save_post", "delete_post"], 'enabled' => true],
    ['key' => 'album', 'file' => 'album.php', 'label' => '相册', 'icon' => '📷', 'acts' => ["save_photo", "delete_photo"], 'enabled' => true],
    ['key' => 'places', 'file' => 'places.php', 'label' => '足迹', 'icon' => '📍', 'acts' => ["save_place", "delete_place"], 'enabled' => true],
    ['key' => 'todos', 'file' => 'todos.php', 'label' => '清单', 'icon' => '✅', 'acts' => ["save_todo", "toggle_todo", "delete_todo"], 'enabled' => true],
    ['key' => 'pages', 'file' => 'pages.php', 'label' => '页面', 'icon' => '📑', 'acts' => ["save_page", "delete_page"], 'enabled' => true],
    ['key' => 'comments', 'file' => 'comments.php', 'label' => '留言', 'icon' => '💬', 'acts' => ["delete_comment", "reply_comment"], 'enabled' => true],
    ['key' => 'users', 'file' => 'users.php', 'label' => '用户', 'icon' => '👥', 'acts' => ["delete_user", "user_edit", "user_refresh_ip", "user_toggle_status"], 'enabled' => true],
    ['key' => 'config', 'file' => 'config.php', 'label' => '设置', 'icon' => '⚙️', 'acts' => ["save_config"], 'enabled' => true],
    ['key' => 'password', 'file' => 'password.php', 'label' => '密码', 'icon' => '🔑', 'acts' => ["change_password"], 'enabled' => true],
    ['key' => 'about', 'file' => 'about.php', 'label' => '关于', 'icon' => '📖', 'acts' => ["save_about"], 'enabled' => true],
    ['key' => 'files', 'file' => 'files.php', 'label' => '文件', 'icon' => '📁', 'acts' => ["upload_file", "delete_file", "delete_dir", "save_file", "mkdir_file"], 'enabled' => true],
    ['key' => 'filter', 'file' => 'filter.php', 'label' => '敏感词', 'icon' => '🚫', 'acts' => ["add_word", "delete_word"], 'enabled' => true],
    ['key' => 'visitors', 'file' => 'visitors.php', 'label' => '访客', 'icon' => '📊', 'acts' => ["clear_visitors"], 'enabled' => true],
    ['key' => 'modules', 'file' => 'modules.php', 'label' => '模块', 'icon' => '🧩', 'acts' => ["save_modules"], 'enabled' => true],
];
