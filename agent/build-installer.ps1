# Builds the installers shops run (agent\installer\agent.iss), into resources\agent\dist:
#
#   EcomVisionSyncAgent-Setup-x64.exe, -x86.exe              Windows 10 and 11
#   EcomVisionSyncAgent-Setup-x64-win7.exe, -x86-win7.exe    Windows 7 and 8.1
#
# Run it after building and signing the agent; agent\release.ps1 does all three in order.
#
#   powershell -ExecutionPolicy Bypass -File agent\build-installer.ps1 [-Version 1.2.0]
#
# Needs Inno Setup 6: https://jrsoftware.org/isdl.php
param([string]$Version = (Get-Date -Format 'yyyy.M.d'))

$ErrorActionPreference = 'Stop'
$dist = Join-Path $PSScriptRoot '..\resources\agent\dist'

# Installer => the signed agent build it carries.
$installers = [ordered]@{ 'x64' = 'amd64'; 'x86' = '386'; 'x64-win7' = 'amd64-win7'; 'x86-win7' = '386-win7' }

foreach ($build in $installers.Values) {
    foreach ($file in "ecomvision-agent-$build.exe", "ecomvision-agent-$build.exe.sig") {
        if (-not (Test-Path (Join-Path $dist $file))) {
            throw "$file is missing from resources\agent\dist. Build and sign the agent first (agent\release.ps1)."
        }
    }
}

$iscc = (Get-Command 'iscc.exe' -ErrorAction SilentlyContinue).Source
if (-not $iscc) {
    $iscc = @(
        "${env:ProgramFiles(x86)}\Inno Setup 6\ISCC.exe",
        "$env:ProgramFiles\Inno Setup 6\ISCC.exe",
        "$env:LOCALAPPDATA\Programs\Inno Setup 6\ISCC.exe"
    ) | Where-Object { Test-Path $_ } | Select-Object -First 1
}
if (-not $iscc) {
    throw 'Inno Setup 6 is not installed. Get it from https://jrsoftware.org/isdl.php'
}

foreach ($arch in $installers.Keys) {
    & $iscc "/DAppVersion=$Version" "/DArch=$arch" /Q (Join-Path $PSScriptRoot 'installer\agent.iss')
    if ($LASTEXITCODE -ne 0) {
        throw "Inno Setup failed for $arch (exit $LASTEXITCODE)."
    }
    Write-Host "Built resources\agent\dist\EcomVisionSyncAgent-Setup-$arch.exe (version $Version)"
}
