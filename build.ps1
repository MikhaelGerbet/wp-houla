# build.ps1 — Build wp-houla plugin package (.zip)
# Usage: .\build.ps1
# Output: releases/wp-houla-{version}.zip

$ErrorActionPreference = "Stop"

# Read version from main plugin file
$pluginFile = "wp-houla.php"
$versionLine = Select-String -Path $pluginFile -Pattern "Version:\s*(.+)" | Select-Object -First 1
if (-not $versionLine) {
    Write-Error "Could not read version from $pluginFile"
    exit 1
}
$version = $versionLine.Matches[0].Groups[1].Value.Trim()
Write-Host "Building wp-houla v$version..." -ForegroundColor Cyan

# Clean previous build
$buildDir = "build"
$releasesDir = "releases"
if (Test-Path $buildDir) { Remove-Item $buildDir -Recurse -Force }
New-Item -ItemType Directory -Path $buildDir | Out-Null
if (-not (Test-Path $releasesDir)) { New-Item -ItemType Directory -Path $releasesDir | Out-Null }

# Copy plugin files to build/wp-houla/
$dest = Join-Path $buildDir "wp-houla"
New-Item -ItemType Directory -Path $dest | Out-Null
@("admin", "includes", "languages", ".wordpress-org", "wp-houla.php", "uninstall.php", "README.md", "README.txt") | ForEach-Object {
    if (Test-Path $_) { Copy-Item $_ -Destination $dest -Recurse }
}

# Remove dev/unnecessary files from the build
$excludePatterns = @(
    ".git",
    ".gitignore",
    ".DS_Store",
    "Thumbs.db",
    "*.log",
    "node_modules",
    ".vscode",
    "tests",
    "phpunit.xml",
    "phpunit.xml.dist",
    "phpcs.xml",
    ".editorconfig",
    ".phpcs.xml",
    "composer.json",
    "composer.lock"
)
foreach ($pattern in $excludePatterns) {
    Get-ChildItem -Path $dest -Recurse -Filter $pattern -Force -ErrorAction SilentlyContinue |
        Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
}

# Create zip
$zipName = "wp-houla-$version.zip"
$zipPath = Join-Path $releasesDir $zipName

if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
Compress-Archive -Path $dest -DestinationPath $zipPath -CompressionLevel Optimal

# Cleanup build dir
Remove-Item $buildDir -Recurse -Force

# Final output
$size = [math]::Round((Get-Item $zipPath).Length / 1KB, 1)
Write-Host ""
Write-Host "Package built successfully!" -ForegroundColor Green
Write-Host "  File: $zipPath" -ForegroundColor White
Write-Host "  Size: ${size} KB" -ForegroundColor White
Write-Host ""
Write-Host "To install: WordPress Admin > Extensions > Ajouter > Telecharger une extension > $zipName" -ForegroundColor Yellow
