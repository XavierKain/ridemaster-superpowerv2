<?php
/**
 * RideMaster - Coach Status Column for wp-admin
 *
 * Adds a colored status column to the Coaches list with inline select to change status.
 * Statuses: pending (orange), validated (green), suspended (red)
 *
 * When changing to "validated": auto-publishes the post + sends email to coach.
 */

// 1. Add the column header
add_filter( 'manage_coach_posts_columns', function( $columns ) {
    $new_columns = [];
    foreach ( $columns as $key => $label ) {
        $new_columns[ $key ] = $label;
        if ( $key === 'title' ) {
            $new_columns['coach_status'] = 'Status';
        }
    }
    return $new_columns;
});

// 2. Render the column content
add_action( 'manage_coach_posts_custom_column', function( $column, $post_id ) {
    if ( $column !== 'coach_status' ) return;

    $terms = wp_get_object_terms( $post_id, 'coach-status', [ 'fields' => 'slugs' ] );
    $current = ! empty( $terms ) ? $terms[0] : 'pending';

    $statuses = [
        'pending'   => [ 'label' => 'Pending',   'color' => '#f59e0b', 'bg' => '#fef3c7' ],
        'validated'  => [ 'label' => 'Validated',  'color' => '#10b981', 'bg' => '#d1fae5' ],
        'suspended' => [ 'label' => 'Suspended', 'color' => '#ef4444', 'bg' => '#fee2e2' ],
    ];

    $s = $statuses[ $current ] ?? $statuses['pending'];

    echo '<div class="rm-status-wrap" data-post-id="' . esc_attr( $post_id ) . '">';

    // Badge (visible by default)
    echo '<span class="rm-status-badge" style="
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        color: ' . esc_attr( $s['color'] ) . ';
        background: ' . esc_attr( $s['bg'] ) . ';
        border: 1px solid ' . esc_attr( $s['color'] ) . ';
    " title="Click to change">' . esc_html( $s['label'] ) . '</span>';

    // Select (hidden by default)
    echo '<select class="rm-status-select" style="display:none;">';
    foreach ( $statuses as $slug => $info ) {
        $selected = ( $slug === $current ) ? ' selected' : '';
        echo '<option value="' . esc_attr( $slug ) . '"' . $selected . '>' . esc_html( $info['label'] ) . '</option>';
    }
    echo '</select>';

    echo '</div>';
}, 10, 2 );

// 3. Make the column sortable
add_filter( 'manage_edit-coach_sortable_columns', function( $columns ) {
    $columns['coach_status'] = 'coach_status';
    return $columns;
});

// 4. Add filter dropdown above the list
add_action( 'restrict_manage_posts', function( $post_type ) {
    if ( $post_type !== 'coach' ) return;

    $current = $_GET['coach_status_filter'] ?? '';
    $statuses = [
        'pending'   => 'Pending',
        'validated'  => 'Validated',
        'suspended' => 'Suspended',
    ];

    echo '<select name="coach_status_filter">';
    echo '<option value="">All Statuses</option>';
    foreach ( $statuses as $slug => $label ) {
        $selected = ( $current === $slug ) ? ' selected' : '';
        echo '<option value="' . esc_attr( $slug ) . '"' . $selected . '>' . esc_html( $label ) . '</option>';
    }
    echo '</select>';
});

// 5. Apply the filter to the query
add_action( 'pre_get_posts', function( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) return;
    if ( $query->get( 'post_type' ) !== 'coach' ) return;

    $filter = $_GET['coach_status_filter'] ?? '';
    if ( $filter && in_array( $filter, [ 'pending', 'validated', 'suspended' ], true ) ) {
        $tax_query = $query->get( 'tax_query' ) ?: [];
        $tax_query[] = [
            'taxonomy' => 'coach-status',
            'field'    => 'slug',
            'terms'    => $filter,
        ];
        $query->set( 'tax_query', $tax_query );
    }
});

// 6. Enqueue JS + CSS on the coaches list page
add_action( 'admin_footer-edit.php', function() {
    global $post_type;
    if ( $post_type !== 'coach' ) return;
    ?>
    <script>
    (function() {
        var statusColors = {
            pending:   { color: '#f59e0b', bg: '#fef3c7' },
            validated:  { color: '#10b981', bg: '#d1fae5' },
            suspended: { color: '#ef4444', bg: '#fee2e2' }
        };
        var statusLabels = { pending: 'Pending', validated: 'Validated', suspended: 'Suspended' };

        document.querySelectorAll('.rm-status-wrap').forEach(function(wrap) {
            var badge  = wrap.querySelector('.rm-status-badge');
            var select = wrap.querySelector('.rm-status-select');
            var postId = wrap.dataset.postId;

            // Click badge -> show select
            badge.addEventListener('click', function() {
                badge.style.display = 'none';
                select.style.display = 'inline-block';
                select.focus();
            });

            // Close select without change
            select.addEventListener('blur', function() {
                select.style.display = 'none';
                badge.style.display = 'inline-block';
            });

            // Change status
            select.addEventListener('change', function() {
                var newStatus = select.value;
                badge.textContent = statusLabels[newStatus] || newStatus;
                var c = statusColors[newStatus] || statusColors.pending;
                badge.style.color = c.color;
                badge.style.background = c.bg;
                badge.style.borderColor = c.color;

                select.style.display = 'none';
                badge.style.display = 'inline-block';

                // AJAX update
                var data = new FormData();
                data.append('action', 'rm_update_coach_status');
                data.append('post_id', postId);
                data.append('status', newStatus);
                data.append('nonce', '<?php echo wp_create_nonce( "rm_coach_status" ); ?>');

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
    <style>
        .column-coach_status { width: 120px; }
        .rm-status-select { min-width: 100px; }
    </style>
    <?php
});

// 7. AJAX handler to update status
add_action( 'wp_ajax_rm_update_coach_status', function() {
    check_ajax_referer( 'rm_coach_status', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( 'Permission denied' );
    }

    $post_id = intval( $_POST['post_id'] ?? 0 );
    $status  = sanitize_text_field( $_POST['status'] ?? '' );

    if ( ! $post_id || ! in_array( $status, [ 'pending', 'validated', 'suspended' ], true ) ) {
        wp_send_json_error( 'Invalid data' );
    }

    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'coach' ) {
        wp_send_json_error( 'Post not found' );
    }

    // Update the taxonomy term (replaces all existing terms)
    wp_set_object_terms( $post_id, $status, 'coach-status' );

    // If verified: publish the post + email the coach
    if ( $status === 'validated' && $post->post_status === 'draft' ) {
        wp_update_post( [ 'ID' => $post_id, 'post_status' => 'publish' ] );

        $author = get_user_by( 'id', $post->post_author );
        if ( $author ) {
            wp_mail(
                $author->user_email,
                'Your Ridemaster Coach account is now active!',
                'Hi ' . $author->first_name . ",\n\nGreat news! Your coach account has been approved.\n\nYou can now log in and complete your profile:\nhttps://ridemaster.eu/coach-dashboard/\n\nSee you on Ridemaster!\nThe Ridemaster Team"
            );
        }
    }

    // If suspended: set post back to draft
    if ( $status === 'suspended' && $post->post_status === 'publish' ) {
        wp_update_post( [ 'ID' => $post_id, 'post_status' => 'draft' ] );
    }

    wp_send_json_success( 'Updated' );
});
