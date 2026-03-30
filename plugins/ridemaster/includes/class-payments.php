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

		// Register WooCommerce payment gateway.
		add_filter( 'woocommerce_payment_gateways', [ $this, 'register_gateway' ] );

		// AJAX: create PaymentIntent for checkout.
		add_action( 'wp_ajax_rm_create_payment_intent', [ $this, 'ajax_create_payment_intent' ] );
		add_action( 'wp_ajax_nopriv_rm_create_payment_intent', [ $this, 'ajax_create_payment_intent' ] );

		// Insurance checkbox on checkout.
		add_action( 'woocommerce_review_order_before_submit', [ $this, 'render_insurance_checkbox' ] );
		add_action( 'woocommerce_checkout_process', [ $this, 'validate_insurance_checkbox' ] );

		// Attach insurance PDF to order confirmation email.
		add_filter( 'woocommerce_email_attachments', [ $this, 'attach_insurance_pdf' ], 10, 4 );

		// Demo data AJAX handlers.
		add_action( 'wp_ajax_rm_generate_demo_data', [ $this, 'ajax_generate_demo_data' ] );
		add_action( 'wp_ajax_rm_clean_demo_data', [ $this, 'ajax_clean_demo_data' ] );
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

		// Get coaches for the demo data selector.
		$coaches = get_posts( [ 'post_type' => 'coach', 'posts_per_page' => -1, 'post_status' => 'any' ] );
		$demo_nonce = wp_create_nonce( 'rm_demo_data' );
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

			<hr>
			<h2>Demo Data</h2>
			<p class="description">Generate realistic demo bookings to preview the coach dashboard and admin payments views. Demo orders are tagged and can be cleaned up easily.</p>

			<table class="form-table">
				<tr>
					<th>Coach</th>
					<td>
						<select id="rm-demo-coach">
							<option value="">— Select a coach —</option>
							<?php foreach ( $coaches as $coach ) : ?>
								<option value="<?php echo esc_attr( $coach->ID ); ?>">
									<?php echo esc_html( $coach->post_title ); ?> (ID: <?php echo $coach->ID; ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th>Number of bookings</th>
					<td>
						<select id="rm-demo-count">
							<option value="5">5 bookings</option>
							<option value="10" selected>10 bookings</option>
							<option value="20">20 bookings</option>
						</select>
					</td>
				</tr>
				<tr>
					<th>Actions</th>
					<td>
						<button type="button" class="button button-primary" id="rm-generate-demo" style="margin-right:12px;">
							Generate Demo Data
						</button>
						<button type="button" class="button" id="rm-clean-demo" style="color:#dc2626;">
							Clean All Demo Data
						</button>
						<p id="rm-demo-status" style="margin-top:10px;font-weight:600;"></p>
					</td>
				</tr>
			</table>

			<script>
			jQuery(function($) {
				$('#rm-generate-demo').on('click', function() {
					var coachId = $('#rm-demo-coach').val();
					if ( ! coachId ) { alert('Please select a coach.'); return; }
					var btn = $(this);
					btn.prop('disabled', true).text('Generating...');
					$('#rm-demo-status').text('').css('color', '');
					$.post(ajaxurl, {
						action: 'rm_generate_demo_data',
						nonce: '<?php echo $demo_nonce; ?>',
						coach_id: coachId,
						count: $('#rm-demo-count').val()
					}, function(resp) {
						btn.prop('disabled', false).text('Generate Demo Data');
						if ( resp.success ) {
							$('#rm-demo-status').text(resp.data.message).css('color', '#065f46');
						} else {
							$('#rm-demo-status').text(resp.data || 'Error').css('color', '#dc2626');
						}
					});
				});

				$('#rm-clean-demo').on('click', function() {
					if ( ! confirm('Delete ALL demo orders? This cannot be undone.') ) return;
					var btn = $(this);
					btn.prop('disabled', true).text('Cleaning...');
					$('#rm-demo-status').text('').css('color', '');
					$.post(ajaxurl, {
						action: 'rm_clean_demo_data',
						nonce: '<?php echo $demo_nonce; ?>'
					}, function(resp) {
						btn.prop('disabled', false).text('Clean All Demo Data');
						if ( resp.success ) {
							$('#rm-demo-status').text(resp.data.message).css('color', '#065f46');
						} else {
							$('#rm-demo-status').text(resp.data || 'Error').css('color', '#dc2626');
						}
					});
				});
			});
			</script>
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
	 * Render the Payments admin dashboard page.
	 */
	public function render_payments_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$tab = sanitize_text_field( $_GET['tab'] ?? 'escrow' );
		$tabs = [
			'escrow'        => 'Escrow',
			'payouts'       => 'Payouts',
			'coaches'       => 'Coaches',
			'hotels'        => 'Hotels',
			'cancellations' => 'Cancellations',
		];
		?>
		<div class="wrap">
			<h1>RideMaster Payments</h1>
			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=ridemaster-payments&tab=' . $slug ) ); ?>"
					   class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<div style="margin-top:20px;">
				<?php
				switch ( $tab ) {
					case 'escrow':
						$this->render_tab_escrow();
						break;
					case 'payouts':
						$this->render_tab_payouts();
						break;
					case 'coaches':
						$this->render_tab_coaches();
						break;
					case 'hotels':
						$this->render_tab_hotels();
						break;
					case 'cancellations':
						$this->render_tab_cancellations();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	private function render_tab_escrow() {
		$stats = RM_Payout_Cron::get_stats();
		$nonce = wp_create_nonce( 'rm_run_payouts' );
		?>
		<div style="display:flex;gap:24px;margin-bottom:24px;">
			<div style="background:#fff;padding:20px;border:1px solid #ddd;border-radius:8px;flex:1;">
				<h3 style="margin:0 0 8px;"><?php echo wp_kses_post( wc_price( $stats['escrow_total'] ) ); ?></h3>
				<p style="margin:0;color:#666;"><?php echo $stats['escrow_count']; ?> orders in escrow</p>
			</div>
			<div style="background:#fff;padding:20px;border:1px solid #ddd;border-radius:8px;flex:1;">
				<h3 style="margin:0 0 8px;"><?php echo wp_kses_post( wc_price( $stats['paid_total'] ) ); ?></h3>
				<p style="margin:0;color:#666;"><?php echo $stats['paid_count']; ?> payouts completed</p>
			</div>
			<div style="background:#fff;padding:20px;border:1px solid #ddd;border-radius:8px;flex:1;">
				<h3 style="margin:0 0 8px;<?php echo $stats['failed_count'] > 0 ? 'color:#dc2626;' : ''; ?>"><?php echo $stats['failed_count']; ?></h3>
				<p style="margin:0;color:#666;">Failed payouts</p>
			</div>
		</div>
		<button type="button" class="button button-primary" onclick="
			if(!confirm('Run payouts now?'))return;
			fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'action=rm_run_payouts_now&nonce=<?php echo $nonce; ?>'})
			.then(r=>r.json()).then(r=>{alert(r.data?.message||'Done');location.reload();});
		">Run Payouts Now</button>

		<h3>Upcoming Payouts</h3>
		<?php if ( empty( $stats['upcoming'] ) ) : ?>
			<p>No upcoming payouts.</p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>Order</th><th>Camp</th><th>Amount</th><th>Payout Date</th></tr></thead>
				<tbody>
				<?php foreach ( $stats['upcoming'] as $item ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $item['order_id'] . '&action=edit' ) ); ?>">#<?php echo $item['order_id']; ?></a></td>
						<td><?php echo $item['camp_id'] ? get_the_title( $item['camp_id'] ) : '—'; ?></td>
						<td><?php echo wc_price( $item['amount'] ); ?></td>
						<td><?php echo esc_html( $item['payout_date'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif;
	}

	private function render_tab_payouts() {
		$orders = wc_get_orders( [
			'limit'      => 50,
			'orderby'    => 'date',
			'order'      => 'DESC',
			'meta_query' => [
				[ 'key' => '_payout_status', 'value' => [ 'paid', 'failed' ], 'compare' => 'IN' ],
			],
		] );
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr><th>Order</th><th>Camp</th><th>Coach Amount</th><th>Hotel Amount</th><th>Payout Date</th><th>Status</th></tr></thead>
			<tbody>
			<?php if ( empty( $orders ) ) : ?>
				<tr><td colspan="6">No payouts yet.</td></tr>
			<?php else : ?>
				<?php foreach ( $orders as $order ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) ); ?>">#<?php echo $order->get_id(); ?></a></td>
						<td><?php $cid = $order->get_meta( '_camp_id' ); echo $cid ? get_the_title( $cid ) : '—'; ?></td>
						<td><?php echo wc_price( $order->get_meta( '_amount_coach' ) ); ?></td>
						<td><?php echo wc_price( $order->get_meta( '_amount_hotel' ) ); ?></td>
						<td><?php echo esc_html( $order->get_meta( '_payout_date_actual' ) ?: $order->get_meta( '_payout_date' ) ); ?></td>
						<td>
							<?php
							$status = $order->get_meta( '_payout_status' );
							if ( $status === 'paid' ) {
								echo '<span style="color:#065f46;font-weight:600;">Paid</span>';
							} else {
								echo '<span style="color:#dc2626;font-weight:600;">Failed</span>';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_tab_coaches() {
		$coaches = get_posts( [ 'post_type' => 'coach', 'posts_per_page' => -1, 'post_status' => 'any' ] );
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr><th>Coach</th><th>Stripe Account</th><th>Status</th></tr></thead>
			<tbody>
			<?php foreach ( $coaches as $coach ) :
				$user_id    = $coach->post_author;
				$stripe_id  = get_user_meta( $user_id, 'stripe_account_id', true );
				$stripe_ok  = get_user_meta( $user_id, 'stripe_onboarding_complete', true ) === '1';
			?>
				<tr>
					<td><?php echo esc_html( $coach->post_title ); ?></td>
					<td><?php echo $stripe_id ? '<code>' . esc_html( $stripe_id ) . '</code>' : '—'; ?></td>
					<td>
						<?php
						if ( ! $stripe_id ) {
							echo '<span style="color:#9ca3af;">Not connected</span>';
						} elseif ( $stripe_ok ) {
							echo '<span style="color:#065f46;font-weight:600;">&#10003; Active</span>';
						} else {
							echo '<span style="color:#92400e;">&#9888; Pending</span>';
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_tab_hotels() {
		$hotels = get_posts( [ 'post_type' => 'hotel', 'posts_per_page' => -1, 'post_status' => 'any' ] );
		if ( empty( $hotels ) ) {
			echo '<p>No hotels registered yet. Hotels are created when coaches add accommodation to their camps.</p>';
			return;
		}
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr><th>Hotel</th><th>Country</th><th>Stripe Account</th><th>Status</th></tr></thead>
			<tbody>
			<?php foreach ( $hotels as $hotel ) :
				$stripe_id = get_post_meta( $hotel->ID, 'hotel_stripe_account_id', true );
				$status    = get_post_meta( $hotel->ID, 'hotel_stripe_status', true );
				$country   = get_post_meta( $hotel->ID, 'hotel_country', true );
			?>
				<tr>
					<td><?php echo esc_html( $hotel->post_title ); ?></td>
					<td><?php echo esc_html( $country ?: '—' ); ?></td>
					<td><?php echo $stripe_id ? '<code>' . esc_html( $stripe_id ) . '</code>' : '—'; ?></td>
					<td>
						<?php
						if ( $status === 'verified' ) {
							echo '<span style="color:#065f46;">&#10003; Verified</span>';
						} elseif ( $status === 'pending' ) {
							echo '<span style="color:#92400e;">Pending</span>';
						} elseif ( $status === 'requires_action' ) {
							$err = get_post_meta( $hotel->ID, 'hotel_stripe_error', true );
							echo '<span style="color:#dc2626;">Requires action</span>';
							if ( $err ) echo '<br><small>' . esc_html( $err ) . '</small>';
						} else {
							echo '<span style="color:#9ca3af;">Not set up</span>';
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_tab_cancellations() {
		$orders = wc_get_orders( [
			'limit'      => 50,
			'orderby'    => 'date',
			'order'      => 'DESC',
			'status'     => [ 'cancelled', 'refunded' ],
			'meta_query' => [
				[ 'key' => '_cancellation_date', 'compare' => 'EXISTS' ],
			],
		] );
		?>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr><th>Order</th><th>Camp</th><th>Cancelled By</th><th>Tier</th><th>Refund</th><th>Date</th><th>Alert</th></tr></thead>
			<tbody>
			<?php if ( empty( $orders ) ) : ?>
				<tr><td colspan="7">No cancellations yet.</td></tr>
			<?php else : ?>
				<?php foreach ( $orders as $order ) : ?>
					<tr>
						<td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $order->get_id() . '&action=edit' ) ); ?>">#<?php echo $order->get_id(); ?></a></td>
						<td><?php $cid = $order->get_meta( '_camp_id' ); echo $cid ? get_the_title( $cid ) : '—'; ?></td>
						<td><?php echo esc_html( ucfirst( $order->get_meta( '_cancellation_by' ) ) ); ?></td>
						<td><?php echo esc_html( $order->get_meta( '_cancellation_tier' ) . '%' ); ?></td>
						<td><?php echo wc_price( $order->get_meta( '_refund_amount' ) ); ?></td>
						<td><?php echo esc_html( $order->get_meta( '_cancellation_date' ) ); ?></td>
						<td>
							<?php if ( $order->get_meta( '_cancellation_alert' ) ) : ?>
								<span style="color:#dc2626;font-weight:600;">&#9888; Action needed</span>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
			</tbody>
		</table>
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

	// =========================================================================
	// WOOCOMMERCE GATEWAY REGISTRATION
	// =========================================================================

	/**
	 * Register our payment gateway with WooCommerce.
	 */
	public function register_gateway( $gateways ) {
		require_once RM_PLUGIN_DIR . 'includes/class-payment-gateway.php';
		$gateways[] = 'RM_Payment_Gateway';
		return $gateways;
	}

	// =========================================================================
	// AJAX: CREATE PAYMENT INTENT
	// =========================================================================

	/**
	 * AJAX: Create a Stripe PaymentIntent for the current cart.
	 */
	public function ajax_create_payment_intent() {
		check_ajax_referer( 'rm_stripe_checkout', 'nonce' );

		if ( ! $this->is_configured() ) {
			wp_send_json_error( 'Stripe is not configured.' );
		}

		$cart_total = WC()->cart->get_total( 'raw' );
		$amount     = intval( round( floatval( $cart_total ) * 100 ) );

		if ( $amount < 50 ) { // Stripe minimum is 50 cents.
			wp_send_json_error( 'Order total is too low.' );
		}

		try {
			$pi = \Stripe\PaymentIntent::create( [
				'amount'   => $amount,
				'currency' => strtolower( get_woocommerce_currency() ),
				'metadata' => [
					'rm_source' => 'checkout',
				],
			] );

			wp_send_json_success( [
				'client_secret' => $pi->client_secret,
				'pi_id'         => $pi->id,
			] );

		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			error_log( '[RM Payments] PaymentIntent create error: ' . $e->getMessage() );
			wp_send_json_error( 'Payment error: ' . $e->getMessage() );
		}
	}

	// =========================================================================
	// INSURANCE CHECKBOX (CHECKOUT)
	// =========================================================================

	/**
	 * Render the mandatory insurance/CGV checkbox on checkout.
	 */
	public function render_insurance_checkbox() {
		$cgv_page_id = get_option( 'rm_cgv_page_id', 0 );
		$cgv_url     = $cgv_page_id ? get_permalink( $cgv_page_id ) : '#';
		$pdf_id      = get_option( 'rm_insurance_pdf_id', 0 );
		$pdf_url     = $pdf_id ? wp_get_attachment_url( $pdf_id ) : '#';

		echo '<p class="form-row rm-insurance-checkbox" style="margin-top:16px;">';
		echo '<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">';
		echo '<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="rm_insurance_accepted" id="rm_insurance_accepted" />';
		echo '<span class="woocommerce-terms-and-conditions-checkbox-text">';
		printf(
			'I accept the <a href="%s" target="_blank">Terms &amp; Conditions</a> and confirm I have read the <a href="%s" target="_blank">accident insurance information notice</a>.',
			esc_url( $cgv_url ),
			esc_url( $pdf_url )
		);
		echo '</span>';
		echo '</label>';
		echo '</p>';
	}

	/**
	 * Validate the insurance checkbox.
	 */
	public function validate_insurance_checkbox() {
		if ( empty( $_POST['rm_insurance_accepted'] ) ) {
			wc_add_notice( 'You must accept the Terms & Conditions and acknowledge the insurance notice to proceed.', 'error' );
		}
	}

	/**
	 * Attach insurance PDF to the order confirmation email.
	 */
	public function attach_insurance_pdf( $attachments, $email_id, $order, $email ) {
		if ( ! in_array( $email_id, [ 'customer_processing_order', 'customer_completed_order', 'customer_on_hold_order' ], true ) ) {
			return $attachments;
		}

		$pdf_id = get_option( 'rm_insurance_pdf_id', 0 );
		if ( ! $pdf_id ) {
			return $attachments;
		}

		$pdf_path = get_attached_file( $pdf_id );
		if ( $pdf_path && file_exists( $pdf_path ) ) {
			$attachments[] = $pdf_path;
		}

		return $attachments;
	}

	// =========================================================================
	// DEMO DATA
	// =========================================================================

	/**
	 * AJAX: Generate demo booking data for a coach.
	 */
	public function ajax_generate_demo_data() {
		check_ajax_referer( 'rm_demo_data', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Not authorized.' );
		}

		$coach_post_id = intval( $_POST['coach_id'] ?? 0 );
		$count         = intval( $_POST['count'] ?? 10 );
		$count         = max( 1, min( 50, $count ) );

		if ( ! $coach_post_id ) {
			wp_send_json_error( 'Invalid coach.' );
		}

		$coach_post = get_post( $coach_post_id );
		if ( ! $coach_post || $coach_post->post_type !== 'coach' ) {
			wp_send_json_error( 'Coach not found.' );
		}

		$coach_user_id   = $coach_post->post_author;
		$coach_stripe_id = get_user_meta( $coach_user_id, 'stripe_account_id', true ) ?: 'acct_demo_' . $coach_post_id;

		// Find this coach's camps.
		$camp_ids = get_posts( [
			'post_type'      => 'product',
			'posts_per_page' => -1,
			'post_status'    => 'publish',
			'fields'         => 'ids',
			'meta_query'     => [
				[ 'key' => '_coach_post_id', 'value' => $coach_post_id ],
			],
		] );

		if ( empty( $camp_ids ) ) {
			wp_send_json_error( 'This coach has no published camps. Create at least one camp first.' );
		}

		// Demo rider names.
		$riders = [
			[ 'first' => 'Sophie', 'last' => 'Martin' ],
			[ 'first' => 'Lucas', 'last' => 'Bernard' ],
			[ 'first' => 'Emma', 'last' => 'Dubois' ],
			[ 'first' => 'Hugo', 'last' => 'Thomas' ],
			[ 'first' => 'Lea', 'last' => 'Robert' ],
			[ 'first' => 'Nathan', 'last' => 'Richard' ],
			[ 'first' => 'Chloe', 'last' => 'Petit' ],
			[ 'first' => 'Louis', 'last' => 'Durand' ],
			[ 'first' => 'Alice', 'last' => 'Leroy' ],
			[ 'first' => 'Gabriel', 'last' => 'Moreau' ],
			[ 'first' => 'Jade', 'last' => 'Simon' ],
			[ 'first' => 'Raphael', 'last' => 'Laurent' ],
			[ 'first' => 'Lina', 'last' => 'Michel' ],
			[ 'first' => 'Adam', 'last' => 'Garcia' ],
			[ 'first' => 'Manon', 'last' => 'David' ],
			[ 'first' => 'Jules', 'last' => 'Bertrand' ],
			[ 'first' => 'Camille', 'last' => 'Roux' ],
			[ 'first' => 'Arthur', 'last' => 'Vincent' ],
			[ 'first' => 'Sarah', 'last' => 'Fournier' ],
			[ 'first' => 'Tom', 'last' => 'Morel' ],
		];

		// Payout statuses to distribute.
		$status_distribution = [
			'pending'   => 0.40,
			'paid'      => 0.35,
			'cancelled' => 0.15,
			'failed'    => 0.10,
		];

		$commission_rate = floatval( get_option( 'rm_commission_rate', 0 ) ) / 100;
		$payout_delay    = intval( get_option( 'rm_payout_delay_days', 15 ) );
		$created_count   = 0;

		for ( $i = 0; $i < $count; $i++ ) {
			$camp_id = $camp_ids[ array_rand( $camp_ids ) ];
			$product = wc_get_product( $camp_id );
			if ( ! $product ) {
				continue;
			}

			$price        = floatval( $product->get_price() ) ?: rand( 500, 2000 );
			$quantity     = rand( 1, 3 );
			$total        = $price * $quantity;
			$rider        = $riders[ $i % count( $riders ) ];
			$hotel_amount = floatval( get_post_meta( $camp_id, '_hotel_amount', true ) ) * $quantity;
			$commission   = round( $total * $commission_rate, 2 );
			$coach_amount = round( $total - $commission - $hotel_amount, 2 );

			$hotel_id        = get_post_meta( $camp_id, '_hotel_id', true );
			$hotel_stripe_id = $hotel_id ? ( get_post_meta( $hotel_id, 'hotel_stripe_account_id', true ) ?: 'acct_hotel_demo_' . $hotel_id ) : '';

			// Determine payout status based on distribution.
			$rand = mt_rand( 0, 100 ) / 100;
			$cumulative = 0;
			$payout_status = 'pending';
			foreach ( $status_distribution as $status => $pct ) {
				$cumulative += $pct;
				if ( $rand <= $cumulative ) {
					$payout_status = $status;
					break;
				}
			}

			// Camp start date: random between 10 days ago and 60 days from now.
			$camp_start_offset = rand( -10, 60 );
			$camp_start_date   = gmdate( 'Y-m-d', strtotime( "+{$camp_start_offset} days" ) );
			$payout_date       = gmdate( 'Y-m-d', strtotime( $camp_start_date ) - ( $payout_delay * DAY_IN_SECONDS ) );

			// Order date: random between 60 days ago and 5 days ago.
			$order_date_offset = rand( 5, 60 );
			$order_date        = gmdate( 'Y-m-d H:i:s', strtotime( "-{$order_date_offset} days" ) );

			// Create the WooCommerce order.
			$order = wc_create_order( [
				'status' => $payout_status === 'paid' ? 'completed' : ( in_array( $payout_status, [ 'cancelled' ], true ) ? 'cancelled' : 'processing' ),
			] );

			if ( is_wp_error( $order ) ) {
				continue;
			}

			// Add product.
			$order->add_product( $product, $quantity );

			// Set billing info.
			$order->set_billing_first_name( $rider['first'] );
			$order->set_billing_last_name( $rider['last'] );
			$order->set_billing_email( strtolower( $rider['first'] ) . '.' . strtolower( $rider['last'] ) . '@demo.ridemaster.eu' );
			$order->set_billing_country( 'FR' );
			$order->set_date_created( $order_date );
			$order->set_total( $total );
			$order->set_payment_method( 'ridemaster_stripe' );
			$order->set_payment_method_title( 'Credit Card (Stripe)' );

			// Set all payment metas.
			$order->update_meta_data( '_rm_demo_order', '1' );
			$order->update_meta_data( '_stripe_payment_intent_id', 'pi_demo_' . $order->get_id() );
			$order->update_meta_data( '_camp_id', $camp_id );
			$order->update_meta_data( '_coach_stripe_account_id', $coach_stripe_id );
			$order->update_meta_data( '_hotel_stripe_account_id', $hotel_stripe_id );
			$order->update_meta_data( '_amount_total', $total );
			$order->update_meta_data( '_amount_commission', $commission );
			$order->update_meta_data( '_amount_hotel', $hotel_amount );
			$order->update_meta_data( '_amount_coach', $coach_amount );
			$order->update_meta_data( '_camp_start_date', $camp_start_date );
			$order->update_meta_data( '_payout_status', $payout_status );
			$order->update_meta_data( '_payout_date', $payout_date );

			// Add extra metas based on status.
			if ( $payout_status === 'paid' ) {
				$order->update_meta_data( '_payout_date_actual', gmdate( 'Y-m-d', strtotime( $payout_date ) + rand( 0, 2 ) * DAY_IN_SECONDS ) );
				$order->update_meta_data( '_transfer_coach_id', 'tr_demo_coach_' . $order->get_id() );
				if ( $hotel_amount > 0 ) {
					$order->update_meta_data( '_transfer_hotel_id', 'tr_demo_hotel_' . $order->get_id() );
				}
			}

			if ( $payout_status === 'cancelled' ) {
				$tiers = [ 100, 90, 50, 25 ];
				$tier  = $tiers[ array_rand( $tiers ) ];
				$refund_amount = round( $total * $tier / 100, 2 );
				$order->update_meta_data( '_cancellation_date', gmdate( 'Y-m-d H:i:s', strtotime( "-" . rand( 1, 30 ) . " days" ) ) );
				$order->update_meta_data( '_cancellation_by', rand( 0, 3 ) === 0 ? 'coach' : 'rider' );
				$order->update_meta_data( '_cancellation_tier', $tier );
				$order->update_meta_data( '_refund_amount', $refund_amount );
				$order->update_meta_data( '_refund_stripe_id', 're_demo_' . $order->get_id() );
			}

			$order->add_order_note( 'Demo order generated by RideMaster.' );
			$order->save();
			$created_count++;
		}

		wp_send_json_success( [
			'message' => sprintf( '%d demo orders created for coach "%s".', $created_count, $coach_post->post_title ),
			'count'   => $created_count,
		] );
	}

	/**
	 * AJAX: Clean all demo data.
	 */
	public function ajax_clean_demo_data() {
		check_ajax_referer( 'rm_demo_data', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Not authorized.' );
		}

		$demo_orders = wc_get_orders( [
			'limit'      => -1,
			'status'     => 'any',
			'meta_query' => [
				[ 'key' => '_rm_demo_order', 'value' => '1' ],
			],
		] );

		$deleted = 0;
		foreach ( $demo_orders as $order ) {
			$order->delete( true ); // Force delete (bypass trash).
			$deleted++;
		}

		wp_send_json_success( [
			'message' => sprintf( '%d demo orders permanently deleted.', $deleted ),
			'count'   => $deleted,
		] );
	}
}
