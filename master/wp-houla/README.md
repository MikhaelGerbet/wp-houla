# WP-Houla

WordPress plugin that connects WooCommerce to [Hou.la](https://hou.la) for social commerce. Syncs your product catalog to your bio page and receives orders placed through Hou.la Pay (Stripe).

## Requirements

- WordPress 5.8+
- WooCommerce 7.0+
- PHP 7.4+
- A Hou.la account

## Architecture

The plugin follows the WordPress boilerplate pattern (Loader + hooks):

```
wp-houla/
  wp-houla.php                    # Entry point, constants, bootstrap
  uninstall.php                   # Cleanup on uninstall
  README.txt                      # WordPress.org readme
  includes/
    class-wp-houla.php            # Core class, loads deps, registers hooks
    class-wp-houla-loader.php     # Hook registration (actions + filters)
    class-wp-houla-i18n.php       # Text domain loader
    class-wp-houla-options.php    # Options CRUD with AES-256-CBC encryption
    class-wp-houla-activator.php  # Activation (PHP check, webhook secret)
    class-wp-houla-deactivator.php# Deactivation (transient cleanup)
    class-wp-houla-auth.php       # OAuth 2.0 + PKCE flow
    class-wp-houla-api.php        # HTTP wrapper for Hou.la API
    class-wp-houla-shortlink.php  # Short link generation (all post types)
    class-wp-houla-post-metabox.php # Post metabox (shortlink + QR + stats)
    class-wp-houla-sync.php       # Product sync (WooCommerce -> Hou.la)
    class-wp-houla-orders.php     # Order creation (Hou.la webhook -> WC)
    class-wp-houla-webhook.php    # REST endpoint with HMAC verification
    class-wp-houla-metabox.php    # Product edit metabox (sync + stats)
  admin/
    class-wp-houla-admin.php      # Admin menu, settings, enqueue
    css/wp-houla-admin.css        # Admin styles
    js/wp-houla-admin.js          # Admin JS (tabs, AJAX)
    images/houla-icon.svg         # Logo
    partials/
      settings-page.php           # Settings page template (4 tabs)
      metabox-product.php         # Product metabox template
  languages/
    wp-houla.pot                  # Translation template
```

## Features

### Authentication
- OAuth 2.0 with PKCE (code_challenge_method=S256)
- Code verifier stored in WP transient (10 min TTL)
- CSRF protection via state parameter
- Automatic token refresh before expiry
- Tokens encrypted at rest (AES-256-CBC with SECURE_AUTH_KEY)

### Short links (like Bitly)
- Automatic Hou.la short link generation on `save_post` for all public post types
- Overrides `get_shortlink()` so WordPress core returns the Hou.la link
- Stored in `_wphoula_shortlink` post meta
- QR code generated alongside each short link (`_wphoula_qrcode`)
- Click stats displayed in the post metabox (total, today, QR scans)
- `[wphoula]` shortcode for embedding links or QR codes in content
- Manual generate/regenerate from the metabox
- Copy-to-clipboard button

### Product sync (WooCommerce)
- Hooks: `woocommerce_new_product`, `woocommerce_update_product`, `woocommerce_before_delete_product`, `woocommerce_trash_product`
- Stock hooks: `woocommerce_product_set_stock`, `woocommerce_variation_set_stock`
- Syncs: name, description, prices, images, gallery, categories, tags, SKU, dimensions, variations, attributes
- Batch sync with lock (10 min transient) to prevent concurrency
- Guard against recursive hooks (`$syncing` static flag)

### Order reception
- REST endpoint: `POST /wp-json/wp-houla/v1/webhook`
- HMAC-SHA256 signature verification
- Creates WooCommerce orders via `wc_create_order()`
- Maps line items by `external_id` (WooCommerce product ID)
- Decrements stock automatically
- Handles billing/shipping address
- Handles refunds (`order.refunded` event)
- Duplicate order detection via `_houla_order_id` meta

### Admin
- Settings page under WooCommerce menu with 4 tabs: Connection, Sync, Orders, Debug
- Product metabox: sync status, last sync time, stats (views, clicks, sales, revenue)
- AJAX: disconnect, batch sync, save settings, sync/unsync product, fetch stats

## Post meta keys

| Key | Scope | Description |
|-----|-------|-------------|
| `_wphoula_shortlink` | any post | Hou.la short link URL |
| `_wphoula_link_id` | any post | Hou.la link ID |
| `_wphoula_qrcode` | any post | Hou.la QR code image URL |
| `_wphoula_product_id` | product | Hou.la internal product ID |
| `_wphoula_synced` | product | Sync flag (1 or empty) |
| `_wphoula_sync_at` | product | Last sync datetime |
| `_houla_order_id` | order | Hou.la order ID |
| `_houla_transaction_id` | order | Stripe transaction ID |

## wp_options keys

| Key | Description |
|-----|-------------|
| `wphoula_options` | All plugin options (serialized array) |
| `wphoula_authorized` | Connection status flag |

## Hooks

The plugin registers these WordPress hooks:

| Hook | Type | Handler |
|------|------|---------|
| `admin_enqueue_scripts` | action | Admin::enqueue_styles, enqueue_scripts |
| `admin_menu` | action | Admin::add_menu_page |
| `admin_notices` | action | Admin::display_settings_notice |
| `plugin_action_links_{basename}` | filter | Admin::add_action_links |
| `save_post` | action | Shortlink::on_save_post |
| `pre_get_shortlink` | filter | Shortlink::filter_get_shortlink |
| `init` | action | Shortlink::register_shortcode |
| `add_meta_boxes` | action | PostMetabox::register_metabox |
| `add_meta_boxes` | action | Metabox::register_metabox |
| `woocommerce_new_product` | action | Sync::on_product_created |
| `woocommerce_update_product` | action | Sync::on_product_updated |
| `woocommerce_before_delete_product` | action | Sync::on_product_deleted |
| `woocommerce_trash_product` | action | Sync::on_product_deleted |
| `woocommerce_product_set_stock` | action | Sync::on_stock_changed |
| `woocommerce_variation_set_stock` | action | Sync::on_stock_changed |
| `rest_api_init` | action | Webhook::register_routes, Auth::register REST callback |

## Commission model

| Plan | Commission |
|------|-----------|
| Free | 8% |
| Pro | 3% |

Commission is handled server-side by Hou.la via Stripe Connect. The plugin does not process payments directly.

## Development

```bash
# Run WordPress coding standards check
composer run phpcs

# Run tests
composer run phpunit
```

## License

GPLv2 or later.
