$ErrorActionPreference = 'Stop'

$phpDir    = (Get-ChildItem -Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Directory -Filter 'PHP.PHP.8.2_*' | Select-Object -First 1).FullName
$sqliteDir = (Get-ChildItem -Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Directory -Filter 'SQLite.SQLite_*' | Select-Object -First 1).FullName
$toolsDir  = 'C:\tools'

foreach ($d in @($phpDir, $sqliteDir, $toolsDir)) {
    if (-not (Test-Path $d)) { Write-Error "Missing: $d"; exit 1 }
}

# 也加个 PHP 的 ext 目录（保险）
# 写到用户 PATH（HKCU\Environment，新进程生效）
$regPath = 'HKCU:\Environment'
$name    = 'Path'
$current = (Get-ItemProperty -Path $regPath -Name $name -ErrorAction SilentlyContinue).$name
if ($null -eq $current) { $current = '' }

$toAdd = @($phpDir, $sqliteDir, $toolsDir) | Where-Object { $_ -notin ($current -split ';') }
if ($toAdd.Count -gt 0) {
    $new = (($current -split ';' | Where-Object { $_ }) + $toAdd) -join ';'
    Set-ItemProperty -Path $regPath -Name $name -Value $new
    Add-Type -Namespace Win32 -Name Broadcast -MemberDefinition @'
[System.Runtime.InteropServices.DllImport("user32.dll", SetLastError=true)]
public static extern int SendMessageTimeout(IntPtr hWnd, uint Msg, IntPtr wParam, string lParam, uint fuFlags, uint uTimeout, out IntPtr lpdwResult);
'@
    $HWND_BROADCAST = [IntPtr]0xffff
    $WM_SETTINGCHANGE = 0x001A
    [IntPtr]$result = [IntPtr]::Zero
    [Win32.Broadcast]::SendMessageTimeout($HWND_BROADCAST, $WM_SETTINGCHANGE, [IntPtr]::Zero, 'Environment', 2, 1000, [ref]$result) | Out-Null
    Write-Host "User PATH updated. Added: $($toAdd -join '; ')"
} else {
    Write-Host "User PATH already contains required dirs."
}

# 当前进程立刻生效
$env:PATH = "$phpDir;$sqliteDir;$toolsDir;$env:PATH"
$env:Path = $env:PATH

Write-Host ""
Write-Host "---- 新开 shell 验证 ----"
Write-Host "php:    $(& "$phpDir\php.exe" -v 2>&1 | Select-Object -First 1)"
Write-Host "sqlite: $(& "$sqliteDir\sqlite3.exe" -version 2>&1 | Select-Object -First 1)"
Write-Host "composer: $(& "$phpDir\php.exe" "$toolsDir\composer" --version 2>&1 | Select-Object -First 2 | Out-String).Trim()"
