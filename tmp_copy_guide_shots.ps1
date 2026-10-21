$src = Join-Path $env:LOCALAPPDATA "Temp\cursor\screenshots\public\gp-assets\img\guide\pages"
$dst = "c:\xampp\htdocs\SpareParts\public\gp-assets\img\guide\pages"
if (Test-Path $src) {
    New-Item -ItemType Directory -Force -Path $dst | Out-Null
    Copy-Item "$src\*.png" $dst -Force
    Get-ChildItem $dst | Format-Table Name, Length
} else {
    Write-Host "No browser screenshots at $src"
}
