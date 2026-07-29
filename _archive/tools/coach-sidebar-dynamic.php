/**
 * RideMaster — Coach Dashboard Sidebar: Dynamic avatar + name
 * Add this as a PHP snippet in Code Snippets plugin.
 *
 * Problem: The dashboard page is a regular WP page, not a Coach CPT single.
 * Elementor's "Featured Image" widget shows the page's image, not the coach's.
 * JetEngine dynamic fields from listings don't work here (wrong post context).
 *
 * Solution: Two shortcodes that resolve current user → coach post → data.
 *
 * Usage in Elementor:
 *   - Replace the Featured Image widget with a Shortcode widget: [rm_coach_avatar]
 *   - Add a Text Editor or Shortcode widget for the name: [rm_coach_name]
 */

/**
 * [rm_coach_avatar] — Outputs the logged-in coach's profile photo.
 *
 * Priority: coach post featured image → coach_profile_photo meta → Gravatar.
 *
 * Why featured image first? The profile page (and inline-edit system) treats
 * coach_profile_photo as type "featured_image", so edits update _thumbnail_id.
 * The separate coach_profile_photo meta field may contain an older upload from
 * JetFormBuilder that no longer matches. Featured image is the source of truth.
 */
add_shortcode( 'rm_coach_avatar', function() {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return '';
    }

    $coach_post_id = get_user_meta( $user_id, 'coach_post_id', true );
    $img_url       = '';

    if ( $coach_post_id ) {
        // 1. Featured image of the coach post (source of truth for profile photo)
        $img_url = get_the_post_thumbnail_url( $coach_post_id, 'medium' );

        // 2. Fallback: coach_profile_photo meta field
        if ( ! $img_url ) {
            $photo_id = get_post_meta( $coach_post_id, 'coach_profile_photo', true );
            if ( $photo_id ) {
                $img_url = wp_get_attachment_image_url( $photo_id, 'medium' );
            }
        }
    }

    // 3. Final fallback: Gravatar
    if ( ! $img_url ) {
        $img_url = get_avatar_url( $user_id, [ 'size' => 300 ] );
    }

    return '<div class="rm-coach-sidebar-avatar">'
         . '<img src="' . esc_url( $img_url ) . '" alt="' . esc_attr__( 'Coach profile photo', 'ridemaster' ) . '" />'
         . '</div>';
} );


/**
 * [rm_coach_name] — Outputs the logged-in coach's display name.
 *
 * Priority: coach post title → WordPress display_name.
 */
add_shortcode( 'rm_coach_name', function() {
    $user_id = get_current_user_id();
    if ( ! $user_id ) {
        return '';
    }

    $name          = '';
    $coach_post_id = get_user_meta( $user_id, 'coach_post_id', true );

    if ( $coach_post_id ) {
        $coach_post = get_post( $coach_post_id );
        if ( $coach_post && ! empty( $coach_post->post_title ) ) {
            $name = $coach_post->post_title;
        }
    }

    // Fallback: WordPress user display name
    if ( ! $name ) {
        $user = get_userdata( $user_id );
        $name = $user ? $user->display_name : '';
    }

    if ( ! $name ) {
        return '';
    }

    return '<div class="rm-coach-sidebar-name">' . esc_html( $name ) . '</div>';
} );
