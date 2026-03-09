<?php
/**
 * RM_Auth — Authentication, redirects, guest photo uploads, and logout bypass.
 *
 * @package RideMaster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RM_Auth {

	/**
	 * Boot all hooks.
	 */
	public function __construct() {

		// Redirect rules (template_redirect).
		add_action( 'template_redirect', array( $this, 'redirect_login_page' ) );
		add_action( 'template_redirect', array( $this, 'redirect_register_page' ) );
		add_action( 'template_redirect', array( $this, 'redirect_suspended_page' ) );
		add_action( 'template_redirect', array( $this, 'redirect_my_account' ) );
		add_action( 'template_redirect', array( $this, 'redirect_coach_dashboard' ) );

		// Login redirect filters.
		add_filter( 'woocommerce_login_redirect', array( $this, 'woocommerce_login_redirect' ), 10, 2 );
		add_filter( 'login_redirect', array( $this, 'login_redirect' ), 10, 3 );

		// Guest photo upload REST endpoint.
		add_action( 'rest_api_init', array( $this, 'register_guest_upload_endpoint' ) );

		// Inject JS for guest uploads on the registration page.
		add_action( 'wp_footer', array( $this, 'guest_upload_js' ) );

		// Associate uploaded photos after JetFormBuilder post insert.
		add_action( 'jet-form-builder/action/after-post-insert', array( $this, 'associate_guest_photos' ), 999 );

		// Bypass the "Are you sure?" logout confirmation screen.
		add_action( 'init', array( $this, 'bypass_logout_confirmation' ) );
	}

	/* -----------------------------------------------------------------------
	 * Helper
	 * -------------------------------------------------------------------- */

	/**
	 * Return the appropriate dashboard / status URL for a coach user.
	 *
	 * @param WP_User $user WordPress user object.
	 * @return string Absolute URL.
	 */
	public function get_coach_redirect_url( $user ) {

		$coach_posts = get_posts( array(
			'post_type'      => 'coach',
			'author'         => $user->ID,
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		) );

		if ( empty( $coach_posts ) ) {
			return home_url( '/coach-en-attente-de-validation/' );
		}

		$coach_post_id = $coach_posts[0];

		if ( has_term( 'validated', 'coach-status', $coach_post_id ) ) {
			return home_url( '/coach-dashboard/' );
		}

		if ( has_term( 'suspended', 'coach-status', $coach_post_id ) ) {
			return home_url( '/coach-account-suspended/' );
		}

		return home_url( '/coach-en-attente-de-validation/' );
	}

	/* -----------------------------------------------------------------------
	 * Redirect hooks (template_redirect)
	 * -------------------------------------------------------------------- */

	/**
	 * 1. Redirect logged-in users away from the login page.
	 */
	public function redirect_login_page() {

		if ( ! is_user_logged_in() || ! is_page( 'login' ) ) {
			return;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$user = wp_get_current_user();

		if ( in_array( 'coach_role', (array) $user->roles, true ) ) {
			wp_redirect( esc_url( $this->get_coach_redirect_url( $user ) ) );
			exit;
		}

		wp_redirect( esc_url( home_url( '/my-account/' ) ) );
		exit;
	}

	/**
	 * 2. Redirect logged-in users away from the registration / pending page.
	 */
	public function redirect_register_page() {

		if ( ! is_user_logged_in() ) {
			return;
		}

		$on_register = is_page( 'coach-register' );
		$on_attente  = is_page( 'coach-en-attente-de-validation' );

		if ( ! $on_register && ! $on_attente ) {
			return;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$user = wp_get_current_user();

		if ( in_array( 'coach_role', (array) $user->roles, true ) ) {

			$coach_posts = get_posts( array(
				'post_type'      => 'coach',
				'author'         => $user->ID,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			) );

			$coach_post_id = ! empty( $coach_posts ) ? $coach_posts[0] : 0;
			$is_validated  = $coach_post_id && has_term( 'validated', 'coach-status', $coach_post_id );
			$is_suspended  = $coach_post_id && has_term( 'suspended', 'coach-status', $coach_post_id );

			if ( $is_validated ) {
				wp_redirect( esc_url( home_url( '/coach-dashboard/' ) ) );
				exit;
			}

			if ( $on_register ) {
				wp_redirect( esc_url( $this->get_coach_redirect_url( $user ) ) );
				exit;
			}

			// On attente page.
			if ( $is_suspended ) {
				wp_redirect( esc_url( home_url( '/coach-account-suspended/' ) ) );
				exit;
			}

			// Pending coach on attente page — stay.
			return;
		}

		// Non-coach (client).
		wp_redirect( esc_url( home_url( '/my-account/' ) ) );
		exit;
	}

	/**
	 * 3. Redirect logged-in users away from the suspended page (unless actually suspended).
	 */
	public function redirect_suspended_page() {

		if ( ! is_user_logged_in() || ! is_page( 'coach-account-suspended' ) ) {
			return;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$user = wp_get_current_user();

		if ( in_array( 'coach_role', (array) $user->roles, true ) ) {

			$coach_posts = get_posts( array(
				'post_type'      => 'coach',
				'author'         => $user->ID,
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'fields'         => 'ids',
			) );

			$coach_post_id = ! empty( $coach_posts ) ? $coach_posts[0] : 0;
			$is_suspended  = $coach_post_id && has_term( 'suspended', 'coach-status', $coach_post_id );

			if ( $is_suspended ) {
				return;
			}

			wp_redirect( esc_url( $this->get_coach_redirect_url( $user ) ) );
			exit;
		}

		// Client.
		wp_redirect( esc_url( home_url( '/my-account/' ) ) );
		exit;
	}

	/**
	 * 4. Redirect coaches away from the WooCommerce My Account page.
	 */
	public function redirect_my_account() {

		if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			wp_redirect( esc_url( home_url( '/login/' ) ) );
			exit;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$user = wp_get_current_user();

		if ( in_array( 'coach_role', (array) $user->roles, true ) ) {
			wp_redirect( esc_url( $this->get_coach_redirect_url( $user ) ) );
			exit;
		}
	}

	/**
	 * 5. Redirect non-validated coaches away from the coach dashboard.
	 */
	public function redirect_coach_dashboard() {

		if ( false === strpos( $_SERVER['REQUEST_URI'], 'coach-dashboard' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		$user = wp_get_current_user();

		if ( ! in_array( 'coach_role', (array) $user->roles, true ) ) {
			return;
		}

		$coach_posts = get_posts( array(
			'post_type'      => 'coach',
			'author'         => $user->ID,
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		) );

		$coach_post_id = ! empty( $coach_posts ) ? $coach_posts[0] : 0;

		if ( $coach_post_id && has_term( 'validated', 'coach-status', $coach_post_id ) ) {
			return;
		}

		wp_redirect( esc_url( $this->get_coach_redirect_url( $user ) ) );
		exit;
	}

	/* -----------------------------------------------------------------------
	 * Login redirect filters
	 * -------------------------------------------------------------------- */

	/**
	 * 6a. WooCommerce login redirect.
	 *
	 * @param string  $redirect Default redirect URL.
	 * @param WP_User $user     Logged-in user.
	 * @return string
	 */
	public function woocommerce_login_redirect( $redirect, $user ) {

		if ( ! $user instanceof WP_User ) {
			return $redirect;
		}

		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return $redirect;
		}

		if ( in_array( 'coach_role', (array) $user->roles, true ) ) {
			return esc_url( $this->get_coach_redirect_url( $user ) );
		}

		return $redirect;
	}

	/**
	 * 6b. Core login redirect.
	 *
	 * @param string  $redirect_to           Requested redirect URL.
	 * @param string  $requested_redirect_to Original requested URL.
	 * @param WP_User $user                  Logged-in user (or WP_Error).
	 * @return string
	 */
	public function login_redirect( $redirect_to, $requested_redirect_to, $user ) {

		if ( is_wp_error( $user ) ) {
			return $redirect_to;
		}

		if ( ! $user instanceof WP_User ) {
			return $redirect_to;
		}

		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return $redirect_to;
		}

		if ( in_array( 'coach_role', (array) $user->roles, true ) ) {
			return esc_url( $this->get_coach_redirect_url( $user ) );
		}

		return $redirect_to;
	}

	/* -----------------------------------------------------------------------
	 * Guest Photo Upload (REST API)
	 * -------------------------------------------------------------------- */

	/**
	 * 7. Register the guest upload REST endpoint.
	 */
	public function register_guest_upload_endpoint() {

		register_rest_route( 'ridemaster/v1', '/guest-upload', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'guest_upload_handler' ),
			'permission_callback' => '__return_true',
		) );
	}

	/**
	 * Handle a guest photo upload.
	 *
	 * @param WP_REST_Request $request REST request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function guest_upload_handler( $request ) {

		$referer = $request->get_header( 'referer' );

		if ( ! $referer || false === strpos( $referer, 'coach-register' ) ) {
			return new WP_Error( 'forbidden', __( 'Invalid referer.', 'ridemaster' ), array( 'status' => 403 ) );
		}

		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', __( 'No file provided.', 'ridemaster' ), array( 'status' => 400 ) );
		}

		$file = $files['file'];

		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return new WP_Error( 'upload_error', __( 'File upload error.', 'ridemaster' ), array( 'status' => 400 ) );
		}

		$allowed_mimes = array( 'image/jpeg', 'image/png', 'image/webp' );
		$mime          = mime_content_type( $file['tmp_name'] );

		if ( ! in_array( $mime, $allowed_mimes, true ) ) {
			return new WP_Error( 'invalid_type', __( 'Invalid file type. Only JPEG, PNG, and WebP are allowed.', 'ridemaster' ), array( 'status' => 400 ) );
		}

		$max_size = 5 * 1024 * 1024; // 5 MB.

		if ( $file['size'] > $max_size ) {
			return new WP_Error( 'too_large', __( 'File exceeds the 5 MB size limit.', 'ridemaster' ), array( 'status' => 400 ) );
		}

		$upload_dir = wp_upload_dir();
		$filename   = sanitize_file_name( $file['name'] );
		$filename   = wp_unique_filename( $upload_dir['path'], $filename );
		$filepath   = $upload_dir['path'] . '/' . $filename;

		if ( ! move_uploaded_file( $file['tmp_name'], $filepath ) ) {
			return new WP_Error( 'move_failed', __( 'Could not save uploaded file.', 'ridemaster' ), array( 'status' => 500 ) );
		}

		$attachment = array(
			'guid'           => $upload_dir['url'] . '/' . $filename,
			'post_mime_type' => $mime,
			'post_title'     => sanitize_file_name( pathinfo( $filename, PATHINFO_FILENAME ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $filepath );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';

		$metadata = wp_generate_attachment_metadata( $attachment_id, $filepath );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return rest_ensure_response( array(
			'success'       => true,
			'attachment_id' => $attachment_id,
			'url'           => esc_url( $upload_dir['url'] . '/' . $filename ),
		) );
	}

	/* -----------------------------------------------------------------------
	 * Guest Upload JS
	 * -------------------------------------------------------------------- */

	/**
	 * 8. Output client-side JS for guest photo uploads on the registration page.
	 */
	public function guest_upload_js() {

		if ( ! is_page( 'coach-register' ) ) {
			return;
		}

		$rest_url = esc_js( esc_url( rest_url( 'ridemaster/v1/guest-upload' ) ) );
		$nonce    = esc_js( wp_create_nonce( 'wp_rest' ) );

		?>
		<script>
		(function() {
			var REST_URL = '<?php echo $rest_url; ?>';
			var NONCE    = '<?php echo $nonce; ?>';

			var FILE_FIELDS = {
				'coach_profile_photo': '_rm_profile_photo_id',
				'coach_cover_photo':   '_rm_cover_photo_id'
			};

			function detectFieldName( container ) {
				// 1. data-field-name attribute.
				var fieldName = container.getAttribute( 'data-field-name' );
				if ( fieldName ) {
					return fieldName;
				}

				// 2. Input name attribute.
				var input = container.querySelector( 'input[type="file"]' );
				if ( input && input.name ) {
					return input.name;
				}

				// 3. Label text containing "profile" or "cover".
				var label = container.querySelector( 'label' );
				if ( label ) {
					var text = label.textContent.toLowerCase();
					if ( text.indexOf( 'profile' ) !== -1 ) {
						return 'coach_profile_photo';
					}
					if ( text.indexOf( 'cover' ) !== -1 ) {
						return 'coach_cover_photo';
					}
				}

				return null;
			}

			var containers = document.querySelectorAll( '.jet-form-builder-file-upload' );

			containers.forEach( function( container ) {
				var fieldName = detectFieldName( container );
				if ( ! fieldName || ! FILE_FIELDS[ fieldName ] ) {
					return;
				}

				var hiddenName  = FILE_FIELDS[ fieldName ];
				var hiddenInput = document.createElement( 'input' );
				hiddenInput.type  = 'hidden';
				hiddenInput.name  = hiddenName;
				hiddenInput.value = '';
				container.appendChild( hiddenInput );

				var fileInput = container.querySelector( 'input[type="file"]' );
				if ( ! fileInput ) {
					return;
				}

				var status = document.createElement( 'span' );
				status.className = 'rm-upload-status';
				container.appendChild( status );

				fileInput.addEventListener( 'change', function() {
					var file = fileInput.files[0];
					if ( ! file ) {
						return;
					}

					var allowedTypes = [ 'image/jpeg', 'image/png', 'image/webp' ];
					if ( allowedTypes.indexOf( file.type ) === -1 ) {
						status.textContent = 'Invalid file type. Use JPEG, PNG, or WebP.';
						return;
					}

					var maxSize = 2 * 1024 * 1024; // 2 MB client-side limit.
					if ( file.size > maxSize ) {
						status.textContent = 'File is too large. Maximum 2 MB.';
						return;
					}

					status.textContent = 'Uploading\u2026';

					var formData = new FormData();
					formData.append( 'file', file );

					fetch( REST_URL, {
						method: 'POST',
						headers: {
							'X-WP-Nonce': NONCE
						},
						body: formData
					})
					.then( function( response ) {
						return response.json();
					})
					.then( function( data ) {
						if ( data && data.success ) {
							hiddenInput.value  = data.attachment_id;
							status.textContent = 'Upload complete.';
						} else {
							status.textContent = ( data && data.message ) ? data.message : 'Upload failed.';
						}
					})
					.catch( function() {
						status.textContent = 'Upload failed.';
					});
				});
			});
		})();
		</script>
		<?php
	}

	/* -----------------------------------------------------------------------
	 * Associate guest photos after form submission
	 * -------------------------------------------------------------------- */

	/**
	 * 9. Link uploaded attachments to the newly created coach post.
	 *
	 * @param mixed $handler JetFormBuilder action handler.
	 */
	public function associate_guest_photos( $handler ) {

		$post_id = isset( $handler->inserted_post_id ) ? (int) $handler->inserted_post_id : 0;

		if ( ! $post_id || 'coach' !== get_post_type( $post_id ) ) {
			return;
		}

		$profile_photo_id = isset( $_POST['_rm_profile_photo_id'] ) ? absint( $_POST['_rm_profile_photo_id'] ) : 0;
		$cover_photo_id   = isset( $_POST['_rm_cover_photo_id'] )   ? absint( $_POST['_rm_cover_photo_id'] )   : 0;
		$post_author      = (int) get_post_field( 'post_author', $post_id );

		add_action( 'shutdown', function() use ( $post_id, $profile_photo_id, $cover_photo_id, $post_author ) {

			if ( $profile_photo_id ) {
				set_post_thumbnail( $post_id, $profile_photo_id );
				wp_update_post( array(
					'ID'          => $profile_photo_id,
					'post_parent' => $post_id,
					'post_author' => $post_author,
				) );
			}

			if ( $cover_photo_id ) {
				update_post_meta( $post_id, 'cover_image', $cover_photo_id );
				wp_update_post( array(
					'ID'          => $cover_photo_id,
					'post_parent' => $post_id,
					'post_author' => $post_author,
				) );
			}
		});
	}

	/* -----------------------------------------------------------------------
	 * Logout bypass
	 * -------------------------------------------------------------------- */

	/**
	 * 10. Skip the "Are you sure you want to log out?" confirmation screen.
	 */
	public function bypass_logout_confirmation() {

		if ( ! isset( $_GET['action'] ) || 'logout' !== $_GET['action'] ) {
			return;
		}

		wp_logout();
		wp_redirect( esc_url( home_url() ) );
		exit;
	}
}
