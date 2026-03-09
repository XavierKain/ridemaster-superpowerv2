<?php
/**
 * Coach identity and profile management.
 *
 * Ensures every coach user has a linked coach post,
 * keeps the post title in sync with name meta,
 * and provides sidebar shortcodes for the dashboard.
 *
 * @package RideMaster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RM_Coach {

	/**
	 * Wire up all hooks and shortcodes.
	 */
	public function __construct() {

		// Core identity.
		add_action( 'init', [ $this, 'ensure_coach_post_id' ] );
		add_action( 'wp_insert_post', [ $this, 'link_coach_post_to_user' ], 10, 3 );

		// Auto-title from name meta.
		add_action( 'added_post_meta', [ $this, 'auto_coach_title' ], 10, 4 );
		add_action( 'updated_post_meta', [ $this, 'auto_coach_title' ], 10, 4 );

		// Dashboard helpers.
		add_action( 'template_redirect', [ $this, 'inject_jfb_preset' ], 5 );
		add_action( 'wp', [ $this, 'set_dashboard_query_var' ] );

		// Sidebar styles.
		add_action( 'wp_head', [ $this, 'sidebar_css' ] );

		// Inject profile photo URL for JS fix on profile edit page.
		add_action( 'wp_footer', [ $this, 'inject_profile_photo_url' ] );

		// Shortcodes.
		add_shortcode( 'rm_coach_avatar', [ $this, 'shortcode_avatar' ] );
		add_shortcode( 'rm_coach_name', [ $this, 'shortcode_name' ] );
		add_shortcode( 'rm_coach_profile_url', [ $this, 'shortcode_profile_url' ] );
	}

	/* ------------------------------------------------------------------
	 * 1. Ensure every coach user has a linked coach post.
	 * ----------------------------------------------------------------*/

	/**
	 * On init, verify the current coach user has a valid coach_post_id.
	 * If not, find an existing post by author or create a new draft.
	 */
	public function ensure_coach_post_id() {

		if ( ! is_user_logged_in() ) {
			return;
		}

		$user = wp_get_current_user();

		if ( ! in_array( 'coach_role', (array) $user->roles, true ) ) {
			return;
		}

		$coach_post_id = (int) get_user_meta( $user->ID, 'coach_post_id', true );

		// Already valid — nothing to do.
		if ( $coach_post_id && get_post( $coach_post_id ) ) {
			return;
		}

		// Try to find an existing coach post by this author.
		$existing = get_posts( [
			'post_type'      => 'coach',
			'post_status'    => 'any',
			'author'         => $user->ID,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		] );

		if ( $existing ) {
			$coach_post_id = $existing[0];
		} else {
			// Create a new draft coach post.
			$coach_post_id = wp_insert_post( [
				'post_type'   => 'coach',
				'post_status' => 'draft',
				'post_author' => $user->ID,
				'post_title'  => $user->display_name,
			] );

			if ( is_wp_error( $coach_post_id ) ) {
				return;
			}

			// Flag the new post as pending review.
			wp_set_object_terms( $coach_post_id, 'pending', 'coach-status' );
		}

		update_user_meta( $user->ID, 'coach_post_id', $coach_post_id );
	}

	/* ------------------------------------------------------------------
	 * 2. Link a newly created coach post back to the author's user meta.
	 * ----------------------------------------------------------------*/

	/**
	 * Fires on wp_insert_post for NEW coach posts only.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an update (true) or new insert (false).
	 */
	public function link_coach_post_to_user( $post_id, $post, $update ) {

		if ( $update ) {
			return;
		}

		if ( 'coach' !== $post->post_type ) {
			return;
		}

		if ( $post->post_author ) {
			update_user_meta( $post->post_author, 'coach_post_id', $post_id );
		}
	}

	/* ------------------------------------------------------------------
	 * 3. Keep the coach post title in sync with name meta fields.
	 * ----------------------------------------------------------------*/

	/**
	 * When coach_first_name or coach_last_name is saved, rebuild the post title.
	 *
	 * @param int    $meta_id    Meta ID.
	 * @param int    $post_id    Post ID.
	 * @param string $meta_key   Meta key.
	 * @param mixed  $meta_value Meta value.
	 */
	public function auto_coach_title( $meta_id, $post_id, $meta_key, $meta_value ) {

		if ( ! in_array( $meta_key, [ 'coach_first_name', 'coach_last_name' ], true ) ) {
			return;
		}

		if ( 'coach' !== get_post_type( $post_id ) ) {
			return;
		}

		$first = get_post_meta( $post_id, 'coach_first_name', true );
		$last  = get_post_meta( $post_id, 'coach_last_name', true );

		// Use the value just saved (may not be persisted yet for added_post_meta).
		if ( 'coach_first_name' === $meta_key ) {
			$first = $meta_value;
		} else {
			$last = $meta_value;
		}

		$full_name = trim( $first . ' ' . $last );

		if ( '' === $full_name ) {
			return;
		}

		// Skip if the title already matches to prevent infinite loops.
		$current_title = get_the_title( $post_id );
		if ( $current_title === $full_name ) {
			return;
		}

		wp_update_post( [
			'ID'         => $post_id,
			'post_title' => $full_name,
			'post_name'  => sanitize_title( $full_name ),
		] );
	}

	/* ------------------------------------------------------------------
	 * 4. Inject the coach post ID so JetFormBuilder presets can read it.
	 * ----------------------------------------------------------------*/

	/**
	 * On the profile page, set $_REQUEST['coach_post_id'] for JFB presets.
	 */
	public function inject_jfb_preset() {

		if ( ! is_user_logged_in() ) {
			return;
		}

		// Match the /coach-dashboard/profile URL.
		$request_uri = trim( wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

		if ( 'coach-dashboard/profile' !== $request_uri ) {
			return;
		}

		$coach_post_id = (int) get_user_meta( get_current_user_id(), 'coach_post_id', true );

		if ( $coach_post_id ) {
			$_REQUEST['coach_post_id'] = $coach_post_id;

			// Sync featured image → coach_profile_photo meta so JFB preset shows the photo.
			$thumb_id = (int) get_post_thumbnail_id( $coach_post_id );
			if ( $thumb_id ) {
				$current = get_post_meta( $coach_post_id, 'coach_profile_photo', true );
				if ( ! $current || (int) $current !== $thumb_id ) {
					update_post_meta( $coach_post_id, 'coach_profile_photo', $thumb_id );
				}
			}
		}
	}

	/* ------------------------------------------------------------------
	 * 5. Expose the coach post ID as a request var for dashboard widgets.
	 * ----------------------------------------------------------------*/

	/**
	 * Set $_REQUEST['current_coach_post_id'] from user meta.
	 */
	public function set_dashboard_query_var() {

		if ( ! is_user_logged_in() ) {
			return;
		}

		// Only set on dashboard pages to avoid polluting $_REQUEST globally.
		if ( ! isset( $_SERVER['REQUEST_URI'] ) || strpos( $_SERVER['REQUEST_URI'], 'coach-dashboard' ) === false ) {
			return;
		}

		$coach_post_id = (int) get_user_meta( get_current_user_id(), 'coach_post_id', true );

		if ( $coach_post_id ) {
			$_REQUEST['current_coach_post_id'] = $coach_post_id;
		}
	}

	/* ------------------------------------------------------------------
	 * 6. Sidebar CSS for avatar and name shortcodes.
	 * ----------------------------------------------------------------*/

	/**
	 * Print sidebar styles in wp_head.
	 */
	public function sidebar_css() {
		?>
		<style>
			.rm-coach-sidebar-avatar { text-align: center; margin-bottom: 8px; }
			.rm-coach-sidebar-avatar img { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 0px solid #e5e7eb; }
			.rm-coach-sidebar-name { text-align: center; font-family: var(--e-global-typography-primary-font-family), Sans-serif; font-size: 20px; font-weight: 600; color: #1f2937; }
		</style>
		<?php
	}

	/* ------------------------------------------------------------------
	 * 7. Shortcodes.
	 * ----------------------------------------------------------------*/

	/**
	 * [rm_coach_avatar] — Coach profile photo with fallback chain.
	 *
	 * Priority: featured image > coach_profile_photo meta (attachment ID) > Gravatar.
	 *
	 * @return string HTML markup.
	 */
	public function shortcode_avatar() {

		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user_id       = get_current_user_id();
		$coach_post_id = (int) get_user_meta( $user_id, 'coach_post_id', true );

		if ( ! $coach_post_id ) {
			return '';
		}

		$photo_url = '';

		// 1. Featured image.
		$thumbnail = get_the_post_thumbnail_url( $coach_post_id, 'medium' );
		if ( $thumbnail ) {
			$photo_url = $thumbnail;
		}

		// 2. coach_profile_photo meta (stored as attachment ID).
		if ( ! $photo_url ) {
			$attachment_id = (int) get_post_meta( $coach_post_id, 'coach_profile_photo', true );
			if ( $attachment_id ) {
				$attachment_url = wp_get_attachment_image_url( $attachment_id, 'medium' );
				if ( $attachment_url ) {
					$photo_url = $attachment_url;
				}
			}
		}

		// 3. Gravatar fallback.
		if ( ! $photo_url ) {
			$user      = get_userdata( $user_id );
			$photo_url = get_avatar_url( $user->user_email, [ 'size' => 150 ] );
		}

		return '<div class="rm-coach-sidebar-avatar"><img src="' . esc_url( $photo_url ) . '" alt="' . esc_attr( 'Coach profile photo' ) . '" /></div>';
	}

	/**
	 * [rm_coach_name] — Coach display name.
	 *
	 * Uses the coach post title, falling back to WP display_name.
	 *
	 * @return string HTML markup.
	 */
	public function shortcode_name() {

		if ( ! is_user_logged_in() ) {
			return '';
		}

		$user_id       = get_current_user_id();
		$coach_post_id = (int) get_user_meta( $user_id, 'coach_post_id', true );

		$name = '';

		if ( $coach_post_id ) {
			$name = get_the_title( $coach_post_id );
		}

		if ( ! $name ) {
			$user = get_userdata( $user_id );
			$name = $user->display_name;
		}

		return '<div class="rm-coach-sidebar-name">' . esc_html( $name ) . '</div>';
	}

	/**
	 * [rm_coach_profile_url] — Permalink to the coach's public profile.
	 *
	 * @return string URL or '#' if no coach post.
	 */
	public function shortcode_profile_url() {

		if ( ! is_user_logged_in() ) {
			return '#';
		}

		$coach_post_id = (int) get_user_meta( get_current_user_id(), 'coach_post_id', true );

		if ( ! $coach_post_id ) {
			return '#';
		}

		return esc_url( get_permalink( $coach_post_id ) );
	}

	/* ------------------------------------------------------------------
	 * 8. Inject profile photo URL for the JS upload-preview fix.
	 * ----------------------------------------------------------------*/

	/**
	 * On the profile edit page, output the coach's profile photo URL
	 * as a JS variable so the UI tweaks plugin can display it even
	 * when JFB doesn't preset the file upload field.
	 */
	public function inject_profile_photo_url() {

		if ( ! is_user_logged_in() ) {
			return;
		}

		$request_uri = trim( wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );

		if ( 'coach-dashboard/profile' !== $request_uri ) {
			return;
		}

		$coach_post_id = (int) get_user_meta( get_current_user_id(), 'coach_post_id', true );

		if ( ! $coach_post_id ) {
			return;
		}

		$photo_url = '';

		// Try featured image first.
		$thumb_url = get_the_post_thumbnail_url( $coach_post_id, 'medium' );
		if ( $thumb_url ) {
			$photo_url = $thumb_url;
		}

		// Fallback to coach_profile_photo meta.
		if ( ! $photo_url ) {
			$att_id = (int) get_post_meta( $coach_post_id, 'coach_profile_photo', true );
			if ( $att_id ) {
				$att_url = wp_get_attachment_image_url( $att_id, 'medium' );
				if ( $att_url ) {
					$photo_url = $att_url;
				}
			}
		}

		if ( ! $photo_url ) {
			return;
		}

		?>
		<script>
		window.rmCoachProfilePhotoUrl = <?php echo wp_json_encode( $photo_url ); ?>;
		</script>
		<?php
	}
}
