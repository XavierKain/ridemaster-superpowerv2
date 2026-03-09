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
            self::log( $message );
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
     * Initialise a newly-created camp product.
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

        // ---------------------------------------------------------------
        // A. Write _price meta from form data.
        // ---------------------------------------------------------------
        if ( ! empty( $_REQUEST['camp_price'] ) ) {
            $price = sanitize_text_field( $_REQUEST['camp_price'] );
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
        //     JFB WooCommerce module runs after save_post and may
        //     overwrite stock data, so we set it last.
        // ---------------------------------------------------------------
        $stock_qty = isset( $_REQUEST['camp_stock'] ) ? intval( $_REQUEST['camp_stock'] ) : 0;
        add_action( 'shutdown', function () use ( $post_id, $stock_qty ) {
            update_post_meta( $post_id, '_stock', $stock_qty );
            update_post_meta( $post_id, '_manage_stock', 'yes' );
            update_post_meta( $post_id, '_stock_status', 'instock' );
            wc_delete_product_transients( $post_id );
            self::log( 'RideMaster: Forced stock values on shutdown for post ' . $post_id . ' (stock=' . $stock_qty . ')' );
        } );

        // ---------------------------------------------------------------
        // C. Merge dates: individual metas + JetEngine advanced-date format.
        // ---------------------------------------------------------------
        $start_date = isset( $_REQUEST['camp_start_date'] ) ? sanitize_text_field( $_REQUEST['camp_start_date'] ) : '';
        $end_date   = isset( $_REQUEST['camp_end_date'] )   ? sanitize_text_field( $_REQUEST['camp_end_date'] )   : '';

        if ( $start_date ) {
            update_post_meta( $post_id, 'camp_start_date', $start_date );
            self::log( 'RideMaster: camp_start_date = ' . $start_date );
        }
        if ( $end_date ) {
            update_post_meta( $post_id, 'camp_end_date', $end_date );
            self::log( 'RideMaster: camp_end_date = ' . $end_date );
        }

        if ( $start_date && $end_date ) {
            $start_ts = strtotime( $start_date );
            $end_ts   = strtotime( $end_date );

            update_post_meta( $post_id, 'full_date', $start_ts );
            update_post_meta( $post_id, 'full_date__end_date', $end_ts );

            $config = wp_json_encode( [
                'dates' => [
                    [
                        'start' => $start_ts,
                        'end'   => $end_ts,
                    ],
                ],
            ] );
            update_post_meta( $post_id, 'full_date__config', $config );

            self::log( 'RideMaster: Saved advanced date metas for post ' . $post_id );
        }

        // ---------------------------------------------------------------
        // D. Assign the "Camp" product category (slug: camp).
        // ---------------------------------------------------------------
        wp_set_object_terms( $post_id, 'camp', 'product_cat', true );
        self::log( 'RideMaster: Assigned product category "camp" to post ' . $post_id );

        // ---------------------------------------------------------------
        // E. Create Coach → Camp relation.
        // ---------------------------------------------------------------
        $current_user_id = get_current_user_id();
        $coach_post_id   = get_user_meta( $current_user_id, 'coach_post_id', true );

        if ( $coach_post_id ) {
            $coach_to_camps = self::find_relation( 'Coach to Camps' );
            if ( $coach_to_camps ) {
                $coach_to_camps->update( $coach_post_id, $post_id );
                self::log( 'RideMaster: Linked Coach ' . $coach_post_id . ' → Camp ' . $post_id );
            } else {
                self::log( 'RideMaster: "Coach to Camps" relation not found.' );
            }

            // Also store a direct meta reference on the product.
            update_post_meta( $post_id, '_coach_post_id', $coach_post_id );
        } else {
            self::log( 'RideMaster: No coach_post_id found for user ' . $current_user_id );
        }

        // ---------------------------------------------------------------
        // F. Create Spot → Camp relation.
        // ---------------------------------------------------------------
        $camp_spot = isset( $_REQUEST['camp_spot'] ) ? intval( $_REQUEST['camp_spot'] ) : 0;

        if ( $camp_spot ) {
            $spot_to_camps = self::find_relation( 'Spot to Camps' );
            if ( $spot_to_camps ) {
                $spot_to_camps->update( $camp_spot, $post_id );
                self::log( 'RideMaster: Linked Spot ' . $camp_spot . ' → Camp ' . $post_id );
            } else {
                self::log( 'RideMaster: "Spot to Camps" relation not found.' );
            }
        }

        // ---------------------------------------------------------------
        // G. Draft/publish control based on coach status (disabled).
        // ---------------------------------------------------------------
        // $coach_status = get_post_meta( $coach_post_id, 'coach_status', true );
        // if ( $coach_status !== 'approved' ) {
        //     wp_update_post( [
        //         'ID'          => $post_id,
        //         'post_status' => 'draft',
        //     ] );
        //     self::log( 'RideMaster: Coach not approved — camp set to draft.' );
        // }

        // ---------------------------------------------------------------
        // H. Clear WooCommerce transients.
        // ---------------------------------------------------------------
        wc_delete_product_transients( $post_id );
        self::log( 'RideMaster: Cleared WooCommerce transients for post ' . $post_id );

        $running = false;
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
