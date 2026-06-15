$ErrorActionPreference = 'Stop'

$phpDir = (Get-ChildItem -Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Directory -Filter 'PHP.PHP.8.2_*' | Select-Object -First 1).FullName
$ini    = Join-Path $phpDir 'php.ini'

$content = Get-Content $ini -Raw

# 1) 移除我们错误加上的 mysqli 行（保留 [PHP] 区块内原始那一行）
$content = [regex]::Replace($content, "(?m)^\s*extension=mysqli\s*\r?\n", "")

# 2) 修 opcache：把 extension=opcache 改成 zend_extension=opcache
$content = [regex]::Replace($content, "(?m)^\s*extension=opcache\s*$", "zend_extension=opcache")

Set-Content -Path $ini -Value $content -Encoding ASCII

Write-Host "---- php -m (after fix) ----"
& "$phpDir\php.exe" -m 2>&1 |
    Where-Object { $_ -and $_ -notmatch '^\[' -and $_ -notmatch '^PHP Warning' -and $_ -notmatch '^Warning' } |
    Sort-Object -Unique

Write-Host "----"
Write-Host "Warnings remaining:"
& "$phpDir\php.exe" -m 2>&1 | Where-Object { $_ -match 'Warning' } | Select-Object -Unique

Write-Host ""
Write-Host "==== 装 Composer 到 C:\tools ===="

$toolsDir = 'C:\tools'
if (-not (Test-Path $toolsDir)) { New-Item -ItemType Directory -Path $toolsDir -Force | Out-Null }

$composerBat = Join-Path $toolsDir 'composer.bat'
if (Test-Path $composerBat) {
    Write-Host "composer.bat exists, removing..."
    Remove-Item $composerBat -Force
}

# 用 Git Bash 的 curl
$curlExe = (Get-Command curl.exe -ErrorAction SilentlyContinue).Source
if (-not $curlExe) { Write-Error "curl not found"; exit 1 }

$tmpInstaller = Join-Path $toolsDir 'composer-setup.php'
$tmpSig       = Join-Path $toolsDir 'composer-setup.sig'

Write-Host "Downloading installer..."
& $curlExe -fsSL -o $tmpInstaller https://getcomposer.org/installer
if ($LASTEXITCODE -ne 0 -or -not (Test-Path $tmpInstaller)) { Write-Error "installer download failed"; exit 1 }

Write-Host "Downloading signature..."
& $curlExe -fsSL -o $tmpSig https://composer.github.io/installer.sig
if ($LASTEXITCODE -ne 0) { Write-Error "signature download failed"; exit 1 }

$expected = (Get-Content $tmpSig -Raw).Trim()
$actual   = (Get-FileHash -Path $tmpInstaller -Algorithm SHA384).Hash.ToLower()
if ($expected -ne $actual) { Write-Error "signature mismatch! exp=$expected act=$actual"; exit 1 }
Write-Host "Signature OK."

Write-Host "Running installer..."
& "$phpDir\php.exe" $tmpInstaller --quiet --install-dir=$toolsDir --filename=composer
$rc = $LASTEXITCODE
Remove-Item $tmpInstaller, $tmpSig -ErrorAction SilentlyContinue
if ($rc -ne 0) { Write-Error "installer failed (exit=$rc)"; exit 1 }

Write-Host "---- composer --version ----"
& $composerBat --version 2>&1 | Select-Object -First 2
Write-Host "Installed at: $composerBat"
