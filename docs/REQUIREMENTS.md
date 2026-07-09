# 系统依赖要求

> 运行 GreenBite 前需要安装以下软件。

## Windows (PowerShell)

| 软件 | 版本 | 安装方式 |
|------|------|----------|
| PHP | 8.2+ | `winget install --id=PHP.PHP.8.2` |
| Composer | 2.x | PHP 包自带安装脚本 |
| SQLite | 3.x | `winget install --id=SQLite.SQLite` |
| Node.js | 18+ (含 npm) | `winget install OpenJS.NodeJS` |
| Git | 2.x | `winget install Git.Git` |

## macOS / Linux

| 软件 | 版本 | 安装方式 |
|------|------|----------|
| PHP | 8.2+ | `brew install php@8.2` (macOS) / `apt install php8.2` (Ubuntu) |
| Composer | 2.x | `php -r "copy(...);"` 或 `brew install composer` |
| SQLite | 3.x | 系统自带 (macOS) / `apt install sqlite3` |
| Node.js | 18+ | `brew install node` / `apt install nodejs` |
| Git | 2.x | 系统自带 / `apt install git` |

## PHP 扩展

Laravel 12 运行需要以下 PHP 扩展（winget 的 PHP 包已包含）：

| 扩展 | 说明 |
|------|------|
| `bcmath` | 精确小数运算 |
| `ctype` | 字符类型检查 |
| `curl` | HTTP 请求（AI / 支付 API） |
| `fileinfo` | 文件类型检测 |
| `gd` | 图片处理（上传缩略图） |
| `intl` | 国际化（日期/货币格式化） |
| `mbstring` | 多字节字符串 |
| `openssl` | 加密 / HTTPS |
| `pdo_sqlite` | SQLite 数据库（开发） |
| `pdo_mysql` | MySQL 数据库（生产） |
| `tokenizer` | 代码解析 |
| `xml` | XML 处理 |
| `zip` | 压缩包处理 |

## 验证安装

```bash
# 检查 PHP 版本和扩展
php -v
php -m | grep -E "bcmath|ctype|curl|fileinfo|gd|intl|mbstring|openssl|pdo_sqlite|pdo_mysql|xml|zip"

# 检查 Composer
composer --version

# 检查 Node
node -v
npm -v

# 检查 SQLite
sqlite3 --version
```

## 可选（生产环境）

| 软件 | 说明 |
|------|------|
| Docker | 容器化部署 |
| MySQL 8.0 | 替代 SQLite 作为生产数据库 |
| Redis 7.x | 缓存 / 队列 |
