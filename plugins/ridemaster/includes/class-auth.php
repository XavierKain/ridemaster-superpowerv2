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
		add_action( 'jet-form-builder/action/after-post-insert', array( $this, 'associate_guest_photos' ), 999, 2 );

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

		// Allow uploads from registration page AND coach profile page.
		$allowed = $referer && (
			false !== strpos( $referer, 'coach-register' ) ||
			false !== strpos( $referer, 'coach-dashboard' ) ||
			is_user_logged_in()
		);

		if ( ! $allowed ) {
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

		$field_type = sanitize_text_field( $request->get_param( 'field_type' ) );
		$mime       = mime_content_type( $file['tmp_name'] );
		$ext        = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( 'document' === $field_type ) {
			// Certification documents: PDF, DOC, DOCX, JPG, PNG — 10 MB limit.
			$allowed_mimes = array(
				'image/jpeg',
				'image/png',
				'application/pdf',
				'application/msword',
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'application/octet-stream', // Some browsers send this for .doc/.docx.
			);
			$allowed_exts = array( 'pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png' );
			$max_size  = 10 * 1024 * 1024; // 10 MB.
			$error_msg = __( 'Invalid file type. Only PDF, DOC, JPG, and PNG are allowed.', 'ridemaster' );

			// Validate by extension OR MIME (extension fallback for unreliable MIME detection).
			$valid_type = in_array( $mime, $allowed_mimes, true ) || in_array( $ext, $allowed_exts, true );
		} else {
			// Photos: JPEG, PNG, WebP — 5 MB limit.
			$allowed_mimes = array( 'image/jpeg', 'image/png', 'image/webp' );
			$max_size  = 5 * 1024 * 1024; // 5 MB.
			$error_msg = __( 'Invalid file type. Only JPEG, PNG, and WebP are allowed.', 'ridemaster' );

			$valid_type = in_array( $mime, $allowed_mimes, true );
		}

		if ( ! $valid_type ) {
			return new WP_Error( 'invalid_type', $error_msg, array( 'status' => 400 ) );
		}

		if ( $file['size'] > $max_size ) {
			$limit_label = ( 'document' === $field_type ) ? '10 MB' : '5 MB';
			return new WP_Error( 'too_large', sprintf( __( 'File exceeds the %s size limit.', 'ridemaster' ), $limit_label ), array( 'status' => 400 ) );
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
				'coach_profile_photo':      '_rm_profile_photo_id',
				'coach_cover_photo':        '_rm_cover_photo_id',
				'certifications-documents': '_rm_certifications_doc_id'
			};

			var IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
			var DOC_EXTS   = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];

			function getFileExtension( filename ) {
				return ( filename || '' ).split('.').pop().toLowerCase();
			}

			function detectFieldName( container ) {
				// 1. data-field-name on wrapper.
				var fn = container.getAttribute( 'data-field-name' );
				if ( fn && FILE_FIELDS[ fn ] ) return fn;

				// 2. Hidden input name.
				var hidden = container.querySelector( 'input[type="hidden"]' );
				if ( hidden && hidden.name && FILE_FIELDS[ hidden.name ] ) return hidden.name;

				// 3. File input name or id.
				var fileIn = container.querySelector( 'input[type="file"]' );
				if ( fileIn ) {
					if ( fileIn.name && FILE_FIELDS[ fileIn.name ] ) return fileIn.name;
					if ( fileIn.id && FILE_FIELDS[ fileIn.id ] ) return fileIn.id;
				}

				// 4. Label text — inside container AND in parent row.
				var row = container.closest( '.jet-form-builder-row' );
				var labels = [];
				var innerLabel = container.querySelector( 'label' );
				if ( innerLabel ) labels.push( innerLabel );
				if ( row ) {
					var rowLabels = row.querySelectorAll( '.jet-form-builder__label, label' );
					for ( var i = 0; i < rowLabels.length; i++ ) {
						labels.push( rowLabels[i] );
					}
				}

				for ( var j = 0; j < labels.length; j++ ) {
					var text = labels[j].textContent.toLowerCase();
					if ( text.indexOf( 'certif' ) !== -1 ) return 'certifications-documents';
					if ( text.indexOf( 'profile' ) !== -1 ) return 'coach_profile_photo';
					if ( text.indexOf( 'cover' ) !== -1 )   return 'coach_cover_photo';
				}

				return null;
			}

			function initUploadHandlers() {
				var containers = document.querySelectorAll( '.jet-form-builder-file-upload' );
				if ( ! containers.length ) return;

				/* Find the parent form to attach hidden inputs safely */
				var form = containers[0].closest( 'form' );

				containers.forEach( function( container ) {
					if ( container.dataset.rmGuestUpload ) return;
					container.dataset.rmGuestUpload = '1';

					var fieldName = detectFieldName( container );
					if ( ! fieldName || ! FILE_FIELDS[ fieldName ] ) return;

					var hiddenName  = FILE_FIELDS[ fieldName ];
					var isDocument  = ( fieldName === 'certifications-documents' );

					/* Create hidden input — attach to form, not container (safer) */
					var hiddenInput = document.createElement( 'input' );
					hiddenInput.type  = 'hidden';
					hiddenInput.name  = hiddenName;
					hiddenInput.value = '';
					if ( form ) {
						form.appendChild( hiddenInput );
					} else {
						container.appendChild( hiddenInput );
					}

					/* Status message */
					var status = document.createElement( 'span' );
					status.className = 'rm-upload-status';
					status.style.cssText = 'display:block;margin-top:6px;font-size:13px;color:#64748b;';
					container.parentNode.insertBefore( status, container.nextSibling );

					var fileInput = container.querySelector( 'input[type="file"]' );
					if ( ! fileInput ) return;

					/* Allow multi-select for document fields */
					if ( isDocument ) {
						fileInput.setAttribute( 'multiple', 'multiple' );
					}

					function uploadSingleFile( file ) {
						var ext = getFileExtension( file.name );

						if ( isDocument ) {
							if ( DOC_EXTS.indexOf( ext ) === -1 ) {
								var errDiv = document.createElement( 'div' );
								errDiv.textContent = '\u274c ' + file.name + ' — invalid type. Use PDF, DOC, JPG, or PNG.';
								errDiv.style.cssText = 'color:#EF4444;margin-top:4px;font-size:13px;';
								status.appendChild( errDiv );
								return;
							}
							if ( file.size > 10 * 1024 * 1024 ) {
								var errDiv2 = document.createElement( 'div' );
								errDiv2.textContent = '\u274c ' + file.name + ' — too large. Maximum 10 MB.';
								errDiv2.style.cssText = 'color:#EF4444;margin-top:4px;font-size:13px;';
								status.appendChild( errDiv2 );
								return;
							}
						} else {
							if ( IMAGE_EXTS.indexOf( ext ) === -1 ) {
								status.textContent = 'Invalid file type. Use JPEG, PNG, or WebP.';
								status.style.color = '#EF4444';
								return;
							}
							if ( file.size > 2 * 1024 * 1024 ) {
								status.textContent = 'File is too large. Maximum 2 MB.';
								status.style.color = '#EF4444';
								return;
							}
						}

						/* Show uploading indicator */
						var uploadingMsg = null;
						if ( isDocument ) {
							uploadingMsg = document.createElement( 'div' );
							uploadingMsg.textContent = 'Uploading ' + file.name + '\u2026';
							uploadingMsg.style.cssText = 'color:#64748b;margin-top:4px;font-size:13px;';
							status.appendChild( uploadingMsg );
						} else {
							status.style.color = '#64748b';
							status.textContent = 'Uploading\u2026';
						}

						var formData = new FormData();
						formData.append( 'file', file );
						if ( isDocument ) {
							formData.append( 'field_type', 'document' );
						}

						fetch( REST_URL, {
							method: 'POST',
							headers: { 'X-WP-Nonce': NONCE },
							body: formData
						})
						.then( function( r ) { return r.json(); } )
						.then( function( data ) {
							if ( uploadingMsg ) uploadingMsg.remove();
							if ( data && data.success ) {
								var newId = String( data.attachment_id );

								if ( isDocument ) {
									/* Multi-doc: accumulate IDs as comma-separated list */
									var ids = hiddenInput.value ? hiddenInput.value.split(',') : [];
									ids.push( newId );
									hiddenInput.value = ids.join(',');

									/* Add file entry to the list */
									var entry = document.createElement( 'div' );
									entry.style.cssText = 'display:flex;align-items:center;gap:6px;margin-top:4px;';
									entry.dataset.rmDocId = newId;

									var entryTxt = document.createElement( 'span' );
									entryTxt.textContent = '\u2705 ' + file.name;
									entryTxt.style.color = '#10B981';
									entry.appendChild( entryTxt );

									var entryDel = document.createElement( 'button' );
									entryDel.type = 'button';
									entryDel.textContent = '\u2715';
									entryDel.style.cssText = 'background:none;border:1px solid #EF4444;color:#EF4444;border-radius:4px;padding:1px 6px;cursor:pointer;font-size:12px;';
									entryDel.addEventListener( 'click', function() {
										var curIds = hiddenInput.value.split(',');
										curIds = curIds.filter(function(id) { return id !== newId; });
										hiddenInput.value = curIds.join(',');
										entry.remove();
									});
									entry.appendChild( entryDel );
									status.appendChild( entry );
								} else {
									/* Photo: single ID */
									hiddenInput.value  = newId;
									status.style.color = '#10B981';
									status.innerHTML   = '';

									var txt = document.createElement( 'span' );
									txt.textContent = '\u2705 ' + file.name;
									status.appendChild( txt );

									var del = document.createElement( 'button' );
									del.type = 'button';
									del.textContent = '\u2715';
									del.style.cssText = 'margin-left:8px;background:none;border:1px solid #EF4444;color:#EF4444;border-radius:4px;padding:1px 6px;cursor:pointer;font-size:12px;';
									del.addEventListener( 'click', function() {
										hiddenInput.value = '';
										fileInput.value   = '';
										status.innerHTML  = '';
										status.textContent = 'File removed.';
										status.style.color = '#64748b';
									});
									status.appendChild( del );
								}
							} else {
								var msg = ( data && data.message ) ? data.message : 'Upload failed.';
								status.textContent = '\u274c ' + msg;
								status.style.color = '#EF4444';
							}
						})
						.catch( function() {
							if ( uploadingMsg ) uploadingMsg.remove();
							var errCatch = document.createElement( 'div' );
							errCatch.textContent = '\u274c Upload failed for ' + file.name;
							errCatch.style.cssText = 'color:#EF4444;margin-top:4px;font-size:13px;';
							status.appendChild( errCatch );
						});
					}

					fileInput.addEventListener( 'change', function() {
						if ( ! fileInput.files || ! fileInput.files.length ) return;

						/* Upload all selected files */
						for ( var fi = 0; fi < fileInput.files.length; fi++ ) {
							uploadSingleFile( fileInput.files[fi] );
						}
					});
				});
			}

			/* Run immediately and with delays for late-rendering JFB widgets */
			initUploadHandlers();
			setTimeout( initUploadHandlers, 500 );
			setTimeout( initUploadHandlers, 1500 );
			if ( document.readyState === 'loading' ) {
				document.addEventListener( 'DOMContentLoaded', initUploadHandlers );
			}
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
	 * @param mixed $action  JetFormBuilder Insert Post action instance.
	 * @param mixed $handler JetFormBuilder form action handler.
	 */
	public function associate_guest_photos( $action, $handler ) {

		$post_id = null;

		if ( method_exists( $handler, 'get_inserted_post_id' ) ) {
			$post_id = $handler->get_inserted_post_id();
		}

		if ( ! $post_id && method_exists( $handler, 'get_response_args' ) ) {
			$args    = $handler->get_response_args();
			$post_id = isset( $args['inserted_post_id'] ) ? $args['inserted_post_id'] : null;
		}

		$post_id = (int) $post_id;

		if ( ! $post_id || 'coach' !== get_post_type( $post_id ) ) {
			return;
		}

		$profile_photo_id     = isset( $_POST['_rm_profile_photo_id'] )      ? absint( $_POST['_rm_profile_photo_id'] )      : 0;
		$cover_photo_id       = isset( $_POST['_rm_cover_photo_id'] )        ? absint( $_POST['_rm_cover_photo_id'] )        : 0;
		// Certification docs can be comma-separated IDs (multiple files).
		$cert_doc_raw  = isset( $_POST['_rm_certifications_doc_id'] ) ? sanitize_text_field( $_POST['_rm_certifications_doc_id'] ) : '';
		$cert_doc_ids  = array_filter( array_map( 'absint', explode( ',', $cert_doc_raw ) ) );
		$post_author   = (int) get_post_field( 'post_author', $post_id );

		add_action( 'shutdown', function() use ( $post_id, $profile_photo_id, $cover_photo_id, $cert_doc_ids, $post_author ) {

			// Bypass meta protection — this is an intentional registration write.
			RM_Coach::bypass_meta_protection( true );

			if ( $profile_photo_id ) {
				set_post_thumbnail( $post_id, $profile_photo_id );
				update_post_meta( $post_id, 'coach_profile_photo', $profile_photo_id );
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

			if ( ! empty( $cert_doc_ids ) ) {
				// Store as comma-separated string.
				update_post_meta( $post_id, 'certifications_documents', implode( ',', $cert_doc_ids ) );
				foreach ( $cert_doc_ids as $att_id ) {
					wp_update_post( array(
						'ID'          => $att_id,
						'post_parent' => $post_id,
						'post_author' => $post_author,
					) );
				}
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
