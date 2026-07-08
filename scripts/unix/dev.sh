#!/usr/bin/env bash
# GreenBite 本地原生开发启动器 (Git Bash)
# 用法:
#   bash scripts/dev.sh doctor    # 体检
#   bash scripts/dev.sh install   # composer install + npm install
#   bash scripts/dev.sh setup     # 复制 .env / generate key / migrate --seed
#   bash scripts/dev.sh serve     # 后台启动 php artisan serve + npm run dev
#   bash scripts/dev.sh stop      # 停掉所有后台进程
#   bash scripts/dev.sh all       # install + setup + serve 一把梭
#   bash scripts/dev.sh tinker    # 进 tinker REPL
#   bash scripts/dev.sh test      # 跑 phpunit

set -euo pipefail

# --- 1. 找 PHP / Composer / SQLite 实际路径（不依赖 PATH） ---
PHP_DIR="$LOCALAPPDATA/Microsoft/WinGet/Packages/PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe"
SQLITE_DIR="$LOCALAPPDATA/Microsoft/WinGet/Packages/SQLite.SQLite_Microsoft.Winget.Source_8wekyb3d8bbwe"
TOOLS_DIR="C:/tools"

# Git Bash 下 $LOCALAPPDATA 是 /c/Users/.../AppData/Local 风格
PHP_DIR_UNIX="/c/Users/${USER:-lihantong}/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe"
SQLITE_DIR_UNIX="/c/Users/${USER:-lihantong}/AppData/Local/Microsoft/WinGet/Packages/SQLite.SQLite_Microsoft.Winget.Source_8wekyb3d8bbwe"

# 优先用我们刚装好的 winget 路径
if [ -x "/c/Program Files/Git/usr/bin/bash" ]; then
  : # shell ok
fi

# 直接用绝对路径调用
PHP="C:/Users/${USER:-lihantong}/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe"
SQLITE3="C:/Users/${USER:-lihantong}/AppData/Local/Microsoft/WinGet/Packages/SQLite.SQLite_Microsoft.Winget.Source_8wekyb3d8bbwe/sqlite3.exe"
COMPOSER_PHAR="C:/tools/composer"

# 验证
[ -f "$PHP" ]      || { echo "❌ PHP not found: $PHP"; exit 1; }
[ -f "$SQLITE3" ]  || echo "⚠️  sqlite3.exe not found (optional)"

# 加入到当前 shell PATH
WINGET_BASE="C:/Users/${USER:-lihantong}/AppData/Local/Microsoft/WinGet/Packages"
export PATH="$WINGET_BASE/PHP.PHP.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe:$WINGET_BASE/SQLite.SQLite_Microsoft.Winget.Source_8wekyb3d8bbwe:C:/tools:$PATH"

# 工具别名（让 php / composer / sqlite3 直接可调）
php()    { "$PHP" "$@"; }
composer(){ "$PHP" "$COMPOSER_PHAR" "$@"; }
sqlite3() { "$SQLITE3" "$@"; }
export -f php
export -f composer
export -f sqlite3 2>/dev/null || true

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

# 日志目录
LOG_DIR="$ROOT/storage/logs"
mkdir -p "$LOG_DIR"
PID_DIR="$ROOT/storage/framework/dev-pids"
mkdir -p "$PID_DIR"

cmd=${1:-help}
shift || true

case "$cmd" in
  doctor)
    echo "=== GreenBite 体检 ==="
    echo "[1/4] PHP"
    php -v | head -2
    echo "[2/4] 必需扩展"
    php -m 2>&1 | grep -E '^(bcmath|openssl|mbstring|curl|pdo_sqlite|sqlite3|zip|intl|gd|tokenizer|xml|dom|fileinfo|ctype|filter|hash|json|PDO|session)$' | sort -u
    echo "[3/4] Composer"
    composer --version 2>&1 | head -2
    echo "[4/4] SQLite"
    sqlite3 -version 2>&1 | head -1
    echo "[5/5] .env"
    if [ -f "$ROOT/.env" ]; then
      echo "  ✅ .env exists"
    else
      echo "  ⚠️  .env missing, run: bash scripts/dev.sh setup"
    fi
    ;;

  install)
    echo "=== composer install ==="
    composer install --no-interaction --prefer-dist 2>&1 | tail -30
    echo "=== npm install ==="
    npm install --no-audit --no-fund 2>&1 | tail -10
    ;;

  setup)
    echo "=== 1. 复制 .env.example -> .env ==="
    if [ ! -f "$ROOT/.env" ]; then
      cp .env.example .env
      echo "  ✅ created .env"
    else
      echo "  ⏭  .env exists, skip"
    fi

    echo "=== 2. APP_KEY ==="
    if ! grep -q '^APP_KEY=base64:' .env; then
      KEY=$(php -r "echo 'base64:'.base64_encode(random_bytes(32));")
      sed -i.bak "s|^APP_KEY=.*|APP_KEY=$KEY|" .env && rm -f .env.bak
      echo "  ✅ APP_KEY generated"
    else
      echo "  ⏭  APP_KEY already set"
    fi

    echo "=== 3. SQLite 数据库 ==="
    DB_FILE="$ROOT/database/database.sqlite"
    if [ ! -f "$DB_FILE" ]; then
      mkdir -p "$(dirname "$DB_FILE")"
      : > "$DB_FILE"
      echo "  ✅ created $DB_FILE"
    fi

    echo "=== 4. migrate --seed ==="
    php artisan migrate --seed --force 2>&1 | tail -25
    ;;

  serve)
    echo "=== 启动 php artisan serve (后台) ==="
    pkill -f "artisan serve" 2>/dev/null || true
    # 用绝对路径 PHP (函数别名 nohup 看不到，必须用 .exe)
    nohup "$PHP" artisan serve --host=127.0.0.1 --port=8000 > "$LOG_DIR/serve.log" 2>&1 &
    echo $! > "$PID_DIR/serve.pid"
    echo "  ✅ serve started, pid=$(cat "$PID_DIR/serve.pid"), log=$LOG_DIR/serve.log"

    echo "=== 启动 npm run dev (后台) ==="
    pkill -f "vite" 2>/dev/null || true
    nohup npm run dev > "$LOG_DIR/vite.log" 2>&1 &
    echo $! > "$PID_DIR/vite.pid"
    echo "  ✅ vite started, pid=$(cat "$PID_DIR/vite.pid"), log=$LOG_DIR/vite.log"

    sleep 2
    echo ""
    echo "=== 健康检查 ==="
    curl -fsS -o /dev/null -w "  /up          → HTTP %{http_code}\n" http://127.0.0.1:8000/up || echo "  /up  启动中..."
    curl -fsS -o /dev/null -w "  /             → HTTP %{http_code}\n" http://127.0.0.1:8000/    || echo "  /    启动中..."
    echo ""
    echo "打开 http://127.0.0.1:8000"
    echo "停止: bash scripts/dev.sh stop"
    ;;

  stop)
    for name in serve vite; do
      f="$PID_DIR/$name.pid"
      if [ -f "$f" ]; then
        pid=$(cat "$f")
        if kill -0 "$pid" 2>/dev/null; then
          kill "$pid" 2>/dev/null && echo "  ✅ stopped $name (pid=$pid)"
        else
          echo "  ⏭  $name not running"
        fi
        rm -f "$f"
      else
        echo "  ⏭  $name pidfile missing"
      fi
    done
    pkill -f "artisan serve" 2>/dev/null || true
    pkill -f "vite" 2>/dev/null || true
    ;;

  tinker)
    php artisan tinker
    ;;

  test)
    php artisan test 2>&1 | tail -40
    ;;

  all)
    "$0" install
    "$0" setup
    "$0" serve
    ;;

  help|*)
    echo "用法: bash scripts/dev.sh {doctor|install|setup|serve|stop|tinker|test|all|help}"
    ;;
esac
