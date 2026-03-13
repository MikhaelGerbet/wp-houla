# build-dev.ps1 — Build a DEV variant of wp-houla that can coexist with the prod plugin
# Usage: .\build-dev.ps1
# Output: releases/wp-houla-dev-{version}.zip
#
# This script creates a full copy of the plugin with ALL identifiers renamed so that
# both wp-houla (prod) and wp-houla-dev can be installed simultaneously on the same WordPress.
# The dev variant points to the dev API URL by default and has its own settings, OAuth tokens,
# post meta, shortcodes, REST routes, etc.

$ErrorActionPreference = "Stop"

# =========================================================================
# Configuration
# =========================================================================

# Dev API URL — change this to your dev/staging API (ngrok, localhost, etc.)
$DevApiUrl = "https://dev.hou.la"

# =========================================================================
# Read version
# =========================================================================

$pluginFile = "wp-houla.php"
$versionLine = Select-String -Path $pluginFile -Pattern "Version:\s*(.+)" | Select-Object -First 1
if (-not $versionLine) {
    Write-Error "Could not read version from $pluginFile"
    exit 1
}
$version = $versionLine.Matches[0].Groups[1].Value.Trim()
Write-Host "Building wp-houla-dev v$version..." -ForegroundColor Cyan

# =========================================================================
# Prepare build directory
# =========================================================================

$buildDir = "build"
$releasesDir = "releases"
if (Test-Path $buildDir) { Remove-Item $buildDir -Recurse -Force }
New-Item -ItemType Directory -Path $buildDir | Out-Null
if (-not (Test-Path $releasesDir)) { New-Item -ItemType Directory -Path $releasesDir | Out-Null }

# Copy plugin files to build/wp-houla-dev/
$dest = Join-Path $buildDir "wp-houla-dev"
New-Item -ItemType Directory -Path $dest | Out-Null
@("admin", "includes", "languages", "wp-houla.php", "uninstall.php", "README.md", "README.txt") | ForEach-Object {
    if (Test-Path $_) { Copy-Item $_ -Destination $dest -Recurse }
}

# Remove dev/unnecessary files from the build
$excludePatterns = @(
    ".git", ".gitignore", ".DS_Store", "Thumbs.db", "*.log",
    "node_modules", ".vscode", "tests", "phpunit.xml", "phpunit.xml.dist",
    "phpcs.xml", ".editorconfig", ".phpcs.xml", "composer.json", "composer.lock"
)
foreach ($pattern in $excludePatterns) {
    Get-ChildItem -Path $dest -Recurse -Filter $pattern -Force -ErrorAction SilentlyContinue |
        Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
}

# =========================================================================
# Rename identifiers — ORDER MATTERS (longest/most specific first)
# =========================================================================

# Collect all PHP and JS files in the build
$files = Get-ChildItem -Path $dest -Recurse -Include "*.php", "*.js" -File

Write-Host "Renaming identifiers in $($files.Count) files..." -ForegroundColor Yellow

foreach ($file in $files) {
    $content = Get-Content -Path $file.FullName -Raw -Encoding UTF8

    # --- 1. Plugin header (only in main plugin file) ---
    if ($file.Name -eq "wp-houla.php") {
        $content = $content -replace `
            'Plugin Name:\s+Houla - Short Links, QR Codes & Social Commerce', `
            'Plugin Name:       Houla DEV - Short Links, QR Codes & Social Commerce'
        $content = $content -replace `
            "Text Domain:\s+wp-houla", `
            "Text Domain:       wp-houla-dev"
    }

    # --- 2. PHP Constants (WPHOULA_ prefix → WPHOULADEV_) ---
    #    CASE-SENSITIVE: -creplace to avoid matching lowercase wphoula_ functions
    $content = $content -creplace '\bWPHOULA_', 'WPHOULADEV_'

    # --- 3. Class names (Wp_Houla_ prefix and standalone Wp_Houla) ---
    #    CASE-SENSITIVE to preserve casing
    $content = $content -creplace '\bWp_Houla_', 'Wp_Houla_Dev_'
    $content = $content -creplace '\bWp_Houla\b', 'Wp_Houla_Dev'

    # --- 4. Function names (activate_wp_houla, deactivate_wp_houla, run_wp_houla, wphoula_*) ---
    $content = $content -creplace '\bactivate_wp_houla\b', 'activate_wp_houla_dev'
    $content = $content -creplace '\bdeactivate_wp_houla\b', 'deactivate_wp_houla_dev'
    $content = $content -creplace '\brun_wp_houla\b', 'run_wp_houla_dev'
    #    No \b for wphoula_ because it appears after _ in wp_ajax_wphoula_ (underscore is a word char)
    $content = $content -creplace 'wphoula_', 'wphouladev_'

    # --- 5. Option names and string identifiers ---
    #    'wphoula-options' → 'wphouladev-options'
    #    'wphoula-authorized' → 'wphouladev-authorized'
    $content = $content -replace "'wphoula-options'", "'wphouladev-options'"
    $content = $content -replace '"wphoula-options"', '"wphouladev-options"'
    $content = $content -replace "'wphoula-authorized'", "'wphouladev-authorized'"
    $content = $content -replace '"wphoula-authorized"', '"wphouladev-authorized"'

    # --- 6. Post meta keys ---
    $content = $content -replace "'_wphoula_", "'_wphouladev_"
    $content = $content -replace '"_wphoula_', '"_wphouladev_'
    $content = $content -replace "'_houla_", "'_houladev_"
    $content = $content -replace '"_houla_', '"_houladev_'

    # --- 7. Admin menu slug, script/style handles, text domain ---
    $content = $content -replace "'wp-houla-admin'", "'wp-houla-dev-admin'"
    $content = $content -replace '"wp-houla-admin"', '"wp-houla-dev-admin"'
    $content = $content -replace "'wp-houla'", "'wp-houla-dev'"
    $content = $content -replace '"wp-houla"', '"wp-houla-dev"'

    # --- 8. REST namespace ---
    $content = $content -replace 'wp-houla/v1', 'wp-houla-dev/v1'

    # --- 9. Screen IDs (used for enqueue_scripts checks) ---
    $content = $content -replace 'woocommerce_page_wp-houla\b', 'woocommerce_page_wp-houla-dev'
    $content = $content -replace 'woocommerce-marketing_page_wp-houla\b', 'woocommerce-marketing_page_wp-houla-dev'
    $content = $content -replace 'marketing_page_wp-houla\b', 'marketing_page_wp-houla-dev'
    $content = $content -replace 'settings_page_wp-houla\b', 'settings_page_wp-houla-dev'
    $content = $content -replace 'toplevel_page_wp-houla\b', 'toplevel_page_wp-houla-dev'

    # --- 10. Shortcode tag ---
    $content = $content -replace "'wphoula'", "'wphouladev'"
    $content = $content -replace '"wphoula"', '"wphouladev"'

    # --- 11. AJAX action names (wp_ajax_wphouladev_ already covered by step 4) ---
    #    Nonce names already covered (wphouladev_admin, wphouladev_metabox, etc.)

    # --- 12. Transient names (already covered by wphoula_ → wphouladev_ in step 4) ---
    #    Also fix SQL LIKE patterns in uninstall.php
    $content = $content -replace "'_transient_wphoula_", "'_transient_wphouladev_"
    $content = $content -replace "'_transient_timeout_wphoula_", "'_transient_timeout_wphouladev_"
    $content = $content -replace '%wphoula_%', '%wphouladev_%'

    # --- 13. File references (class-wp-houla-*.php includes) ---
    $content = $content -replace 'class-wp-houla-', 'class-wp-houla-dev-'
    #    Also fix class-wp-houla.php (no dash suffix — the main class file)
    $content = $content -replace 'class-wp-houla\.php', 'class-wp-houla-dev.php'
    $content = $content -replace 'wp-houla-admin\.js', 'wp-houla-dev-admin.js'
    $content = $content -replace 'wp-houla-admin\.css', 'wp-houla-dev-admin.css'

    # --- 14. i18n text domain in load_plugin_textdomain and __() calls ---
    #    Already done by step 7 ('wp-houla' → 'wp-houla-dev')

    # --- 15. Dashboard widget ID ---
    $content = $content -replace "'wphouladev_dashboard_widget'", "'wphouladev_dashboard_widget'"
    # (already covered, but ensure metabox IDs)
    $content = $content -replace "'wphouladev_post_metabox'", "'wphouladev_post_metabox'"
    $content = $content -replace "'wphouladev_product_metabox'", "'wphouladev_product_metabox'"

    # --- 16. Dev API URL (replace default prod URL constant value) ---
    if ($file.Name -eq "wp-houla.php") {
        $content = $content -replace `
            "define\(\s*'WPHOULADEV_DEFAULT_API_URL',\s*'https://hou\.la'\s*\)", `
            "define( 'WPHOULADEV_DEFAULT_API_URL', '$DevApiUrl' )"
        $content = $content -replace `
            "define\(\s*'WPHOULADEV_API_URL',\s*WPHOULADEV_DEFAULT_API_URL\s*\)", `
            "define( 'WPHOULADEV_API_URL', '$DevApiUrl' )"
    }

    # Write back
    Set-Content -Path $file.FullName -Value $content -Encoding UTF8 -NoNewline
}

# =========================================================================
# Rename PHP files (class-wp-houla-*.php → class-wp-houla-dev-*.php)
# =========================================================================

Write-Host "Renaming files..." -ForegroundColor Yellow

# Rename includes/ class files
# IMPORTANT: Collect the file list FIRST to avoid re-enumeration after rename
$includesDir = Join-Path $dest "includes"
if (Test-Path $includesDir) {
    $filesToRename = @(Get-ChildItem -Path $includesDir -Filter "class-wp-houla-*.php")
    foreach ($f in $filesToRename) {
        $newName = $f.Name -replace '^class-wp-houla-', 'class-wp-houla-dev-'
        Rename-Item -Path $f.FullName -NewName $newName
    }
    # Also rename the main class file if it exists
    $mainClass = Join-Path $includesDir "class-wp-houla.php"
    if (Test-Path $mainClass) {
        Rename-Item -Path $mainClass -NewName "class-wp-houla-dev.php"
    }
}

# Rename admin class file
$adminClass = Join-Path $dest "admin\class-wp-houla-admin.php"
if (Test-Path $adminClass) {
    Rename-Item -Path $adminClass -NewName "class-wp-houla-dev-admin.php"
}

# Rename admin JS/CSS files
$jsDir = Join-Path $dest "admin\js"
if (Test-Path $jsDir) {
    $jsFile = Join-Path $jsDir "wp-houla-admin.js"
    if (Test-Path $jsFile) { Rename-Item -Path $jsFile -NewName "wp-houla-dev-admin.js" }
}
$cssDir = Join-Path $dest "admin\css"
if (Test-Path $cssDir) {
    $cssFile = Join-Path $cssDir "wp-houla-admin.css"
    if (Test-Path $cssFile) { Rename-Item -Path $cssFile -NewName "wp-houla-dev-admin.css" }
}

# Rename language files
$langDir = Join-Path $dest "languages"
if (Test-Path $langDir) {
    Get-ChildItem -Path $langDir -Filter "wp-houla*" | ForEach-Object {
        $newName = $_.Name -replace '^wp-houla', 'wp-houla-dev'
        Rename-Item -Path $_.FullName -NewName $newName
    }
}

# Rename the main plugin file
$mainFile = Join-Path $dest "wp-houla.php"
if (Test-Path $mainFile) {
    Rename-Item -Path $mainFile -NewName "wp-houla-dev.php"
}

# =========================================================================
# Apply JS-side renames (AJAX action names used in JavaScript)
# =========================================================================

$jsFiles = Get-ChildItem -Path $dest -Recurse -Include "*.js" -File
foreach ($jsFile in $jsFiles) {
    $content = Get-Content -Path $jsFile.FullName -Raw -Encoding UTF8

    # AJAX action references in JS
    $content = $content -replace 'wphoula_', 'wphouladev_'

    # Nonce names
    $content = $content -replace "'wp-houla'", "'wp-houla-dev'"
    $content = $content -replace '"wp-houla"', '"wp-houla-dev"'

    Set-Content -Path $jsFile.FullName -Value $content -Encoding UTF8 -NoNewline
}

# =========================================================================
# Create zip
# =========================================================================

$zipName = "wp-houla-dev-$version.zip"
$zipPath = Join-Path $releasesDir $zipName

if (Test-Path $zipPath) { Remove-Item $zipPath -Force }
Compress-Archive -Path $dest -DestinationPath $zipPath -CompressionLevel Optimal

# Cleanup build dir
Remove-Item $buildDir -Recurse -Force

# =========================================================================
# Done
# =========================================================================

$size = [math]::Round((Get-Item $zipPath).Length / 1KB, 1)
Write-Host ""
Write-Host "DEV package built successfully!" -ForegroundColor Green
Write-Host "  File: $zipPath" -ForegroundColor White
Write-Host "  Size: ${size} KB" -ForegroundColor White
Write-Host "  API URL: $DevApiUrl" -ForegroundColor White
Write-Host ""
Write-Host "Differences from prod plugin:" -ForegroundColor Cyan
Write-Host "  - Plugin name: 'Houla DEV - Short Links, QR Codes & Social Commerce'" -ForegroundColor White
Write-Host "  - Folder: wp-houla-dev/" -ForegroundColor White
Write-Host "  - Options: wphouladev-options (separate settings)" -ForegroundColor White
Write-Host "  - Post meta: _wphouladev_* (separate from prod data)" -ForegroundColor White
Write-Host "  - REST API: /wp-json/wp-houla-dev/v1/" -ForegroundColor White
Write-Host "  - Shortcode: [wphouladev]" -ForegroundColor White
Write-Host "  - OAuth client: wp-houla-dev" -ForegroundColor White
Write-Host ""
Write-Host "IMPORTANT: Register these on your Hou.la API:" -ForegroundColor Red
Write-Host "  1. OAuth redirect URI: {your-wp-site}/wp-json/wp-houla-dev/v1/oauth/callback" -ForegroundColor Yellow
Write-Host "  2. Webhook endpoint:   {your-wp-site}/wp-json/wp-houla-dev/v1/webhook" -ForegroundColor Yellow
Write-Host ""
Write-Host "Install: WordPress Admin > Extensions > Ajouter > Telecharger une extension > $zipName" -ForegroundColor Yellow
