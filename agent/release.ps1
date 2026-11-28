# Releases a new version of the sync agent in one go, in the only order that works:
#
#   1. build the agent, with the public key inside: 64-bit and 32-bit for Windows 10 and 11 (with
#      the current Go), and again for Windows 7 and 8.1 (with Go 1.20, the last Go to run there)
#   2. sign them with the private key, and check the server accepts every signature
#   3. build the installers, which carry the signed agents
#
# From the project folder:
#
#   powershell -ExecutionPolicy Bypass -File agent\release.ps1 -Version 1.2.1
#   powershell -ExecutionPolicy Bypass -File agent\release.ps1 -Version 1.2.1 -Key D:\keys\ecomvision-agent.pem
#
# Then copy resources\agent\dist to the live server (never the key). Installed agents update
# their sync on their next cycle; a change to the service, updater or setup reaches a shop only
# when it runs the new installer (docs/SYNC.md). Needs Go, Go 1.20 (go1.20.14, see below), PHP and
# Inno Setup 6.
param(
    [Parameter(Mandatory = $true)][string]$Version,
    [string]$Key = (Join-Path $env:USERPROFILE 'ecomvision-agent.pem')
)

$ErrorActionPreference = 'Stop'
$root = Resolve-Path (Join-Path $PSScriptRoot '..')
$dist = Join-Path $root 'resources\agent\dist'
$publicKey = Join-Path $root 'resources\agent\signing.pub'

function Step([string]$text) { Write-Host "`n== $text" -ForegroundColor Cyan }

# ---- 0. Checks --------------------------------------------------------------------------------

if ($Version -notmatch '^\d+\.\d+\.\d+$') {
    throw "Version must look like 1.2.1 (got '$Version')."
}
if (-not (Test-Path $Key)) {
    throw "No signing key at $Key. Pass -Key with its path. Without the key that made resources\agent\signing.pub, installed agents refuse the update."
}
if (-not (Test-Path $publicKey)) {
    throw "resources\agent\signing.pub is missing. See docs/SYNC.md, First-time setup."
}
foreach ($tool in 'go', 'php') {
    if (-not (Get-Command $tool -ErrorAction SilentlyContinue)) {
        throw "$tool is not installed or not on PATH."
    }
}
# Go 1.20 builds the Windows 7 / 8.1 agents: later Go needs Windows 10.
$go120 = Join-Path (go env GOPATH) 'bin\go1.20.14.exe'
if (-not (Test-Path $go120) -or -not (Test-Path (Join-Path $env:USERPROFILE 'sdk\go1.20.14\bin\go.exe'))) {
    throw "Go 1.20 is needed for the Windows 7 / 8.1 builds. Install it once with:`n  go install golang.org/dl/go1.20.14@latest`n  go1.20.14 download"
}

# ---- 1. Build ---------------------------------------------------------------------------------

Step "Building the agent $Version"
$keyBase64 = [Convert]::ToBase64String([IO.File]::ReadAllBytes($publicKey))
New-Item -ItemType Directory -Force $dist | Out-Null
$saved = @{ CGO_ENABLED = $env:CGO_ENABLED; GOOS = $env:GOOS; GOARCH = $env:GOARCH }
Push-Location $PSScriptRoot
try {
    # Build => the Go that makes it, and the channel it updates on (update.go, buildChannel).
    $builds = @(
        @{ Name = 'amd64'; Arch = 'amd64'; Go = 'go'; Channel = '' },
        @{ Name = '386'; Arch = '386'; Go = 'go'; Channel = '' },
        @{ Name = 'amd64-win7'; Arch = 'amd64'; Go = $go120; Channel = '-win7' },
        @{ Name = '386-win7'; Arch = '386'; Go = $go120; Channel = '-win7' }
    )
    foreach ($build in $builds) {
        $env:CGO_ENABLED = '0'; $env:GOOS = 'windows'; $env:GOARCH = $build.Arch
        $flags = "-X main.publicKeyBase64=$keyBase64" + $(if ($build.Channel) { " -X main.buildChannel=$($build.Channel)" } else { '' })
        & $build.Go build -trimpath -buildvcs=false -ldflags $flags -o (Join-Path $dist "ecomvision-agent-$($build.Name).exe") .
        if ($LASTEXITCODE -ne 0) { throw "Building the $($build.Name) agent failed." }
        Write-Host "Built ecomvision-agent-$($build.Name).exe"
    }
} finally {
    Pop-Location
    foreach ($name in $saved.Keys) { Set-Item "env:$name" $saved[$name] -ErrorAction SilentlyContinue }
    if (-not $saved.CGO_ENABLED) { Remove-Item env:CGO_ENABLED -ErrorAction SilentlyContinue }
    if (-not $saved.GOOS) { Remove-Item env:GOOS -ErrorAction SilentlyContinue }
    if (-not $saved.GOARCH) { Remove-Item env:GOARCH -ErrorAction SilentlyContinue }
}

# ---- 2. Sign, and check -----------------------------------------------------------------------

Step 'Signing'
Push-Location $root
try {
    php artisan sync:agent-sign $Key
    if ($LASTEXITCODE -ne 0) { throw 'Signing failed.' }

    # The server's own check: exactly what decides whether an agent is ever delivered.
    $check = php artisan tinker --execute="`$p = app(App\Sync\AgentPackage::class); echo collect(App\Sync\AgentPackage::ARCHITECTURES)->every(fn (`$a) => `$p->isSigned(`$a)) ? 'signed' : 'NOT signed';"
    if (($check | Select-Object -Last 1).Trim() -ne 'signed') {
        throw 'The server does not accept the signatures. Is this the key that made resources\agent\signing.pub?'
    }
    Write-Host 'The server accepts all four signatures.'
} finally {
    Pop-Location
}

# ---- 3. Installers ----------------------------------------------------------------------------

Step 'Building the installers'
& (Join-Path $PSScriptRoot 'build-installer.ps1') -Version $Version

# ---- Done -------------------------------------------------------------------------------------

Step "Agent $Version is ready. Copy these to resources\agent\dist on the live server:"
Get-ChildItem $dist -File | ForEach-Object { '  {0,-36} {1,6:N1} MB' -f $_.Name, ($_.Length / 1MB) }
Write-Host "`nNever copy the key ($Key)."
