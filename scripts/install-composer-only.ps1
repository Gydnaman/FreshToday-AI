$ErrorActionPreference = 'Continue'
$phpDir = (Get-ChildItem -Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Directory -Filter 'PHP.PHP.8.2_*' | Select-Object -First 1).FullName
$phpExe = Join-Path $phpDir 'php.exe'
$toolsDir = 'C:\tools'
$tmp = Join-Path $env:TEMP 'composer-setup.php'

if (-not (Test-Path $toolsDir)) { New-Item -ItemType Directory -Path $toolsDir -Force | Out-Null }

Write-Host "PHP: $phpExe"
Write-Host "ToolsDir: $toolsDir"

$curlExe = (Get-Command curl.exe -ErrorAction SilentlyContinue).Source
Write-Host "curl: $curlExe"

Write-Host "Downloading installer to $tmp ..."
& $curlExe -fsSL -o $tmp https://getcomposer.org/installer
Write-Host "  curl exit=$LASTEXITCODE, size=$((Get-Item $tmp -ErrorAction SilentlyContinue).Length)"

if (-not (Test-Path $tmp) -or (Get-Item $tmp).Length -lt 1000) {
    Write-Error "Installer not downloaded properly"
    exit 1
}

Write-Host "Running php installer (verbose)..."
& $phpExe $tmp --install-dir=$toolsDir --filename=composer 2>&1
$rc = $LASTEXITCODE
Write-Host "  installer exit=$rc"

if ($rc -ne 0) {
    Write-Error "Installer failed"
    exit 1
}

Write-Host "Files in C:\tools:"
Get-ChildItem $toolsDir | Select-Object Name, Length

$bat = Join-Path $toolsDir 'composer.bat'
if (Test-Path $bat) {
    Write-Host "---- $bat --version ----"
    & $bat --version
}
