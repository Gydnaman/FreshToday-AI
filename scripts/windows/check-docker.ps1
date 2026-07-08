$ErrorActionPreference = 'Continue'
function Show($t){ Write-Host "=== $t ===" -ForegroundColor Cyan }

Show '1. Docker CLI'
docker --version 2>&1 | Select-Object -First 3
docker compose version 2>&1 | Select-Object -First 1
docker-compose --version 2>&1 | Select-Object -First 1

Show '2. 替代品 (podman/nerdctl/rancher/orbctl)'
foreach($n in 'podman','nerdctl','rancher','orbctl'){
    $c = Get-Command $n -ErrorAction SilentlyContinue
    if($c){ "$n : $($c.Path)" } else { "$n : (not found)" }
}

Show '3. WSL 状态'
wsl --status 2>&1 | Select-Object -First 15
''
wsl --list --verbose 2>&1 | Select-Object -First 10

Show '4. Windows 容器/Hyper-V 特性'
$feats = Get-WindowsOptionalFeature -Online -ErrorAction SilentlyContinue |
         Where-Object { $_.FeatureName -match 'Hyper|Container' }
$feats | Select-Object FeatureName,State | Format-Table -AutoSize

Show '5. CPU 虚拟化能力'
$ci = Get-ComputerInfo -ErrorAction SilentlyContinue
$ci | Select-Object HyperVRequirementVirtualizationFirmwareEnabled,
                     HyperVRequirementSecondLevelAddressTranslationEnabled,
                     HyperVRequirementVMMonitorModeExtensions,
                     OsName,OsVersion | Format-List

Show '6. 相关服务'
Get-Service | Where-Object { $_.Name -match 'docker|vmcompute|Hyper|container' } |
    Select-Object Name,Status,StartType | Format-Table -AutoSize

Show '7. 候选安装目录'
$dirs = @(
  'C:\Program Files\Docker\Docker',
  'C:\ProgramData\Docker',
  'C:\ProgramData\DockerDesktop',
  "$env:LOCALAPPDATA\Docker",
  'C:\Program Files\Rancher Desktop',
  'C:\Program Files\OrbStack',
  "$env:LOCALAPPDATA\Programs\OrbStack",
  'C:\Program Files\Podman'
)
foreach($d in $dirs){ if(Test-Path $d){ "YES $d" } else { "NO  $d" } }

Show '8. PATH 里是否含 docker'
$env:PATH -split ';' | Where-Object { $_ -match 'docker|podman|orbstack|rancher' }

Show '9. Windows 版本 & SKU'
(Get-CimInstance Win32_OperatingSystem | Select-Object Caption,Version,BuildNumber,OSArchitecture | Format-List | Out-String).Trim()
