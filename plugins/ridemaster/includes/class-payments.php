<?php
/**
 * RM_Payments — Stripe Connect integration, payment orchestration.
 *
 * @package RideMaster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RM_Payments {

	/** Stripe API mode: 'test' or 'live'. */
	private $mode;

	/** Stripe Secret Key (resolved from mode). */
	private $secret_key;

	public function __construct() {
		// Admin settings page.
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );

		// Initialize Stripe SDK with the correct key.
		$this->mode       = get_option( 'rm_stripe_mode', 'test' );
		$this->secret_key = $this->mode === 'live'
			? get_option( 'rm_stripe_live_secret_key', '' )
			: get_option( 'rm_stripe_test_secret_key', '' );

		if ( $this->secret_key && class_exists( '\Stripe\Stripe' ) ) {
			\Stripe\Stripe::setApiKey( $this->secret_key );
		}

		// Coach Stripe onboarding.
		add_action( 'wp_ajax_rm_stripe_connect', [ $this, 'ajax_stripe_connect' ] );
		add_action( 'wp_ajax_rm_stripe_disconnect', [ $this, 'ajax_stripe_disconnect' ] );

		// Stripe OAuth return handler.
		add_action( 'template_redirect', [ $this, 'handle_stripe_return' ] );

		// Stripe webhook endpoint.
		add_action( 'rest_api_init', [ $this, 'register_webhook_endpoint' ] );
	}

	// =========================================================================
	// HELPERS
	// =========================================================================

	/**
	 * Get the current Stripe mode.
	 */
	public function get_mode() {
		return $this->mode;
	}

	/**
	 * Check if Stripe is configured (has API keys).
	 */
	public function is_configured() {
		return ! empty( $this->secret_key );
	}

	// =========================================================================
	// ADMIN MENU
	// =========================================================================

	/**
	 * Register the RideMaster settings menu.
	 */
	public function register_admin_menu() {
		add_menu_page(
			'RideMaster',
			'RideMaster',
			'manage_options',
			'ridemaster',
			[ $this, 'render_settings_page' ],
			'dashicons-palmtree',
			30
		);

		add_submenu_page(
			'ridemaster',
			'Settings',
			'Settings',
			'manage_options',
			'ridemaster',
			[ $this, 'render_settings_page' ]
		);

		add_submenu_page(
			'ridemaster',
			'Payments',
			'Payments',
			'manage_options',
			'ridemaster-payments',
			[ $this, 'render_payments_page' ]
		);
	}

	// =========================================================================
	// SETTINGS REGISTRATION
	// =========================================================================

	/**
	 * Register all settings with the WordPress Settings API.
	 */
	public function register_settings() {
		// --- Stripe section ---
		add_settings_section( 'rm_stripe_section', 'Stripe Connect', null, 'ridemaster' );

		register_setting( 'ridemaster_settings', 'rm_stripe_mode', [
			'type'              => 'string',
			'sanitize_callback' => function( $val ) {
				return in_array( $val, [ 'test', 'live' ], true ) ? $val : 'test';
			},
			'default'           => 'test',
		] );
		add_settings_field( 'rm_stripe_mode', 'Mode', [ $this, 'render_mode_field' ], 'ridemaster', 'rm_stripe_section' );

		$keys = [
			'rm_stripe_test_publishable_key' => 'Test Publishable Key',
			'rm_stripe_test_secret_key'      => 'Test Secret Key',
			'rm_stripe_live_publishable_key' => 'Live Publishable Key',
			'rm_stripe_live_secret_key'      => 'Live Secret Key',
			'rm_stripe_webhook_secret'       => 'Webhook Secret',
		];

		foreach ( $keys as $option_name => $label ) {
			register_setting( 'ridemaster_settings', $option_name, [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			] );
			add_settings_field( $option_name, $label, function() use ( $option_name ) {
				$value = get_option( $option_name, '' );
				$type  = strpos( $option_name, 'secret' ) !== false ? 'password' : 'text';
				printf(
					'<input type="%s" name="%s" value="%s" class="regular-text" autocomplete="off" />',
					esc_attr( $type ),
					esc_attr( $option_name ),
					esc_attr( $value )
				);
			}, 'ridemaster', 'rm_stripe_section' );
		}

		// --- Commission section ---
		add_settings_section( 'rm_commission_section', 'Commission', null, 'ridemaster' );

		register_setting( 'ridemaster_settings', 'rm_commission_rate', [
			'type'              => 'number',
			'sanitize_callback' => function( $val ) {
				$val = floatval( $val );
				return max( 0, min( 100, $val ) );
			},
			'default'           => 0,
		] );
		add_settings_field( 'rm_commission_rate', 'Commission Rate (%)', function() {
			$value = get_option( 'rm_commission_rate', 0 );
			printf(
				'<input type="number" name="rm_commission_rate" value="%s" min="0" max="100" step="0.1" class="small-text" /> %%',
				esc_attr( $value )
			);
			echo '<p class="description">Applied to new orders only. Set to 0 for launch.</p>';
		}, 'ridemaster', 'rm_commission_section' );

		// --- Payout section ---
		add_settings_section( 'rm_payout_section', 'Payouts', null, 'ridemaster' );

		register_setting( 'ridemaster_settings', 'rm_payout_delay_days', [
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 15,
		] );
		add_settings_field( 'rm_payout_delay_days', 'Payout Delay (days before camp)', function() {
			$value = get_option( 'rm_payout_delay_days', 15 );
			printf(
				'<input type="number" name="rm_payout_delay_days" value="%s" min="1" max="60" class="small-text" /> days',
				esc_attr( $value )
			);
			echo '<p class="description">Transfers are triggered this many days before the camp start date.</p>';
		}, 'ridemaster', 'rm_payout_section' );

		// --- Insurance section ---
		add_settings_section( 'rm_insurance_section', 'Insurance & Compliance', null, 'ridemaster' );

		register_setting( 'ridemaster_settings', 'rm_insurance_pdf_id', [
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		] );
		add_settings_field( 'rm_insurance_pdf_id', 'Insurance Notice PDF', [ $this, 'render_pdf_upload_field' ], 'ridemaster', 'rm_insurance_section' );

		register_setting( 'ridemaster_settings', 'rm_insurance_label', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'Individual Accident Insurance included',
		] );
		add_settings_field( 'rm_insurance_label', 'Insurance Label', function() {
			$value = get_option( 'rm_insurance_label', 'Individual Accident Insurance included' );
			printf(
				'<input type="text" name="rm_insurance_label" value="%s" class="regular-text" />',
				esc_attr( $value )
			);
		}, 'ridemaster', 'rm_insurance_section' );

		register_setting( 'ridemaster_settings', 'rm_cgv_page_id', [
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => 0,
		] );
		add_settings_field( 'rm_cgv_page_id', 'Terms & Conditions Page', function() {
			$value = get_option( 'rm_cgv_page_id', 0 );
			wp_dropdown_pages( [
				'name'             => 'rm_cgv_page_id',
				'selected'         => $value,
				'show_option_none' => '— Select a page —',
			] );
		}, 'ridemaster', 'rm_insurance_section' );
	}

	// =========================================================================
	// SETTINGS PAGE RENDERERS
	// =========================================================================

	/**
	 * Render the main settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>RideMaster Settings</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'ridemaster_settings' );
				do_settings_sections( 'ridemaster' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the mode toggle field.
	 */
	public function render_mode_field() {
		$mode = get_option( 'rm_stripe_mode', 'test' );
		?>
		<label>
			<input type="radio" name="rm_stripe_mode" value="test" <?php checked( $mode, 'test' ); ?> />
			Test
		</label>
		&nbsp;&nbsp;
		<label>
			<input type="radio" name="rm_stripe_mode" value="live" <?php checked( $mode, 'live' ); ?> />
			Live
		</label>
		<p class="description">Use Test mode during development. Switch to Live when ready for real payments.</p>
		<?php
	}

	/**
	 * Render PDF upload field for insurance notice.
	 */
	public function render_pdf_upload_field() {
		$pdf_id  = get_option( 'rm_insurance_pdf_id', 0 );
		$pdf_url = $pdf_id ? wp_get_attachment_url( $pdf_id ) : '';
		$pdf_name = $pdf_id ? basename( get_attached_file( $pdf_id ) ) : '';
		?>
		<div id="rm-insurance-pdf-wrap">
			<?php if ( $pdf_url ) : ?>
				<p><a href="<?php echo esc_url( $pdf_url ); ?>" target="_blank"><?php echo esc_html( $pdf_name ); ?></a></p>
			<?php endif; ?>
			<input type="hidden" name="rm_insurance_pdf_id" id="rm_insurance_pdf_id" value="<?php echo esc_attr( $pdf_id ); ?>" />
			<button type="button" class="button" id="rm-upload-insurance-pdf">
				<?php echo $pdf_id ? 'Change PDF' : 'Upload PDF'; ?>
			</button>
			<?php if ( $pdf_id ) : ?>
				<button type="button" class="button" id="rm-remove-insurance-pdf">Remove</button>
			<?php endif; ?>
		</div>
		<script>
		jQuery(function($) {
			$('#rm-upload-insurance-pdf').on('click', function(e) {
				e.preventDefault();
				var frame = wp.media({ title: 'Select Insurance PDF', library: { type: 'application/pdf' }, multiple: false });
				frame.on('select', function() {
					var attachment = frame.state().get('selection').first().toJSON();
					$('#rm_insurance_pdf_id').val(attachment.id);
					$(e.target).text('Change PDF');
				});
				frame.open();
			});
			$('#rm-remove-insurance-pdf').on('click', function() {
				$('#rm_insurance_pdf_id').val('0');
				$(this).remove();
			});
		});
		</script>
		<?php
		wp_enqueue_media();
	}

	/**
	 * Render the Payments admin page (placeholder).
	 */
	public function render_payments_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1>RideMaster Payments</h1>
			<p>Payment dashboard will be available once the checkout system is implemented.</p>
		</div>
		<?php
	}

	// =========================================================================
	// COACH STRIPE CONNECT ONBOARDING
	// =========================================================================

	/**
	 * AJAX: Generate Stripe Express onboarding link and return it.
	 */
	public function ajax_stripe_connect() {
		check_ajax_referer( 'rm_stripe_connect', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'Not logged in.' );
		}

		$user = get_userdata( $user_id );
		if ( ! $user || ! in_array( 'coach_role', (array) $user->roles, true ) ) {
			wp_send_json_error( 'Not authorized.' );
		}

		if ( ! $this->is_configured() ) {
			wp_send_json_error( 'Stripe is not configured. Please contact the administrator.' );
		}

		try {
			// Check if coach already has a Stripe account (reconnect scenario).
			$existing_account_id = get_user_meta( $user_id, 'stripe_account_id', true );

			if ( ! $existing_account_id ) {
				// Create a new Express account.
				$account = \Stripe\Account::create( [
					'type'         => 'express',
					'country'      => 'FR',
					'email'        => $user->user_email,
					'capabilities' => [
						'transfers' => [ 'requested' => true ],
					],
					'business_type' => 'individual',
					'metadata'      => [
						'rm_user_id'  => $user_id,
						'rm_coach_id' => get_user_meta( $user_id, 'coach_post_id', true ),
					],
				] );
				$existing_account_id = $account->id;
				update_user_meta( $user_id, 'stripe_account_id', $existing_account_id );
			}

			// Create an Account Link for onboarding.
			$return_url  = add_query_arg( 'rm_stripe_return', '1', home_url( '/coach-dashboard/' ) );
			$refresh_url = add_query_arg( 'rm_stripe_refresh', '1', home_url( '/coach-dashboard/' ) );

			$account_link = \Stripe\AccountLink::create( [
				'account'     => $existing_account_id,
				'refresh_url' => $refresh_url,
				'return_url'  => $return_url,
				'type'        => 'account_onboarding',
			] );

			wp_send_json_success( [ 'url' => $account_link->url ] );

		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			error_log( '[RM Payments] Stripe Connect error: ' . $e->getMessage() );
			wp_send_json_error( 'Stripe error: ' . $e->getMessage() );
		}
	}

	/**
	 * Handle the return from Stripe onboarding.
	 */
	public function handle_stripe_return() {
		if ( ! isset( $_GET['rm_stripe_return'] ) && ! isset( $_GET['rm_stripe_refresh'] ) ) {
			return;
		}

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}

		$account_id = get_user_meta( $user_id, 'stripe_account_id', true );
		if ( ! $account_id || ! $this->is_configured() ) {
			return;
		}

		// If refresh URL was hit, redirect back to start onboarding again.
		if ( isset( $_GET['rm_stripe_refresh'] ) ) {
			try {
				$account_link = \Stripe\AccountLink::create( [
					'account'     => $account_id,
					'refresh_url' => add_query_arg( 'rm_stripe_refresh', '1', home_url( '/coach-dashboard/' ) ),
					'return_url'  => add_query_arg( 'rm_stripe_return', '1', home_url( '/coach-dashboard/' ) ),
					'type'        => 'account_onboarding',
				] );
				wp_redirect( $account_link->url );
				exit;
			} catch ( \Stripe\Exception\ApiErrorException $e ) {
				error_log( '[RM Payments] Stripe refresh error: ' . $e->getMessage() );
			}
		}

		// Return URL: check account status.
		try {
			$account  = \Stripe\Account::retrieve( $account_id );
			$complete = $account->charges_enabled && $account->payouts_enabled;
			update_user_meta( $user_id, 'stripe_onboarding_complete', $complete ? '1' : '0' );
			update_user_meta( $user_id, 'stripe_account_status', $complete ? 'active' : 'pending' );
		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			error_log( '[RM Payments] Stripe account check error: ' . $e->getMessage() );
		}

		// Redirect to clean dashboard URL.
		wp_redirect( home_url( '/coach-dashboard/' ) );
		exit;
	}

	/**
	 * AJAX: Disconnect coach from Stripe.
	 */
	public function ajax_stripe_disconnect() {
		check_ajax_referer( 'rm_stripe_connect', 'nonce' );

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			wp_send_json_error( 'Not logged in.' );
		}

		delete_user_meta( $user_id, 'stripe_account_id' );
		delete_user_meta( $user_id, 'stripe_onboarding_complete' );
		delete_user_meta( $user_id, 'stripe_account_status' );

		wp_send_json_success( [ 'message' => 'Stripe account disconnected.' ] );
	}

	// =========================================================================
	// STRIPE WEBHOOK
	// =========================================================================

	/**
	 * Register the Stripe webhook REST endpoint.
	 * URL: /wp-json/ridemaster/v1/stripe-webhook
	 */
	public function register_webhook_endpoint() {
		register_rest_route( 'ridemaster/v1', '/stripe-webhook', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_webhook' ],
			'permission_callback' => '__return_true',
		] );
	}

	/**
	 * Handle incoming Stripe webhook events.
	 */
	public function handle_webhook( \WP_REST_Request $request ) {
		$payload = $request->get_body();
		$sig     = $request->get_header( 'Stripe-Signature' );
		$secret  = get_option( 'rm_stripe_webhook_secret', '' );

		if ( ! $secret ) {
			return new \WP_REST_Response( [ 'error' => 'Webhook secret not configured.' ], 400 );
		}

		try {
			$event = \Stripe\Webhook::constructEvent( $payload, $sig, $secret );
		} catch ( \Stripe\Exception\SignatureVerificationException $e ) {
			error_log( '[RM Payments] Webhook signature failed: ' . $e->getMessage() );
			return new \WP_REST_Response( [ 'error' => 'Invalid signature.' ], 400 );
		} catch ( \Exception $e ) {
			error_log( '[RM Payments] Webhook error: ' . $e->getMessage() );
			return new \WP_REST_Response( [ 'error' => $e->getMessage() ], 400 );
		}

		switch ( $event->type ) {
			case 'account.updated':
				$this->on_account_updated( $event->data->object );
				break;
			default:
				error_log( '[RM Payments] Unhandled webhook event: ' . $event->type );
				break;
		}

		return new \WP_REST_Response( [ 'received' => true ], 200 );
	}

	/**
	 * Handle account.updated webhook — update coach Stripe status.
	 */
	private function on_account_updated( $account ) {
		$users = get_users( [
			'meta_key'   => 'stripe_account_id',
			'meta_value' => $account->id,
			'number'     => 1,
		] );

		if ( empty( $users ) ) {
			error_log( '[RM Payments] account.updated: no WP user for Stripe ' . $account->id );
			return;
		}

		$user_id  = $users[0]->ID;
		$complete = $account->charges_enabled && $account->payouts_enabled;

		update_user_meta( $user_id, 'stripe_onboarding_complete', $complete ? '1' : '0' );
		update_user_meta( $user_id, 'stripe_account_status', $complete ? 'active' : 'pending' );
	}
}
