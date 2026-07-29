<?php
/**
 * Plugin Name: Ridemaster Translate Helper
 * Description: Temporary FR translation helpers + EN→FR template auto-sync.
 * Version: 2.0
 */

/**
 * FR context detection that also works in AJAX/REST submissions where WPML may
 * not yet resolve the language. Falls back to the referer URL.
 */
function rm_is_fr_context() {
    if ( apply_filters( 'wpml_current_language', null ) === 'fr' ) return true;
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    return $ref && strpos( $ref, '/fr/' ) !== false;
}

/* ==========================================================================
 * Global FR URL swap — runs on FR page output to rewrite any hardcoded EN
 * URLs left in templates / WooCommerce / shortcodes to their FR equivalents.
 * ========================================================================== */

/**
 * URL swap map (EN → FR). Order matters for prefix matching — longer paths first.
 */
function rm_fr_url_map() {
    static $map = null;
    if ( $map !== null ) return $map;
    $map = [
        // Absolute URLs (with host)
        'https://ridemaster.eu/my-account/lost-password' => 'https://ridemaster.eu/fr/mon-compte/lost-password',
        'https://ridemaster.eu/my-account'               => 'https://ridemaster.eu/fr/mon-compte',
        'https://ridemaster.eu/coach-register'           => 'https://ridemaster.eu/fr/inscription-coach',
        'https://ridemaster.eu/coach-dashboard'          => 'https://ridemaster.eu/fr/coach-dashboard',
        'https://ridemaster.eu/login'                    => 'https://ridemaster.eu/fr/connexion',
        'https://ridemaster.eu/cart'                     => 'https://ridemaster.eu/fr/panier',
        'https://ridemaster.eu/checkout'                 => 'https://ridemaster.eu/fr/commander',
        'https://ridemaster.eu/cookie-policy-eu'         => 'https://ridemaster.eu/fr/cookie-policy-eu',
        'https://ridemaster.eu/privacy-policy'           => 'https://ridemaster.eu/fr/privacy-policy',
        // Relative URLs (Elementor sometimes stores these)
        '"url":"\/my-account'                            => '"url":"\/fr\/mon-compte',
        '"url":"\/coach-register'                        => '"url":"\/fr\/inscription-coach',
        '"url":"\/coach-dashboard'                       => '"url":"\/fr\/coach-dashboard',
        '"url":"\/login'                                 => '"url":"\/fr\/connexion',
        '"url":"\/cart'                                  => '"url":"\/fr\/panier',
        'href="/my-account'                              => 'href="/fr/mon-compte',
        'href="/coach-register'                          => 'href="/fr/inscription-coach',
        'href="/coach-dashboard'                         => 'href="/fr/coach-dashboard',
        'href="/login'                                   => 'href="/fr/connexion',
        'href="/cart'                                    => 'href="/fr/panier',
    ];
    return $map;
}

/**
 * Translate post permalinks to the current language's version. Ensures camp/
 * coach/spot/hotel cards on a FR page link to the FR translation (not the
 * default-language post that a listing query may have returned).
 */
function rm_lang_aware_permalink( $url, $post ) {
    static $lock = false;
    if ( $lock ) return $url;
    $current = apply_filters( 'wpml_current_language', null );
    if ( ! $current ) return $url;
    $post_id = is_object( $post ) ? $post->ID : (int) $post;
    if ( ! $post_id ) return $url;
    $post_type = is_object( $post ) ? $post->post_type : get_post_type( $post_id );
    if ( ! in_array( $post_type, [ 'coach', 'product', 'spot', 'hotel', 'page' ], true ) ) return $url;
    $translated = apply_filters( 'wpml_object_id', $post_id, $post_type, false, $current );
    if ( $translated && (int) $translated !== (int) $post_id ) {
        $lock = true;
        $new_url = get_permalink( (int) $translated );
        $lock = false;
        if ( $new_url ) return $new_url;
    }
    return $url;
}
add_filter( 'post_link',      'rm_lang_aware_permalink', 99, 2 );
add_filter( 'post_type_link', 'rm_lang_aware_permalink', 99, 2 );
add_filter( 'page_link',      'rm_lang_aware_permalink', 99, 2 );

/**
 * Output-buffer the entire response on FR pages and swap EN URLs to FR.
 * Skips admin / AJAX / REST contexts.
 */
add_action( 'template_redirect', function() {
    if ( is_admin() || wp_doing_ajax() ) return;
    if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) return;
    if ( apply_filters( 'wpml_current_language', null ) !== 'fr' ) return;

    ob_start( function( $html ) {
        if ( ! is_string( $html ) || $html === '' ) return $html;
        $map = rm_fr_url_map();
        // Skip any already-FR URL by checking each pair against the candidate
        return strtr( $html, $map );
    } );
}, 0 );

/* ==========================================================================
 * JFB → Coach: ensure profile/cover photos persist to the right meta keys.
 *
 * JFB's fields_map doesn't always handle `_thumbnail_id` (WordPress's special
 * featured-image meta) correctly. We resolve this by reading the raw form
 * values from $_REQUEST after the post is inserted and forcing the metas.
 * ========================================================================== */
/**
 * JFB redirect_to_page action: when the user submits a form from a FR page,
 * the JFB-stored redirect URL is a generic relative path (e.g. /coach-dashboard/
 * my-camps/) — WPML doesn't auto-prefix /fr/. Rewrite the response's redirect
 * URL to include /fr/ when the request originated from a FR page.
 */
/**
 * Hook the form-handler's response right before it's sent. Rewrite the
 * `redirect` URL to include /fr/ when the form was submitted from a FR page
 * (JFB's redirect_to_page stores a relative URL without language prefix).
 */
add_action( 'jet-form-builder/form-handler/after-send', function ( $form_handler, $is_success ) {
    if ( ! rm_is_fr_context() ) return;
    if ( empty( $form_handler->response_data['redirect'] ) ) return;
    $url = $form_handler->response_data['redirect'];
    if ( preg_match( '#^/(?!fr/)[a-z][a-z0-9-]+/#', $url ) ) {
        $form_handler->response_data['redirect'] = '/fr' . $url;
    } elseif ( strpos( $url, 'ridemaster.eu/' ) !== false && strpos( $url, 'ridemaster.eu/fr/' ) === false ) {
        $form_handler->response_data['redirect'] = preg_replace(
            '#(https?://ridemaster\.eu)/(?!fr/)#',
            '$1/fr/',
            $url
        );
    }
}, 5, 2 );

/**
 * Snapshot uploaded $_FILES at the EARLIEST possible point so they survive
 * even after JFB's processing consumes the tmp files. The IIFE runs at
 * MU-plugin parse time (before any hook), which is the earliest reachable
 * point inside WordPress's request lifecycle.
 */
( function () {
    if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) return;
    if ( empty( $_FILES ) ) return;
    $GLOBALS['rm_files_snapshot'] = [];
    foreach ( $_FILES as $name => $file ) {
        if ( ! is_array( $file ) || empty( $file['tmp_name'] ) ) continue;
        $tmp  = is_array( $file['tmp_name'] ) ? ( $file['tmp_name'][0] ?? '' ) : $file['tmp_name'];
        $oname = is_array( $file['name'] ) ? ( $file['name'][0] ?? 'file' ) : $file['name'];
        if ( ! $tmp || ! file_exists( $tmp ) ) continue;
        $safe_name = preg_replace( '/[^a-zA-Z0-9._-]/', '', $oname );
        $persist   = sys_get_temp_dir() . '/rm-' . substr( md5( $tmp . microtime( true ) ), 0, 10 ) . '-' . $safe_name;
        if ( @copy( $tmp, $persist ) ) {
            $GLOBALS['rm_files_snapshot'][ $name ] = [
                'path' => $persist,
                'name' => $oname,
                'type' => is_array( $file['type'] ) ? ( $file['type'][0] ?? '' ) : $file['type'],
            ];
        }
    }
} )();

add_action( 'jet-form-builder/action/after-post-insert', function( $action, $handler ) {
    $post_id = method_exists( $handler, 'get_inserted_post_id' ) ? $handler->get_inserted_post_id() : 0;
    if ( ! $post_id ) return;
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'coach' ) return;

    // JFB stores form values in jet_fb_context() — try that first.
    $get_value = function ( $field ) {
        if ( ! function_exists( 'jet_fb_context' ) ) return null;
        try {
            return jet_fb_context()->get_value( $field );
        } catch ( \Throwable $e ) {
            return null;
        }
    };

    // Helper: try jet_fb_context first, then fall back to the early file
    // snapshot we made in wp_loaded (JFB may have consumed the original $_FILES
    // tmp files by the time we get here).
    $resolve_attachment = function ( $field_name ) use ( $get_value, $post_id ) {
        $val = $get_value( $field_name );
        if ( is_array( $val ) ) $val = reset( $val );
        if ( is_numeric( $val ) && (int) $val > 0 ) {
            return (int) $val;
        }
        $snap = $GLOBALS['rm_files_snapshot'][ $field_name ] ?? null;
        if ( ! $snap || empty( $snap['path'] ) || ! file_exists( $snap['path'] ) ) {
            return 0;
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $upload_dir = wp_upload_dir();
        $base_name  = wp_unique_filename( $upload_dir['path'], $snap['name'] );
        $dest_path  = $upload_dir['path'] . '/' . $base_name;
        if ( ! @copy( $snap['path'], $dest_path ) ) return 0;
        @unlink( $snap['path'] );
        $mime = wp_check_filetype( $dest_path )['type'] ?: ( $snap['type'] ?: 'image/jpeg' );
        $attach_id = wp_insert_attachment( [
            'guid'           => $upload_dir['url'] . '/' . $base_name,
            'post_mime_type' => $mime,
            'post_title'     => preg_replace( '/\.[^.]+$/', '', $snap['name'] ),
            'post_content'   => '',
            'post_status'    => 'inherit',
            'post_author'    => get_post( $post_id )->post_author,
        ], $dest_path, $post_id );
        if ( ! $attach_id || is_wp_error( $attach_id ) ) return 0;
        wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $dest_path ) );
        return (int) $attach_id;
    };

    $profile = $resolve_attachment( 'coach_profile_photo' );
    if ( $profile ) set_post_thumbnail( $post_id, $profile );

    $cover = $resolve_attachment( 'coach_cover_photo' );
    if ( $cover ) update_post_meta( $post_id, 'cover_image', $cover );
}, 50, 2 );

/* ==========================================================================
 * Cascade-delete: when a coach user is deleted, also delete their coach CPT
 * and their camp products (so we don't leave orphan content visible on the
 * site).
 * ========================================================================== */
add_action( 'delete_user', function( $user_id, $reassign = null, $user = null ) {
    $coach_id = (int) get_user_meta( $user_id, 'coach_post_id', true );
    $to_trash = [];
    if ( $coach_id ) {
        $to_trash[] = $coach_id;
        // Also any WPML translations of the coach
        global $sitepress;
        if ( $sitepress ) {
            $trid = $sitepress->get_element_trid( $coach_id, 'post_coach' );
            if ( $trid ) {
                $tr = $sitepress->get_element_translations( $trid, 'post_coach', false, true );
                foreach ( $tr as $t ) $to_trash[] = (int) $t->element_id;
            }
        }
    }
    // All products by this author (and their translations)
    $author_camps = get_posts( [
        'post_type'      => 'product',
        'post_status'    => 'any',
        'author'         => $user_id,
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'suppress_filters' => true,
    ] );
    foreach ( $author_camps as $cid ) {
        $to_trash[] = (int) $cid;
        global $sitepress;
        if ( $sitepress ) {
            $trid = $sitepress->get_element_trid( $cid, 'post_product' );
            if ( $trid ) {
                $tr = $sitepress->get_element_translations( $trid, 'post_product', false, true );
                foreach ( $tr as $t ) $to_trash[] = (int) $t->element_id;
            }
        }
    }
    foreach ( array_unique( $to_trash ) as $pid ) {
        wp_delete_post( $pid, true );
    }
}, 10, 3 );

/* ==========================================================================
 * JFB UX: scroll + highlight first invalid required field on submit attempt.
 * ========================================================================== */
add_action( 'wp_footer', function() {
    if ( is_admin() ) return;
    ?>
    <script>
    /* RM JFB UX — manual validation + visual highlight.
       JFB sets <form novalidate> so HTML5 invalid events DO NOT fire. We do
       all validation ourselves on the submit button's click event (which fires
       before JFB's own submit handler binds via bubble). */
    (function() {
        if (!document.querySelector('.jet-form-builder')) return;
        var submitAttempted = new WeakSet();

        function fieldHasValue(f) {
            if (!f) return false;
            if (f.type === 'checkbox' || f.type === 'radio') return f.checked;
            if (f.tagName === 'SELECT') {
                return f.value !== '' && f.value !== null;
            }
            return (f.value || '').toString().trim() !== '';
        }

        function highlightField(field) {
            // Find the most useful wrapper to outline
            var wrap = field.closest('.jet-form-builder-row, .jet-form-builder__field-wrap, .jet-form-builder__row')
                || field.parentElement;
            if (wrap) wrap.classList.add('rm-jfb-invalid');
            // Also mark the field itself for inputs/selects styling
            field.classList.add('rm-jfb-invalid-field');
        }

        function clearField(field) {
            var wrap = field.closest('.jet-form-builder-row, .jet-form-builder__field-wrap, .jet-form-builder__row');
            if (wrap) wrap.classList.remove('rm-jfb-invalid');
            field.classList.remove('rm-jfb-invalid-field');
        }

        function validateForm(form) {
            var firstInvalid = null;

            // Required inputs (text/email/url/number/date/etc) + textareas + selects
            form.querySelectorAll('input[required]:not([type="checkbox"]):not([type="radio"]), select[required], textarea[required]').forEach(function(f) {
                if (!fieldHasValue(f) || (f.type === 'email' && !f.checkValidity())) {
                    highlightField(f);
                    if (!firstInvalid) firstInvalid = f;
                } else {
                    clearField(f);
                }
            });

            // Checkbox / radio groups: at least one in the group must be checked
            var groups = {};
            form.querySelectorAll('input[type="checkbox"][required], input[type="radio"][required]').forEach(function(cb) {
                var name = cb.name || cb.getAttribute('name');
                if (!name) return;
                if (!groups[name]) groups[name] = [];
                groups[name].push(cb);
            });
            Object.keys(groups).forEach(function(name) {
                var grp = groups[name];
                var any = grp.some(function(cb) { return cb.checked; });
                if (!any) {
                    // Find a wrap that contains ALL checkboxes in the group
                    // (the group's row) so we highlight the whole area, not
                    // just the first checkbox.
                    var groupWrap = grp[0].closest('.jet-form-builder-row');
                    if (groupWrap) {
                        groupWrap.classList.add('rm-jfb-invalid');
                    } else {
                        highlightField(grp[0]);
                    }
                    grp.forEach(function(cb) { cb.classList.add('rm-jfb-invalid-field'); });
                    if (!firstInvalid) firstInvalid = grp[0];
                } else {
                    var groupWrap = grp[0].closest('.jet-form-builder-row');
                    if (groupWrap) groupWrap.classList.remove('rm-jfb-invalid');
                    grp.forEach(clearField);
                }
            });

            return firstInvalid;
        }

        // Intercept the submit button at the capture phase — BEFORE JFB's own
        // click handler (which submits via AJAX). If invalid, prevent + highlight.
        document.addEventListener('click', function(e) {
            var btn = e.target;
            if (!btn || !btn.closest) return;
            // JFB submit buttons
            var submitBtn = btn.closest('button[type="submit"].jet-form-builder__submit, button[type="submit"].jet-form-builder__action-button');
            if (!submitBtn) return;
            var form = submitBtn.closest('.jet-form-builder');
            if (!form) return;

            submitAttempted.add(form);
            var firstInvalid = validateForm(form);
            if (firstInvalid) {
                e.preventDefault();
                e.stopImmediatePropagation();
                setTimeout(function() {
                    try { firstInvalid.scrollIntoView({behavior: 'smooth', block: 'center'}); } catch(_) {}
                    if (firstInvalid.focus) { try { firstInvalid.focus({preventScroll: true}); } catch(_) {} }
                }, 50);
                return false;
            }
        }, true);

        // Clear highlight live as the user fixes fields
        document.addEventListener('input', function(e) {
            var f = e.target;
            if (!f || !f.closest || !f.closest('.jet-form-builder')) return;
            var form = f.closest('.jet-form-builder');
            if (!submitAttempted.has(form)) return; // don't toggle pre-submit
            if (fieldHasValue(f) && (f.type !== 'email' || f.checkValidity())) clearField(f);
        }, true);

        document.addEventListener('change', function(e) {
            var f = e.target;
            if (!f || !f.closest || !f.closest('.jet-form-builder')) return;
            var form = f.closest('.jet-form-builder');
            if (!submitAttempted.has(form)) return;
            if (f.tagName === 'SELECT') {
                if (fieldHasValue(f)) clearField(f);
            }
            if (f.type === 'checkbox' && f.name) {
                var grp = form.querySelectorAll('input[type="checkbox"][name="' + f.name + '"]');
                var any = Array.from(grp).some(function(g) { return g.checked; });
                if (any) Array.from(grp).forEach(clearField);
            }
        }, true);
    })();
    </script>
    <style>
    .rm-jfb-invalid {
        outline: 3px solid #ef4444 !important;
        outline-offset: 4px;
        background: rgba(254, 242, 242, 0.7) !important;
        border-radius: 10px;
        animation: rm-shake 0.4s ease-in-out;
    }
    .rm-jfb-invalid input, .rm-jfb-invalid select, .rm-jfb-invalid textarea,
    input.rm-jfb-invalid-field, select.rm-jfb-invalid-field, textarea.rm-jfb-invalid-field {
        border: 2px solid #ef4444 !important;
        background: #fef2f2 !important;
    }
    @keyframes rm-shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-8px); }
        75% { transform: translateX(8px); }
    }
    /* Hide the accommodation listing leak in edit mode when no hotel is linked.
       The template's outer .rm-accommodation-section has a JEDV "exists _hotel_id"
       condition that hides it in display mode. Inline-edit may bypass that, so
       we force-hide ALL children of the section except the .rm-accommodation-empty
       placeholder when in edit mode without a linked hotel. */
    body.rm-camp-no-hotel.rm-edit-mode .rm-accommodation-section > *:not(.rm-accommodation-empty):not(:has(.rm-accommodation-empty)),
    body.rm-camp-no-hotel.rm-edit-mode .rm-accommodation-section [class*="jet-listing"],
    body.rm-camp-no-hotel.rm-edit-mode .rm-accommodation-section [class*="jet-engine"] {
        display: none !important;
    }
    body.rm-camp-no-hotel.rm-edit-mode .rm-accommodation-section .rm-accommodation-empty,
    body.rm-camp-no-hotel.rm-edit-mode .rm-accommodation-section .rm-accommodation-empty * {
        display: block !important;
    }
    </style>
    <?php
}, 100 );

/* ==========================================================================
 * WPML duplicate-as-translation for user-created content
 *
 * When a coach/camp/spot/hotel post is created via JFB form in language X,
 * automatically create a linked duplicate in language Y so both site
 * versions show the same content. Coach can later refine translations
 * field-by-field via the WPML translation editor.
 * ========================================================================== */

/**
 * Create a duplicate post in $target_lang linked to $master_id via WPML trid.
 * Returns new post ID or false.
 */
function rm_create_translation_duplicate( $master_id, $target_lang ) {
    global $sitepress, $wpdb;
    if ( ! $sitepress || ! $master_id ) return false;

    $master = get_post( $master_id );
    if ( ! $master ) return false;

    $element_type = 'post_' . $master->post_type;
    $trid = $sitepress->get_element_trid( $master_id, $element_type );
    if ( ! $trid ) return false;

    $translations = $sitepress->get_element_translations( $trid, $element_type, false, true );
    if ( ! empty( $translations[ $target_lang ] ) ) {
        return (int) $translations[ $target_lang ]->element_id;
    }

    // Avoid recursion when wp_insert_post triggers hooks again.
    remove_action( 'jet-form-builder/action/after-post-insert', 'rm_jfb_after_post_insert', 100 );
    remove_action( 'wp_after_insert_post', 'rm_wp_after_insert_post', 100 );

    $new_id = wp_insert_post( [
        'post_title'   => $master->post_title,
        'post_content' => $master->post_content,
        'post_excerpt' => $master->post_excerpt,
        'post_status'  => $master->post_status,
        'post_type'    => $master->post_type,
        'post_author'  => $master->post_author,
        'post_parent'  => $master->post_parent,
        'menu_order'   => $master->menu_order,
        'post_name'    => $master->post_name . '-' . $target_lang,
    ], true );

    add_action( 'jet-form-builder/action/after-post-insert', 'rm_jfb_after_post_insert', 100, 3 );
    add_action( 'wp_after_insert_post', 'rm_wp_after_insert_post', 100, 4 );

    if ( is_wp_error( $new_id ) || ! $new_id ) return false;

    // Copy postmeta (skip WPML/Elementor internal keys + our own sync flag).
    // Translate meta values that reference other WPML-translated posts so
    // (e.g. _coach_post_id, camp_spot, _hotel_id, hotel_id) the EN camp
    // points to the EN coach, not the FR one.
    $skip_prefixes = [ '_wpml_', '_icl_', '_wp_old_', '_edit_lock', '_edit_last' ];
    $skip_keys     = [ '_rm_translation_synced' ];
    // meta_key => post_type the value references (for WPML translation)
    $ref_keys = [
        '_coach_post_id' => 'coach',
        'camp_spot'      => 'spot',
        '_hotel_id'      => 'hotel',
        'hotel_id'       => 'hotel',
    ];
    $meta = get_post_meta( $master_id );
    foreach ( $meta as $key => $vals ) {
        if ( in_array( $key, $skip_keys, true ) ) continue;
        $skip = false;
        foreach ( $skip_prefixes as $p ) {
            if ( strpos( $key, $p ) === 0 ) { $skip = true; break; }
        }
        if ( $skip ) continue;
        foreach ( $vals as $val ) {
            $raw = maybe_unserialize( $val );
            // Translate post-id references to the target language
            if ( isset( $ref_keys[ $key ] ) && is_numeric( $raw ) && (int) $raw > 0 ) {
                $tr_id = (int) apply_filters( 'wpml_object_id', (int) $raw, $ref_keys[ $key ], true, $target_lang );
                if ( $tr_id ) $raw = $tr_id;
            }
            add_post_meta( $new_id, $key, $raw );
        }
    }

    // Mirror JetEngine relations (Coach-Camp = rel 20, Spot-Camp = rel 18) so
    // the EN camp dup links to the EN coach + EN spot, not the FR ones.
    if ( $master->post_type === 'product' && function_exists( 'jet_engine' ) && isset( jet_engine()->relations ) ) {
        $rel_table = 'vev_jet_rel_default';
        $rels = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$rel_table} WHERE child_object_id = %d",
            $master_id
        ) );
        foreach ( $rels as $r ) {
            $rel_id = (int) $r->rel_id;
            // 20 = Coach->Camp (parent=coach), 18 = Spot->Camp (parent=spot)
            $parent_type = $rel_id === 20 ? 'coach' : ( $rel_id === 18 ? 'spot' : null );
            if ( ! $parent_type ) continue;
            $parent_id = (int) $r->parent_object_id;
            $tr_parent = (int) apply_filters( 'wpml_object_id', $parent_id, $parent_type, true, $target_lang );
            if ( ! $tr_parent ) continue;
            // Avoid duplicate row if relation already exists
            $existing = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT _ID FROM {$rel_table} WHERE rel_id = %d AND parent_object_id = %d AND child_object_id = %d",
                $rel_id, $tr_parent, $new_id
            ) );
            if ( ! $existing ) {
                $wpdb->insert( $rel_table, [
                    'created'          => current_time( 'mysql' ),
                    'rel_id'           => $rel_id,
                    'parent_rel'       => 0,
                    'parent_object_id' => $tr_parent,
                    'child_object_id'  => $new_id,
                ] );
            }
        }
    }

    // Copy taxonomies via direct SQL (WPML's wp_set_object_terms filter
    // re-translates IDs to the request lang; SQL bypasses that).
    $taxonomies = get_object_taxonomies( $master->post_type );
    foreach ( $taxonomies as $tax ) {
        $terms = wp_get_object_terms( $master_id, $tax, [ 'fields' => 'ids' ] );
        if ( is_wp_error( $terms ) || empty( $terms ) ) continue;
        $translated = [];
        foreach ( $terms as $tid ) {
            $tr_tid = (int) apply_filters( 'wpml_object_id', (int) $tid, $tax, true, $target_lang );
            if ( $tr_tid ) $translated[] = $tr_tid;
        }
        $translated = array_values( array_unique( $translated ) );
        $wpdb->query( $wpdb->prepare(
            "DELETE tr FROM {$wpdb->term_relationships} tr
             JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             WHERE tr.object_id = %d AND tt.taxonomy = %s",
            $new_id, $tax
        ) );
        foreach ( $translated as $tid ) {
            $tt_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s",
                $tid, $tax
            ) );
            if ( $tt_id ) {
                $wpdb->insert( $wpdb->term_relationships, [ 'object_id' => $new_id, 'term_taxonomy_id' => $tt_id ] );
            }
        }
        if ( $translated ) wp_update_term_count_now( $translated, $tax );
        clean_object_term_cache( $new_id, $tax );
    }

    // Link translations via WPML
    $sitepress->set_element_language_details( $new_id, $element_type, $trid, $target_lang );

    update_post_meta( $master_id, '_rm_translation_synced', '1' );
    update_post_meta( $new_id, '_rm_translation_synced', '1' );

    return (int) $new_id;
}

function rm_should_sync_translation( $post ) {
    static $types = [ 'coach', 'product', 'spot', 'hotel' ];
    if ( ! in_array( $post->post_type, $types, true ) ) return false;
    if ( in_array( $post->post_status, [ 'auto-draft', 'inherit', 'trash' ], true ) ) return false;
    if ( get_post_meta( $post->ID, '_rm_translation_synced', true ) ) return false;
    return true;
}

function rm_ensure_translation_for_post( $post_id ) {
    $post = get_post( $post_id );
    if ( ! $post || ! rm_should_sync_translation( $post ) ) return;

    global $sitepress;
    if ( ! $sitepress ) return;

    $element_type = 'post_' . $post->post_type;
    $lang = $sitepress->get_language_for_element( $post_id, $element_type );

    if ( ! $lang ) {
        $lang = rm_is_fr_context() ? 'fr' : 'en';
        $sitepress->set_element_language_details( $post_id, $element_type, null, $lang );
    }

    $other = $lang === 'fr' ? 'en' : 'fr';
    $trid  = $sitepress->get_element_trid( $post_id, $element_type );
    $tr    = $sitepress->get_element_translations( $trid, $element_type, false, true );
    if ( isset( $tr[ $other ] ) ) {
        update_post_meta( $post_id, '_rm_translation_synced', '1' );
        return;
    }

    rm_create_translation_duplicate( $post_id, $other );
}

/**
 * Queue post IDs for translation-duplication, process on shutdown so all
 * meta written by later actions in the JFB chain is present.
 */
function rm_queue_translation_sync( $post_id ) {
    static $queued = [];
    if ( ! $post_id ) return;
    if ( isset( $queued[ $post_id ] ) ) return;
    $queued[ $post_id ] = true;
    $GLOBALS['rm_translation_queue'][ $post_id ] = true;
}

function rm_jfb_after_post_insert( $action, $handler, $base_action ) {
    $post_id = method_exists( $handler, 'get_inserted_post_id' ) ? $handler->get_inserted_post_id() : 0;
    if ( ! $post_id && isset( $handler->response_data['inserted_post_id'] ) ) {
        $post_id = (int) $handler->response_data['inserted_post_id'];
    }
    rm_queue_translation_sync( $post_id );
}
add_action( 'jet-form-builder/action/after-post-insert', 'rm_jfb_after_post_insert', 100, 3 );

function rm_wp_after_insert_post( $post_id, $post, $update, $post_before ) {
    if ( $update ) return;
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
    rm_queue_translation_sync( $post_id );
}
add_action( 'wp_after_insert_post', 'rm_wp_after_insert_post', 100, 4 );

/**
 * Process queued translation-syncs at shutdown — by then JFB has finished
 * its action chain (including any update_post_meta calls in later actions).
 */
add_action( 'shutdown', function() {
    $queue = $GLOBALS['rm_translation_queue'] ?? [];
    if ( ! $queue ) return;
    foreach ( array_keys( $queue ) as $pid ) {
        rm_ensure_translation_for_post( (int) $pid );
    }
    $GLOBALS['rm_translation_queue'] = [];
}, 0 );

/**
 * Bidirectional meta + taxonomy mirror: when a coach/camp/spot/hotel post is
 * edited (any meta update), mirror the same value to all its WPML translations.
 *
 * Triggered on updated_post_meta + added_post_meta. Uses a static guard to
 * avoid infinite recursion (a → b, b → a).
 */
function rm_mirror_meta_to_translations( $meta_id, $object_id, $meta_key, $meta_value ) {
    static $busy = false;
    if ( $busy ) return;
    if ( strpos( $meta_key, '_wpml_' ) === 0 || strpos( $meta_key, '_icl_' ) === 0 ) return;
    if ( $meta_key === '_rm_translation_synced' ) return;
    if ( $meta_key === '_edit_lock' || $meta_key === '_edit_last' ) return;

    $post = get_post( $object_id );
    if ( ! $post ) return;
    static $types = [ 'coach', 'product', 'spot', 'hotel' ];
    if ( ! in_array( $post->post_type, $types, true ) ) return;

    global $sitepress;
    if ( ! $sitepress ) return;
    $element_type = 'post_' . $post->post_type;
    $trid = $sitepress->get_element_trid( $object_id, $element_type );
    if ( ! $trid ) return;
    $tr = $sitepress->get_element_translations( $trid, $element_type, false, true );
    if ( count( $tr ) < 2 ) return;

    // Meta keys that reference WPML-translated posts — translate the value.
    static $ref_keys = [
        '_coach_post_id' => 'coach',
        'camp_spot'      => 'spot',
        '_hotel_id'      => 'hotel',
        'hotel_id'       => 'hotel',
    ];

    $busy = true;
    foreach ( $tr as $t ) {
        $tid = (int) $t->element_id;
        if ( $tid === (int) $object_id ) continue;
        $value_for_target = $meta_value;
        if ( isset( $ref_keys[ $meta_key ] ) && is_numeric( $meta_value ) && (int) $meta_value > 0 ) {
            $tr_ref = (int) apply_filters( 'wpml_object_id', (int) $meta_value, $ref_keys[ $meta_key ], true, $t->language_code );
            if ( $tr_ref ) $value_for_target = $tr_ref;
        }
        update_post_meta( $tid, $meta_key, $value_for_target );
    }
    $busy = false;
}
add_action( 'updated_post_meta', 'rm_mirror_meta_to_translations', 100, 4 );
add_action( 'added_post_meta',   'rm_mirror_meta_to_translations', 100, 4 );

/**
 * Mirror taxonomy edits across post translations — translates each term to
 * the target language's equivalent via WPML, so the FR Espagnol term (76)
 * applied to a FR post becomes the EN Spanish term (29) when mirrored to
 * the EN post. Without this, both terms would end up on both posts
 * (duplicate language tag bug).
 */
/**
 * Mirror $source_id's $terms (for $taxonomy) to all WPML translations of
 * the source, translating each term to the target language and writing via
 * direct SQL (bypasses WPML's wp_set_object_terms re-routing).
 *
 * Used by class-inline-edit.php's set_terms_direct() to avoid the silent
 * post-mutation that happens when calling do_action('set_object_terms').
 */
function rm_mirror_terms_directly( $source_id, $terms, $taxonomy ) {
    global $sitepress, $wpdb;
    if ( ! $sitepress ) return;
    $post = get_post( $source_id );
    if ( ! $post ) return;
    static $types = [ 'coach', 'product', 'spot', 'hotel' ];
    if ( ! in_array( $post->post_type, $types, true ) ) return;
    $element_type = 'post_' . $post->post_type;
    $trid = $sitepress->get_element_trid( $source_id, $element_type );
    if ( ! $trid ) return;
    $tr = $sitepress->get_element_translations( $trid, $element_type, false, true );
    if ( count( $tr ) < 2 ) return;
    foreach ( $tr as $t ) {
        $tid = (int) $t->element_id;
        if ( $tid === (int) $source_id ) continue;
        $target_lang = $t->language_code;
        $translated = [];
        foreach ( (array) $terms as $term_id ) {
            $term_id = (int) $term_id;
            if ( ! $term_id ) continue;
            $tr_term = (int) apply_filters( 'wpml_object_id', $term_id, $taxonomy, true, $target_lang );
            if ( $tr_term ) $translated[] = $tr_term;
        }
        $translated = array_values( array_unique( $translated ) );
        $wpdb->query( $wpdb->prepare(
            "DELETE tr FROM {$wpdb->term_relationships} tr
             JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             WHERE tr.object_id = %d AND tt.taxonomy = %s",
            $tid, $taxonomy
        ) );
        foreach ( $translated as $term_id ) {
            $tt_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s",
                $term_id, $taxonomy
            ) );
            if ( $tt_id ) {
                $wpdb->insert( $wpdb->term_relationships, [ 'object_id' => $tid, 'term_taxonomy_id' => $tt_id ] );
            }
        }
        if ( $translated ) wp_update_term_count_now( $translated, $taxonomy );
        clean_object_term_cache( $tid, $taxonomy );
    }
}

add_action( 'set_object_terms', function( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
    static $busy = false;
    if ( $busy ) return;
    $post = get_post( $object_id );
    if ( ! $post ) return;
    static $types = [ 'coach', 'product', 'spot', 'hotel' ];
    if ( ! in_array( $post->post_type, $types, true ) ) return;

    global $sitepress;
    if ( ! $sitepress ) return;
    $element_type = 'post_' . $post->post_type;
    $trid = $sitepress->get_element_trid( $object_id, $element_type );
    if ( ! $trid ) return;
    $tr = $sitepress->get_element_translations( $trid, $element_type, false, true );
    if ( count( $tr ) < 2 ) return;

    global $wpdb;
    $busy = true;
    foreach ( $tr as $t ) {
        $tid = (int) $t->element_id;
        if ( $tid === (int) $object_id ) continue;
        $target_lang = $t->language_code;
        $translated_terms = [];
        foreach ( (array) $terms as $term_id ) {
            $term_id = (int) $term_id;
            if ( ! $term_id ) continue;
            $tr_term = apply_filters( 'wpml_object_id', $term_id, $taxonomy, true, $target_lang );
            if ( $tr_term ) $translated_terms[] = (int) $tr_term;
        }
        $translated_terms = array_values( array_unique( $translated_terms ) );
        // Direct SQL — bypass WPML's wp_set_object_terms hook which re-routes
        // term IDs back to the current request language.
        $wpdb->query( $wpdb->prepare(
            "DELETE tr FROM {$wpdb->term_relationships} tr
             JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             WHERE tr.object_id = %d AND tt.taxonomy = %s",
            $tid, $taxonomy
        ) );
        foreach ( $translated_terms as $tt_id_term ) {
            $tt_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s",
                $tt_id_term, $taxonomy
            ) );
            if ( $tt_id ) {
                $wpdb->insert( $wpdb->term_relationships, [ 'object_id' => $tid, 'term_taxonomy_id' => $tt_id ] );
            }
        }
        if ( $translated_terms ) {
            wp_update_term_count_now( $translated_terms, $taxonomy );
        }
        clean_object_term_cache( $tid, $taxonomy );
    }
    $busy = false;
}, 100, 6 );

// --- Menu URL rewrite for FR custom links ---
add_filter( 'walker_nav_menu_start_el', function( $item_output, $item, $depth, $args ) {
    if ( apply_filters( 'wpml_current_language', null ) !== 'fr' ) return $item_output;
    $type = get_post_meta( $item->ID, '_menu_item_type', true );
    if ( $type !== 'custom' ) return $item_output;
    return preg_replace(
        '#(href="https://ridemaster\.eu/)(?!fr/)([a-z][a-z0-9-]*/)#',
        '$1fr/$2',
        $item_output
    );
}, 10, 4 );

// --- Menu item title translations ---
add_filter( 'nav_menu_item_title', function( $title, $item, $args, $depth ) {
    if ( apply_filters( 'wpml_current_language', null ) !== 'fr' ) return $title;
    return [ 'Coaches' => 'Coachs' ][ $title ] ?? $title;
}, 10, 4 );

// --- JetSmartFilters: translate UI text meta on FR ---
add_filter( 'get_post_metadata', function( $value, $object_id, $meta_key ) {
    if ( apply_filters( 'wpml_current_language', null ) !== 'fr' ) return $value;
    $placeholders_by_id = [ 513 => 'Rechercher un camp...', 1008 => 'Rechercher un coach...', 1022 => 'Rechercher un spot...' ];
    if ( $meta_key === '_s_placeholder' && isset( $placeholders_by_id[ $object_id ] ) ) return [ $placeholders_by_id[ $object_id ] ];
    $key_map = [
        '_date_period_datepicker_button_text' => 'Choisir une date',
        '_all_option_label'                   => 'Tous',
        '_date_from_placeholder'              => 'Du',
        '_date_to_placeholder'                => 'Au',
    ];
    if ( isset( $key_map[ $meta_key ] ) ) return [ $key_map[ $meta_key ] ];
    return $value;
}, 10, 3 );

// --- gettext FR overrides ---
add_filter( 'gettext', function( $translated, $original, $domain ) {
    if ( apply_filters( 'wpml_current_language', null ) !== 'fr' ) return $translated;
    static $map = null;
    if ( $map === null ) {
        $map = [
            'January'=>'Janvier','February'=>'Février','March'=>'Mars','April'=>'Avril','May'=>'Mai',
            'June'=>'Juin','July'=>'Juillet','August'=>'Août','September'=>'Septembre','October'=>'Octobre',
            'November'=>'Novembre','December'=>'Décembre',
            'Jan'=>'Janv','Feb'=>'Févr','Mar'=>'Mars','Apr'=>'Avr','Jun'=>'Juin','Jul'=>'Juil',
            'Aug'=>'Août','Sep'=>'Sept','Oct'=>'Oct','Nov'=>'Nov','Dec'=>'Déc',
            'Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi',
            'Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi',
            'Sun'=>'Dim','Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mer','Thu'=>'Jeu','Fri'=>'Ven','Sat'=>'Sam',
            'Today'=>'Aujourd\'hui','Clear'=>'Effacer','Close'=>'Fermer',
            'Read more'=>'Lire la suite','Search submit'=>'Soumettre la recherche',
            'Search'=>'Rechercher','Loading'=>'Chargement','Loading...'=>'Chargement...',
            'Coaches'=>'Coachs','Spots'=>'Spots','Hotels'=>'Hôtels',
            'Coach'=>'Coach','Spot'=>'Spot','Hotel'=>'Hôtel',
        ];
    }
    return $map[ $original ] ?? $translated;
}, 20, 3 );

// --- [language_flags] shortcode fallback ---
if ( ! shortcode_exists( 'language_flags' ) ) {
    add_shortcode( 'language_flags', function( $atts = [] ) {
        $atts = shortcode_atts( [ 'sep' => ' ', 'post_id' => 0 ], $atts );
        $pid = (int)( $atts['post_id'] ?: get_the_ID() );
        $terms = get_the_terms( $pid, 'language' );
        if ( ! $terms || is_wp_error( $terms ) ) return '';
        $out = [];
        foreach ( $terms as $t ) {
            $icon = get_term_meta( $t->term_id, 'flag_icon', true );
            $out[] = $icon ?: esc_html( $t->name );
        }
        return implode( $atts['sep'], $out );
    } );
}

// --- Yoast filters ---
add_filter( 'wpseo_title', function( $title ) {
    if ( ! is_string( $title ) || $title === '' ) return $title;
    if ( apply_filters( 'wpml_current_language', null ) !== 'fr' ) return $title;
    return strtr( $title, [ 'Coaches Archive' => 'Archives Coachs', 'Spots Archive' => 'Archives Spots', 'Hotels Archive' => 'Archives Hôtels' ] );
}, 99 );

add_filter( 'wpseo_opengraph_title', function( $title ) {
    if ( ! is_string( $title ) || $title === '' ) return $title;
    if ( apply_filters( 'wpml_current_language', null ) !== 'fr' ) return $title;
    return strtr( $title, [ 'Coaches Archive' => 'Archives Coachs', 'Spots Archive' => 'Archives Spots', 'Login' => 'Connexion' ] );
}, 99 );

add_filter( 'wpseo_opengraph_desc', function( $desc ) {
    if ( ! is_string( $desc ) || $desc === '' ) return $desc;
    if ( apply_filters( 'wpml_current_language', null ) !== 'fr' ) return $desc;
    return strtr( $desc, [
        'Welcome Back' => 'Bon retour',
        'Log in to your account' => 'Connectez-vous à votre compte',
        'No Account? Register as a coach here!' => 'Pas de compte ? Inscrivez-vous en tant que coach ici !',
    ] );
}, 99 );

add_filter( 'wpseo_schema_graph', function( $graph, $context ) {
    if ( apply_filters( 'wpml_current_language', null ) !== 'fr' ) return $graph;
    $map = [ 'Coaches Archive' => 'Archives Coachs', 'Spots Archive' => 'Archives Spots', 'Hotels Archive' => 'Archives Hôtels', 'Home' => 'Accueil', 'Coaches' => 'Coachs' ];
    array_walk_recursive( $graph, function( &$v ) use ( $map ) {
        if ( is_string( $v ) ) $v = strtr( $v, $map );
    } );
    return $graph;
}, 99, 2 );

// --- Disable WPML footer language switcher (user uses custom widget) ---
add_action( 'wp_loaded', function() {
    global $wp_filter;
    if ( isset( $wp_filter['wp_footer']->callbacks[19] ) ) {
        foreach ( $wp_filter['wp_footer']->callbacks[19] as $id => $info ) {
            if ( is_array( $info['function'] ) && is_object( $info['function'][0] ) ) {
                if ( get_class( $info['function'][0] ) === 'WPML_LS_Render' && $info['function'][1] === 'wp_footer_action' ) {
                    remove_action( 'wp_footer', [ $info['function'][0], 'wp_footer_action' ], 19 );
                }
            }
        }
    }
}, 999 );

/* ==========================================================================
 * EN→FR template auto-sync
 *
 * Whenever an EN template (elementor_library / jet-engine / jet-woo-builder)
 * is saved, regenerate its FR copy with:
 *  1. EN _elementor_data verbatim (preserves user's edits to layout/logo/flag)
 *  2. Swap EN→FR references for nested listings/templates
 *  3. Re-bake WPML string translations into the FR copy
 *
 * This makes EN the source of truth — FR is auto-generated.
 * ========================================================================== */

/**
 * Build the EN→FR ID map for a given post_type (jet-engine, etc.)
 * Returns [en_id => fr_id]
 */
function rm_build_en_fr_template_map() {
    static $map = null;
    if ( $map !== null ) return $map;
    global $wpdb;
    $rows = $wpdb->get_results(
        "SELECT t.element_id AS en_id, t2.element_id AS fr_id
         FROM {$wpdb->prefix}icl_translations t
         JOIN {$wpdb->prefix}icl_translations t2 ON t2.trid=t.trid AND t2.language_code='fr'
         WHERE t.language_code='en'
           AND t.element_type IN ('post_jet-engine','post_jet-woo-builder','post_elementor_library','post_page')"
    );
    $map = [];
    foreach ( $rows as $r ) $map[ (int) $r->en_id ] = (int) $r->fr_id;
    return $map;
}

/**
 * Swap EN→FR template/listing IDs inside an _elementor_data JSON string.
 */
function rm_swap_template_ids_in_data( $raw_data, $map ) {
    if ( ! is_string( $raw_data ) || empty( $raw_data ) ) return $raw_data;
    $new = $raw_data;
    foreach ( $map as $en_id => $fr_id ) {
        if ( $en_id === $fr_id ) continue;
        $new = str_replace( '"lisitng_id":"' . $en_id . '"', '"lisitng_id":"' . $fr_id . '"', $new );
        $new = str_replace( '"listing_id":"' . $en_id . '"', '"listing_id":"' . $fr_id . '"', $new );
        $new = str_replace( '"template_id":"' . $en_id . '"', '"template_id":"' . $fr_id . '"', $new );
        $new = str_replace( '"lisitng_id":' . $en_id . ',', '"lisitng_id":' . $fr_id . ',', $new );
        $new = str_replace( '"listing_id":' . $en_id . ',', '"listing_id":' . $fr_id . ',', $new );
    }
    return $new;
}

/**
 * Sync one EN template to its FR copy. Returns FR id or false.
 */
function rm_sync_en_to_fr( $en_id ) {
    global $wpdb;
    $sitepress = $GLOBALS['sitepress'] ?? null;
    if ( ! $sitepress ) return false;

    $en_post = get_post( $en_id );
    if ( ! $en_post ) return false;

    $element_type = 'post_' . $en_post->post_type;
    $trid = $sitepress->get_element_trid( $en_id, $element_type );
    if ( ! $trid ) return false;
    $tr = $sitepress->get_element_translations( $trid, $element_type, false, true );
    if ( empty( $tr['fr'] ) ) return false;
    $fr_id = (int) $tr['fr']->element_id;

    // 1. Copy EN _elementor_data verbatim to FR (preserves user edits to EN)
    $raw = $wpdb->get_var( $wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_elementor_data' LIMIT 1",
        $en_id
    ) );
    if ( ! $raw ) return $fr_id;
    $wpdb->update(
        $wpdb->postmeta,
        [ 'meta_value' => $raw ],
        [ 'post_id' => $fr_id, 'meta_key' => '_elementor_data' ],
        [ '%s' ],
        [ '%d', '%s' ]
    );

    // 2. Bake WPML string translations into FR copy (uses EN data → swaps EN strings to FR)
    if ( class_exists( 'WPML_Elementor_Translatable_Nodes' )
         && class_exists( 'WPML_Elementor_DB_Factory' )
         && class_exists( 'WPML_Elementor_Update_Translation' ) ) {
        try {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT s.name, st.value, st.status FROM {$wpdb->prefix}icl_strings s
                 JOIN {$wpdb->prefix}icl_string_translations st ON st.string_id=s.id
                 WHERE s.context=%s AND st.language='fr'",
                'elementor-' . $en_id
            ) );
            $strs = [];
            foreach ( $rows as $r ) {
                $strs[ $r->name ]['fr'] = [ 'value' => $r->value, 'status' => (int) $r->status ];
            }
            if ( $strs ) {
                $nodes = new WPML_Elementor_Translatable_Nodes();
                $ds    = new WPML_Elementor_Data_Settings( ( new WPML_Elementor_DB_Factory() )->create() );
                $updater = new WPML_Elementor_Update_Translation( $nodes, $ds );
                update_post_meta( $fr_id, '_last_translation_edit_mode', 'translation-editor' );
                $updater->update( $fr_id, $en_post, $strs, 'fr' );
            }
        } catch ( Throwable $e ) {
            error_log( "RM sync: bake fail for $en_id → $fr_id: " . $e->getMessage() );
        }
    }

    // 3. NOW swap EN→FR IDs on the baked FR data (after bake, so swap is permanent)
    $map = rm_build_en_fr_template_map();
    $baked = $wpdb->get_var( $wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_elementor_data' LIMIT 1",
        $fr_id
    ) );
    if ( $baked ) {
        $swapped = rm_swap_template_ids_in_data( $baked, $map );
        if ( $swapped !== $baked ) {
            $wpdb->update(
                $wpdb->postmeta,
                [ 'meta_value' => $swapped ],
                [ 'post_id' => $fr_id, 'meta_key' => '_elementor_data' ],
                [ '%s' ],
                [ '%d', '%s' ]
            );
        }
    }

    // 3b. Apply hardcoded EN→FR swaps for shortcode params not covered by WPML String Translation
    $baked2 = $wpdb->get_var( $wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key='_elementor_data' LIMIT 1",
        $fr_id
    ) );
    if ( $baked2 ) {
        $swaps = [
            // JetEngine dynamic-field "after" prefix params (URL-encoded JSON)
            '%22%20camp(s)%20available%22' => '%22%20camp(s)%20disponible(s)%22',
            '%22About%20%22'               => '%22%C3%80%20propos%20de%20%22',
            '%22Available%20Camps%20in%20%22' => '%22Camps%20disponibles%20%C3%A0%20%22',
            '%22Upcoming%20Camps%20by%20%22'  => '%22Camps%20%C3%A0%20venir%20avec%20%22',
            '%22Upcoming%20Camps%22'         => '%22Camps%20%C3%A0%20venir%22',
            '%22Active%20Camps%22'           => '%22Camps%20actifs%22',
            '%22Certified%20Coaches%22'      => '%22Coachs%20certifi%C3%A9s%22',
            // Date format settings
            '"date_range_format":"M d, Y"'   => '"date_range_format":"j M Y"',
            '"date_range_format":"M d"'      => '"date_range_format":"j M"',
            '"date_range_format_start":"M d"'  => '"date_range_format_start":"j M"',
            '"date_range_format_end":"M d, Y"' => '"date_range_format_end":"j M Y"',
            '"date_format":"M d, Y"'         => '"date_format":"j M Y"',
            '"date_format":"F j, Y"'         => '"date_format":"j F Y"',
            // JetWoo gallery grid overlay text
            '"grid_overlay_text":"More"' => '"grid_overlay_text":"Autres"',
            '"grid_overlay_text":"More Images"' => '"grid_overlay_text":"Autres images"',
                        // EN URLs → FR URLs (raw form, may appear in some shortcodes/widgets)
            'https://ridemaster.eu/coach-dashboard/' => 'https://ridemaster.eu/fr/coach-dashboard/',
            'https://ridemaster.eu/coach-register/'  => 'https://ridemaster.eu/fr/inscription-coach/',
            'https://ridemaster.eu/login/'           => 'https://ridemaster.eu/fr/connexion/',
            'https://ridemaster.eu/my-account/'      => 'https://ridemaster.eu/fr/mon-compte/',
            // JSON-escaped form (URLs inside _elementor_data are escaped with \/ )
            'https:\/\/ridemaster.eu\/coach-dashboard\/'             => 'https:\/\/ridemaster.eu\/fr\/tableau-de-bord-coach\/',
            'https:\/\/ridemaster.eu\/coach-dashboard\/profile'      => 'https:\/\/ridemaster.eu\/fr\/tableau-de-bord-coach\/profile',
            'https:\/\/ridemaster.eu\/coach-dashboard\/my-camps\/'   => 'https:\/\/ridemaster.eu\/fr\/tableau-de-bord-coach\/my-camps\/',
            'https:\/\/ridemaster.eu\/coach-dashboard\/create-camp\/' => 'https:\/\/ridemaster.eu\/fr\/tableau-de-bord-coach\/create-camp\/',
            'https:\/\/ridemaster.eu\/coach-dashboard\/create-spot\/' => 'https:\/\/ridemaster.eu\/fr\/tableau-de-bord-coach\/create-spot\/',
            'https:\/\/ridemaster.eu\/coach-dashboard\/create-hotel\/' => 'https:\/\/ridemaster.eu\/fr\/tableau-de-bord-coach\/create-hotel\/',
            'https:\/\/ridemaster.eu\/coach-dashboard'                => 'https:\/\/ridemaster.eu\/fr\/tableau-de-bord-coach',
            'https:\/\/ridemaster.eu\/coach-dashboard-2\/'             => 'https:\/\/ridemaster.eu\/fr\/tableau-de-bord-coach\/',
            'https:\/\/ridemaster.eu\/coach-dashboard-2'               => 'https:\/\/ridemaster.eu\/fr\/tableau-de-bord-coach',
            'https:\/\/ridemaster.eu\/coach-register\/'  => 'https:\/\/ridemaster.eu\/fr\/inscription-coach\/',
            'https:\/\/ridemaster.eu\/login\/'           => 'https:\/\/ridemaster.eu\/fr\/connexion\/',
            'https:\/\/ridemaster.eu\/my-account\/'      => 'https:\/\/ridemaster.eu\/fr\/mon-compte\/',
            'https:\/\/ridemaster.eu\/my-account'        => 'https:\/\/ridemaster.eu\/fr\/mon-compte',
        ];
        $swapped2 = strtr( $baked2, $swaps );
        if ( $swapped2 !== $baked2 ) {
            $wpdb->update(
                $wpdb->postmeta,
                [ 'meta_value' => $swapped2 ],
                [ 'post_id' => $fr_id, 'meta_key' => '_elementor_data' ],
                [ '%s' ],
                [ '%d', '%s' ]
            );
        }
    }

    // 4. Clear caches
    delete_post_meta( $fr_id, '_elementor_css' );
    delete_post_meta( $fr_id, '_elementor_element_cache' );
    $upload_dir = wp_upload_dir();
    $css_file = $upload_dir['basedir'] . '/elementor/css/post-' . $fr_id . '.css';
    if ( file_exists( $css_file ) ) @unlink( $css_file );

    if ( class_exists( '\\Elementor\\Plugin' ) ) {
        \Elementor\Plugin::$instance->files_manager->clear_cache();
    }

    return $fr_id;
}

/**
 * On save_post: if it's an EN Elementor-built post, sync to FR.
 */
add_action( 'save_post', function( $post_id, $post ) {
    if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) return;
    if ( ! in_array( $post->post_type, [ 'elementor_library', 'jet-engine', 'jet-woo-builder', 'page' ], true ) ) return;

    $sitepress = $GLOBALS['sitepress'] ?? null;
    if ( ! $sitepress ) return;

    $lang = $sitepress->get_language_for_element( $post_id, 'post_' . $post->post_type );
    if ( $lang !== 'en' ) return; // only sync FROM EN

    rm_sync_en_to_fr( $post_id );

    // Also clear SG cache for FR pages that might use this template
    if ( class_exists( 'SiteGround_Optimizer\\Supercacher\\Supercacher' ) ) {
        \SiteGround_Optimizer\Supercacher\Supercacher::purge_cache();
    }
}, 99, 2 );

/**
 * WP-CLI command to manually re-sync all EN templates to FR.
 *   wp rm sync-all-templates
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    WP_CLI::add_command( 'rm sync-all-templates', function() {
        global $wpdb;
        $en_ids = $wpdb->get_col(
            "SELECT element_id FROM {$wpdb->prefix}icl_translations
             WHERE language_code='en'
               AND element_type IN ('post_jet-engine','post_jet-woo-builder','post_elementor_library','post_page')"
        );
        $synced = 0;
        foreach ( $en_ids as $en_id ) {
            $fr = rm_sync_en_to_fr( (int) $en_id );
            if ( $fr ) {
                $synced++;
                WP_CLI::log( "✓ EN $en_id → FR $fr" );
            }
        }
        WP_CLI::success( "Synced $synced templates" );
    } );
}


// --- Fix WPML stripping logo attachment ID for FR pages ---
// On FR pages, WPML's get_post filter returns null for attachments with no FR translation,
// breaking Elementor's site-logo dynamic tag.
// Re-derive the logo ID directly from the option to bypass WPML's filter.
add_filter( 'theme_mod_custom_logo', function( $value ) {
    if ( ! empty( $value ) ) return $value;
    // Read directly from option (bypass WPML)
    $mods = get_option( 'theme_mods_' . get_stylesheet() );
    return $mods['custom_logo'] ?? $value;
}, 99 );

// Also force wp_get_attachment_image_src to return correct data for the logo even if attachment query is filtered out
add_filter( 'wp_get_attachment_image_src', function( $image, $attachment_id, $size ) {
    if ( $image !== false || ! $attachment_id ) return $image;
    // Empty result for a valid attachment ID — happens when WPML filters out attachments without FR translation
    // Re-query directly without WPML interference
    if ( has_filter( 'wpml_unfiltered_admin_string' ) ) {
        // Temporarily remove WPML hooks on get_post
        $post = get_post( $attachment_id );
        if ( $post && $post->post_type === 'attachment' ) {
            $meta = wp_get_attachment_metadata( $attachment_id );
            $url  = wp_get_attachment_url( $attachment_id );
            if ( $url ) {
                $w = $meta['width'] ?? 500;
                $h = $meta['height'] ?? 226;
                return [ $url, $w, $h, false ];
            }
        }
    }
    return $image;
}, 99, 3 );

// --- JetForm Builder: translate form labels/placeholders on FR pages ---
// Uses render_block_data filter so it works regardless of how the form is rendered
add_filter( 'render_block_data', function( $block ) {
    if ( apply_filters( 'wpml_current_language', null ) !== 'fr' ) return $block;
    if ( empty( $block['blockName'] ) || strpos( $block['blockName'], 'jet-forms/' ) !== 0 ) return $block;

    static $map = null;
    if ( $map === null ) {
        $map = [
            'Personal Information'   => 'Informations personnelles',
            'General Information'    => 'Informations générales',
            'Camp Details'           => 'Détails du camp',
            'Dates & Pricing'        => 'Dates & Tarifs',
            'Categorization'         => 'Catégorisation',
            'Photos'                 => 'Photos',
            'Social Links'           => 'Réseaux sociaux',
            'Sports & Languages'     => 'Sports & Langues',
            'First Name'             => 'Prénom',
            'Last Name'              => 'Nom',
            'Email'                  => 'Email',
            'Password'               => 'Mot de passe',
            'Confirm Password'       => 'Confirmer le mot de passe',
            'About You'              => 'À propos de vous',
            'Years of Experience'    => "Années d'expérience",
            'Certifications'         => 'Certifications',
            'Your certification'     => 'Votre certification',
            'Upload your certifications documents' => 'Téléversez vos documents de certification',
            'Experience'             => 'Expérience',
            'Cover Photo'            => 'Photo de couverture',
            'Profile Photo'          => 'Photo de profil',
            'Location'               => 'Lieu',
            'Sports'                 => 'Sports',
            'Sport'                  => 'Sport',
            'Sports You Teach'       => 'Sports enseignés',
            'Languages Spoken'       => 'Langues parlées',
            'Language'               => 'Langue',
            'Instagram'              => 'Instagram',
            'YouTube'                => 'YouTube',
            'Website'                => 'Site Web',
            'Camp Name'              => 'Nom du camp',
            'Camp Description'       => 'Description du camp',
            'Camp language'          => 'Langue du camp',
            'Description'            => 'Description',
            'Start Date'             => 'Date de début',
            'End Date'               => 'Date de fin',
            'Price Per Person'       => 'Prix par personne',
            'Maximum Participants'   => 'Participants max.',
            'Levels Accepted'        => 'Niveaux acceptés',
            'Levels'                 => 'Niveaux',
            'Main Camp Image (Featured Image)' => 'Image principale du camp (image mise en avant)',
            'Photo Gallery (max 10 images)' => 'Galerie photo (max 10 images)',
            "What's Included"        => 'Ce qui est inclus',
            "What's Not Included"    => "Ce qui n'est pas inclus",
            'Included in the camp'   => 'Inclus dans le camp',
            'Not Included in the camp' => 'Non inclus dans le camp',
            'Spot / Destination'     => 'Spot / Destination',
            'Select a Spot'          => 'Sélectionner un spot',
            'Accommodation Name'     => "Nom de l'hébergement",
            'Accommodation Photos'   => "Photos de l'hébergement",
            'Hotel amount per person (EUR)' => 'Montant hôtel par personne (EUR)',
            'Select Hotel'           => 'Sélectionner un hôtel',
            'Spot Name'              => 'Nom du spot',
            'Spot Description'       => 'Description du spot',
            'Spot Gallery images'    => 'Images de la galerie du spot',
            'Main spot image'        => 'Image principale du spot',
            'Country'                => 'Pays',
            'Region'                 => 'Région',
            'Address'                => 'Adresse',
            'Wind Direction'         => 'Direction du vent',
            'Best Season'            => 'Meilleure saison',
            'Nearest Airport'        => 'Aéroport le plus proche',
            'Timezone'               => 'Fuseau horaire',
            'Currency'               => 'Monnaie',
            'Wetsuit'                => 'Combinaison',
            'Water Temperature'      => "Température de l'eau",
            'Water Types'            => "Types d'eau",
            'IBAN'                   => 'IBAN',
            'Representative Name'    => 'Nom du représentant',
            'Representative Date of Birth' => 'Date de naissance du représentant',
            'Save My Profile'        => 'Enregistrer mon profil',
            'Publish My Camp'        => 'Publier mon camp',
            'Publish my Spot'        => 'Publier mon spot',
            'Create Hotel'           => "Créer l'hôtel",
            'Create my Coach account' => 'Créer mon compte coach',
            'Your first name'        => 'Votre prénom',
            'Your last name'         => 'Votre nom',
            'Enter your first name'  => 'Saisissez votre prénom',
            'Enter your last name'   => 'Saisissez votre nom',
            'Enter your email address' => 'Saisissez votre adresse email',
            'Choose a password'      => 'Choisissez un mot de passe',
            'Confirm your password'  => 'Confirmez votre mot de passe',
            'Choose an existing hotel...' => 'Choisir un hôtel existant...',
            'Choose an existing spot...'  => 'Choisir un spot existant...',
            'Describe your camp, what makes it unique, what participants will experience...' => 'Décrivez votre camp, ce qui le rend unique, ce que les participants vont vivre...',
            'IKO Level 3, First Aid, Rescue Cert... (one per line)' => 'IKO Niveau 3, Premiers secours, Cert. sauvetage... (une par ligne)',
            'e.g. 10'                => 'ex. 10',
            'e.g. 8'                 => 'ex. 8',
            'e.g. 890'               => 'ex. 890',
            'e.g. Coaching sessions, Equipment rental...' => 'ex. Sessions de coaching, location matériel...',
            'e.g. Flights, Meals, Travel insurance...'    => 'ex. Vols, repas, assurance voyage...',
            'e.g. Kite Week Tarifa - Beginner Friendly'   => 'ex. Semaine Kite Tarifa - Débutants bienvenus',
            'https://www.instagram.com/your-profile/'     => 'https://www.instagram.com/votre-profil/',
            'https://www.youtube.com/@your-channel'       => 'https://www.youtube.com/@votre-chaine',
            '0 if no hotel'          => "0 si pas d'hôtel",
        ];
    }
    if ( ! is_array( $block['attrs'] ?? null ) ) return $block;
    foreach ( [ 'label', 'placeholder', 'submit_label', 'button_label', 'text', 'title' ] as $key ) {
        if ( isset( $block['attrs'][ $key ] ) && isset( $map[ $block['attrs'][ $key ] ] ) ) {
            $block['attrs'][ $key ] = $map[ $block['attrs'][ $key ] ];
        }
    }
    return $block;
}, 5 );

/* ==========================================================================
 * JetForm Builder — FR translations for form messages + emails
 * ========================================================================== */

/**
 * Form-level message FR translations (success, validation, upload, etc).
 * Hooks `jet-form-builder/message-types` which lets us replace defaults.
 */
add_filter( 'jet-form-builder/message-types', function( $types ) {
    if ( ! rm_is_fr_context() ) return $types;
    $fr = [
        'success'           => 'Formulaire envoyé avec succès.',
        'failed'            => "Une erreur est survenue lors de l'envoi du formulaire. Veuillez réessayer plus tard.",
        'validation_failed' => 'Un ou plusieurs champs contiennent une erreur. Veuillez vérifier et réessayer.',
        'captcha_failed'    => 'Échec de la validation du captcha.',
        'invalid_email'     => "L'adresse e-mail saisie est invalide.",
        'empty_field'       => 'Ce champ est obligatoire.',
        'internal_error'    => 'Erreur interne du serveur. Veuillez réessayer plus tard.',
        'upload_max_files'  => "Limite maximale de fichiers téléversés atteinte.",
        'upload_max_size'   => 'Taille maximale de téléversement dépassée.',
        'upload_mime_types' => "Ce type de fichier n'est pas autorisé.",
    ];
    foreach ( $fr as $k => $v ) {
        if ( isset( $types[ $k ] ) ) $types[ $k ]['value'] = $v;
    }
    return $types;
}, 20 );

/**
 * Action-level message FR translations (Register User: username_exists, etc).
 * Saved per-form custom messages override these — see DB patch below.
 */
/**
 * JFB postmeta FR override: combined filter for _jf_messages + _jf_actions.
 * Reads raw value via $wpdb to avoid recursive get_post_meta calls.
 */
add_filter( 'get_post_metadata', function( $value, $object_id, $meta_key, $single ) {
    if ( $meta_key !== '_jf_messages' && $meta_key !== '_jf_actions' ) return $value;
    if ( ! rm_is_fr_context() ) return $value;

    static $cache = [];
    $ck = $meta_key . ':' . $object_id . ':' . ( $single ? '1' : '0' );
    if ( array_key_exists( $ck, $cache ) ) return $cache[ $ck ];

    global $wpdb;
    $raw = $wpdb->get_var( $wpdb->prepare(
        "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s LIMIT 1",
        $object_id, $meta_key
    ) );
    if ( $raw === null ) { $cache[ $ck ] = $value; return $value; }

    // Both _jf_messages and _jf_actions are stored as JSON strings.
    $data = json_decode( $raw, true );
    if ( ! is_array( $data ) ) { $cache[ $ck ] = $value; return $value; }

    if ( $meta_key === '_jf_messages' ) {
        $fr_msgs = [
            'success'           => 'Formulaire envoyé avec succès.',
            'failed'            => "Une erreur est survenue lors de l'envoi du formulaire. Veuillez réessayer plus tard.",
            'validation_failed' => 'Un ou plusieurs champs contiennent une erreur. Veuillez vérifier et réessayer.',
            'captcha_failed'    => 'Échec de la validation du captcha.',
            'invalid_email'     => "L'adresse e-mail saisie est invalide.",
            'empty_field'       => 'Ce champ est obligatoire.',
            'internal_error'    => 'Erreur interne du serveur. Veuillez réessayer plus tard.',
            'upload_max_files'  => 'Limite maximale de fichiers téléversés atteinte.',
            'upload_max_size'   => 'Taille maximale de téléversement dépassée.',
            'upload_mime_types' => "Ce type de fichier n'est pas autorisé.",
        ];
        foreach ( $fr_msgs as $k => $v ) {
            if ( isset( $data[ $k ] ) ) $data[ $k ] = $v;
        }
    } else {
        // _jf_actions: rewrite register_user messages
        $fr_register_msgs = [
            'username_exists'        => "Cet identifiant est déjà utilisé.",
            'empty_password'         => "Veuillez définir un mot de passe.",
            'already_logged_in'      => "Vous êtes déjà connecté.",
            'not_logged_in'          => "Vous n'êtes pas connecté.",
            'not_enough_cap'         => "Permissions insuffisantes pour créer un compte.",
            'password_mismatch'      => "Les mots de passe ne correspondent pas.",
            'email_exists'           => "Cette adresse e-mail est déjà utilisée.",
            'sanitize_user'          => "L'identifiant contient des caractères non autorisés.",
            'empty_username'         => "Veuillez saisir un identifiant.",
            'empty_email'            => "Veuillez saisir une adresse e-mail.",
            'incorrect_old_password' => "L'ancien mot de passe saisi est incorrect.",
        ];
        foreach ( $data as &$a ) {
            if ( ( $a['type'] ?? '' ) === 'register_user'
                && isset( $a['settings']['register_user']['messages'] ) ) {
                foreach ( $fr_register_msgs as $k => $v ) {
                    if ( isset( $a['settings']['register_user']['messages'][ $k ] ) ) {
                        $a['settings']['register_user']['messages'][ $k ] = $v;
                    }
                }
            }
        }
        unset( $a );
    }

    $json = wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    $cache[ $ck ] = [ $json ];
    return [ $json ];
}, 10, 4 );

add_filter( 'jet-form-builder/form-messages/register', function( $messages_types ) {
    if ( ! rm_is_fr_context() ) return $messages_types;
    if ( ! class_exists( 'Jet_Form_Builder\\Form_Messages\\Actions\\Base_Action_Messages' ) ) return $messages_types;

    if ( ! class_exists( 'RM_FR_Override_Messages' ) ) {
        eval( '
            class RM_FR_Override_Messages extends \\Jet_Form_Builder\\Form_Messages\\Actions\\Base_Action_Messages {
                public function is_supported( \\Jet_Form_Builder\\Actions\\Types\\Base $action ): bool { return true; }
                protected function messages(): array {
                    return [
                        "username_exists"        => [ "label" => "X", "value" => "Cet identifiant est déjà utilisé." ],
                        "empty_password"         => [ "label" => "X", "value" => "Veuillez définir un mot de passe." ],
                        "already_logged_in"      => [ "label" => "X", "value" => "Vous êtes déjà connecté." ],
                        "not_logged_in"          => [ "label" => "X", "value" => "Vous n\'êtes pas connecté." ],
                        "not_enough_cap"         => [ "label" => "X", "value" => "Permissions insuffisantes pour créer un compte." ],
                        "password_mismatch"      => [ "label" => "X", "value" => "Les mots de passe ne correspondent pas." ],
                        "email_exists"           => [ "label" => "X", "value" => "Cette adresse e-mail est déjà utilisée." ],
                        "sanitize_user"          => [ "label" => "X", "value" => "L\'identifiant contient des caractères non autorisés." ],
                        "empty_username"         => [ "label" => "X", "value" => "Veuillez saisir un identifiant." ],
                        "empty_email"            => [ "label" => "X", "value" => "Veuillez saisir une adresse e-mail." ],
                        "incorrect_old_password" => [ "label" => "X", "value" => "L\'ancien mot de passe saisi est incorrect." ],
                    ];
                }
            }
        ' );
    }
    $messages_types[] = new RM_FR_Override_Messages();
    return $messages_types;
}, 99 );

/**
 * JFB welcome email body — translate to FR when form submitted from FR page.
 * The form's email content lives in postmeta; we filter the body at send-time.
 */
add_filter( 'jet-form-builder/send-email/message_content', function( $message ) {
    if ( ! rm_is_fr_context() ) return $message;
    // Welcome-coach email (form 1595, action index 4) — match by signature phrase
    if ( strpos( $message, 'Thanks for registering as a Coach on Ridemaster' ) !== false ) {
        return '<h2>Bienvenue sur Ridemaster, %coach_first_name% !</h2>'
            . '<p>Merci de vous être inscrit en tant que coach sur Ridemaster. Nous sommes ravis de vous accueillir !</p>'
            . '<p>Votre compte est actuellement <strong>en cours de vérification</strong>. Notre équipe va valider votre profil et l\'activer rapidement. Vous recevrez un e-mail de confirmation dès que votre compte sera approuvé.</p>'
            . '<p>En attendant, voici les prochaines étapes :</p>'
            . '<ol>'
            . '<li>Notre équipe examine votre inscription</li>'
            . '<li>Une fois approuvé, vous pourrez vous connecter à votre tableau de bord coach</li>'
            . '<li>Vous pourrez ensuite compléter votre profil, ajouter des photos et créer votre premier camp</li>'
            . '</ol>'
            . '<p>Si vous avez des questions, répondez simplement à cet e-mail.</p>'
            . '<p>À bientôt sur Ridemaster ! 🏄</p>'
            . '<p><em>L\'équipe Ridemaster</em></p>';
    }
    return $message;
}, 10 );

/**
 * JFB email subject — JFB has no filter for subject. We intercept via wp_mail
 * itself (see rm_filter_outgoing_mail below).
 */

/**
 * Store the user's signup language on user_register so later emails (admin
 * approval, etc.) can be sent in the right language.
 */
add_action( 'user_register', function( $user_id ) {
    if ( rm_is_fr_context() ) {
        update_user_meta( $user_id, 'rm_signup_lang', 'fr' );
    } else {
        update_user_meta( $user_id, 'rm_signup_lang', 'en' );
    }
}, 10, 1 );

/* ==========================================================================
 * Outgoing mail sender + FR subject rewriting
 * ========================================================================== */

add_filter( 'wp_mail_from', function( $email ) {
    // Force all outgoing mail to come from the Ridemaster Gmail account so
    // deliverability is handled by Google's reputation (avoids spam on Hotmail).
    if ( $email !== 'ridemaster.coaching@gmail.com' ) {
        return 'ridemaster.coaching@gmail.com';
    }
    return $email;
}, 5 );

add_filter( 'wp_mail_from_name', function( $name ) {
    if ( $name === 'WordPress' || $name === '' || $name === null ) return 'Ridemaster';
    return $name;
}, 5 );

/**
 * Rewrite outgoing email subjects when in FR context (JFB doesn't filter
 * subjects). Matches by EN signature; safe no-op otherwise.
 */
add_filter( 'wp_mail', function( $args ) {
    if ( empty( $args['subject'] ) ) return $args;

    // Coach welcome email from JFB — FR if form was submitted from FR page
    if ( rm_is_fr_context()
        && strpos( $args['subject'], 'Thanks for registering as a Coach on Ridemaster' ) !== false ) {
        $args['subject'] = 'Merci de votre inscription en tant que coach sur Ridemaster.';
    }
    return $args;
}, 5 );
