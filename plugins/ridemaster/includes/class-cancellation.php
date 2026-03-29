<?php
/**
 * RM_Cancellation — Cancellation and refund logic with tiered rates.
 *
 * @package RideMaster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RM_Cancellation {

	/**
	 * Refund tiers: days before camp => refund percentage.
	 */
	const TIERS = [
		45 => 100, // > 45 days: 100% refund.
		31 => 90,  // 45 to 31 days: 90% refund.
		15 => 50,  // 30 to 15 days: 50% refund.
		0  => 25,  // < 15 days: 25% refund.
	];

	public function __construct() {
		// Admin AJAX: process cancellation.
		add_action( 'wp_ajax_rm_cancel_order', [ $this, 'ajax_cancel_order' ] );

		// Add cancellation button to order admin page.
		add_action( 'woocommerce_order_actions', [ $this, 'add_cancel_action' ] );
		add_action( 'woocommerce_order_action_rm_rider_cancel', [ $this, 'process_rider_cancel_action' ] );
		add_action( 'woocommerce_order_action_rm_coach_cancel', [ $this, 'process_coach_cancel_action' ] );
	}

	/**
	 * Calculate the refund tier for a given order.
	 *
	 * @param  int $order_id  Order ID.
	 * @return array { 'tier' => int (percentage), 'days_before' => int, 'refund_amount' => float, 'retained_amount' => float }
	 */
	public static function calculate_tier( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return null;
		}

		$camp_start = $order->get_meta( '_camp_start_date' );
		if ( ! $camp_start ) {
			return null;
		}

		$today       = new \DateTime( 'now', new \DateTimeZone( 'UTC' ) );
		$camp_date   = new \DateTime( $camp_start, new \DateTimeZone( 'UTC' ) );
		$days_before = intval( $today->diff( $camp_date )->format( '%r%a' ) );

		// Find the applicable tier.
		$refund_pct = 25; // Default: less than 15 days.
		foreach ( self::TIERS as $threshold => $pct ) {
			if ( $days_before > $threshold ) {
				$refund_pct = $pct;
				break;
			}
		}

		$total          = floatval( $order->get_total() );
		$refund_amount  = round( $total * $refund_pct / 100, 2 );
		$retained       = round( $total - $refund_amount, 2 );

		return [
			'tier'            => $refund_pct,
			'days_before'     => max( 0, $days_before ),
			'refund_amount'   => $refund_amount,
			'retained_amount' => $retained,
			'total'           => $total,
		];
	}

	/**
	 * Process a rider cancellation.
	 *
	 * @param  int $order_id  Order ID.
	 * @return array|WP_Error Result with details.
	 */
	public static function cancel_by_rider( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new \WP_Error( 'invalid', 'Order not found.' );
		}

		$tier_info = self::calculate_tier( $order_id );
		if ( ! $tier_info ) {
			return new \WP_Error( 'no_date', 'Cannot determine camp start date.' );
		}

		$payout_status = $order->get_meta( '_payout_status' );
		$result        = [ 'type' => 'rider' ];

		// Init Stripe.
		$mode       = get_option( 'rm_stripe_mode', 'test' );
		$secret_key = $mode === 'live'
			? get_option( 'rm_stripe_live_secret_key', '' )
			: get_option( 'rm_stripe_test_secret_key', '' );

		if ( empty( $secret_key ) || ! class_exists( '\Stripe\Stripe' ) ) {
			return new \WP_Error( 'stripe', 'Stripe not configured.' );
		}

		\Stripe\Stripe::setApiKey( $secret_key );

		$pi_id = $order->get_meta( '_stripe_payment_intent_id' );

		try {
			if ( $payout_status === 'paid' ) {
				// Payout already made — reverse transfers first.
				$coach_transfer_id = $order->get_meta( '_transfer_coach_id' );
				if ( $coach_transfer_id ) {
					$reversal = \Stripe\Transfer::createReversal( $coach_transfer_id );
					$order->update_meta_data( '_reverse_transfer_coach_id', $reversal->id );
					$order->add_order_note( 'Coach transfer reversed: ' . $reversal->id );
				}

				// Hotel: do NOT auto-reverse. Flag for admin.
				$hotel_transfer_id = $order->get_meta( '_transfer_hotel_id' );
				if ( $hotel_transfer_id ) {
					$order->update_meta_data( '_cancellation_alert', '1' );
					$order->add_order_note( 'ALERT: Hotel transfer already made. Manual intervention required for hotel refund.' );
				}
			}

			// Refund the rider (partial or full).
			if ( $tier_info['refund_amount'] > 0 && $pi_id ) {
				$refund = \Stripe\Refund::create( [
					'payment_intent' => $pi_id,
					'amount'         => intval( round( $tier_info['refund_amount'] * 100 ) ),
				] );
				$order->update_meta_data( '_refund_stripe_id', $refund->id );
				$result['refund_id'] = $refund->id;
			}

			// Update order metas.
			$order->update_meta_data( '_cancellation_date', gmdate( 'Y-m-d H:i:s' ) );
			$order->update_meta_data( '_cancellation_by', 'rider' );
			$order->update_meta_data( '_cancellation_tier', $tier_info['tier'] );
			$order->update_meta_data( '_refund_amount', $tier_info['refund_amount'] );
			$order->update_meta_data( '_payout_status', 'cancelled' );

			// Set order status.
			if ( $tier_info['tier'] === 100 ) {
				$order->set_status( 'refunded' );
			} else {
				$order->set_status( 'cancelled' );
			}

			$order->add_order_note( sprintf(
				'Rider cancellation: %d%% refund (%s). %d days before camp. Retained: %s.',
				$tier_info['tier'],
				wc_price( $tier_info['refund_amount'] ),
				$tier_info['days_before'],
				wc_price( $tier_info['retained_amount'] )
			) );

			$order->save();
			$result['success'] = true;
			$result['tier']    = $tier_info;

			return $result;

		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			error_log( '[RM Cancellation] Rider cancel error for order ' . $order_id . ': ' . $e->getMessage() );
			$order->add_order_note( 'Cancellation error: ' . $e->getMessage() );
			$order->update_meta_data( '_cancellation_alert', '1' );
			$order->save();
			return new \WP_Error( 'stripe', $e->getMessage() );
		}
	}

	/**
	 * Process a coach cancellation (100% refund always).
	 *
	 * @param  int $order_id  Order ID.
	 * @return array|WP_Error Result.
	 */
	public static function cancel_by_coach( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return new \WP_Error( 'invalid', 'Order not found.' );
		}

		$mode       = get_option( 'rm_stripe_mode', 'test' );
		$secret_key = $mode === 'live'
			? get_option( 'rm_stripe_live_secret_key', '' )
			: get_option( 'rm_stripe_test_secret_key', '' );

		if ( empty( $secret_key ) || ! class_exists( '\Stripe\Stripe' ) ) {
			return new \WP_Error( 'stripe', 'Stripe not configured.' );
		}

		\Stripe\Stripe::setApiKey( $secret_key );

		$pi_id         = $order->get_meta( '_stripe_payment_intent_id' );
		$payout_status = $order->get_meta( '_payout_status' );

		try {
			if ( $payout_status === 'paid' ) {
				// Reverse coach transfer.
				$coach_transfer_id = $order->get_meta( '_transfer_coach_id' );
				if ( $coach_transfer_id ) {
					$reversal = \Stripe\Transfer::createReversal( $coach_transfer_id );
					$order->update_meta_data( '_reverse_transfer_coach_id', $reversal->id );
					$order->add_order_note( 'Coach transfer reversed: ' . $reversal->id );
				}

				// Hotel: flag for admin (do not auto-reverse).
				$hotel_transfer_id = $order->get_meta( '_transfer_hotel_id' );
				if ( $hotel_transfer_id ) {
					$order->update_meta_data( '_cancellation_alert', '1' );
					$order->add_order_note( 'URGENT ALERT: Coach cancellation after payout. Hotel funds already transferred. Manual recovery required.' );

					// Send urgent admin email.
					$admin_email = get_option( 'admin_email' );
					$hotel_amount = $order->get_meta( '_amount_hotel' );
					wp_mail(
						$admin_email,
						'[URGENT] Coach cancellation with hotel funds already paid — Order #' . $order_id,
						sprintf(
							"Coach cancelled after payout was made.\nOrder: #%d\nHotel amount already transferred: %s\n\nPlease recover funds from hotel manually or debit coach account.",
							$order_id,
							wc_price( $hotel_amount )
						)
					);
				}
			}

			// Full refund to rider.
			if ( $pi_id ) {
				$refund = \Stripe\Refund::create( [
					'payment_intent' => $pi_id,
				] );
				$order->update_meta_data( '_refund_stripe_id', $refund->id );
			}

			$total = floatval( $order->get_total() );
			$order->update_meta_data( '_cancellation_date', gmdate( 'Y-m-d H:i:s' ) );
			$order->update_meta_data( '_cancellation_by', 'coach' );
			$order->update_meta_data( '_cancellation_tier', 100 );
			$order->update_meta_data( '_refund_amount', $total );
			$order->update_meta_data( '_payout_status', 'cancelled' );
			$order->set_status( 'refunded' );

			$order->add_order_note( sprintf(
				'Coach cancellation: 100%% refund (%s) issued to rider.',
				wc_price( $total )
			) );

			$order->save();

			return [ 'success' => true, 'type' => 'coach', 'refund_amount' => $total ];

		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			error_log( '[RM Cancellation] Coach cancel error for order ' . $order_id . ': ' . $e->getMessage() );
			$order->add_order_note( 'Coach cancellation error: ' . $e->getMessage() );
			$order->update_meta_data( '_cancellation_alert', '1' );
			$order->save();
			return new \WP_Error( 'stripe', $e->getMessage() );
		}
	}

	/**
	 * Add custom order actions in admin.
	 */
	public function add_cancel_action( $actions ) {
		global $theorder;
		if ( ! $theorder || ! in_array( $theorder->get_status(), [ 'processing', 'on-hold' ], true ) ) {
			return $actions;
		}
		// Only for camp orders (with a camp_id).
		if ( ! $theorder->get_meta( '_camp_id' ) ) {
			return $actions;
		}

		$actions['rm_rider_cancel'] = 'RideMaster: Rider Cancellation (tiered refund)';
		$actions['rm_coach_cancel'] = 'RideMaster: Coach Cancellation (100% refund)';
		return $actions;
	}

	/**
	 * Handle the rider cancellation order action.
	 */
	public function process_rider_cancel_action( $order ) {
		$result = self::cancel_by_rider( $order->get_id() );
		if ( is_wp_error( $result ) ) {
			$order->add_order_note( 'Rider cancellation failed: ' . $result->get_error_message() );
		}
	}

	/**
	 * Handle the coach cancellation order action.
	 */
	public function process_coach_cancel_action( $order ) {
		$result = self::cancel_by_coach( $order->get_id() );
		if ( is_wp_error( $result ) ) {
			$order->add_order_note( 'Coach cancellation failed: ' . $result->get_error_message() );
		}
	}

	/**
	 * AJAX: Cancel an order (admin only).
	 */
	public function ajax_cancel_order() {
		check_ajax_referer( 'rm_cancel_order', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Not authorized.' );
		}

		$order_id = intval( $_POST['order_id'] ?? 0 );
		$type     = sanitize_text_field( $_POST['cancel_type'] ?? 'rider' );

		if ( ! $order_id ) {
			wp_send_json_error( 'Invalid order.' );
		}

		if ( $type === 'coach' ) {
			$result = self::cancel_by_coach( $order_id );
		} else {
			$result = self::cancel_by_rider( $order_id );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}
}
