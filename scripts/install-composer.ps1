$ErrorActionPreference = 'Stop'

$phpDir    = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.NTS.8.2_Microsoft.WinGet.Source_8wekyb3d8bbwe"
$altPhpDir = "$env:LOCALAPPDATA\Microsoft\WinGet\Packages\PHP.PHP.NTS.8.2_Microsoft.Winget.Source_8wekyb3d8bbwe"
if (-not (Test-Path $phpDir)) { $phpDir = $altPhpDir }
$phpExe   = Join-Path $phpDir 'php.exe'
$toolsDir = 'C:\tools'
$composerBat = Join-Path $toolsDir 'composer.bat'
$tmpInstaller = Join-Path $toolsDir 'composer-setup.php'
$tmpSig       = Join-Path $toolsDir 'composer-setup.sig'

if (-not (Test-Path $phpExe)) { Write-Error "php.exe not found: $phpExe"; exit 1 }
if (-not (Test-Path $toolsDir)) { New-Item -ItemType Directory -Path $toolsDir -Force | Out-Null }

if (Test-Path $composerBat) {
    Write-Host "composer.bat exists, verifying..."
    & $composerBat --version 2>&1 | Select-Object -First 2
    if ($LASTEXITCODE -eq 0) { Write-Host "OK"; exit 0 }
    Remove-Item $composerBat -Force
}

# 用 curl.exe（PowerShell 7 自带；Win10 没 PS7 就用 Git Bash 的 curl）
$curlExe = (Get-Command curl.exe -ErrorAction SilentlyContinue).Source
if (-not $curlExe) { $curlExe = (Get-Command curl -ErrorAction SilentlyContinue).Source }
if (-not $curlExe) { Write-Error "curl not found"; exit 1 }
Write-Host "Using curl: $curlExe"

Write-Host "Downloading installer..."
& $curlExe -fsSL -o $tmpInstaller https://getcomposer.org/installer
if ($LASTEXITCODE -ne 0 -or -not (Test-Path $tmpInstaller)) {
    Write-Error "Download installer failed (exit=$LASTEXITCODE)"
    exit 1
}
Write-Host "Downloaded $([math]::Round((Get-Item $tmpInstaller).Length/1KB,1)) KB"

Write-Host "Downloading signature..."
& $curlExe -fsSL -o $tmpSig https://composer.github.io/installer.sig
if ($LASTEXITCODE -ne 0) { Write-Error "Download signature failed"; exit 1 }

$expected = (Get-Content $tmpSig -Raw).Trim()
$actual   = (Get-FileHash -Path $tmpInstaller -Algorithm SHA384).Hash.ToLower()
if ($expected -ne $actual) {
    Write-Error "Installer signature mismatch!`n  expected=$expected`n  actual  =$actual"
    exit 1
}
Write-Host "Signature OK."

Write-Host "Running installer..."
& $phpExe $tmpInstaller --quiet --install-dir=$toolsDir --filename=composer
$installCode = $LASTEXITCODE
Remove-Item $tmpInstaller, $tmpSig -ErrorAction SilentlyContinue
if ($installCode -ne 0) { Write-Error "Installer failed (exit=$installCode)"; exit 1 }

Write-Host "----"
& $composerBat --version 2>&1 | Select-Object -First 2
Write-Host "Installed at: $composerBat"
