<?php
/**
 * HTTP wrapper for the Hou.la API.
 *
 * All requests go through this class, which handles:
 * - Bearer token injection
 * - Automatic token refresh on 401
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
        $token = $this->auth->get_access_token();

        if ( false === $token ) {
            return new WP_Error( 'wphoula_not_connected', __( 'Not connected to Hou.la.', 'wp-houla' ) );
        }

        $api_url = function_exists( 'wphoula_get_api_url' ) ? wphoula_get_api_url() : WPHOULA_API_URL;
        $url = $api_url . '/api' . $endpoint;

        if ( ! empty( $query ) ) {
            $url = add_query_arg( $query, $url );
        }

        $args = array(
            'method'  => $method,
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'User-Agent'    => 'wp-houla/' . WPHOULA_VERSION,
            ),
        );

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

        // Handle 401 - attempt token refresh
        if ( 401 === $status_code && $attempt < $this->max_retries ) {
            $this->log( 'Received 401, attempting token refresh...' );
            if ( $this->auth->refresh_token() ) {
                return $this->request( $method, $endpoint, $body, $query, $attempt + 1 );
            }
            return new WP_Error( 'wphoula_unauthorized', __( 'Authentication failed. Please reconnect.', 'wp-houla' ) );
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
