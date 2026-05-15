<?php
/**
 * HTTP wrapper for the Hou.la API.
 *
 * All requests go through this class, which handles:
 * - API key authentication (primary, persistent)
 * - Bearer token fallback (for legacy/migration)
 * - Automatic API key revocation detection
 * - JSON serialization/deserialization
 * - Error logging
 *
 * @since      1.0.0
 * @package    Wp_Houla
 * @subpackage Wp_Houla/includes
 */

class Wp_Houla_Api {

    /** @var Wp_Houla_Auth */
    private $auth;

    /** @var int Maximum retry attempts on 401 */
    private $max_retries = 1;

    public function __construct() {
        $this->auth = new Wp_Houla_Auth();
    }

    // =====================================================================
    // Public methods
    // =====================================================================

    /**
     * Perform a GET request to the Hou.la API.
     *
     * @param string $endpoint API path (e.g. "/ecommerce/products/123/stats").
     * @param array  $query    Query string parameters.
     * @return array|WP_Error  Decoded JSON body or WP_Error.
     */
    public function get( $endpoint, array $query = array() ) {
        return $this->request( 'GET', $endpoint, array(), $query );
    }

    /**
     * Perform a POST request to the Hou.la API.
     *
     * @param string $endpoint API path.
     * @param array  $body     Request body (will be JSON-encoded).
     * @return array|WP_Error  Decoded JSON body or WP_Error.
     */
    public function post( $endpoint, array $body = array() ) {
        return $this->request( 'POST', $endpoint, $body );
    }

    /**
     * Perform a PATCH request to the Hou.la API.
     *
     * @param string $endpoint API path.
     * @param array  $body     Request body (will be JSON-encoded).
     * @return array|WP_Error  Decoded JSON body or WP_Error.
     */
    public function patch( $endpoint, array $body = array() ) {
        return $this->request( 'PATCH', $endpoint, $body );
    }

    /**
     * Perform a DELETE request to the Hou.la API.
     *
     * @param string $endpoint API path.
     * @return array|WP_Error  Decoded JSON body or WP_Error.
     */
    public function delete( $endpoint ) {
        return $this->request( 'DELETE', $endpoint );
    }

    // =====================================================================
    // Core request handler
    // =====================================================================

    /**
     * Resolve the authentication headers.
     *
     * Priority:
     * 1. API key (X-Api-Key header) -- persistent, survives token expiry
     * 2. OAuth access token (Bearer header) -- short-lived, needs refresh
     *
     * @return array|false  Auth headers array, or false if not authenticated.
     */
    private function resolve_auth_headers() {
        // 1. Try API key first (persistent)
        $api_key = $this->auth->get_api_key();
        if ( $api_key ) {
            return array(
                'X-Api-Key' => $api_key,
            );
        }

        // 2. Fallback to OAuth Bearer token
        $token = $this->auth->get_access_token();
        if ( $token ) {
            return array(
                'Authorization' => 'Bearer ' . $token,
            );
        }

        return false;
    }

    /**
     * Execute an HTTP request to the Hou.la API.
     *
     * @param string $method   HTTP method (GET, POST, PATCH, DELETE).
     * @param string $endpoint API path.
     * @param array  $body     Request body for POST/PATCH.
     * @param array  $query    Query string parameters for GET.
     * @param int    $attempt  Current retry attempt.
     * @return array|WP_Error
     */
    private function request( $method, $endpoint, array $body = array(), array $query = array(), $attempt = 0 ) {
        $auth_headers = $this->resolve_auth_headers();

        if ( false === $auth_headers ) {
            return new WP_Error( 'wphoula_not_connected', __( 'Not connected to Hou.la.', 'wp-houla' ) );
        }

        $api_url = function_exists( 'wphoula_get_api_url' ) ? wphoula_get_api_url() : WPHOULA_API_URL;
        $url = $api_url . '/api' . $endpoint;

        if ( ! empty( $query ) ) {
            $url = add_query_arg( $query, $url );
        }

        $args = array(
            'method'  => $method,
            'timeout' => strpos( $endpoint, '/batch' ) !== false ? 120 : 30,
            'headers' => array_merge(
                $auth_headers,
                array(
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'User-Agent'    => 'wp-houla/' . WPHOULA_VERSION,
                    'ngrok-skip-browser-warning' => 'true',
                )
            ),
        );

        // Disable SSL verification for ngrok/localhost dev tunnels
        if ( strpos( $url, 'ngrok' ) !== false || strpos( $url, 'localhost' ) !== false || strpos( $url, '127.0.0.1' ) !== false ) {
            $args['sslverify'] = false;
        }

        if ( in_array( $method, array( 'POST', 'PATCH' ), true ) && ! empty( $body ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            $this->log( $method . ' ' . $endpoint . ' failed: ' . $response->get_error_message() );
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code( $response );
        $body_raw    = wp_remote_retrieve_body( $response );
        $body_json   = json_decode( $body_raw, true );

        // Handle 401 -- API key may be revoked or token expired
        if ( 401 === $status_code && $attempt < $this->max_retries ) {
            $this->log( 'Received 401 on ' . $endpoint );

            // If we were using an API key, it may have been revoked
            if ( isset( $auth_headers['X-Api-Key'] ) ) {
                $this->log( 'API key rejected, clearing stored key and trying token refresh...' );
                // Clear the revoked key
                $options = new Wp_Houla_Options();
                $options->set( 'api_key', '' );
            }

            // Attempt OAuth token refresh
            if ( $this->auth->refresh_token() ) {
                // Re-provision a fresh API key so future calls use it (not the JWT)
                $this->auth->provision_api_key();
                return $this->request( $method, $endpoint, $body, $query, $attempt + 1 );
            }

            // Both auth methods failed -- mark as disconnected
            $this->log( 'All authentication methods failed. Marking as disconnected.' );
            $this->auth->disconnect();
            return new WP_Error( 'wphoula_unauthorized', __( 'Echec de l\'authentification. Veuillez vous reconnecter.', 'wp-houla' ) );
        }

        // Handle errors
        if ( $status_code >= 400 ) {
            $msg = isset( $body_json['message'] ) ? $body_json['message'] : 'HTTP ' . $status_code;
            $this->log( $method . ' ' . $endpoint . ' error: ' . $msg );
            return new WP_Error( 'wphoula_api_error', $msg, array( 'status' => $status_code ) );
        }

        return $body_json ?: array();
    }

    // =====================================================================
    // Logging
    // =====================================================================

    /**
     * Log a debug message.
     *
     * @param string $message
     */
    private function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[WP-Houla API] ' . $message );
        }
    }
}
