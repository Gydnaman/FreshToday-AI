@echo off
echo === 1. Docker CLI ===
docker --version 2>&1
docker-compose --version 2>&1
docker compose version 2>&1
echo.
echo === 2. Podman / nerdctl / Rancher / OrbStack ===
where podman 2>&1
where nerdctl 2>&1
where rancher 2>&1
where orbctl 2>&1
echo.
echo === 3. WSL ===
wsl --status 2>&1
wsl --list --verbose 2>&1
echo.
echo === 4. Hyper-V 功能 ===
powershell -NoProfile -Command "$f=Get-WindowsOptionalFeature -Online -FeatureName Microsoft-Hyper-V,VirtualMachinePlatform,Hyper,Hyper-Management-Clients,Hyper-V-Hypervisor,Containers 2>&1; $f | Format-Table FeatureName,State"
echo.
echo === 5. CPU 虚拟化 ===
powershell -NoProfile -Command "Get-ComputerInfo | Select-Object HyperVRequirementVirtualizationFirmwareEnabled,HyperVRequirementSecondLevelAddressTranslationEnabled,HyperVRequirementVMMonitorModeExtensions | Format-List"
echo.
echo === 6. Docker 相关服务 ===
powershell -NoProfile -Command "Get-Service | Where-Object { $_.Name -match 'docker|vmcompute|Hyper-V|Container' } | Format-Table Name,Status,StartType"
echo.
echo === 7. Docker Desktop 安装目录 ===
if exist "C:\Program Files\Docker\Docker" (echo YES C:\Program Files\Docker\Docker) else (echo NO C:\Program Files\Docker\Docker)
if exist "C:\ProgramData\Docker" (echo YES C:\ProgramData\Docker) else (echo NO C:\ProgramData\Docker)
if exist "%LOCALAPPDATA%\Docker" (echo YES LOCALAPPDATA Docker) else (echo NO LOCALAPPDATA Docker)
if exist "C:\Program Files\Rancher Desktop" (echo YES Rancher Desktop) else (echo NO Rancher Desktop)
if exist "C:\Program Files\OrbStack" (echo YES OrbStack) else (echo NO OrbStack)
echo.
echo === 8. PATH 中是否含 docker ===
echo %PATH% | findstr /I "docker" 2>&1
echo.
echo === 9. 你说的「扩展」指什么？ ===
powershell -NoProfile -Command "Get-Command docker -ErrorAction SilentlyContinue; Get-Command docker.exe -ErrorAction SilentlyContinue; Get-Command dockerd -ErrorAction SilentlyContinue"
