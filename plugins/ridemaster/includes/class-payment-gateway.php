<?php
/**
 * RM_Payment_Gateway — WooCommerce Payment Gateway using Stripe PaymentIntents.
 *
 * @package RideMaster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
	return;
}

class RM_Payment_Gateway extends WC_Payment_Gateway {

	public function __construct() {
		$this->id                 = 'ridemaster_stripe';
		$this->method_title       = 'RideMaster Stripe';
		$this->method_description = 'Pay securely with your credit card via Stripe Connect.';
		$this->has_fields         = true;
		$this->supports           = [ 'products', 'refunds' ];

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title', 'Credit Card (Stripe)' );
		$this->description = $this->get_option( 'description', 'Pay securely with your credit card.' );
		$this->enabled     = $this->get_option( 'enabled', 'yes' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_stripe_scripts' ] );
	}

	public function init_form_fields() {
		$this->form_fields = [
			'enabled' => [
				'title'   => 'Enable/Disable',
				'type'    => 'checkbox',
				'label'   => 'Enable RideMaster Stripe Payments',
				'default' => 'yes',
			],
			'title' => [
				'title'   => 'Title',
				'type'    => 'text',
				'default' => 'Credit Card (Stripe)',
			],
			'description' => [
				'title'   => 'Description',
				'type'    => 'textarea',
				'default' => 'Pay securely with your credit card.',
			],
		];
	}

	/**
	 * Enqueue Stripe.js and our checkout script on the checkout page.
	 */
	public function enqueue_stripe_scripts() {
		if ( ! is_checkout() ) {
			return;
		}

		$mode = get_option( 'rm_stripe_mode', 'test' );
		$pk   = $mode === 'live'
			? get_option( 'rm_stripe_live_publishable_key', '' )
			: get_option( 'rm_stripe_test_publishable_key', '' );

		if ( empty( $pk ) ) {
			return;
		}

		wp_enqueue_script( 'stripe-js', 'https://js.stripe.com/v3/', [], null, true );
		wp_enqueue_script(
			'rm-stripe-checkout',
			RM_PLUGIN_URL . 'assets/js/stripe-checkout.js',
			[ 'stripe-js', 'jquery' ],
			RM_VERSION,
			true
		);

		wp_localize_script( 'rm-stripe-checkout', 'rmStripe', [
			'publishableKey' => $pk,
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'rm_stripe_checkout' ),
		] );

		wp_enqueue_style(
			'rm-stripe-checkout',
			RM_PLUGIN_URL . 'assets/css/stripe-checkout.css',
			[],
			RM_VERSION
		);
	}

	/**
	 * Render the payment fields (Stripe Elements container).
	 */
	public function payment_fields() {
		if ( $this->description ) {
			echo '<p>' . esc_html( $this->description ) . '</p>';
		}

		// Insurance label.
		$insurance_label = get_option( 'rm_insurance_label', 'Individual Accident Insurance included' );
		if ( $insurance_label ) {
			$pdf_id  = get_option( 'rm_insurance_pdf_id', 0 );
			$pdf_url = $pdf_id ? wp_get_attachment_url( $pdf_id ) : '';
			echo '<p style="color:#065f46;font-size:13px;margin-bottom:12px;">';
			echo '&#10003; ' . esc_html( $insurance_label );
			if ( $pdf_url ) {
				echo ' — <a href="' . esc_url( $pdf_url ) . '" target="_blank" style="color:#0d9488;">View notice</a>';
			}
			echo '</p>';
		}

		echo '<div id="rm-stripe-card-element" style="padding:12px;border:1px solid #d1d5db;border-radius:8px;background:#fff;margin-bottom:8px;"></div>';
		echo '<div id="rm-stripe-card-errors" style="color:#dc2626;font-size:13px;min-height:20px;"></div>';
		echo '<input type="hidden" name="rm_stripe_payment_intent_id" id="rm-stripe-payment-intent-id" />';
	}

	/**
	 * Validate the payment fields.
	 */
	public function validate_fields() {
		if ( empty( $_POST['rm_stripe_payment_intent_id'] ) ) {
			wc_add_notice( 'Payment processing failed. Please try again.', 'error' );
			return false;
		}
		return true;
	}

	/**
	 * Process the payment — confirm the PaymentIntent and record order metas.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return [ 'result' => 'failure' ];
		}

		$pi_id = sanitize_text_field( $_POST['rm_stripe_payment_intent_id'] ?? '' );
		if ( empty( $pi_id ) ) {
			wc_add_notice( 'Missing payment information.', 'error' );
			return [ 'result' => 'failure' ];
		}

		try {
			// Retrieve and verify the PaymentIntent.
			$pi = \Stripe\PaymentIntent::retrieve( $pi_id );

			if ( $pi->status !== 'succeeded' ) {
				wc_add_notice( 'Payment was not completed. Status: ' . $pi->status, 'error' );
				return [ 'result' => 'failure' ];
			}

			// Verify amount matches.
			$expected_amount = intval( round( $order->get_total() * 100 ) );
			if ( intval( $pi->amount ) !== $expected_amount ) {
				wc_add_notice( 'Payment amount mismatch.', 'error' );
				error_log( '[RM Payments] Amount mismatch: PI=' . $pi->amount . ' expected=' . $expected_amount );
				return [ 'result' => 'failure' ];
			}

			// Calculate the split.
			$split = $this->calculate_split( $order );

			// Store all payment metas on the order.
			$order->update_meta_data( '_stripe_payment_intent_id', $pi_id );
			$order->update_meta_data( '_amount_total', $split['total'] );
			$order->update_meta_data( '_amount_commission', $split['commission'] );
			$order->update_meta_data( '_amount_hotel', $split['hotel'] );
			$order->update_meta_data( '_amount_coach', $split['coach'] );
			$order->update_meta_data( '_coach_stripe_account_id', $split['coach_stripe_id'] );
			$order->update_meta_data( '_hotel_stripe_account_id', $split['hotel_stripe_id'] );
			$order->update_meta_data( '_camp_id', $split['camp_id'] );
			$order->update_meta_data( '_camp_start_date', $split['camp_start_date'] );
			$order->update_meta_data( '_payout_status', 'pending' );
			$order->update_meta_data( '_payout_date', $split['payout_date'] );

			// Mark order as processing (payment received, awaiting camp).
			$order->payment_complete( $pi_id );
			$order->add_order_note( sprintf(
				'Stripe payment captured. PI: %s | Coach: %s | Hotel: %s | Commission: %s',
				$pi_id,
				wc_price( $split['coach'] ),
				wc_price( $split['hotel'] ),
				wc_price( $split['commission'] )
			) );

			$order->save();

			// Reduce stock.
			wc_reduce_stock_levels( $order_id );

			// Empty cart.
			WC()->cart->empty_cart();

			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			];

		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			error_log( '[RM Payments] process_payment error: ' . $e->getMessage() );
			wc_add_notice( 'Payment error: ' . $e->getMessage(), 'error' );
			return [ 'result' => 'failure' ];
		}
	}

	/**
	 * Calculate the split for an order (commission, coach, hotel).
	 */
	private function calculate_split( $order ) {
		$total           = floatval( $order->get_total() );
		$commission_rate = floatval( get_option( 'rm_commission_rate', 0 ) ) / 100;
		$payout_delay    = intval( get_option( 'rm_payout_delay_days', 15 ) );

		// Find the camp product in the order.
		$camp_id         = 0;
		$hotel_amount    = 0;
		$coach_stripe_id = '';
		$hotel_stripe_id = '';
		$camp_start_date = '';
		$quantity        = 1;

		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$camp_id    = $product_id;
			$quantity   = $item->get_quantity();

			// Get hotel amount per person.
			$hotel_amount_pp = floatval( get_post_meta( $product_id, '_hotel_amount', true ) );
			$hotel_amount    = $hotel_amount_pp * $quantity;

			// Get coach Stripe account.
			$coach_post_id = get_post_meta( $product_id, '_coach_post_id', true );
			if ( $coach_post_id ) {
				$coach_post   = get_post( $coach_post_id );
				$coach_user_id = $coach_post ? $coach_post->post_author : 0;
				if ( $coach_user_id ) {
					$coach_stripe_id = get_user_meta( $coach_user_id, 'stripe_account_id', true );
				}
			}

			// Get hotel Stripe account.
			$hotel_id = get_post_meta( $product_id, '_hotel_id', true );
			if ( $hotel_id ) {
				$hotel_stripe_id = get_post_meta( $hotel_id, 'hotel_stripe_account_id', true );
			}

			// Get camp start date.
			$camp_start_date = get_post_meta( $product_id, 'full_date', true );
			if ( is_numeric( $camp_start_date ) ) {
				$camp_start_date = gmdate( 'Y-m-d', intval( $camp_start_date ) );
			}

			break; // Only process first camp product.
		}

		$commission = round( $total * $commission_rate, 2 );
		$coach      = round( $total - $commission - $hotel_amount, 2 );

		// Calculate payout date.
		$payout_date = '';
		if ( $camp_start_date ) {
			$camp_ts    = strtotime( $camp_start_date );
			$payout_ts  = $camp_ts - ( $payout_delay * DAY_IN_SECONDS );
			$now_ts     = time();

			if ( $payout_ts <= $now_ts ) {
				// Last-minute booking: payout tomorrow.
				$payout_date = gmdate( 'Y-m-d', $now_ts + DAY_IN_SECONDS );
			} else {
				$payout_date = gmdate( 'Y-m-d', $payout_ts );
			}
		}

		return [
			'total'           => $total,
			'commission'      => $commission,
			'hotel'           => $hotel_amount,
			'coach'           => max( 0, $coach ),
			'coach_stripe_id' => $coach_stripe_id,
			'hotel_stripe_id' => $hotel_stripe_id,
			'camp_id'         => $camp_id,
			'camp_start_date' => $camp_start_date,
			'payout_date'     => $payout_date,
		];
	}

	/**
	 * Process a refund via Stripe.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new WP_Error( 'invalid_order', 'Order not found.' );
		}

		$pi_id = $order->get_meta( '_stripe_payment_intent_id' );
		if ( ! $pi_id ) {
			return new WP_Error( 'no_pi', 'No Stripe PaymentIntent found for this order.' );
		}

		try {
			$refund_params = [
				'payment_intent' => $pi_id,
			];
			if ( $amount ) {
				$refund_params['amount'] = intval( round( $amount * 100 ) );
			}
			if ( $reason ) {
				$refund_params['reason'] = 'requested_by_customer';
			}

			$refund = \Stripe\Refund::create( $refund_params );

			$order->add_order_note( sprintf(
				'Stripe refund processed: %s (%s). Refund ID: %s',
				wc_price( $amount ),
				$reason ?: 'No reason',
				$refund->id
			) );

			$order->update_meta_data( '_refund_stripe_id', $refund->id );
			$order->save();

			return true;

		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			error_log( '[RM Payments] Refund error: ' . $e->getMessage() );
			return new WP_Error( 'stripe_error', $e->getMessage() );
		}
	}
}
