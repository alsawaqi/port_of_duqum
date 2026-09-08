# Used by the local Windows scheduled task. No listener or extra port is opened.
$ErrorActionPreference = 'Stop'
$portalRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$phpExecutable = 'C:\xampp\php\php.exe'
if (-not (Test-Path -LiteralPath $phpExecutable) -or -not (Test-Path -LiteralPath (Join-Path $portalRoot 'spark'))) {
    throw 'The local PHP runtime or portal CLI was not found.'
}
$worker = Start-Process -FilePath $phpExecutable -ArgumentList ('"' + (Join-Path $portalRoot 'spark') + '" sms:process') -WorkingDirectory $portalRoot -WindowStyle Hidden -Wait -PassThru
exit $worker.ExitCode
