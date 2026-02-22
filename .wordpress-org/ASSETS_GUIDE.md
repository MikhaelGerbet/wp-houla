# WordPress.org Plugin Assets Guide

This document describes the required assets for the WordPress.org plugin directory listing.

## Directory Structure

```
.wordpress-org/            → SVN assets directory (NOT included in plugin ZIP)
  banner-772x250.png       → Plugin banner (standard)
  banner-1544x500.png      → Plugin banner (retina)
  icon-128x128.png         → Plugin icon (standard)
  icon-256x256.png         → Plugin icon (retina)
  screenshot-1.png         → Settings — Connection tab
  screenshot-2.png         → Settings — Sync tab
  screenshot-3.png         → Settings — Orders tab
  screenshot-4.png         → Settings — Debug tab
  screenshot-5.png         → Post metabox (shortlink + QR + stats)
  screenshot-6.png         → Product metabox (sync + commerce stats)
  screenshot-7.png         → Shortcode output in content
```

## Asset Specifications

### Banner (required)

| File | Dimensions | Format | Description |
|------|-----------|--------|-------------|
| `banner-772x250.png` | 772 × 250 px | PNG or JPG | Main banner shown on the plugin page |
| `banner-1544x500.png` | 1544 × 500 px | PNG or JPG | Retina banner (2×) |

**Design guidelines:**
- Use the Hou.la gradient (#667eea → #764ba2) as background
- Place the Hou.la logo on the left
- Add tagline: "Short Links · QR Codes · Social Commerce" on the right
- Keep text readable on both sizes
- No WordPress logo (prohibited by guidelines)

### Icon (required)

| File | Dimensions | Format |
|------|-----------|--------|
| `icon-128x128.png` | 128 × 128 px | PNG |
| `icon-256x256.png` | 256 × 256 px | PNG |

**Design guidelines:**
- Use the Hou.la "H" logo mark
- White icon on the Hou.la gradient background
- Simple, recognizable at small sizes
- No text (too small to read)

### Screenshots (referenced in README.txt)

Screenshots must be numbered sequentially and match the `== Screenshots ==` section in README.txt.

| # | File | What to capture |
|---|------|-----------------|
| 1 | `screenshot-1.png` | **Settings — Connection tab**: Show the connected state with workspace name and email visible. Capture the full settings page with the "Connection" tab active. |
| 2 | `screenshot-2.png` | **Settings — Sync tab**: Show auto-sync enabled, last sync date, product count, and the "Batch Sync" button. |
| 3 | `screenshot-3.png` | **Settings — Orders tab**: Show order count, last order date, and the webhook URL field. |
| 4 | `screenshot-4.png` | **Settings — Debug tab**: Show the debug toggle switch. |
| 5 | `screenshot-5.png` | **Post metabox**: Open a post in the editor, show the Hou.la sidebar metabox with a short link, QR code preview, copy and download buttons, and click statistics. |
| 6 | `screenshot-6.png` | **Product metabox**: Open a WooCommerce product, show the Hou.la metabox with sync status, last sync date, commerce stats (views, clicks, sales, revenue), and Synchronize/Remove buttons. |
| 7 | `screenshot-7.png` | **Shortcode output**: Show a published post with `[wphoula qrcode=1]` rendered — the QR code image appearing in the content. |

**Screenshot guidelines:**
- Capture at 1280px width minimum
- Use a clean WordPress install (Twenty Twenty-Four theme)
- Use realistic demo data (not lorem ipsum)
- Crop to the relevant area (no browser chrome)
- PNG format, optimized file size (< 300 KB each)
- Light theme (most users use the default admin theme)

## How to Create Screenshots

1. Set up a local WordPress with WooCommerce and WP-Houla installed
2. Connect to a Hou.la test account
3. Create sample posts and products, generate links
4. Use browser DevTools screenshot (Ctrl+Shift+P → "Capture screenshot") for clean captures
5. Crop and optimize with any image editor
6. Name files exactly as specified above

## SVN Upload Process

WordPress.org uses SVN, not Git. Assets go in the `/assets/` directory of the SVN repository:

```bash
# After plugin is approved, you'll get SVN access:
svn co https://plugins.svn.wordpress.org/wp-houla/
cd wp-houla/assets/
# Copy all files from .wordpress-org/ here
cp /path/to/.wordpress-org/* .
svn add *
svn ci -m "Add plugin assets (banner, icon, screenshots)"
```

See: https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/
