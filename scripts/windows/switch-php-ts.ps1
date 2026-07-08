$ErrorActionPreference = 'Continue'

Write-Host "=== 1. 删除旧 NTS 安装目录 ===" -ForegroundColor Cyan
$oldNts = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.NTS.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe"
if (Test-Path $oldNts) {
    # 强制去掉只读
    attrib -r "$oldNts\*.*" /S /D 2>&1 | Out-Null
    Remove-Item -LiteralPath $oldNts -Recurse -Force -ErrorAction SilentlyContinue
    if (Test-Path $oldNts) {
        Write-Host "  still exists, trying cmd rmdir..."
        cmd /c "rmdir /S /Q `"$oldNts`"" 2>&1 | Out-Null
    }
    if (Test-Path $oldNts) {
        Write-Host "  FAILED to remove: $oldNts" -ForegroundColor Red
    } else {
        Write-Host "  removed OK" -ForegroundColor Green
    }
} else {
    Write-Host "  (already gone)"
}

Write-Host ""
Write-Host "=== 2. 装 PHP 8.2 TS (含 bcmath/intl/gd) ===" -ForegroundColor Cyan
winget install --id=PHP.PHP.8.2 -e --accept-package-agreements --accept-source-agreements --silent 2>&1 | Select-Object -Last 8

Write-Host ""
Write-Host "=== 3. 找新安装目录 ===" -ForegroundColor Cyan
$pkgRoot = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages"
$candidates = Get-ChildItem -Path $pkgRoot -Directory -Filter 'PHP.PHP.8.2_*' -ErrorAction SilentlyContinue
if (-not $candidates) {
    Write-Host "  TS install dir not found" -ForegroundColor Red
    exit 1
}
$phpDir = $candidates[0].FullName
Write-Host "  $phpDir"

Write-Host ""
Write-Host "=== 4. ext 目录里要有的关键扩展 ===" -ForegroundColor Cyan
$extDir = Join-Path $phpDir 'ext'
foreach ($e in 'bcmath','openssl','mbstring','curl','pdo_sqlite','sqlite3','zip','intl','gd','fileinfo','tokenizer','xml','dom','opcache') {
    $f = Join-Path $extDir ("php_$e.dll")
    if (Test-Path $f) { Write-Host "  YES  php_$e.dll" -ForegroundColor Green } else { Write-Host "  NO   php_$e.dll" -ForegroundColor Red }
}
