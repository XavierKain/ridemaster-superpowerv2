<?php
/**
 * RM_Camp — Camp (WooCommerce product) creation and JetEngine relation management.
 *
 * @package RideMaster
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RM_Camp {

    /**
     * Debug log helper — only writes when WP_DEBUG is on.
     */
    private static function log( $message ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( $message );
        }
    }

    /**
     * Boot the hooks.
     */
    public function __construct() {
        add_action( 'save_post_product', [ $this, 'init_new_camp' ], 10, 3 );
        add_action( 'save_post_product', [ $this, 'auto_link_coach_to_spot' ], 30, 3 );
    }

    /* ------------------------------------------------------------------
     * Helper: find a JetEngine relation by its human-readable label.
     * In JetEngine 3.x the name lives in $args['labels']['name'].
     * ------------------------------------------------------------------ */

    /**
     * Find a JetEngine relation object by its label name.
     *
     * @param  string $label  The relation label (e.g. "Coach to Camps").
     * @return object|null    The relation object, or null if not found.
     */
    public static function find_relation( $label ) {
        if ( ! function_exists( 'jet_engine' ) ) {
            return null;
        }

        $relations = jet_engine()->relations->get_active_relations();

        foreach ( $relations as $relation ) {
            $args = $relation->get_args();
            if ( isset( $args['labels']['name'] ) && $args['labels']['name'] === $label ) {
                return $relation;
            }
        }

        return null;
    }

    /* ------------------------------------------------------------------
     * A — init_new_camp
     * Fired on save_post_product (priority 10).
     * Only processes brand-new products created by the JetFormBuilder form.
     * ------------------------------------------------------------------ */

    /**
     * Initialise a newly-created camp product (JFB hook entrypoint).
     *
     * Maps $_REQUEST → normalized data array and delegates to apply_meta_from_data.
     * This wrapper preserves the exact behavior of the previous monolithic
     * implementation while enabling reuse from the importer plugin.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @param bool     $update  Whether this is an update (true) or new post (false).
     */
    public function init_new_camp( $post_id, $post, $update ) {

        // --- Re-entrance guard ---
        static $running = false;
        if ( $running ) {
            return;
        }

        // Only new products, not updates.
        if ( $update ) {
            return;
        }

        // Skip revisions and autosaves.
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }

        // Only process when the request originates from the JetFormBuilder form.
        if ( ! isset( $_REQUEST['camp_title'] ) ) {
            return;
        }

        $running = true;

        self::log( 'RideMaster: init_new_camp fired for post ' . $post_id );

        // Map $_REQUEST → normalized data array.
        $data = [
            'price'         => isset( $_REQUEST['camp_price'] )     ? sanitize_text_field( $_REQUEST['camp_price'] )     : '',
            'max_spots'     => isset( $_REQUEST['camp_max_spots'] ) ? intval( $_REQUEST['camp_max_spots'] )              : ( isset( $_REQUEST['camp_stock'] ) ? intval( $_REQUEST['camp_stock'] ) : 0 ),
            'start_date'    => isset( $_REQUEST['camp_start_date'] ) ? sanitize_text_field( $_REQUEST['camp_start_date'] ) : '',
            'end_date'      => isset( $_REQUEST['camp_end_date'] )   ? sanitize_text_field( $_REQUEST['camp_end_date'] )   : '',
            'coach_user_id' => get_current_user_id(),
            'spot_id'       => isset( $_REQUEST['camp_spot'] ) ? intval( $_REQUEST['camp_spot'] ) : 0,
            'check_stripe'  => true,  // JFB path always checks the current user's Stripe status.
        ];

        self::apply_meta_from_data( $post_id, $data );

        $running = false;
    }

    /**
     * Apply all camp meta + taxonomies + relations + Stripe-blocker logic from a normalized data array.
     *
     * Extracted from init_new_camp so the importer plugin can reuse it without
     * relying on $_REQUEST.
     *
     * @param int   $post_id Camp product ID (must already exist).
     * @param array $data    Normalized data:
     *                       - price (string|int)
     *                       - max_spots (int)
     *                       - start_date (string YYYY-MM-DD)
     *                       - end_date (string YYYY-MM-DD)
     *                       - coach_user_id (int) — WP user whose Stripe status to check (optional)
     *                       - coach_post_id (int) — explicit coach CPT ID. If 0/missing AND
     *                         coach_user_id is set, falls back to that user's `coach_post_id` usermeta. (optional)
     *                       - spot_id (int) — spot CPT ID to link. 0/missing = skip Spot→Camp link. (optional)
     *                       - check_stripe (bool) — whether to apply Stripe blocker (true for JFB)
     */
    public static function apply_meta_from_data( $post_id, array $data ) {

        // ---------------------------------------------------------------
        // A. Write _price meta.
        // ---------------------------------------------------------------
        if ( ! empty( $data['price'] ) ) {
            $price = $data['price'];
            update_post_meta( $post_id, '_price', $price );
            update_post_meta( $post_id, '_regular_price', $price );
            self::log( 'RideMaster: Set _price = ' . $price . ' for post ' . $post_id );
        }

        // ---------------------------------------------------------------
        // B. Set product type to "simple".
        // ---------------------------------------------------------------
        wp_set_object_terms( $post_id, 'simple', 'product_type' );
        self::log( 'RideMaster: Set product type to simple for post ' . $post_id );

        // ---------------------------------------------------------------
        // B2. Force stock values via shutdown hook.
        // ---------------------------------------------------------------
        $stock_qty = isset( $data['max_spots'] ) ? intval( $data['max_spots'] ) : 0;
        add_action( 'shutdown', function () use ( $post_id, $stock_qty ) {
            update_post_meta( $post_id, '_stock', $stock_qty );
            update_post_meta( $post_id, '_manage_stock', 'yes' );
            update_post_meta( $post_id, '_stock_status', $stock_qty > 0 ? 'instock' : 'outofstock' );
            wc_delete_product_transients( $post_id );
            self::log( 'RideMaster: Forced stock values on shutdown for post ' . $post_id . ' (stock=' . $stock_qty . ')' );
        } );

        // ---------------------------------------------------------------
        // C. Merge dates.
        // ---------------------------------------------------------------
        $start_date = isset( $data['start_date'] ) ? $data['start_date'] : '';
        $end_date   = isset( $data['end_date'] )   ? $data['end_date']   : '';

        if ( $start_date ) {
            update_post_meta( $post_id, 'camp_start_date', $start_date );
        }
        if ( $end_date ) {
            update_post_meta( $post_id, 'camp_end_date', $end_date );
        }

        if ( $start_date && $end_date ) {
            $start_ts = strtotime( $start_date );
            $end_ts   = strtotime( $end_date );

            update_post_meta( $post_id, 'full_date', $start_ts );
            update_post_meta( $post_id, 'full_date__end_date', $end_ts );

            $config = wp_json_encode( [
                'dates' => [
                    [ 'start' => $start_ts, 'end' => $end_ts ],
                ],
            ] );
            update_post_meta( $post_id, 'full_date__config', $config );

            self::log( 'RideMaster: Saved advanced date metas for post ' . $post_id );
        }

        // ---------------------------------------------------------------
        // D. Assign the "Camp" product category.
        // ---------------------------------------------------------------
        wp_set_object_terms( $post_id, 'camp', 'product_cat', true );

        // ---------------------------------------------------------------
        // E. Stripe flag (camp still publishes; payout blocked until coach
        //    connects Stripe — see payment gateway / payout flow).
        // ---------------------------------------------------------------
        if ( ! empty( $data['check_stripe'] ) ) {
            $user_to_check   = isset( $data['coach_user_id'] ) ? intval( $data['coach_user_id'] ) : 0;
            $stripe_complete = $user_to_check ? get_user_meta( $user_to_check, 'stripe_onboarding_complete', true ) : '';
            if ( $stripe_complete !== '1' ) {
                update_post_meta( $post_id, '_rm_blocked_reason', 'stripe_not_connected' );
                self::log( 'RideMaster: Camp ' . $post_id . ' published with stripe_not_connected flag — coach needs to connect Stripe to receive payouts.' );
            } else {
                delete_post_meta( $post_id, '_rm_blocked_reason' );
            }
        }

        // ---------------------------------------------------------------
        // F1. Create Coach → Camp relation.
        // ---------------------------------------------------------------
        $coach_post_id = isset( $data['coach_post_id'] ) ? intval( $data['coach_post_id'] ) : 0;
        if ( ! $coach_post_id && ! empty( $data['coach_user_id'] ) ) {
            $coach_post_id = (int) get_user_meta( $data['coach_user_id'], 'coach_post_id', true );
        }

        if ( $coach_post_id ) {
            $coach_to_camps = self::find_relation( 'Coach to Camps' );
            if ( $coach_to_camps ) {
                $coach_to_camps->update( $coach_post_id, $post_id );
                self::log( 'RideMaster: Linked Coach ' . $coach_post_id . ' → Camp ' . $post_id );
            } else {
                self::log( 'RideMaster: "Coach to Camps" relation not found.' );
            }
            update_post_meta( $post_id, '_coach_post_id', $coach_post_id );
        }

        // ---------------------------------------------------------------
        // F2. Create Spot → Camp relation.
        // ---------------------------------------------------------------
        $camp_spot = isset( $data['spot_id'] ) ? intval( $data['spot_id'] ) : 0;
        if ( $camp_spot ) {
            $spot_to_camps = self::find_relation( 'Spot to Camps' );
            if ( $spot_to_camps ) {
                $spot_to_camps->update( $camp_spot, $post_id );
                self::log( 'RideMaster: Linked Spot ' . $camp_spot . ' → Camp ' . $post_id );
            }
        }

        // ---------------------------------------------------------------
        // H. Clear WC transients.
        // ---------------------------------------------------------------
        wc_delete_product_transients( $post_id );
    }

    /**
     * Create a new camp product from a structured payload (used by the importer).
     *
     * @param array $payload {
     *     @type string $title             Post title.
     *     @type string $description_html  Post content (HTML allowed via wp_kses_post).
     *     @type string $price             Numeric string.
     *     @type int    $max_spots         Capacity.
     *     @type string $start_date        YYYY-MM-DD.
     *     @type string $end_date          YYYY-MM-DD.
     *     @type string $schedule          Optional textual schedule.
     *     @type array  $included          Array of strings.
     *     @type array  $not_included      Array of strings.
     *     @type string $sport             Sport term slug.
     *     @type array  $level             Array of level term slugs.
     *     @type array  $languages         Array of language term slugs.
     *     @type string $camp_status       Camp-status term slug (e.g. 'open').
     *     @type int    $coach_post_id     Existing coach CPT ID to link.
     *     @type int    $spot_id           Existing spot CPT ID to link.
     *     @type int    $hotel_id          Existing hotel CPT ID to link (optional).
     *     @type string $import_source_url URL for idempotency tracking.
     *     @type bool   $check_stripe      Whether to apply Stripe blocker (default true).
     * }
     * @return int|WP_Error Camp product ID, or WP_Error on failure.
     */
    public static function create_from_payload( array $payload ) {

        $post_id = wp_insert_post( [
            'post_type'    => 'product',
            'post_title'   => sanitize_text_field( $payload['title'] ?? '' ),
            'post_content' => isset( $payload['description_html'] ) ? wp_kses_post( $payload['description_html'] ) : '',
            'post_status'  => 'publish',
        ], true );

        if ( is_wp_error( $post_id ) || ! $post_id ) {
            return is_wp_error( $post_id ) ? $post_id : new WP_Error( 'wp_insert_post_failed', 'wp_insert_post returned 0' );
        }

        // Convert payload format to internal data shape used by apply_meta_from_data.
        $data = [
            'price'         => $payload['price'] ?? '',
            'max_spots'     => $payload['max_spots'] ?? 0,
            'start_date'    => $payload['start_date'] ?? '',
            'end_date'      => $payload['end_date'] ?? '',
            'coach_post_id' => $payload['coach_post_id'] ?? 0,
            'coach_user_id' => $payload['coach_user_id'] ?? 0,
            'spot_id'       => $payload['spot_id'] ?? 0,
            'check_stripe'  => $payload['check_stripe'] ?? true,
        ];

        self::apply_meta_from_data( $post_id, $data );

        // Apply remaining metadata that is NOT handled by apply_meta_from_data.

        // Schedule.
        if ( ! empty( $payload['schedule'] ) ) {
            update_post_meta( $post_id, 'camp_schedule', wp_kses_post( $payload['schedule'] ) );
        }

        // Repeater-format included.
        if ( ! empty( $payload['included'] ) && is_array( $payload['included'] ) ) {
            $rows = array_map( function ( $v ) {
                return [ 'included_in_the_camp' => sanitize_text_field( $v ) ];
            }, $payload['included'] );
            update_post_meta( $post_id, 'camp_included', $rows );
        }
        if ( ! empty( $payload['not_included'] ) && is_array( $payload['not_included'] ) ) {
            $rows = array_map( function ( $v ) {
                return [ 'not_included_in_the_camp' => sanitize_text_field( $v ) ];
            }, $payload['not_included'] );
            update_post_meta( $post_id, 'camp_not_included', $rows );
        }

        // Taxonomies (slugs).
        if ( ! empty( $payload['sport'] ) ) {
            wp_set_object_terms( $post_id, [ sanitize_title( $payload['sport'] ) ], 'sport' );
        }
        if ( ! empty( $payload['level'] ) ) {
            $slugs = array_map( 'sanitize_title', (array) $payload['level'] );
            wp_set_object_terms( $post_id, $slugs, 'level' );
        }
        if ( ! empty( $payload['languages'] ) ) {
            $slugs = array_map( 'sanitize_title', (array) $payload['languages'] );
            wp_set_object_terms( $post_id, $slugs, 'language' );
        }
        if ( ! empty( $payload['camp_status'] ) ) {
            wp_set_object_terms( $post_id, [ sanitize_title( $payload['camp_status'] ) ], 'camp-status' );
        }

        // Hotel linkage (simple meta, no JE relation for hotel).
        if ( ! empty( $payload['hotel_id'] ) ) {
            update_post_meta( $post_id, '_hotel_id', intval( $payload['hotel_id'] ) );
        }

        // Idempotency tracking.
        if ( ! empty( $payload['import_source_url'] ) ) {
            update_post_meta( $post_id, '_import_source_url', esc_url_raw( $payload['import_source_url'] ) );
            update_post_meta( $post_id, '_import_imported_at', time() );
        }

        return $post_id;
    }

    /* ------------------------------------------------------------------
     * auto_link_coach_to_spot
     * Fired on save_post_product (priority 30).
     * Ensures a Coach↔Spot link exists when they share a Camp, and
     * cleans up orphan Coach↔Spot links that no longer share any Camp.
     *
     * Relation IDs (jet_rel_default table):
     *   20 = Coach to Camps
     *   18 = Spot to Camps
     *   19 = Coach to Spots
     * ------------------------------------------------------------------ */

    /**
     * Automatically link a Coach to a Spot when they share a Camp.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     * @param bool     $update  Whether this is an update.
     */
    public function auto_link_coach_to_spot( $post_id, $post, $update ) {

        // Skip autosaves, revisions, and auto-drafts.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( 'auto-draft' === get_post_status( $post_id ) ) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'jet_rel_default';

        self::log( 'RideMaster: auto_link_coach_to_spot fired for post ' . $post_id );

        // 1. Get Coach linked to this Camp (rel_id 20, coach is parent).
        $coach_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT parent_object_id FROM {$table} WHERE rel_id = %d AND child_object_id = %d LIMIT 1",
                20,
                $post_id
            )
        );

        // 2. Get Spot linked to this Camp (rel_id 18, spot is parent).
        $spot_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT parent_object_id FROM {$table} WHERE rel_id = %d AND child_object_id = %d LIMIT 1",
                18,
                $post_id
            )
        );

        self::log( 'RideMaster: Camp ' . $post_id . ' — Coach=' . ( $coach_id ?: 'none' ) . ', Spot=' . ( $spot_id ?: 'none' ) );

        // 3. If both exist, ensure a Coach → Spot link (rel_id 19) is present.
        if ( $coach_id && $spot_id ) {
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE rel_id = %d AND parent_object_id = %d AND child_object_id = %d",
                    19,
                    $coach_id,
                    $spot_id
                )
            );

            if ( ! $existing ) {
                $wpdb->insert(
                    $table,
                    [
                        'rel_id'           => 19,
                        'parent_object_id' => $coach_id,
                        'child_object_id'  => $spot_id,
                    ],
                    [ '%d', '%d', '%d' ]
                );
                self::log( 'RideMaster: Created Coach ' . $coach_id . ' → Spot ' . $spot_id . ' link (rel_id 19).' );
            } else {
                self::log( 'RideMaster: Coach → Spot link already exists.' );
            }
        }

        // 4. Cleanup orphan Coach ↔ Spot links.
        $this->cleanup_all_orphan_coach_spot_links();
    }

    /* ------------------------------------------------------------------
     * Cleanup helper
     * ------------------------------------------------------------------ */

    /**
     * Remove Coach↔Spot links (rel_id 19) that no longer share a common Camp.
     *
     * For every Coach→Spot row we check whether the coach's camps (rel 20)
     * and the spot's camps (rel 18) still intersect. If not, the link is
     * deleted.
     */
    private function cleanup_all_orphan_coach_spot_links() {
        global $wpdb;
        $table = $wpdb->prefix . 'jet_rel_default';

        // Get all Coach → Spot links.
        $links = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, parent_object_id AS coach_id, child_object_id AS spot_id FROM {$table} WHERE rel_id = %d",
                19
            )
        );

        if ( empty( $links ) ) {
            return;
        }

        foreach ( $links as $link ) {

            // Camps linked to this Coach (rel_id 20, coach is parent).
            $coach_camps = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT child_object_id FROM {$table} WHERE rel_id = %d AND parent_object_id = %d",
                    20,
                    $link->coach_id
                )
            );

            // Camps linked to this Spot (rel_id 18, spot is parent).
            $spot_camps = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT child_object_id FROM {$table} WHERE rel_id = %d AND parent_object_id = %d",
                    18,
                    $link->spot_id
                )
            );

            // If there is no common camp, remove the Coach → Spot link.
            $common = array_intersect( $coach_camps, $spot_camps );

            if ( empty( $common ) ) {
                $wpdb->delete(
                    $table,
                    [ 'id' => $link->id ],
                    [ '%d' ]
                );
                self::log( 'RideMaster: Removed orphan Coach ' . $link->coach_id . ' → Spot ' . $link->spot_id . ' link (no common camp).' );
            }
        }
    }
}
