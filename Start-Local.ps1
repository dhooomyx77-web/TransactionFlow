param([string]$PhpPath = 'php', [int]$Port = 8090)
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
if (!(Test-Path config.local.php)) { throw 'Create config.local.php using config.example.php first. See README.md.' }
Write-Host "TransactionFlow: http://127.0.0.1:$Port"
Write-Host 'Local development only. Press Ctrl+C to stop.'
& $PhpPath -S "127.0.0.1:$Port" -t public
