<?php
/**
 * Metabox for all public post types (pages, posts, CPTs).
 *
 * Shows:
 * - Hou.la short link (copyable)
 * - QR code image
 * - Click stats (loaded via AJAX)
 * - Regenerate button
 *
 * This is separate from the WooCommerce product metabox
 * (class-wp-houla-metabox.php) which handles product sync.
 *
 * @since      1.0.0
 * @package    Wp_Houla
 * @subpackage Wp_Houla/includes
 */

class Wp_Houla_Post_Metabox {

    /** @var Wp_Houla_Api */
    private $api;

    /** @var Wp_Houla_Auth */
    private $auth;

    public function __construct() {
        $this->api  = new Wp_Houla_Api();
        $this->auth = new Wp_Houla_Auth();
    }

    // =====================================================================
    // Registration
    // =====================================================================

    /**
     * Register the Hou.la metabox on all public post types.
     */
    public function register_metabox() {
        if ( ! $this->auth->is_connected() ) {
            return;
        }

        $post_types = apply_filters( 'wphoula_allowed_post_types', get_post_types( array( 'public' => true ) ) );

        foreach ( $post_types as $type ) {
            // Skip 'product' - handled by Wp_Houla_Metabox (product-specific)
            if ( 'product' === $type && class_exists( 'WooCommerce' ) ) {
                continue;
            }

            add_meta_box(
                'wphoula_post_metabox',
                __( 'Hou.la', 'wp-houla' ),
                array( $this, 'render' ),
                $type,
                'side',
                'default'
            );
        }
    }

    // =====================================================================
    // Render
    // =====================================================================

    /**
     * Render the metabox content.
     *
     * @param WP_Post $post Current post object.
     */
    public function render( $post ) {
        $post_id   = $post->ID;
        $shortlink = get_post_meta( $post_id, '_wphoula_shortlink', true );
        $link_id   = get_post_meta( $post_id, '_wphoula_link_id', true );
        $qrcode    = get_post_meta( $post_id, '_wphoula_qrcode', true );
        $nonce     = wp_create_nonce( 'wphoula_post_metabox' );

        ?>
        <div class="wphoula-post-metabox" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">

            <?php if ( $shortlink ) : ?>

                <!-- Short link -->
                <div class="wphoula-shortlink-row">
                    <input type="text" readonly value="<?php echo esc_attr( $shortlink ); ?>"
                           class="widefat wphoula-shortlink-input" id="wphoula-shortlink-input">
                    <button type="button" class="button button-small" id="wphoula-copy-link"
                            title="<?php esc_attr_e( 'Copy', 'wp-houla' ); ?>">
                        <span class="dashicons dashicons-clipboard"></span>
                    </button>
                </div>

                <!-- QR Code -->
                <?php if ( $qrcode ) : ?>
                    <div class="wphoula-qrcode-preview">
                        <img src="<?php echo esc_url( $qrcode ); ?>"
                             alt="QR Code" width="140" height="140"
                             class="wphoula-qrcode-img">
                        <a href="<?php echo esc_url( $qrcode ); ?>" download class="button button-small">
                            <span class="dashicons dashicons-download"></span>
                            <?php esc_html_e( 'Download QR', 'wp-houla' ); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <!-- Click stats (loaded via AJAX) -->
                <div class="wphoula-post-stats" id="wphoula-post-stats" style="display:none;">
                    <table class="wphoula-stats-table">
                        <tr>
                            <td><?php esc_html_e( 'Total clicks', 'wp-houla' ); ?></td>
                            <td class="wphoula-stat-value" id="wphoula-post-stat-clicks">-</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'Today', 'wp-houla' ); ?></td>
                            <td class="wphoula-stat-value" id="wphoula-post-stat-today">-</td>
                        </tr>
                        <tr>
                            <td><?php esc_html_e( 'QR scans', 'wp-houla' ); ?></td>
                            <td class="wphoula-stat-value" id="wphoula-post-stat-qr">-</td>
                        </tr>
                    </table>
                </div>

                <!-- Actions -->
                <div class="wphoula-metabox__actions">
                    <button type="button" class="button button-small" id="wphoula-regenerate-link">
                        <span class="dashicons dashicons-update"></span>
                        <?php esc_html_e( 'Regenerate', 'wp-houla' ); ?>
                    </button>
                </div>

            <?php elseif ( 'publish' === $post->post_status ) : ?>

                <!-- Not yet generated -->
                <p class="description">
                    <?php esc_html_e( 'No Hou.la short link yet.', 'wp-houla' ); ?>
                </p>
                <div class="wphoula-metabox__actions">
                    <button type="button" class="button button-primary button-small" id="wphoula-generate-link">
                        <span class="dashicons dashicons-admin-links"></span>
                        <?php esc_html_e( 'Generate short link', 'wp-houla' ); ?>
                    </button>
                </div>

            <?php else : ?>

                <p class="description">
                    <?php esc_html_e( 'A Hou.la short link will be generated when this post is published.', 'wp-houla' ); ?>
                </p>

            <?php endif; ?>

            <span class="spinner wphoula-metabox__spinner" id="wphoula-post-spinner"></span>
        </div>
        <?php
    }

    // =====================================================================
    // AJAX handlers
    // =====================================================================

    /**
     * AJAX: Generate or regenerate shortlink for a post.
     */
    public function ajax_generate_shortlink() {
        check_ajax_referer( 'wphoula_post_metabox', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'wp-houla' ) );
        }

        $post_id = absint( $_POST['post_id'] ?? 0 );
        $force   = ! empty( $_POST['force'] );

        if ( ! $post_id ) {
            wp_send_json_error( __( 'Invalid post ID.', 'wp-houla' ) );
        }

        $shortlink_gen = new Wp_Houla_Shortlink();
        $shortlink     = $shortlink_gen->generate_shortlink( $post_id, $force );

        if ( ! $shortlink ) {
            wp_send_json_error( __( 'Failed to generate short link.', 'wp-houla' ) );
        }

        wp_send_json_success( array(
            'shortlink' => $shortlink,
            'qrcode'    => get_post_meta( $post_id, '_wphoula_qrcode', true ),
        ) );
    }

    /**
     * AJAX: Fetch click stats for a post's short link.
     */
    public function ajax_get_link_stats() {
        check_ajax_referer( 'wphoula_post_metabox', 'nonce' );

        $post_id = absint( $_POST['post_id'] ?? 0 );
        $link_id = get_post_meta( $post_id, '_wphoula_link_id', true );

        if ( empty( $link_id ) ) {
            wp_send_json_error( __( 'No link found.', 'wp-houla' ) );
        }

        $stats = $this->api->get( '/links/' . $link_id . '/stats' );

        if ( is_wp_error( $stats ) ) {
            wp_send_json_error( $stats->get_error_message() );
        }

        wp_send_json_success( $stats );
    }
}
