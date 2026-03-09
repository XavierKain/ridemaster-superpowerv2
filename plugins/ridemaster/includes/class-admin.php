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

					var xhr = new XMLHttpRequest();
					xhr.open('POST', ajaxurl, true);
					xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					xhr.send(
						'action=rm_update_coach_status' +
						'&nonce=<?php echo esc_js( $nonce ); ?>' +
						'&post_id=' + encodeURIComponent(postId) +
						'&status='  + encodeURIComponent(status)
					);
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
}
