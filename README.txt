=== WP-Houla - Short Links, QR Codes & Social Commerce ===
Contributors: mikhaelgerbet
Tags: short links, qr code, url shortener, woocommerce, social commerce
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 7.0
WC tested up to: 9.0

Automatic short links and QR codes for every post. Sync your WooCommerce products to your Hou.la bio page and sell from Instagram, TikTok, or X.

== Description ==

WP-Houla connects your WordPress site to [Hou.la](https://hou.la), the marketing platform built for short links, QR codes, and social commerce.

Every post you publish gets a tracked short link and a downloadable QR code. If you run a WooCommerce store, your products sync to a bio page where customers can browse and buy through Stripe - orders flow back into WooCommerce automatically.

= Why WP-Houla? =

Other URL shortener plugins stop at link generation. WP-Houla gives you the full stack: links, QR codes, analytics, product sync, and a complete order pipeline between your bio page and WooCommerce. One plugin, zero configuration after the initial connect.

= Short Links on Every Post =

* Links are generated the moment a post, page, or custom post type is published
* Replaces WordPress's native `get_shortlink()` so themes and sharing tools use your Hou.la link automatically
* Copy button and QR code preview in the editor sidebar
* Per-post click and scan statistics without leaving the admin
* `[wphoula]` shortcode to embed short links or QR code images in your content
* Works on posts, pages, products, and any custom post type with `public => true`

= Social Commerce with WooCommerce =

Turn your social media bio into a storefront:

* **Real-time sync** - products are pushed to Hou.la when created, updated, or deleted
* **Complete product data** - name, prices, sale prices, images, gallery, categories, tags, SKU, weight, dimensions, stock levels, variations, and attributes
* **Batch sync** - push your entire catalog to Hou.la in one click
* **Category filtering** - optionally limit sync to specific WooCommerce product categories
* **Automatic order creation** - purchases made on a Hou.la bio page create WooCommerce orders with full billing, shipping, and line item data
* **Stock management** - inventory is decremented when orders arrive and restocked on refunds
* **Refund handling** - webhook-triggered refund processing with automatic WooCommerce status updates

= Enterprise-Grade Security =

* **OAuth 2.0 + PKCE** - no client secrets stored on your server. Authentication follows the same standard used by Google, GitHub, and Stripe
* **AES-256-CBC** - access tokens and refresh tokens are encrypted at rest using your WordPress salts
* **HMAC-SHA256** - every incoming webhook is verified against a per-site shared secret
* **State parameter** - CSRF protection on the OAuth flow

= Setup in Under a Minute =

1. Install and activate the plugin
2. Navigate to **WooCommerce > Hou.la** and click **Connect to Hou.la**
3. Authorize in the popup - you are redirected back automatically
4. Done. Short links generate on publish, products sync when they change.

= Who Is This For? =

* **Content creators** - share tracked short links on social media with per-link analytics
* **WooCommerce stores** - list products on your Instagram, TikTok, or X bio page and collect orders without a separate checkout
* **Marketers** - print QR codes on flyers, packaging, or posters that link to your WordPress content with scan tracking
* **Agencies** - manage short links across multiple client sites from a single Hou.la workspace

= Developer Friendly =

Customize behavior with WordPress filters:

`add_filter( 'wphoula_allowed_post_types', function( $types ) {
    // Exclude pages from automatic shortlink generation
    return array_diff( $types, array( 'page' ) );
} );`

The plugin follows WordPress coding standards, uses the REST API for webhook endpoints, and stores all data in standard `wp_options` and `wp_postmeta` tables.

= External Service Disclosure =

This plugin connects to the [Hou.la](https://hou.la) API at `api.hou.la` to provide short link generation, QR code creation, product synchronization, and order processing.

**No data is transmitted until you explicitly connect your Hou.la account.** The connection requires your active consent through an OAuth authorization flow.

When connected, the plugin sends:

* Post permalinks and titles (to generate short links)
* WooCommerce product data including name, description, prices, images, categories, tags, SKU, weight, dimensions, stock, variations, and attributes (to sync products)

The plugin receives:

* Short link URLs and QR code URLs
* Order data from purchases made on Hou.la bio pages (to create WooCommerce orders)
* Click, scan, and commerce statistics

Relevant policies:

* [Terms of Service](https://hou.la/conditions-generales-utilisation)
* [Privacy Policy](https://hou.la/politique-de-confidentialite)

= Resources =

* [Hou.la website](https://hou.la)
* [API documentation](https://hou.la/api-documentation)
* [Source code on GitHub](https://github.com/MikhaelGerbet/wp-houla)
* [Report a bug](https://github.com/MikhaelGerbet/wp-houla/issues)
* [Email support](mailto:hello@hou.la)

== Installation ==

= Automatic Installation =

1. In your WordPress admin, go to **Plugins > Add New**
2. Search for **WP-Houla**
3. Click **Install Now**, then **Activate**

= Manual Installation =

1. Download the latest `.zip` from [GitHub Releases](https://github.com/MikhaelGerbet/wp-houla/releases)
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload the zip file and click **Install Now**
4. Activate the plugin

= Connect Your Account =

1. After activation, go to **WooCommerce > Hou.la**
2. Click **Connect to Hou.la**
3. Authorize the application on the Hou.la consent screen
4. You are redirected back to WordPress. The Connection tab shows your workspace name and email.
5. (Optional) Switch to the **Sync** tab and click **Sync All Products Now** to push your full WooCommerce catalog.

Short links are generated automatically for every published post from this point forward.

== Frequently Asked Questions ==

= Does the plugin work without WooCommerce? =

Yes. Short links and QR codes work on all public post types regardless of WooCommerce. The commerce features (product sync, order reception) require WooCommerce 7.0+.

= Do I need a paid Hou.la account? =

No. The free plan includes unlimited short links, QR codes, and bio pages. Commerce features are available on all plans. The difference between plans is the commission rate on sales: 8% on the free plan, 3% on Pro.

= How are payments handled? =

All payment processing happens on Hou.la through Stripe Connect. No credit card data, no PCI scope, no payment gateway configuration on your WordPress site. Completed orders are sent to WooCommerce via a signed webhook.

= What happens to my short links if I deactivate the plugin? =

Short links remain active on Hou.la. Only the local metadata in your WordPress database is removed when you uninstall. Reactivating and reconnecting restores the integration.

= Can I regenerate a short link for a post? =

Yes. Open the post in the editor and click **Regenerate** in the Hou.la metabox. A new link and QR code are created. The previous link continues to work.

= Are drafts and scheduled posts supported? =

Drafts do not get short links. Links are generated when the post status becomes `publish`, `future`, or `private`.

= Which post types get short links? =

All post types registered with `public => true`: posts, pages, WooCommerce products, and any custom post type. Use the `wphoula_allowed_post_types` filter to include or exclude specific types.

= Can I run batch sync more than once? =

Yes. A 10-minute lock prevents concurrent syncs. Once the lock expires or the current sync finishes, you can run it again. Each batch sync creates or updates all published products.

= Does the webhook work behind a CDN or reverse proxy? =

Yes, as long as the `X-Houla-Signature` HTTP header is forwarded to WordPress. Cloudflare, Nginx, Apache, and most hosting providers forward custom headers by default.

= Is the plugin translation-ready? =

Yes. The plugin ships with a complete `.pot` file and a French translation (`.po` and `.mo`). You can translate it into any language using Poedit, Loco Translate, or the WordPress.org translation platform.

= Where is the data stored? =

Plugin settings are stored in `wp_options` under the key `wphoula-options`. Per-post data (short link URL, link ID, QR code URL) is stored in `wp_postmeta`. Product sync data (Hou.la product ID, sync status, sync date) is also in `wp_postmeta`. All data is removed on uninstall.

= Where can I get support? =

Open an issue on the [GitHub repository](https://github.com/MikhaelGerbet/wp-houla/issues) or email [hello@hou.la](mailto:hello@hou.la).

== Screenshots ==

1. **Settings - Connection** - One-click OAuth 2.0 connection to your Hou.la workspace. Displays connected status, workspace name, and account email.
2. **Settings - Sync** - Toggle automatic product sync, view synced product count and last sync date, launch a full batch synchronization.
3. **Settings - Orders** - Order statistics, webhook URL for your Hou.la account, and last order timestamp.
4. **Settings - Debug** - Enable debug logging, set custom API URL for development, view plugin version, PHP version, WooCommerce version, REST API base URL, and webhook secret status.
5. **Post editor metabox** - Hou.la short link with copy button, QR code preview with download, and click/scan statistics for the current post.
6. **Product editor metabox** - Sync status badge, last sync date, commerce statistics (views, clicks, sales, revenue), re-sync and unsync controls.
7. **Shortcode output** - QR code image rendered in post content using the `[wphoula qrcode=1]` shortcode.

== Changelog ==

= 1.3.0 - 2026-03-15 =
* Full WooCommerce sync: API ecommerce endpoints now functional (product CRUD, batch sync, stock updates)
* Ecommerce connection registration: webhook URL and secret are automatically sent to Hou.la on first sync
* Order webhook dispatch: Hou.la pushes order.paid and order.refunded events to WooCommerce when bio page purchases complete
* Category filtering: limit product sync to specific WooCommerce categories via the Sync tab
* Development mode: configurable API URL in the Debug tab for local/staging testing (ngrok, local IP, etc.)
* Connection status tracking: products synced count, orders pushed count, last sync/push timestamps

= 1.2.4 - 2026-02-23 =
* Fix QR code: fetch real PNG image from API instead of storing a redirect URL
* QR code uses the user's default template colors when available
* Fix metabox title icon vertical alignment (icon + text centered)
* Fix spinner positioning (absolute, no longer floats awkwardly)
* Use esc_attr for data URL img src (esc_url strips data: protocol)
* Add download="qrcode.png" attribute to QR download button

= 1.2.3 - 2026-02-23 =
* Fix critical bug: API endpoint was /links (404) instead of /link
* Fix QR code URL: use flashUrl from API response
* Improved error logging with verbose API response details

= 1.2.2 - 2026-02-23 =
* Fix shortlink metabox missing on WooCommerce products
* Add transition_post_status hook for Gutenberg compatibility
* Add woocommerce_new_product / woocommerce_update_product hooks
* Improved debug logging

= 1.2.1 - 2026-02-23 =
* Fix tabs not working when plugin is under WooCommerce Marketing menu
* Default post types now limited to Articles, Pages, and Products (instead of all public types)
* Add `woocommerce-marketing_page_wp-houla` to recognized admin page hooks

= 1.2.0 - 2026-02-23 =
* Dashboard widget with link stats, click count, and top 5 links
* Short link column in Posts and Pages admin lists
* Fix shortlink API call (use correct field name for URL)
* Automatic WordPress source tracking on created links

= 1.0.0 - 2025-01-15 =
* OAuth 2.0 + PKCE authentication with Hou.la
* Automatic short link generation for all public post types
* QR code generation with in-editor preview and download
* `[wphoula]` shortcode supporting link and QR code display modes
* WooCommerce product synchronization (automatic, manual, and batch)
* Webhook endpoint for order reception and refund processing
* HMAC-SHA256 signature verification on all incoming webhooks
* AES-256-CBC encryption of OAuth tokens at rest
* Settings page with Connection, Sync, Orders, and Debug tabs
* Post editor metabox with short link, QR code, and statistics
* Product editor metabox with sync controls and commerce stats
* Complete French translation (fr_FR)

== Upgrade Notice ==

= 1.3.0 =
WooCommerce sync is now fully functional. Products sync to Hou.la bio pages, orders flow back to WooCommerce. Category filtering and development mode added.

= 1.2.4 =
QR codes now display correctly with real PNG images from the API using your preferred template colors.

= 1.2.3 =
Critical fix: short link generation was failing with a 404 error.

= 1.2.2 =
Shortlink metabox now appears on WooCommerce products.

= 1.2.1 =
Fix tabs and default post types selection.

= 1.2.0 =

= 1.0.0 =
First stable release. Install the plugin, connect your Hou.la account, and every published post gets a short link and QR code automatically.
