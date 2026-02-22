=== WP-Houla ===
Contributors: houlateam
Tags: woocommerce, link-in-bio, ecommerce, social-commerce, short-links
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Connect your WooCommerce store to Hou.la. Auto-sync products to your bio page and receive orders via Hou.la Pay.

== Description ==

WP-Houla connects your WooCommerce store to [Hou.la](https://hou.la), the link-in-bio platform with built-in commerce. Your products are automatically synced and displayed on your Hou.la bio page. When a customer buys through Hou.la Pay (powered by Stripe), the order flows back into WooCommerce.

= Features =

* **Short links** - Automatic Hou.la short link for every published post, page, and custom post type
* **QR codes** - Each short link gets a QR code, downloadable from the post metabox
* **Click stats** - See total clicks, today's clicks, and QR scans right in the editor
* **WordPress shortlink** - Overrides `get_shortlink()` so the WordPress "Get Shortlink" button returns the Hou.la link
* **[wphoula] shortcode** - Embed short links or QR codes in your content
* **OAuth 2.0 + PKCE** - Secure authentication with your Hou.la account
* **Auto-sync products** - New, updated, and deleted WooCommerce products are pushed to Hou.la in real time
* **Batch sync** - One-click full catalog synchronization
* **Receive orders** - Orders placed via Hou.la Pay appear in WooCommerce with full details
* **Stock management** - Stock is decremented when an order arrives; restocked on refund
* **Product metabox** - See Hou.la sync status, stats, short link and QR code on each product
* **Webhook security** - All incoming webhooks are verified via HMAC-SHA256 signature
* **Token encryption** - Access tokens are encrypted at rest using AES-256-CBC

= Requirements =

* WordPress 5.8 or later
* WooCommerce 7.0 or later
* PHP 7.4 or later
* A Hou.la account (free or Pro)

== Installation ==

1. Upload the `wp-houla` folder to `/wp-content/plugins/`
2. Activate the plugin through the "Plugins" menu in WordPress
3. Go to WooCommerce > Hou.la
4. Click "Connect to Hou.la" and authorize your account
5. Enable auto-sync to start pushing products

== Frequently Asked Questions ==

= Do I need a paid Hou.la plan? =

No. The free plan supports product sync and orders. The Pro plan reduces the commission from 8% to 3%.

= How are orders processed? =

When a customer completes a purchase on your bio page, Stripe processes the payment and Hou.la sends a webhook to your site. WP-Houla creates a WooCommerce order with status "processing" and payment method "Hou.la Pay (Stripe)".

= Is my data secure? =

Yes. OAuth tokens are encrypted using AES-256-CBC with your site's SECURE_AUTH_KEY. Webhooks are verified using HMAC-SHA256 signatures. No sensitive data is stored in plain text.

= What data is synced? =

Product name, description, price (regular + sale), images, categories, tags, SKU, stock status, variations, and dimensions.

== Screenshots ==

1. Settings page - Connection tab
2. Settings page - Sync tab with batch sync
3. Product metabox with sync status and stats
4. Order received via Hou.la Pay in WooCommerce

== Changelog ==

= 1.0.0 =
* Initial release
* Automatic short link generation for all public post types
* QR code generation with download from metabox
* Click stats in post editor (total, today, QR scans)
* [wphoula] shortcode for links and QR codes
* Overrides WordPress get_shortlink() for Hou.la links
* OAuth 2.0 + PKCE authentication
* Product auto-sync (create, update, delete, stock changes)
* Batch sync for full catalog
* Order creation from Hou.la webhooks
* Refund handling
* Product metabox with Hou.la stats + shortlink + QR
* HMAC-SHA256 webhook verification
* AES-256-CBC token encryption

== Upgrade Notice ==

= 1.0.0 =
Initial release. Install, connect your Hou.la account, and start selling through your bio page.
