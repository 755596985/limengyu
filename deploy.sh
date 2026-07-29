#!/bin/bash
# =============================================
# 情侣小窝 - 一键部署脚本
# 适用于 Linux VPS (CentOS / Ubuntu / Debian)
# 使用: bash deploy.sh
# =============================================

set -e

# 颜色
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'
info()  { echo -e "${GREEN}[INFO]${NC} $1"; }
warn()  { echo -e "${YELLOW}[WARN]${NC} $1"; }
error() { echo -e "${RED}[ERROR]${NC} $1"; }

REPO="https://github.com/755596985/limengyu.git"
INSTALL_DIR=""
DB_HOST="localhost"
DB_PORT=3306
DB_NAME=""
DB_USER=""
DB_PASS=""

# ---------- 环境检查 ----------
check_env() {
    info "===== 环境检查 ====="

    # PHP
    if ! command -v php &>/dev/null; then
        error "未检测到 PHP，请先安装 PHP 7.4+ (mbstring/json/fileinfo/gd)"
        exit 1
    fi
    PHP_VER=$(php -r 'echo PHP_VERSION;')
    info "PHP 版本: $PHP_VER"

    # MySQL
    if ! command -v mysql &>/dev/null; then
        error "未检测到 MySQL Client，请先安装 MySQL/MariaDB"
        exit 1
    fi
    info "MySQL 已安装"

    # 扩展检查
    for ext in mbstring json fileinfo gd; do
        php -m | grep -qi "$ext" && info "  ✓ $ext 扩展" || warn "  ✗ $ext 扩展缺失"
    done

    # Git
    if command -v git &>/dev/null; then
        info "Git 已安装"
    else
        warn "Git 未安装，将使用 curl 下载 ZIP"
    fi

    # unzip
    if ! command -v unzip &>/dev/null; then
        error "请先安装 unzip"
        exit 1
    fi
}

# ---------- 获取源码 ----------
get_source() {
    info "===== 获取源码 ====="
    read -p "部署目录 (默认: /var/www/html/couple): " INSTALL_DIR
    INSTALL_DIR=${INSTALL_DIR:-/var/www/html/couple}

    if [ -d "$INSTALL_DIR" ]; then
        warn "目录 $INSTALL_DIR 已存在"
        read -p "覆盖 [y/N]: " OVERWRITE
        [[ "$OVERWRITE" != "y" && "$OVERWRITE" != "Y" ]] && exit 0
    fi

    mkdir -p "$INSTALL_DIR"

    if command -v git &>/dev/null; then
        info "正在通过 Git 克隆..."
        git clone --depth=1 "$REPO" "$INSTALL_DIR" 2>/dev/null || {
            cd "$INSTALL_DIR" && git pull
        }
    else
        info "正在下载 ZIP..."
        curl -sL "https://github.com/755596985/limengyu/archive/refs/heads/main.zip" -o /tmp/couple.zip
        unzip -qo /tmp/couple.zip -d /tmp/couple_tmp
        cp -r /tmp/couple_tmp/limengyu-main/* "$INSTALL_DIR/"
        rm -rf /tmp/couple.zip /tmp/couple_tmp
    fi

    # 创建必要目录
    mkdir -p "$INSTALL_DIR/data" "$INSTALL_DIR/uploads"
    info "源码已部署到: $INSTALL_DIR"
}

# ---------- 数据库配置 ----------
setup_db() {
    info "===== 数据库配置 ====="
    read -p "数据库主机 (默认: localhost): " input_host
    DB_HOST=${input_host:-$DB_HOST}
    read -p "数据库端口 (默认: 3306): " input_port
    DB_PORT=${input_port:-$DB_PORT}
    read -p "数据库名: " DB_NAME
    read -p "数据库用户: " DB_USER
    read -s -p "数据库密码: " DB_PASS
    echo ""

    # 测试连接
    if mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT 1" &>/dev/null; then
        info "数据库连接成功"
    else
        warn "连接失败，尝试创建数据库..."
        mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || {
            error "数据库创建失败，请手动创建后重试"
            exit 1
        }
        info "数据库已创建"
    fi

    # 导入结构
    info "正在导入数据库结构..."
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$INSTALL_DIR/schema.sql" 2>/dev/null && \
        info "数据库结构导入成功" || \
        warn "导入可能已完成（表已存在时忽略）"

    # 写配置文件
    cat > "$INSTALL_DIR/include/config.db.php" << PHPEOF
<?php
/**
 * 数据库配置
 * ⚠️ 安全警告：生产环境建议通过环境变量设置。
 */
return [
    'host'    => getenv('CP_DB_HOST') ?: '$DB_HOST',
    'port'    => (int)(getenv('CP_DB_PORT') ?: $DB_PORT),
    'dbname'  => getenv('CP_DB_NAME') ?: '$DB_NAME',
    'user'    => getenv('CP_DB_USER') ?: '$DB_USER',
    'pass'    => getenv('CP_DB_PASS') ?: '$DB_PASS',
    'charset' => 'utf8mb4',
];
PHPEOF
    info "数据库配置已写入: include/config.db.php"
}

# ---------- 创建管理员 ----------
create_admin() {
    info "===== 创建管理员账号 ====="
    read -p "管理员用户名 (默认: admin): " ADMIN_USER
    ADMIN_USER=${ADMIN_USER:-admin}
    read -s -p "管理员密码: " ADMIN_PASS
    echo ""
    read -s -p "确认密码: " ADMIN_PASS2
    echo ""

    if [ "$ADMIN_PASS" != "$ADMIN_PASS2" ]; then
        error "两次密码不一致"
        exit 1
    fi

    HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_DEFAULT);")
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" \
        -e "DELETE FROM cp_admin; INSERT INTO cp_admin (username, password) VALUES ('$ADMIN_USER', '$HASH');"
    info "管理员账号已创建"
}

# ---------- 设置权限 ----------
set_perms() {
    info "===== 设置权限 ====="
    chmod -R 755 "$INSTALL_DIR"
    chmod -R 777 "$INSTALL_DIR/data" "$INSTALL_DIR/uploads"
    # 可选：指定 www-data 用户
    if id www-data &>/dev/null; then
        chown -R www-data:www-data "$INSTALL_DIR"
        info "所有权已设置为 www-data"
    elif id nginx &>/dev/null; then
        chown -R nginx:nginx "$INSTALL_DIR"
        info "所有权已设置为 nginx"
    fi
}

# ---------- Nginx 配置 ----------
nginx_conf() {
    info "===== Nginx 配置（可选）====="
    read -p "请输入域名 (留空跳过 Nginx 配置): " DOMAIN
    [[ -z "$DOMAIN" ]] && echo "跳过" && return

    if [ ! -f /etc/nginx/sites-available/$DOMAIN ]; then
        cat > /etc/nginx/sites-available/$DOMAIN << NGINX
server {
    listen 80;
    server_name $DOMAIN;
    root $INSTALL_DIR;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~ /(data|uploads)/ {
        location ~ \.php\$ { deny all; }
    }
}
NGINX
        ln -sf /etc/nginx/sites-available/$DOMAIN /etc/nginx/sites-enabled/
        nginx -t && systemctl reload nginx
        info "Nginx 配置完成: http://$DOMAIN"
    else
        warn "配置已存在: /etc/nginx/sites-available/$DOMAIN"
    fi
}

# ---------- 完成 ----------
finish() {
    echo ""
    info "========================================"
    info "  ✅ 情侣小窝 部署完成！"
    info "  访问地址: http://$(curl -s ifconfig.me 2>/dev/null || echo '你的服务器IP')"
    info "  后台地址: /admin/"
    info "  安装向导: /install.php"
    info "========================================"
    echo ""
    warn "⚠️  安全提醒："
    echo "  1. 部署完成后请删除 install.php"
    echo "  2. 确保 data/ 和 uploads/ 目录可写"
    echo "  3. 建议配置 HTTPS"
    echo "  4. 数据库密码可改由环境变量注入"
    echo ""
}

# ---------- 主流程 ----------
main() {
    echo ""
    echo "================================"
    echo "   情侣小窝 - 一键部署脚本"
    echo "================================"
    echo ""

    check_env
    get_source
    setup_db
    create_admin
    set_perms
    nginx_conf
    finish
}

main "$@"
