<?php
/**
 * WooCommerce order creation from Hou.la webhook payloads.
 *
 * When a buyer completes a purchase through a Hou.la bio page,
 * Hou.la sends an order.paid webhook. This class:
 * - Creates a WooCommerce order via wc_create_order()
 * - Maps products by external_id (WooCommerce product ID)
 * - Sets billing/shipping from the webhook payload
 * - Marks payment as "houla_pay"
 * - Decrements stock
 * - Stores Hou.la metadata (_houla_order_id, _houla_transaction_id)
 *
 * @since      1.0.0
 * @package    Wp_Houla
 * @subpackage Wp_Houla/includes
 */

class Wp_Houla_Orders {

    /** @var Wp_Houla_Options */
    private $options;

    public function __construct() {
        $this->options = new Wp_Houla_Options();
    }

    // =====================================================================
    // Order creation
    // =====================================================================

    /**
     * Create a WooCommerce order from a Hou.la order.paid webhook.
     *
     * Expected $data structure:
     * {
     *   "houla_order_id":    "ord_abc123",
     *   "transaction_id":    "pi_xyz456",
     *   "customer": {
     *     "email":      "buyer@example.com",
     *     "first_name": "John",
     *     "last_name":  "Doe",
     *     "phone":      "+33612345678",
     *     "address": {
     *       "line1":       "12 rue de la Paix",
     *       "line2":       "Apt 3",
     *       "city":        "Paris",
     *       "state":       "IDF",
     *       "postcode":    "75002",
     *       "country":     "FR"
     *     }
     *   },
     *   "items": [
     *     { "external_id": "42", "quantity": 2, "price": 29.90, "variation_id": null },
     *     { "external_id": "77", "quantity": 1, "price": 15.00, "variation_id": "83" }
     *   ],
     *   "shipping": {
     *     "method":  "flat_rate",
     *     "amount":  5.90,
     *     "label":   "Livraison standard"
     *   },
     *   "total":    74.80,
     *   "currency": "EUR",
     *   "paid_at":  "2025-01-15T14:30:00Z"
     * }
     *
     * @param array $data Parsed webhook payload.
     * @return array|WP_Error Array with 'order_id' on success, WP_Error on failure.
     */
    public function create_order( $data ) {
        // Validate required fields
        if ( empty( $data['houla_order_id'] ) || empty( $data['items'] ) ) {
            return new WP_Error( 'wphoula_invalid_order', 'Missing houla_order_id or items.' );
        }

        // Check for duplicate order
        $existing = $this->find_order_by_houla_id( $data['houla_order_id'] );
        if ( $existing ) {
            $this->log( 'Duplicate order skipped: ' . $data['houla_order_id'] . ' (WC #' . $existing . ').' );
            return array( 'order_id' => $existing );
        }

        try {
            $order = wc_create_order();

            // ----------------------------------------------------------
            // Line items
            // ----------------------------------------------------------
            foreach ( $data['items'] as $item ) {
                $product_id   = absint( $item['external_id'] );
                $variation_id = ! empty( $item['variation_id'] ) ? absint( $item['variation_id'] ) : 0;
                $quantity     = max( 1, absint( $item['quantity'] ) );
                $price        = isset( $item['price'] ) ? floatval( $item['price'] ) : 0;

                $product = wc_get_product( $variation_id ?: $product_id );
                if ( ! $product ) {
                    $this->log( 'Product #' . $product_id . ' not found, skipping line item.' );
                    continue;
                }

                $item_id = $order->add_product( $product, $quantity, array(
                    'subtotal' => $price * $quantity,
                    'total'    => $price * $quantity,
                ) );

                // Decrement stock
                if ( $product->get_manage_stock() ) {
                    wc_update_product_stock( $product, $quantity, 'decrease' );
                }
            }

            // ----------------------------------------------------------
            // Customer / Billing
            // ----------------------------------------------------------
            $customer = isset( $data['customer'] ) ? $data['customer'] : array();
            $address  = isset( $customer['address'] ) ? $customer['address'] : array();

            $billing = array(
                'first_name' => $this->safe( $customer, 'first_name' ),
                'last_name'  => $this->safe( $customer, 'last_name' ),
                'email'      => $this->safe( $customer, 'email' ),
                'phone'      => $this->safe( $customer, 'phone' ),
                'address_1'  => $this->safe( $address, 'line1' ),
                'address_2'  => $this->safe( $address, 'line2' ),
                'city'       => $this->safe( $address, 'city' ),
                'state'      => $this->safe( $address, 'state' ),
                'postcode'   => $this->safe( $address, 'postcode' ),
                'country'    => $this->safe( $address, 'country' ),
            );

            $order->set_address( $billing, 'billing' );
            $order->set_address( $billing, 'shipping' );

            // ----------------------------------------------------------
            // Shipping
            // ----------------------------------------------------------
            if ( ! empty( $data['shipping'] ) ) {
                $shipping_item = new WC_Order_Item_Shipping();
                $shipping_item->set_method_title( $this->safe( $data['shipping'], 'label', 'Houla Shipping' ) );
                $shipping_item->set_method_id( $this->safe( $data['shipping'], 'method', 'flat_rate' ) );
                $shipping_item->set_total( floatval( $data['shipping']['amount'] ?? 0 ) );
                $order->add_item( $shipping_item );
            }

            // ----------------------------------------------------------
            // Payment & metadata
            // ----------------------------------------------------------
            $order->set_payment_method( 'houla_pay' );
            $order->set_payment_method_title( 'Hou.la Pay (Stripe)' );

            if ( ! empty( $data['currency'] ) ) {
                $order->set_currency( strtoupper( $data['currency'] ) );
            }

            // Store Hou.la metadata
            $order->update_meta_data( '_houla_order_id', sanitize_text_field( $data['houla_order_id'] ) );
            if ( ! empty( $data['transaction_id'] ) ) {
                $order->set_transaction_id( sanitize_text_field( $data['transaction_id'] ) );
                $order->update_meta_data( '_houla_transaction_id', sanitize_text_field( $data['transaction_id'] ) );
            }
            if ( ! empty( $data['paid_at'] ) ) {
                $order->set_date_paid( $data['paid_at'] );
            }

            // Recalculate totals and set status
            $order->calculate_totals();
            $order->set_status( 'processing', __( 'Order received via Hou.la Pay.', 'wp-houla' ) );
            $order->save();

            // Update counters
            $this->increment_counter( 'orders_received' );
            $this->options->set( 'last_order_at', current_time( 'mysql' ) );

            $this->log( 'Order created: WC #' . $order->get_id() . ' from Hou.la ' . $data['houla_order_id'] . '.' );

            return array( 'order_id' => $order->get_id() );

        } catch ( \Exception $e ) {
            $this->log( 'Order creation failed: ' . $e->getMessage() );
            return new WP_Error( 'wphoula_order_failed', $e->getMessage() );
        }
    }

    // =====================================================================
    // Order refund
    // =====================================================================

    /**
     * Handle an order.refunded webhook.
     *
     * Expected $data structure:
     * {
     *   "houla_order_id": "ord_abc123",
     *   "reason":         "Customer requested refund"
     * }
     *
     * @param array $data Parsed webhook payload.
     * @return array|WP_Error
     */
    public function refund_order( $data ) {
        if ( empty( $data['houla_order_id'] ) ) {
            return new WP_Error( 'wphoula_invalid_refund', 'Missing houla_order_id.' );
        }

        $order_id = $this->find_order_by_houla_id( $data['houla_order_id'] );
        if ( ! $order_id ) {
            return new WP_Error( 'wphoula_order_not_found', 'Order not found for ' . $data['houla_order_id'] . '.' );
        }

        $order  = wc_get_order( $order_id );
        $reason = isset( $data['reason'] ) ? sanitize_text_field( $data['reason'] ) : __( 'Refunded via Hou.la.', 'wp-houla' );

        // Create the refund
        $refund = wc_create_refund( array(
            'amount'         => $order->get_total(),
            'reason'         => $reason,
            'order_id'       => $order_id,
            'refund_payment' => false, // Payment was already refunded on Stripe side
            'restock_items'  => true,
        ) );

        if ( is_wp_error( $refund ) ) {
            $this->log( 'Refund failed for WC #' . $order_id . ': ' . $refund->get_error_message() );
            return $refund;
        }

        $order->set_status( 'refunded', $reason );
        $order->save();

        $this->log( 'Order WC #' . $order_id . ' refunded (Hou.la ' . $data['houla_order_id'] . ').' );

        return array( 'order_id' => $order_id );
    }

    // =====================================================================
    // Lookup
    // =====================================================================

    /**
     * Find a WooCommerce order ID by Hou.la order ID.
     *
     * @param string $houla_order_id
     * @return int|false WooCommerce order ID or false.
     */
    private function find_order_by_houla_id( $houla_order_id ) {
        $orders = wc_get_orders( array(
            'meta_key'   => '_houla_order_id',
            'meta_value' => sanitize_text_field( $houla_order_id ),
            'limit'      => 1,
            'return'     => 'ids',
        ) );

        return ! empty( $orders ) ? $orders[0] : false;
    }

    // =====================================================================
    // Utilities
    // =====================================================================

    /**
     * Safely get a value from an array.
     *
     * @param array  $arr
     * @param string $key
     * @param mixed  $default
     * @return mixed
     */
    private function safe( $arr, $key, $default = '' ) {
        return isset( $arr[ $key ] ) ? sanitize_text_field( $arr[ $key ] ) : $default;
    }

    /**
     * Increment a numeric counter in options.
     *
     * @param string $key
     */
    private function increment_counter( $key ) {
        $val = (int) $this->options->get( $key );
        $this->options->set( $key, $val + 1 );
    }

    /**
     * Log a debug message.
     *
     * @param string $message
     */
    private function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( '[WP-Houla Orders] ' . $message );
        }
    }
}
