$ErrorActionPreference = 'Stop'

$pkgRoot = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages"
$phpDir  = (Get-ChildItem -Path $pkgRoot -Directory -Filter 'PHP.PHP.8.2_*' | Select-Object -First 1).FullName
$iniDev  = Join-Path $phpDir 'php.ini-development'
$ini     = Join-Path $phpDir 'php.ini'
$extDir  = Join-Path $phpDir 'ext'

if (-not (Test-Path $iniDev)) { Write-Error "php.ini-development not found: $iniDev"; exit 1 }

# 1) 复制 php.ini
if (Test-Path $ini) {
    Write-Host "php.ini exists, regenerating from development template..."
    Remove-Item $ini -Force
}
Copy-Item $iniDev $ini -Force
Write-Host "Copied php.ini-development -> php.ini"

# 2) 读出 ini
$content = Get-Content $ini -Raw

# 3) 强制写 extension_dir 为绝对路径
$extDirEsc = $extDir -replace '\\','\\'
$content = [regex]::Replace($content, '(?m)^;?\s*extension_dir\s*=.*$', "extension_dir = `"$extDirEsc`"")

# 4) 解开所有需要的扩展注释
$mustEnable = @(
    'openssl','mbstring','curl','fileinfo','pdo_sqlite','sqlite3',
    'zip','intl','gd','opcache','mysqli','pdo_mysql'
)
foreach ($name in $mustEnable) {
    $content = [regex]::Replace($content, "(?m)^;\s*extension=$name\s*$", "extension=$name")
    if ($content -notmatch "(?m)^extension=$name\s*$") {
        # ini 没列出这行 → 在 [PHP] 区段末尾追加
        $content = $content -replace "(\[PHP\][\s\S]*?)(\r?\n\[)", "`$1`r`nextension=$name`$2"
    }
}

# 5) 一些常用生产设置（让 Laravel serve 跑得稳）
$content = $content -replace "(?m)^;\s*date\.timezone\s*=.*$", "date.timezone = `"Asia/Hong_Kong`""
$content = $content -replace "(?m)^;\s*memory_limit\s*=.*$", "memory_limit = 512M"
$content = $content -replace "(?m)^;\s*max_execution_time\s*=.*$", "max_execution_time = 120"
$content = $content -replace "(?m)^;\s*upload_max_filesize\s*=.*$", "upload_max_filesize = 20M"
$content = $content -replace "(?m)^;\s*post_max_size\s*=.*$", "post_max_size = 25M"

Set-Content -Path $ini -Value $content -Encoding ASCII

Write-Host "----"
Write-Host "Verifying required extensions loaded:"
& "$phpDir\php.exe" -m 2>&1 |
    Where-Object { $_ -in 'bcmath','openssl','mbstring','curl','fileinfo','pdo_sqlite','sqlite3','zip','intl','gd','tokenizer','xml','dom','ctype','filter','hash','json','pcre','PDO','session','openssl' } |
    Sort-Object -Unique
