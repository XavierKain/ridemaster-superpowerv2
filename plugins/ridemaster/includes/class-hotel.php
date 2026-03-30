<?php
/**
 * RM_Hotel — Hotel Stripe Custom account management.
 *
 * Hotels are JetEngine CPT "hotel" with meta fields for IBAN, legal info, etc.
 * This class handles creating/managing Stripe Custom accounts for hotels.
 *
 * @package RideMaster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RM_Hotel {

	public function __construct() {
		// When a hotel post is saved with IBAN info, create/update Stripe Custom account.
		add_action( 'save_post_hotel', [ $this, 'sync_stripe_account' ], 20, 3 );

		// Stamp _coach_post_id on new hotels (ownership).
		add_action( 'save_post_hotel', [ $this, 'stamp_coach' ], 10, 3 );

		// Filter hotel queries so coaches only see their own hotels.
		add_action( 'pre_get_posts', [ $this, 'filter_hotels_by_coach' ] );

		// AJAX: delete hotel.
		add_action( 'wp_ajax_rm_delete_hotel', [ $this, 'ajax_delete_hotel' ] );
	}

	/**
	 * Stamp _coach_post_id on newly created hotel posts.
	 */
	public function stamp_coach( $post_id, $post, $update ) {
		if ( $update || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$coach_post_id = get_user_meta( $user_id, 'coach_post_id', true );
		if ( $coach_post_id ) {
			update_post_meta( $post_id, '_coach_post_id', $coach_post_id );
		}
	}

	/**
	 * Create or update the Stripe Custom account for a hotel.
	 * Triggered when hotel meta (IBAN, name, etc.) is saved.
	 */
	public function sync_stripe_account( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Only proceed if we have the minimum required info.
		$hotel_name  = get_the_title( $post_id );
		$hotel_iban  = get_post_meta( $post_id, 'hotel_iban', true );
		$hotel_country = get_post_meta( $post_id, 'hotel_country', true );

		if ( empty( $hotel_name ) || empty( $hotel_iban ) || empty( $hotel_country ) ) {
			return;
		}

		// Check if Stripe is configured.
		$mode       = get_option( 'rm_stripe_mode', 'test' );
		$secret_key = $mode === 'live'
			? get_option( 'rm_stripe_live_secret_key', '' )
			: get_option( 'rm_stripe_test_secret_key', '' );

		if ( empty( $secret_key ) || ! class_exists( '\Stripe\Stripe' ) ) {
			return;
		}

		\Stripe\Stripe::setApiKey( $secret_key );

		$existing_account_id = get_post_meta( $post_id, 'hotel_stripe_account_id', true );

		$hotel_address      = get_post_meta( $post_id, 'hotel_address', true );
		$hotel_rep_name     = get_post_meta( $post_id, 'hotel_representative_name', true );
		$hotel_rep_dob      = get_post_meta( $post_id, 'hotel_representative_dob', true );

		try {
			if ( $existing_account_id ) {
				// Update existing account.
				$this->update_stripe_account( $existing_account_id, $hotel_name, $hotel_iban, $hotel_country, $hotel_address, $hotel_rep_name, $hotel_rep_dob );
				update_post_meta( $post_id, 'hotel_stripe_status', 'verified' );
			} else {
				// Create new Custom account.
				$account_id = $this->create_stripe_account( $post_id, $hotel_name, $hotel_iban, $hotel_country, $hotel_address, $hotel_rep_name, $hotel_rep_dob );
				if ( $account_id ) {
					update_post_meta( $post_id, 'hotel_stripe_account_id', $account_id );
					update_post_meta( $post_id, 'hotel_stripe_status', 'pending' );
				}
			}
		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			error_log( '[RM Hotel] Stripe error for hotel ' . $post_id . ': ' . $e->getMessage() );
			update_post_meta( $post_id, 'hotel_stripe_status', 'requires_action' );
			update_post_meta( $post_id, 'hotel_stripe_error', $e->getMessage() );
		}
	}

	/**
	 * Create a Stripe Custom account for a hotel.
	 */
	private function create_stripe_account( $post_id, $name, $iban, $country, $address, $rep_name, $rep_dob ) {
		$params = [
			'type'         => 'custom',
			'country'      => strtoupper( substr( $country, 0, 2 ) ),
			'capabilities' => [
				'transfers' => [ 'requested' => true ],
			],
			'business_type'    => 'company',
			'business_profile' => [
				'name' => $name,
				'mcc'  => '7011', // Hotels and Motels.
			],
			'company' => [
				'name' => $name,
			],
			'tos_acceptance' => [
				'date' => time(),
				'ip'   => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
			],
			'metadata' => [
				'rm_hotel_post_id' => $post_id,
			],
		];

		// Add address if available.
		if ( $address ) {
			$params['company']['address'] = [
				'line1'   => $address,
				'country' => strtoupper( substr( $country, 0, 2 ) ),
			];
		}

		$account = \Stripe\Account::create( $params );

		// Add IBAN as external account (bank account for payouts).
		if ( $iban ) {
			\Stripe\Account::createExternalAccount( $account->id, [
				'external_account' => [
					'object'               => 'bank_account',
					'account_holder_name'  => $name,
					'account_holder_type'  => 'company',
					'country'              => strtoupper( substr( $country, 0, 2 ) ),
					'currency'             => 'eur',
					'account_number'       => $iban,
				],
			] );
		}

		// Add representative if available.
		if ( $rep_name ) {
			$name_parts = explode( ' ', $rep_name, 2 );
			$person_params = [
				'first_name'   => $name_parts[0],
				'last_name'    => $name_parts[1] ?? '',
				'relationship' => [ 'representative' => true ],
			];

			if ( $rep_dob ) {
				$dob = date_parse( $rep_dob );
				if ( $dob['year'] ) {
					$person_params['dob'] = [
						'day'   => $dob['day'],
						'month' => $dob['month'],
						'year'  => $dob['year'],
					];
				}
			}

			\Stripe\Account::createPerson( $account->id, $person_params );
		}

		return $account->id;
	}

	/**
	 * Update an existing Stripe Custom account.
	 */
	private function update_stripe_account( $account_id, $name, $iban, $country, $address, $rep_name, $rep_dob ) {
		$params = [
			'business_profile' => [
				'name' => $name,
			],
			'company' => [
				'name' => $name,
			],
		];

		if ( $address ) {
			$params['company']['address'] = [
				'line1'   => $address,
				'country' => strtoupper( substr( $country, 0, 2 ) ),
			];
		}

		\Stripe\Account::update( $account_id, $params );
	}

	/**
	 * Check if a hotel has a valid Stripe account ready for payouts.
	 */
	public static function is_payout_ready( $hotel_post_id ) {
		$account_id = get_post_meta( $hotel_post_id, 'hotel_stripe_account_id', true );
		$status     = get_post_meta( $hotel_post_id, 'hotel_stripe_status', true );
		return ! empty( $account_id ) && in_array( $status, [ 'verified', 'pending' ], true );
	}

	/**
	 * AJAX: Trash a hotel owned by the current coach.
	 */
	public function ajax_delete_hotel() {
		check_ajax_referer( 'rm_inline_edit', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'Not logged in.' );
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( 'coach_role', (array) $user->roles, true ) ) {
			wp_send_json_error( 'Not authorized.' );
		}

		$post_id = intval( $_POST['post_id'] ?? 0 );
		if ( ! $post_id ) {
			wp_send_json_error( 'Invalid post.' );
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'hotel' ) {
			wp_send_json_error( 'Invalid hotel.' );
		}

		// Check ownership.
		$coach_post_id = get_user_meta( $user_id, 'coach_post_id', true );
		$hotel_coach   = get_post_meta( $post_id, '_coach_post_id', true );
		if ( ! $coach_post_id || $coach_post_id !== $hotel_coach ) {
			wp_send_json_error( 'Unauthorized — not your hotel.' );
		}

		$result = wp_trash_post( $post_id );
		if ( ! $result ) {
			wp_send_json_error( 'Failed to delete the hotel.' );
		}

		wp_send_json_success( [ 'message' => 'Hotel deleted successfully.' ] );
	}

	/**
	 * Filter hotel queries so non-admin users only see their own hotels.
	 * This affects JetFormBuilder select fields, JetEngine listings, and any WP_Query for hotels.
	 */
	public function filter_hotels_by_coach( $query ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return; // Don't filter in admin (admins see all).
		}

		if ( $query->get( 'post_type' ) !== 'hotel' ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		// Admins see all hotels.
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$coach_post_id = get_user_meta( $user_id, 'coach_post_id', true );
		if ( ! $coach_post_id ) {
			return;
		}

		$meta_query = $query->get( 'meta_query' ) ?: [];
		$meta_query[] = [
			'key'   => '_coach_post_id',
			'value' => $coach_post_id,
		];
		$query->set( 'meta_query', $meta_query );
	}
}
