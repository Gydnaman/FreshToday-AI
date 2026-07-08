$php = (Get-ChildItem -Path "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Directory -Filter 'PHP.PHP.8.2_*' | Select-Object -First 1).FullName + '\php.exe'
Write-Host "PHP: $php"
Write-Host "---- php -m (sorted) ----"
& $php -m 2>&1 | Where-Object { $_ -and $_ -notmatch '^\[' } | Sort-Object -Unique
Write-Host "---- php -v ----"
& $php -v 2>&1 | Select-Object -First 2
