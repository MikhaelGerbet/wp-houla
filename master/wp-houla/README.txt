=== WP-Houla — Short Links, QR Codes & Social Commerce for WordPress ===
Contributors: mikhaelgerbet
Donate link: https://hou.la/
Tags: short links, qr code, url shortener, woocommerce, social commerce, link in bio, link shortener, analytics, marketing
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 6.0
WC tested up to: 9.0

Turn every WordPress post into a short link with QR code and sync your WooCommerce catalog to your Hou.la bio page for social selling.

== Description ==

**WP-Houla** connects your WordPress site to [Hou.la](https://hou.la), a marketing platform for short links, QR codes, and bio pages with built-in social commerce.

= Short Links & QR Codes =

Every published post, page, or custom post type automatically gets a **Hou.la short link** and a **QR code**:

* Automatic generation on publish — no extra clicks needed
* Replaces WordPress's native shortlink (`get_shortlink()`) with the Hou.la link
* QR code preview and download in the editor sidebar
* Click and scan statistics displayed per post
* `[wphoula]` shortcode to embed links and QR codes in content
* Bulk generation for existing content

= Social Commerce via WooCommerce =

Sell directly from your social media bio page:

* **Real-time sync** — products are pushed to Hou.la on create, update, and delete
* **Full product data** — name, description, prices (regular, sale), images, gallery, categories, tags, SKU, weight, dimensions, stock, variations, and attributes
* **Batch sync** — push your entire catalog in one click
* **Automatic orders** — purchases made on your Hou.la bio page create WooCommerce orders automatically
* **Stock management** — inventory is decremented when orders come in and restocked on refunds
* **Refund handling** — webhook-based refund processing with automatic restocking

= Security =

* **OAuth 2.0 + PKCE** authentication (no secrets stored in plaintext)
* **AES-256-CBC** encryption of access and refresh tokens at rest
* **HMAC-SHA256** verification on every incoming webhook
* **CSRF protection** via OAuth state parameter

= How It Works =

1. Install and activate the plugin
2. Go to **WooCommerce → Hou.la** and click **Connect**
3. Authorize on Hou.la — you're redirected back automatically
4. That's it! Posts get short links, products sync to your bio page

= Use Cases =

* **Bloggers** — share branded short links on social media with analytics
* **E-commerce** — sell WooCommerce products from Instagram, TikTok, or X bio pages
* **Marketers** — generate QR codes for print materials linking to your content
* **Agencies** — manage multiple clients' links with workspace-level analytics

= External Service =

This plugin connects to the [Hou.la](https://hou.la) API (`api.hou.la`) to provide its functionality. **No data is sent until the user explicitly connects their Hou.la account via OAuth.**

**Data sent to Hou.la:**
* Post URLs and titles (for short link generation)
* Product details — name, description, prices, images, categories, tags, SKU, weight, dimensions, stock, variations, attributes (for product sync)

**Data received from Hou.la:**
* Short link URLs and QR code URLs
* Order data (for WooCommerce order creation)
* Click and scan statistics

* [Hou.la Terms of Service](https://hou.la/conditions-generales-utilisation)
* [Hou.la Privacy Policy](https://hou.la/politique-de-confidentialite)

= Links =

* [Hou.la website](https://hou.la)
* [Documentation](https://github.com/MikhaelGerbet/wp-houla)
* [GitHub repository](https://github.com/MikhaelGerbet/wp-houla)
* [Report a bug](https://github.com/MikhaelGerbet/wp-houla/issues)

== Installation ==

= From the WordPress Plugin Directory =

1. Go to **Plugins → Add New** in your WordPress admin
2. Search for **WP-Houla**
3. Click **Install Now** then **Activate**

= From a ZIP file =

1. Download the latest release from [GitHub Releases](https://github.com/MikhaelGerbet/wp-houla/releases)
2. Go to **Plugins → Add New → Upload Plugin**
3. Select the `wp-houla-x.x.x.zip` file
4. Click **Install Now** then **Activate**

= From source =

1. Clone the repository: `git clone https://github.com/MikhaelGerbet/wp-houla.git`
2. Copy `master/wp-houla/` to `wp-content/plugins/`
3. Activate from the **Plugins** menu

= Initial Setup =

1. Go to **WooCommerce → Hou.la**
2. Click **Connect to Hou.la**
3. Authorize the app in the Hou.la window — you'll be redirected automatically
4. (Optional) Go to the **Sync** tab and click **Batch Sync** to push your entire catalog

== Frequently Asked Questions ==

= Does the plugin work without WooCommerce? =

WooCommerce is required for activation. Once activated, short links and QR codes work on all public post types regardless of WooCommerce features.

= Is a paid account required? =

No. The free Hou.la plan gives you unlimited short links and QR codes. The only difference is the commission on sales: 8% (free) vs 3% (Pro).

= How are payments processed? =

Payments are handled entirely by Hou.la via Stripe Connect. No payment data ever touches your WordPress site. Stripe ensures PCI DSS compliance.

= Do short links survive plugin deactivation? =

Yes. Links on Hou.la remain active. Only the local WordPress metadata is removed on uninstall.

= Can I regenerate a short link? =

Yes. Click "Regenerate" in the Hou.la metabox. A new link is created; the previous one remains active.

= Do drafts get short links? =

No. Links are generated only for published, scheduled, or private content.

= What post types are supported? =

All public post types: posts, pages, and any CPT with `public => true`. Customize with the `wphoula_allowed_post_types` filter:

`add_filter( 'wphoula_allowed_post_types', function( $types ) {
    return array_diff( $types, array( 'page' ) );
} );`

= Can I run the batch sync multiple times? =

A 10-minute lock prevents simultaneous syncs. Wait for the current sync to finish or for the lock to expire before starting another.

= Do webhooks work behind Cloudflare or Nginx? =

Yes, as long as the `X-Houla-Signature` header is forwarded. Most CDNs and reverse proxies do this by default.

= Where can I find the API documentation? =

Full API documentation is available at [hou.la/api-documentation](https://hou.la/api-documentation).

== Screenshots ==

1. **Settings — Connection tab** — Connect to your Hou.la account with one click via OAuth 2.0
2. **Settings — Sync tab** — Configure automatic sync and launch batch synchronization
3. **Settings — Orders tab** — View order stats and webhook endpoint
4. **Settings — Debug tab** — Enable API call logging for troubleshooting
5. **Post metabox** — Short link, QR code preview, download, and click statistics
6. **Product metabox** — Sync status, commerce stats, and shortlink for WooCommerce products
7. **Shortcode output** — QR code and short link rendered in post content

== Changelog ==

= 1.0.0 =
* Initial release
* OAuth 2.0 + PKCE authentication with Hou.la
* Automatic short link generation for all public post types
* QR code generation with editor preview and download
* `[wphoula]` shortcode with link and QR code modes
* WooCommerce product synchronization (auto, manual, batch)
* Webhook endpoint for order reception and refunds
* HMAC-SHA256 webhook signature verification
* AES-256-CBC token encryption at rest
* Admin settings with 4 tabs (Connection, Sync, Orders, Debug)
* Post metabox with link, QR code, and statistics
* Product metabox with sync controls and commerce stats
* Translation-ready with .pot file

== Upgrade Notice ==

= 1.0.0 =
Initial release — install and connect to Hou.la to start generating short links, QR codes, and syncing your WooCommerce products.
