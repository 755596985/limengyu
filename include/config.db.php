<?php
/**
 * 数据库配置—优先使用环境变量，勿将真实密码提交到公开仓库
 * ⚠️ 安全警告：下方硬编码密码仅作本地开发回退使用。
 *    生产环境请务必通过环境变量 CP_DB_HOST / CP_DB_PORT / CP_DB_NAME / CP_DB_USER / CP_DB_PASS 设置，
 *    或删除下方回退值以提高安全性。
 */
return [
    'host'    => getenv('CP_DB_HOST') ?: 'localhost',
    'port'    => (int)(getenv('CP_DB_PORT') ?: 3306),
    'dbname'  => getenv('CP_DB_NAME') ?: 'your_database',
    'user'    => getenv('CP_DB_USER') ?: 'your_user',
    'pass'    => getenv('CP_DB_PASS') ?: 'your_password',
    'charset' => 'utf8mb4',
];
