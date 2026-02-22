<?php
/**
 * Admin settings page template.
 *
 * Available variables:
 *   $options - Wp_Houla_Options instance
 *   $auth    - Wp_Houla_Auth instance
 *
 * @since      1.0.0
 * @package    Wp_Houla
 * @subpackage Wp_Houla/admin/partials
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_connected   = $auth->is_connected();
$workspace_name = $options->get( 'workspace_name' );
$user_email     = $options->get( 'user_email' );
$auto_sync      = $options->get( 'auto_sync' );
$sync_on_publish = $options->get( 'sync_on_publish' );
$debug          = $options->get( 'debug' );
$products_synced = $options->get( 'products_synced' );
$last_full_sync  = $options->get( 'last_full_sync' );
$orders_received = $options->get( 'orders_received' );
$last_order_at   = $options->get( 'last_order_at' );
?>

<div class="wrap wphoula-settings">
    <h1>
        <img src="<?php echo esc_url( WPHOULA_URL . 'admin/images/houla-icon.svg' ); ?>" alt="Hou.la" class="wphoula-logo" width="28" height="28">
        <?php esc_html_e( 'Hou.la Settings', 'wp-houla' ); ?>
    </h1>

    <?php if ( isset( $_GET['wphoula_connected'] ) && $_GET['wphoula_connected'] === '1' ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e( 'Successfully connected to Hou.la!', 'wp-houla' ); ?></p>
        </div>
    <?php endif; ?>

    <!-- ============================================================= -->
    <!-- Tab navigation                                                 -->
    <!-- ============================================================= -->
    <nav class="nav-tab-wrapper wphoula-tabs">
        <a href="#tab-connection" class="nav-tab nav-tab-active" data-tab="connection">
            <?php esc_html_e( 'Connection', 'wp-houla' ); ?>
        </a>
        <a href="#tab-sync" class="nav-tab" data-tab="sync">
            <?php esc_html_e( 'Sync', 'wp-houla' ); ?>
        </a>
        <a href="#tab-orders" class="nav-tab" data-tab="orders">
            <?php esc_html_e( 'Orders', 'wp-houla' ); ?>
        </a>
        <a href="#tab-debug" class="nav-tab" data-tab="debug">
            <?php esc_html_e( 'Debug', 'wp-houla' ); ?>
        </a>
    </nav>

    <!-- ============================================================= -->
    <!-- TAB: Connection                                                -->
    <!-- ============================================================= -->
    <div class="wphoula-tab-content" id="tab-connection">
        <div class="wphoula-card">
            <h2><?php esc_html_e( 'Hou.la Account', 'wp-houla' ); ?></h2>

            <?php if ( $is_connected ) : ?>
                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Status', 'wp-houla' ); ?></th>
                        <td>
                            <span class="wphoula-status wphoula-status--connected">
                                <?php esc_html_e( 'Connected', 'wp-houla' ); ?>
                            </span>
                        </td>
                    </tr>
                    <?php if ( $workspace_name ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Workspace', 'wp-houla' ); ?></th>
                        <td><?php echo esc_html( $workspace_name ); ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ( $user_email ) : ?>
                    <tr>
                        <th><?php esc_html_e( 'Email', 'wp-houla' ); ?></th>
                        <td><?php echo esc_html( $user_email ); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>

                <p>
                    <button type="button" class="button button-secondary" id="wphoula-disconnect">
                        <?php esc_html_e( 'Disconnect', 'wp-houla' ); ?>
                    </button>
                </p>

            <?php else : ?>
                <p><?php esc_html_e( 'Connect your Hou.la account to sync WooCommerce products and receive orders.', 'wp-houla' ); ?></p>
                <p>
                    <a href="<?php echo esc_url( $auth->get_authorization_url() ); ?>" class="button button-primary button-hero">
                        <?php esc_html_e( 'Connect to Hou.la', 'wp-houla' ); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================= -->
    <!-- TAB: Sync                                                      -->
    <!-- ============================================================= -->
    <div class="wphoula-tab-content" id="tab-sync" style="display:none;">
        <div class="wphoula-card">
            <h2><?php esc_html_e( 'Product Sync', 'wp-houla' ); ?></h2>

            <?php if ( ! $is_connected ) : ?>
                <p class="description"><?php esc_html_e( 'Connect your account first to configure sync options.', 'wp-houla' ); ?></p>
            <?php else : ?>

                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Auto-sync', 'wp-houla' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" id="wphoula-auto-sync" <?php checked( $auto_sync ); ?>>
                                <?php esc_html_e( 'Automatically sync product changes to Hou.la', 'wp-houla' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Sync on publish', 'wp-houla' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" id="wphoula-sync-on-publish" <?php checked( $sync_on_publish ); ?>>
                                <?php esc_html_e( 'Sync new products when they are published', 'wp-houla' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Products synced', 'wp-houla' ); ?></th>
                        <td><strong><?php echo esc_html( $products_synced ?: '0' ); ?></strong></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Last full sync', 'wp-houla' ); ?></th>
                        <td><?php echo $last_full_sync ? esc_html( $last_full_sync ) : esc_html__( 'Never', 'wp-houla' ); ?></td>
                    </tr>
                </table>

                <p>
                    <button type="button" class="button button-primary" id="wphoula-save-settings">
                        <?php esc_html_e( 'Save Settings', 'wp-houla' ); ?>
                    </button>

                    <button type="button" class="button button-secondary" id="wphoula-batch-sync">
                        <?php esc_html_e( 'Sync All Products Now', 'wp-houla' ); ?>
                    </button>
                    <span id="wphoula-sync-status" class="wphoula-spinner" style="display:none;"></span>
                </p>

            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================= -->
    <!-- TAB: Orders                                                    -->
    <!-- ============================================================= -->
    <div class="wphoula-tab-content" id="tab-orders" style="display:none;">
        <div class="wphoula-card">
            <h2><?php esc_html_e( 'Orders via Hou.la', 'wp-houla' ); ?></h2>

            <?php if ( ! $is_connected ) : ?>
                <p class="description"><?php esc_html_e( 'Connect your account first.', 'wp-houla' ); ?></p>
            <?php else : ?>

                <table class="form-table">
                    <tr>
                        <th><?php esc_html_e( 'Webhook URL', 'wp-houla' ); ?></th>
                        <td>
                            <code><?php echo esc_html( rest_url( 'wp-houla/v1/webhook' ) ); ?></code>
                            <p class="description"><?php esc_html_e( 'This URL is automatically registered with Hou.la.', 'wp-houla' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Orders received', 'wp-houla' ); ?></th>
                        <td><strong><?php echo esc_html( $orders_received ?: '0' ); ?></strong></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Last order', 'wp-houla' ); ?></th>
                        <td><?php echo $last_order_at ? esc_html( $last_order_at ) : esc_html__( 'None yet', 'wp-houla' ); ?></td>
                    </tr>
                </table>

                <p class="description">
                    <?php esc_html_e( 'Orders placed through your Hou.la bio page appear in WooCommerce > Orders with the payment method "Hou.la Pay (Stripe)".', 'wp-houla' ); ?>
                </p>

            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================================= -->
    <!-- TAB: Debug                                                     -->
    <!-- ============================================================= -->
    <div class="wphoula-tab-content" id="tab-debug" style="display:none;">
        <div class="wphoula-card">
            <h2><?php esc_html_e( 'Debug Information', 'wp-houla' ); ?></h2>

            <table class="form-table">
                <tr>
                    <th><?php esc_html_e( 'Debug logging', 'wp-houla' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" id="wphoula-debug" <?php checked( $debug ); ?>>
                            <?php esc_html_e( 'Enable debug logging (writes to wp-content/debug.log)', 'wp-houla' ); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Plugin version', 'wp-houla' ); ?></th>
                    <td><?php echo esc_html( WPHOULA_VERSION ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'PHP version', 'wp-houla' ); ?></th>
                    <td><?php echo esc_html( PHP_VERSION ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'WordPress version', 'wp-houla' ); ?></th>
                    <td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'WooCommerce version', 'wp-houla' ); ?></th>
                    <td><?php echo defined( 'WC_VERSION' ) ? esc_html( WC_VERSION ) : '---'; ?></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'REST API base', 'wp-houla' ); ?></th>
                    <td><code><?php echo esc_html( rest_url( 'wp-houla/v1/' ) ); ?></code></td>
                </tr>
                <tr>
                    <th><?php esc_html_e( 'Webhook secret', 'wp-houla' ); ?></th>
                    <td>
                        <?php
                        $secret = $options->get( 'webhook_secret' );
                        echo $secret
                            ? '<code>' . esc_html( substr( $secret, 0, 8 ) ) . '...</code>'
                            : esc_html__( 'Not generated', 'wp-houla' );
                        ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>
