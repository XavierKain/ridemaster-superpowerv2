<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RM_Admin {

	public function __construct() {
		// Coach status column.
		add_filter( 'manage_coach_posts_columns', array( $this, 'add_status_column' ) );
		add_action( 'manage_coach_posts_custom_column', array( $this, 'render_status_column' ), 10, 2 );
		add_filter( 'manage_edit-coach_sortable_columns', array( $this, 'sortable_status_column' ) );
		add_action( 'restrict_manage_posts', array( $this, 'status_filter_dropdown' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_status_filter' ) );
		add_action( 'admin_footer-edit.php', array( $this, 'status_column_js_css' ) );
		add_action( 'wp_ajax_rm_update_coach_status', array( $this, 'ajax_update_status' ) );

		// Auto-publish on status change.
		add_action( 'set_object_terms', array( $this, 'on_coach_status_change' ), 10, 4 );

		// Media library restriction.
		add_filter( 'ajax_query_attachments_args', array( $this, 'restrict_media_library' ) );

		// Certification document meta box on coach edit screen.
		add_action( 'add_meta_boxes', array( $this, 'add_certification_meta_box' ) );

		// Certification column on All Coaches list.
		add_filter( 'manage_coach_posts_columns', array( $this, 'add_certification_column' ) );
		add_action( 'manage_coach_posts_custom_column', array( $this, 'render_certification_column' ), 10, 2 );

		// Stripe status column on All Coaches list.
		add_filter( 'manage_coach_posts_columns', array( $this, 'add_stripe_column' ), 20 );
		add_action( 'manage_coach_posts_custom_column', array( $this, 'render_stripe_column' ), 10, 2 );
	}

	/* -------------------------------------------------------------------------
	 * 1. Coach Status Column
	 * ---------------------------------------------------------------------- */

	/**
	 * Insert coach_status column after title.
	 */
	public function add_status_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['coach_status'] = __( 'Status', 'ridemaster' );
			}
		}
		return $new;
	}

	/**
	 * Insert certification column before coach_status (after title).
	 */
	public function add_certification_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'coach_status' === $key ) {
				$new['certification_doc'] = __( 'Certification', 'ridemaster' );
			}
			$new[ $key ] = $label;
		}
		return $new;
	}

	/**
	 * Render the certification column: download link or dash.
	 */
	public function render_certification_column( $column, $post_id ) {
		if ( 'certification_doc' !== $column ) {
			return;
		}

		$meta_val = get_post_meta( $post_id, 'certifications_documents', true );
		if ( ! $meta_val ) {
			echo '<span style="color:#cbd5e1;">&mdash;</span>';
			return;
		}

		$att_ids = array_filter( array_map( 'absint', explode( ',', $meta_val ) ) );
		if ( empty( $att_ids ) ) {
			echo '<span style="color:#cbd5e1;">&mdash;</span>';
			return;
		}

		foreach ( $att_ids as $att_id ) {
			$file_url = wp_get_attachment_url( $att_id );
			if ( ! $file_url ) {
				continue;
			}

			$file_name = basename( get_attached_file( $att_id ) );
			$file_ext  = strtoupper( pathinfo( $file_name, PATHINFO_EXTENSION ) );

			printf(
				'<a href="%s" target="_blank" rel="noopener" title="%s" style="display:inline-flex;align-items:center;gap:4px;color:#0D9488;font-weight:500;text-decoration:none;margin-bottom:2px;">' .
					'<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>' .
					'<span>%s</span>' .
				'</a><br>',
				esc_url( $file_url ),
				esc_attr( $file_name ),
				esc_html( $file_ext )
			);
		}
	}

	/**
	 * Render the status badge + hidden select for each row.
	 */
	public function render_status_column( $column, $post_id ) {
		if ( 'coach_status' !== $column ) {
			return;
		}

		$terms  = wp_get_object_terms( $post_id, 'coach-status', array( 'fields' => 'slugs' ) );
		$status = ! empty( $terms ) && ! is_wp_error( $terms ) ? $terms[0] : 'pending';

		$colors = array(
			'pending'   => array( 'color' => '#f59e0b', 'bg' => '#fef3c7' ),
			'validated' => array( 'color' => '#10b981', 'bg' => '#d1fae5' ),
			'suspended' => array( 'color' => '#ef4444', 'bg' => '#fee2e2' ),
		);

		$c = isset( $colors[ $status ] ) ? $colors[ $status ] : $colors['pending'];

		?>
		<div class="rm-status-wrap" data-post-id="<?php echo esc_attr( $post_id ); ?>">
			<span class="rm-status-badge" style="display:inline-block;padding:4px 10px;border-radius:12px;font-size:12px;font-weight:600;cursor:pointer;color:<?php echo esc_attr( $c['color'] ); ?>;background:<?php echo esc_attr( $c['bg'] ); ?>;">
				<?php echo esc_html( ucfirst( $status ) ); ?>
			</span>
			<select class="rm-status-select" style="display:none;">
				<option value="pending"<?php selected( $status, 'pending' ); ?>>Pending</option>
				<option value="validated"<?php selected( $status, 'validated' ); ?>>Validated</option>
				<option value="suspended"<?php selected( $status, 'suspended' ); ?>>Suspended</option>
			</select>
		</div>
		<?php
	}

	/**
	 * Make the status column sortable.
	 */
	public function sortable_status_column( $columns ) {
		$columns['coach_status'] = 'coach_status';
		return $columns;
	}

	/**
	 * Add a status filter dropdown above the coach list table.
	 */
	public function status_filter_dropdown() {
		global $typenow;
		if ( 'coach' !== $typenow ) {
			return;
		}

		$current = isset( $_GET['coach_status_filter'] ) ? sanitize_text_field( $_GET['coach_status_filter'] ) : '';
		?>
		<select name="coach_status_filter">
			<option value=""><?php esc_html_e( 'All', 'ridemaster' ); ?></option>
			<option value="pending"<?php selected( $current, 'pending' ); ?>><?php esc_html_e( 'Pending', 'ridemaster' ); ?></option>
			<option value="validated"<?php selected( $current, 'validated' ); ?>><?php esc_html_e( 'Validated', 'ridemaster' ); ?></option>
			<option value="suspended"<?php selected( $current, 'suspended' ); ?>><?php esc_html_e( 'Suspended', 'ridemaster' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Apply the status filter to the main query.
	 */
	public function apply_status_filter( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		if ( ! isset( $query->query_vars['post_type'] ) || 'coach' !== $query->query_vars['post_type'] ) {
			return;
		}

		if ( empty( $_GET['coach_status_filter'] ) ) {
			return;
		}

		$status = sanitize_text_field( $_GET['coach_status_filter'] );
		if ( ! in_array( $status, array( 'pending', 'validated', 'suspended' ), true ) ) {
			return;
		}

		$query->set( 'tax_query', array(
			array(
				'taxonomy' => 'coach-status',
				'field'    => 'slug',
				'terms'    => $status,
			),
		) );
	}

	/**
	 * Output inline JS and CSS for the status column on the coach list screen.
	 */
	public function status_column_js_css() {
		$screen = get_current_screen();
		if ( ! $screen || 'coach' !== $screen->post_type ) {
			return;
		}

		$nonce = wp_create_nonce( 'rm_coach_status' );
		?>
		<style>
			.column-coach_status { width: 120px; }
			.rm-status-select { min-width: 100px; }
		</style>
		<script>
		(function(){
			var colors = {
				pending:   { color: '#f59e0b', bg: '#fef3c7' },
				validated: { color: '#10b981', bg: '#d1fae5' },
				suspended: { color: '#ef4444', bg: '#fee2e2' }
			};

			document.querySelectorAll('.rm-status-wrap').forEach(function(wrap){
				var badge  = wrap.querySelector('.rm-status-badge');
				var select = wrap.querySelector('.rm-status-select');
				var postId = wrap.getAttribute('data-post-id');

				badge.addEventListener('click', function(){
					badge.style.display  = 'none';
					select.style.display = 'inline-block';
					select.focus();
				});

				select.addEventListener('blur', function(){
					select.style.display = 'none';
					badge.style.display  = 'inline-block';
				});

				select.addEventListener('change', function(){
					var status = select.value;
					var c      = colors[status] || colors.pending;

					badge.textContent       = status.charAt(0).toUpperCase() + status.slice(1);
					badge.style.color       = c.color;
					badge.style.background  = c.bg;
					select.style.display    = 'none';
					badge.style.display     = 'inline-block';

					var data = new FormData();
					data.append('action', 'rm_update_coach_status');
					data.append('nonce', '<?php echo esc_js( $nonce ); ?>');
					data.append('post_id', postId);
					data.append('status', status);

					fetch(ajaxurl, { method: 'POST', body: data })
						.then(function(r) { return r.json(); })
						.then(function(res) {
							if (res.success) {
								location.reload();
							} else {
								alert('Error: ' + (res.data || 'Unknown error'));
								location.reload();
							}
						});
				});
			});
		})();
		</script>
		<?php
	}

	/**
	 * AJAX handler: update coach status taxonomy, auto-publish/draft, email.
	 */
	public function ajax_update_status() {
		if ( ! check_ajax_referer( 'rm_coach_status', 'nonce', false ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$status  = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';

		if ( ! $post_id || ! in_array( $status, array( 'pending', 'validated', 'suspended' ), true ) ) {
			wp_send_json_error( 'Invalid data' );
		}

		wp_set_object_terms( $post_id, $status, 'coach-status' );

		$post = get_post( $post_id );

		if ( 'validated' === $status && 'draft' === $post->post_status ) {
			wp_update_post( array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			) );
			$this->send_coach_approved_email( $post_id );
		}

		if ( 'suspended' === $status && 'publish' === $post->post_status ) {
			wp_update_post( array(
				'ID'          => $post_id,
				'post_status' => 'draft',
			) );
		}

		wp_send_json_success( 'Updated' );
	}

	/* -------------------------------------------------------------------------
	 * 2. Auto-publish on status change
	 * ---------------------------------------------------------------------- */

	/**
	 * When coach-status terms are set, auto-publish if active + draft.
	 */
	public function on_coach_status_change( $post_id, $terms, $tt_ids, $taxonomy ) {
		if ( 'coach-status' !== $taxonomy ) {
			return;
		}

		if ( has_term( 'validated', 'coach-status', $post_id ) && 'draft' === get_post_status( $post_id ) ) {
			wp_update_post( array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			) );
			$this->send_coach_approved_email( $post_id );
		}
	}

	/* -------------------------------------------------------------------------
	 * 3. Media library restriction
	 * ---------------------------------------------------------------------- */

	/**
	 * Non-admins only see their own media uploads.
	 */
	public function restrict_media_library( $query ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			$query['author'] = get_current_user_id();
		}
		return $query;
	}

	/* -------------------------------------------------------------------------
	 * 4. Certification Document Meta Box
	 * ---------------------------------------------------------------------- */

	/**
	 * Register the certification document meta box on coach edit screen.
	 */
	public function add_certification_meta_box() {
		add_meta_box(
			'rm_certification_doc',
			__( 'Certification Document', 'ridemaster' ),
			array( $this, 'render_certification_meta_box' ),
			'coach',
			'side',
			'high'
		);
	}

	/**
	 * Render the certification document meta box content.
	 */
	public function render_certification_meta_box( $post ) {
		$meta_val = get_post_meta( $post->ID, 'certifications_documents', true );

		if ( ! $meta_val ) {
			echo '<p style="color:#94a3b8;font-style:italic;">' . esc_html__( 'No document uploaded.', 'ridemaster' ) . '</p>';
			return;
		}

		$att_ids = array_filter( array_map( 'absint', explode( ',', $meta_val ) ) );

		if ( empty( $att_ids ) ) {
			echo '<p style="color:#94a3b8;font-style:italic;">' . esc_html__( 'No document uploaded.', 'ridemaster' ) . '</p>';
			return;
		}

		foreach ( $att_ids as $attachment_id ) :
			$attachment = get_post( $attachment_id );
			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				continue;
			}

			$file_url  = wp_get_attachment_url( $attachment_id );
			$file_name = basename( get_attached_file( $attachment_id ) );
			$file_type = strtoupper( pathinfo( $file_name, PATHINFO_EXTENSION ) );
			$file_path = get_attached_file( $attachment_id );
			$file_size = $file_path && file_exists( $file_path ) ? size_format( filesize( $file_path ), 1 ) : '';
			$is_image  = wp_attachment_is_image( $attachment_id );
		?>
			<div style="margin-bottom:12px;padding:10px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
				<?php if ( $is_image ) : ?>
					<img src="<?php echo esc_url( $file_url ); ?>" alt="<?php echo esc_attr( $file_name ); ?>"
						style="max-width:100%;height:auto;border-radius:4px;margin-bottom:6px;" />
				<?php else : ?>
					<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
						<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0D9488" stroke-width="1.5" style="flex-shrink:0;">
							<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
						</svg>
						<div style="min-width:0;">
							<div style="font-size:12px;font-weight:500;color:#334155;word-break:break-all;"><?php echo esc_html( $file_name ); ?></div>
							<span style="font-size:10px;font-weight:600;color:#0D9488;background:#CCFBF1;padding:1px 5px;border-radius:3px;"><?php echo esc_html( $file_type ); ?></span>
							<?php if ( $file_size ) : ?>
								<span style="font-size:10px;color:#94a3b8;margin-left:3px;"><?php echo esc_html( $file_size ); ?></span>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
				<a href="<?php echo esc_url( $file_url ); ?>" target="_blank" rel="noopener"
					style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:#0D9488;color:#fff;border-radius:4px;font-size:11px;font-weight:600;text-decoration:none;">
					<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
						<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
					</svg>
					View / Download
				</a>
			</div>
		<?php endforeach;
	}

	/* -------------------------------------------------------------------------
	 * Helpers
	 * ---------------------------------------------------------------------- */

	/**
	 * Send the approval email to the coach.
	 */
	private function send_coach_approved_email( $post_id ) {
		$post       = get_post( $post_id );
		$author     = get_userdata( $post->post_author );
		$first_name = $author ? $author->first_name : '';
		$email      = $author ? $author->user_email : '';

		if ( ! $email ) {
			return;
		}

		$subject = 'Your Ridemaster Coach account is now active!';
		$message  = "Hi {$first_name},\n\n";
		$message .= "Great news! Your coach account has been approved.\n\n";
		$message .= "You can now log in and complete your profile:\n";
		$message .= "https://ridemaster.eu/coach-dashboard/\n\n";
		$message .= "See you on Ridemaster!\n";
		$message .= 'The Ridemaster Team';

		wp_mail( $email, $subject, $message );
	}

	/* -------------------------------------------------------------------------
	 * 5. Stripe Status Column
	 * ---------------------------------------------------------------------- */

	/**
	 * Add Stripe status column to coaches list.
	 */
	public function add_stripe_column( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( $key === 'rm_certification' ) {
				$new['rm_stripe'] = 'Stripe';
			}
		}
		if ( ! isset( $new['rm_stripe'] ) ) {
			$new['rm_stripe'] = 'Stripe';
		}
		return $new;
	}

	/**
	 * Render the Stripe status column content.
	 */
	public function render_stripe_column( $column, $post_id ) {
		if ( $column !== 'rm_stripe' ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			echo '—';
			return;
		}

		$user_id = $post->post_author;
		if ( ! $user_id ) {
			echo '—';
			return;
		}

		$stripe_id   = get_user_meta( $user_id, 'stripe_account_id', true );
		$stripe_done = get_user_meta( $user_id, 'stripe_onboarding_complete', true ) === '1';

		if ( ! $stripe_id ) {
			echo '<span style="color:#9ca3af;">Not connected</span>';
		} elseif ( $stripe_done ) {
			echo '<span style="color:#065f46;font-weight:600;">&#10003; Connected</span>';
		} else {
			echo '<span style="color:#92400e;">&#9888; Pending</span>';
		}
	}
}
