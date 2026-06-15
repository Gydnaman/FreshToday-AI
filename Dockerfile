# =============================================================================
# GreenBite Production Dockerfile (Multi-Stage Build)
# 文档引用: docs/bmad/deployment.md §3 (Laravel Forge + 自管 MySQL 主从)
# 关联任务: REVIEW-REPORT v1.0 §5 P0-1 (Dockerfile 未交付)
# 镜像大小目标: < 250 MB
# 阶段: 4 (deps-builder / frontend-builder / vendor / runtime)
# =============================================================================

# -----------------------------------------------------------------------------
# Stage 1: Composer 依赖安装 (生成 vendor/)
# -----------------------------------------------------------------------------
FROM composer:2.7 AS vendor

WORKDIR /app

# 仅先复制 composer 清单，最大化 Docker 层缓存命中
COPY composer.json composer.lock ./

# --no-dev 排除开发依赖；--no-scripts 跳过 Laravel post-autoload
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader

# -----------------------------------------------------------------------------
# Stage 2: Node 前端构建 (生成 public/build/)
# -----------------------------------------------------------------------------
FROM node:20-alpine AS frontend

WORKDIR /app

# 先复制清单以命中 npm 缓存
COPY package.json package-lock.json ./

RUN npm ci --no-audit --no-fund --prefer-offline

# 复制 vite.config.js 与资源
COPY vite.config.js ./
COPY resources/ ./resources/
COPY public/ ./public/

RUN npm run build

# -----------------------------------------------------------------------------
# Stage 3: PHP-FPM + 扩展 (运行时基础镜像)
# -----------------------------------------------------------------------------
FROM php:8.2-fpm-alpine AS php-base

# 临时安装构建工具，用于编译 PHP 扩展
# docker-php-ext-install/enable 来自官方 PHP 镜像
RUN apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        linux-headers \
        autoconf \
        gcc \
        g++ \
        make \
    && apk add --no-cache \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libzip-dev \
        icu-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        bcmath \
        intl \
        zip \
        gd \
        opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps \
    && rm -rf /tmp/* /var/cache/apk/*

# OPcache + FPM 调优
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-greenbite.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# -----------------------------------------------------------------------------
# Stage 4: 运行时 — 包含 PHP-FPM + Supervisord (fpm + queue + scheduler)
# -----------------------------------------------------------------------------
FROM php-base AS runtime

# 安装运行时系统依赖 + Supervisord + Nginx
RUN apk add --no-cache \
        nginx \
        supervisor \
        curl \
        bash \
        tini \
        tzdata \
        icu-libs \
        libzip \
        libpng \
        libjpeg-turbo \
        freetype \
    && cp /usr/share/zoneinfo/Asia/Hong_Kong /etc/localtime \
    && echo "Asia/Hong_Kong" > /etc/timezone \
    && apk del tzdata

# 复制 vendor (from stage 1)
COPY --from=vendor /app/vendor /app/vendor

# 复制前端构建产物 (from stage 2)
COPY --from=frontend /app/public/build /app/public/build

# 设置工作目录
WORKDIR /app

# 复制应用代码 (.dockerignore 排除 node_modules / tests / .git 等)
COPY . /app

# 关键目录权限
RUN mkdir -p \
        /app/storage/framework/cache/data \
        /app/storage/framework/sessions \
        /app/storage/framework/views \
        /app/storage/logs \
        /app/bootstrap/cache \
        /run/nginx \
    && chown -R www-data:www-data /app \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# 复制 Supervisord 配置 (fpm + queue:work + scheduler)
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/supervisor/queue-worker.conf /etc/supervisor/conf.d/queue-worker.conf
COPY docker/supervisor/scheduler.conf /etc/supervisor/conf.d/scheduler.conf

# 复制 Nginx 配置
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# 复制 entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# 暴露端口
EXPOSE 80

# 健康检查
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://127.0.0.1:80/up || exit 1

# 使用 tini 作为 PID 1，正确处理信号
ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/entrypoint"]

# 默认启动 Supervisord
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
