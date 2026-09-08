[CmdletBinding()]
param(
    [string] $PhpPath = 'C:\xampp\php\php.exe',
    [ValidateRange(1024, 65535)]
    [int] $Port = 1044,
    [ValidateSet('www.localhost', 'localhost', '127.0.0.1')]
    [string] $LocalHostName = 'www.localhost'
)

$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\..'))
$localOrigin = "http://${LocalHostName}:$Port"
$failed = $false

Write-Output "Project: $projectRoot"
Write-Output "Checking XAMPP on $localOrigin (does not start, stop, or reconfigure services)."
$listeners = @(netstat -ano -p tcp | Where-Object { $_ -match "^\s*TCP\s+127\.0\.0\.1:$Port\s+.*LISTENING\s+\d+\s*$" })
if ($listeners.Count -eq 0) {
    Write-Warning "No IPv4 loopback listener found on port $Port. Review XAMPP's existing virtual host before starting Apache."
    $failed = $true
} else {
    foreach ($listener in $listeners) {
        $listenerProcessId = ($listener.Trim() -split '\s+')[-1]
        Write-Output "Loopback listener: port $Port, PID $listenerProcessId"
    }
}

if (-not (Test-Path -LiteralPath $PhpPath -PathType Leaf)) {
    throw "XAMPP PHP was not found at $PhpPath. Pass -PhpPath with its actual location."
}
& $PhpPath (Join-Path $PSScriptRoot 'check_local_database.php')
if ($LASTEXITCODE -ne 0) { $failed = $true }

$checks = @(
    @{ Path = '/signin'; Expected = 200 },
    @{ Path = '/.env'; Expected = 403 },
    @{ Path = '/app/Config/Database.php'; Expected = 403 },
    @{ Path = '/documentation/tools/check_local_database.php'; Expected = 403 }
)
foreach ($check in $checks) {
    # Discard all bodies and headers: no cookies, credentials, or source are printed.
    # Keep requests on loopback while sending the configured virtual-host name.
    # Do not depend on Windows DNS or a system proxy to resolve *.localhost.
    $httpStatus = & curl.exe --silent --show-error --noproxy '*' --resolve "${LocalHostName}:${Port}:127.0.0.1" --output NUL --write-out '%{http_code}' --max-time 20 "$localOrigin$($check.Path)"
    $requestExitCode = $LASTEXITCODE
    $pass = $requestExitCode -eq 0 -and [string] $httpStatus -eq [string] $check.Expected
    Write-Output ("{0} {1} -> HTTP {2} (expected {3})" -f $(if ($pass) { 'PASS' } else { 'FAIL' }), $check.Path, $httpStatus, $check.Expected)
    if (-not $pass) { $failed = $true }
}

if ($failed) { exit 1 }
Write-Output 'Local readiness checks passed. No Docker or XAMPP services were changed.'
