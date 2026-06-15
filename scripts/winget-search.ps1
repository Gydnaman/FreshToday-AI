$ErrorActionPreference = 'Continue'
function S($t){ Write-Host "=== $t ===" -ForegroundColor Cyan }

S 'winget version'
winget --version

S 'search PHP'
winget search PHP --accept-source-agreements 2>&1 | Select-String -Pattern 'Php|PHP' | Select-Object -First 20

S 'search Composer'
winget search Composer --accept-source-agreements 2>&1 | Select-String -Pattern 'Composer' | Select-Object -First 10

S 'search SQLite'
winget search SQLite --accept-source-agreements 2>&1 | Select-Object -First 15
