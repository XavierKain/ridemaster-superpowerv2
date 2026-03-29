<?php
/**
 * RM_Payout_Cron — Daily cron job for J-15 payouts via Stripe Transfers.
 *
 * @package RideMaster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RM_Payout_Cron {

	const CRON_HOOK = 'rm_process_payouts';

	public function __construct() {
		// Schedule the daily cron event.
		add_action( 'init', [ $this, 'schedule_cron' ] );

		// Handle the cron event.
		add_action( self::CRON_HOOK, [ $this, 'process_payouts' ] );

		// Admin manual trigger.
		add_action( 'wp_ajax_rm_run_payouts_now', [ $this, 'ajax_run_payouts' ] );
	}

	/**
	 * Schedule the daily payout cron if not already scheduled.
	 */
	public function schedule_cron() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::CRON_HOOK );
		}
	}

	/**
	 * Process all pending payouts where payout_date <= today.
	 */
	public function process_payouts() {
		$mode       = get_option( 'rm_stripe_mode', 'test' );
		$secret_key = $mode === 'live'
			? get_option( 'rm_stripe_live_secret_key', '' )
			: get_option( 'rm_stripe_test_secret_key', '' );

		if ( empty( $secret_key ) || ! class_exists( '\Stripe\Stripe' ) ) {
			error_log( '[RM Payout] Stripe not configured. Skipping payout run.' );
			return;
		}

		\Stripe\Stripe::setApiKey( $secret_key );

		$today = gmdate( 'Y-m-d' );

		// Query orders with pending payouts due today or earlier.
		$orders = wc_get_orders( [
			'limit'      => -1,
			'status'     => [ 'processing', 'completed' ],
			'meta_query' => [
				'relation' => 'AND',
				[
					'key'   => '_payout_status',
					'value' => 'pending',
				],
				[
					'key'     => '_payout_date',
					'value'   => $today,
					'compare' => '<=',
					'type'    => 'DATE',
				],
			],
		] );

		if ( empty( $orders ) ) {
			return;
		}

		$success_count = 0;
		$fail_count    = 0;

		foreach ( $orders as $order ) {
			$result = $this->process_single_payout( $order );
			if ( $result ) {
				$success_count++;
			} else {
				$fail_count++;
			}
		}

		error_log( sprintf( '[RM Payout] Run complete: %d success, %d failed.', $success_count, $fail_count ) );

		// Send admin alert if there were failures.
		if ( $fail_count > 0 ) {
			$admin_email = get_option( 'admin_email' );
			wp_mail(
				$admin_email,
				'[RideMaster] Payout failures detected',
				sprintf( '%d payout(s) failed during the last run. Please check RideMaster > Payments for details.', $fail_count )
			);
		}
	}

	/**
	 * Process a single order payout — transfer to coach and hotel.
	 */
	private function process_single_payout( $order ) {
		$order_id         = $order->get_id();
		$pi_id            = $order->get_meta( '_stripe_payment_intent_id' );
		$coach_stripe_id  = $order->get_meta( '_coach_stripe_account_id' );
		$hotel_stripe_id  = $order->get_meta( '_hotel_stripe_account_id' );
		$amount_coach     = floatval( $order->get_meta( '_amount_coach' ) );
		$amount_hotel     = floatval( $order->get_meta( '_amount_hotel' ) );

		if ( ! $pi_id ) {
			$order->add_order_note( 'Payout skipped: no PaymentIntent ID.' );
			$order->update_meta_data( '_payout_status', 'failed' );
			$order->save();
			return false;
		}

		$all_ok = true;

		// Transfer to Coach.
		if ( $amount_coach > 0 && $coach_stripe_id ) {
			try {
				$transfer = \Stripe\Transfer::create( [
					'amount'      => intval( round( $amount_coach * 100 ) ),
					'currency'    => strtolower( $order->get_currency() ),
					'destination' => $coach_stripe_id,
					'description' => sprintf( 'Camp payout — Order #%d', $order_id ),
					'metadata'    => [
						'order_id' => $order_id,
						'type'     => 'coach',
					],
				] );

				$order->update_meta_data( '_transfer_coach_id', $transfer->id );
				$order->add_order_note( sprintf(
					'Coach payout: %s transferred to %s. Transfer: %s',
					wc_price( $amount_coach ),
					$coach_stripe_id,
					$transfer->id
				) );

			} catch ( \Stripe\Exception\ApiErrorException $e ) {
				error_log( '[RM Payout] Coach transfer failed for order ' . $order_id . ': ' . $e->getMessage() );
				$order->add_order_note( 'Coach payout FAILED: ' . $e->getMessage() );
				$order->update_meta_data( '_payout_error_coach', $e->getMessage() );
				$all_ok = false;
			}
		}

		// Transfer to Hotel.
		if ( $amount_hotel > 0 && $hotel_stripe_id ) {
			try {
				$transfer = \Stripe\Transfer::create( [
					'amount'      => intval( round( $amount_hotel * 100 ) ),
					'currency'    => strtolower( $order->get_currency() ),
					'destination' => $hotel_stripe_id,
					'description' => sprintf( 'Hotel payout — Order #%d', $order_id ),
					'metadata'    => [
						'order_id' => $order_id,
						'type'     => 'hotel',
					],
				] );

				$order->update_meta_data( '_transfer_hotel_id', $transfer->id );
				$order->add_order_note( sprintf(
					'Hotel payout: %s transferred to %s. Transfer: %s',
					wc_price( $amount_hotel ),
					$hotel_stripe_id,
					$transfer->id
				) );

			} catch ( \Stripe\Exception\ApiErrorException $e ) {
				error_log( '[RM Payout] Hotel transfer failed for order ' . $order_id . ': ' . $e->getMessage() );
				$order->add_order_note( 'Hotel payout FAILED: ' . $e->getMessage() );
				$order->update_meta_data( '_payout_error_hotel', $e->getMessage() );
				$all_ok = false;
			}
		}

		// Update payout status.
		if ( $all_ok ) {
			$order->update_meta_data( '_payout_status', 'paid' );
			$order->update_meta_data( '_payout_date_actual', gmdate( 'Y-m-d' ) );
			$order->set_status( 'completed' );
		} else {
			$order->update_meta_data( '_payout_status', 'failed' );
		}

		$order->save();
		return $all_ok;
	}

	/**
	 * AJAX: Manually trigger a payout run (admin only).
	 */
	public function ajax_run_payouts() {
		check_ajax_referer( 'rm_run_payouts', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Not authorized.' );
		}

		$this->process_payouts();
		wp_send_json_success( [ 'message' => 'Payout run completed. Check order notes for details.' ] );
	}

	/**
	 * Get summary stats for the admin dashboard.
	 */
	public static function get_stats() {
		$stats = [
			'escrow_count'   => 0,
			'escrow_total'   => 0,
			'paid_count'     => 0,
			'paid_total'     => 0,
			'failed_count'   => 0,
			'upcoming'       => [],
		];

		// Escrow orders (pending payout).
		$escrow_orders = wc_get_orders( [
			'limit'      => -1,
			'status'     => [ 'processing' ],
			'meta_query' => [
				[ 'key' => '_payout_status', 'value' => 'pending' ],
			],
		] );

		foreach ( $escrow_orders as $order ) {
			$stats['escrow_count']++;
			$stats['escrow_total'] += floatval( $order->get_total() );
			$payout_date = $order->get_meta( '_payout_date' );
			if ( $payout_date ) {
				$stats['upcoming'][] = [
					'order_id'    => $order->get_id(),
					'amount'      => $order->get_total(),
					'payout_date' => $payout_date,
					'camp_id'     => $order->get_meta( '_camp_id' ),
				];
			}
		}

		// Sort upcoming by payout date.
		usort( $stats['upcoming'], function( $a, $b ) {
			return strcmp( $a['payout_date'], $b['payout_date'] );
		} );

		// Paid orders.
		$paid_orders = wc_get_orders( [
			'limit'      => -1,
			'status'     => [ 'completed' ],
			'meta_query' => [
				[ 'key' => '_payout_status', 'value' => 'paid' ],
			],
		] );

		foreach ( $paid_orders as $order ) {
			$stats['paid_count']++;
			$stats['paid_total'] += floatval( $order->get_total() );
		}

		// Failed.
		$failed_orders = wc_get_orders( [
			'limit'      => -1,
			'meta_query' => [
				[ 'key' => '_payout_status', 'value' => 'failed' ],
			],
		] );
		$stats['failed_count'] = count( $failed_orders );

		return $stats;
	}
}
