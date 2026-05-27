<?php
/**
 * TEMPORARY DEBUG CLASS — REMOVE BEFORE SHIPPING TO PRODUCTION.
 *
 * Exposes raw postmeta and JetEngine relation rows via REST for parity
 * tests during the RM_Camp / RM_Coach refactor (Tasks 3 and 6 of the
 * camp-import-tool plan).
 *
 * Scheduled for removal in Task 16 of:
 * docs/superpowers/plans/2026-05-27-camp-import-tool.md
 *
 * If you are reading this and the import tool has shipped, this file
 * should not exist. Delete it and remove the two require/add_action
 * lines from ridemaster-importer.php.
 *
 * @internal
 * @temporary
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RM_Importer_Debug {

    public static function register_routes() {
        register_rest_route( RM_Importer_Endpoint::NAMESPACE, '/__debug_dump_meta/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'dump_meta' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'id' => [ 'required' => true, 'type' => 'integer' ],
            ],
        ] );

        register_rest_route( RM_Importer_Endpoint::NAMESPACE, '/__debug_dump_relations/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ __CLASS__, 'dump_relations' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
            'args' => [
                'id' => [ 'required' => true, 'type' => 'integer' ],
            ],
        ] );

        register_rest_route( RM_Importer_Endpoint::NAMESPACE, '/__test_camp_parity', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'test_camp_parity' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );

        register_rest_route( RM_Importer_Endpoint::NAMESPACE, '/__test_coach_create', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'test_coach_create' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );

        register_rest_route( RM_Importer_Endpoint::NAMESPACE, '/__test_spot_create', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'test_spot_create' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );

        register_rest_route( RM_Importer_Endpoint::NAMESPACE, '/__test_hotel_create', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'test_hotel_create' ],
            'permission_callback' => function () {
                return current_user_can( 'manage_options' );
            },
        ] );
    }

    public static function dump_meta( WP_REST_Request $req ) {
        $id = (int) $req['id'];
        $post = get_post( $id );
        if ( ! $post ) {
            return new WP_Error( 'NOT_FOUND', "Post $id not found", [ 'status' => 404 ] );
        }

        $meta = get_post_meta( $id );
        // Flatten single-value arrays for easier diffing.
        $flat = [];
        foreach ( $meta as $k => $v ) {
            $flat[ $k ] = ( is_array( $v ) && count( $v ) === 1 ) ? $v[0] : $v;
        }

        // Get taxonomies + their terms (slugs only for stable diffing).
        $taxes = get_object_taxonomies( $post->post_type );
        $tax_data = [];
        foreach ( $taxes as $tax ) {
            $terms = wp_get_object_terms( $id, $tax, [ 'fields' => 'slugs' ] );
            if ( ! is_wp_error( $terms ) ) {
                sort( $terms );
                $tax_data[ $tax ] = $terms;
            }
        }

        return new WP_REST_Response( [
            'post_id'      => $id,
            'post_type'    => $post->post_type,
            'post_status'  => $post->post_status,
            'post_title'   => $post->post_title,
            'post_author'  => (int) $post->post_author,
            'meta'         => $flat,
            'taxonomies'   => $tax_data,
        ], 200 );
    }

    public static function dump_relations( WP_REST_Request $req ) {
        global $wpdb;
        $id = (int) $req['id'];
        $table = $wpdb->prefix . 'jet_rel_default';

        // Guard: JetEngine may be absent (e.g. on a staging env without it).
        $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
        if ( ! $exists ) {
            return new WP_Error( 'TABLE_NOT_FOUND', "Table {$table} does not exist (is JetEngine active?)", [ 'status' => 500 ] );
        }

        $as_parent = $wpdb->get_results(
            $wpdb->prepare( "SELECT rel_id, parent_object_id, child_object_id FROM {$table} WHERE parent_object_id = %d ORDER BY rel_id, child_object_id", $id ),
            ARRAY_A
        );
        $as_child = $wpdb->get_results(
            $wpdb->prepare( "SELECT rel_id, parent_object_id, child_object_id FROM {$table} WHERE child_object_id = %d ORDER BY rel_id, parent_object_id", $id ),
            ARRAY_A
        );

        return new WP_REST_Response( [
            'post_id'   => $id,
            'as_parent' => $as_parent,
            'as_child'  => $as_child,
        ], 200 );
    }

    /**
     * Parity test: create two camps via two code paths and return their meta side-by-side.
     *
     * Path A: simulate the JFB hook entrypoint by setting $_REQUEST and inserting a product.
     * Path B: call RM_Camp::create_from_payload() directly with equivalent data.
     *
     * Both camps share the same input values (price, max_spots, dates, spot).
     * The caller diffs the two meta dumps to confirm byte-identical behavior.
     *
     * Returns { jfb_id, payload_id, jfb_meta, payload_meta } as JSON.
     * The caller is responsible for deleting both camps after inspection.
     */
    public static function test_camp_parity( WP_REST_Request $req ) {
        if ( ! class_exists( 'RM_Camp' ) ) {
            return new WP_Error( 'RM_CAMP_MISSING', 'RM_Camp class not loaded', [ 'status' => 500 ] );
        }

        // Save current $_REQUEST so we can restore it afterwards — the parity
        // path overwrites several keys to simulate the JFB form payload, which
        // would otherwise pollute the rest of the request lifecycle.
        $saved_request = $_REQUEST;

        try {
            // ---- Path A: JFB-style ----
            $_REQUEST['camp_title']       = '[TEST-JFB] Parity Camp';
            $_REQUEST['camp_price']       = '777';
            $_REQUEST['camp_max_spots']   = 5;
            $_REQUEST['camp_start_date']  = '2026-08-01';
            $_REQUEST['camp_end_date']    = '2026-08-08';
            $_REQUEST['camp_spot']        = 195;  // Tarifa

            $jfb_id = wp_insert_post( [
                'post_type'    => 'product',
                'post_title'   => '[TEST-JFB] Parity Camp',
                'post_status'  => 'publish',
            ] );

            // ---- Path B: create_from_payload ----
            $payload_id = RM_Camp::create_from_payload( [
                'title'         => '[TEST-PAYLOAD] Parity Camp',
                'price'         => '777',
                'max_spots'     => 5,
                'start_date'    => '2026-08-01',
                'end_date'      => '2026-08-08',
                'spot_id'       => 195,
                'coach_post_id' => 0,
                'check_stripe'  => false,
            ] );

            // Force shutdown actions (stock meta forcing) to run now.
            do_action( 'shutdown' );
        } finally {
            $_REQUEST = $saved_request;
        }

        // Collect meta for both.
        $dump = function ( $id ) {
            $meta = get_post_meta( $id );
            $flat = [];
            foreach ( $meta as $k => $v ) {
                $flat[ $k ] = ( is_array( $v ) && count( $v ) === 1 ) ? $v[0] : $v;
            }
            return $flat;
        };

        $response = [
            'jfb_id'       => $jfb_id,
            'payload_id'   => is_wp_error( $payload_id ) ? null : $payload_id,
            'payload_err'  => is_wp_error( $payload_id ) ? $payload_id->get_error_message() : null,
            'jfb_meta'     => $jfb_id     ? $dump( $jfb_id )     : [],
            'payload_meta' => $payload_id && ! is_wp_error( $payload_id ) ? $dump( $payload_id ) : [],
        ];

        // Cleanup.
        if ( $jfb_id ) {
            wp_delete_post( $jfb_id, true );
        }
        if ( $payload_id && ! is_wp_error( $payload_id ) ) {
            wp_delete_post( $payload_id, true );
        }

        return new WP_REST_Response( $response, 200 );
    }

    /**
     * Test route: exercise RM_Coach::create_from_payload with a JSON payload
     * and return the result. Used by tests/importer/05-coach-create.sh.
     *
     * Temporary — removed in Task 16 along with the rest of the debug class.
     */
    public static function test_coach_create( WP_REST_Request $req ) {
        if ( ! class_exists( 'RM_Coach' ) ) {
            return new WP_Error( 'RM_COACH_MISSING', 'RM_Coach class not loaded', [ 'status' => 500 ] );
        }
        $result = RM_Coach::create_from_payload( $req->get_json_params() );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return new WP_REST_Response( $result, 200 );
    }

    /**
     * Test route: exercise RM_Spot::create_from_payload with a JSON payload
     * and return the result. Used by tests/importer/07-spot-create.sh.
     *
     * Temporary — removed in Task 16 along with the rest of the debug class.
     */
    public static function test_spot_create( WP_REST_Request $req ) {
        if ( ! class_exists( 'RM_Spot' ) ) {
            return new WP_Error( 'RM_SPOT_MISSING', 'RM_Spot class not loaded', [ 'status' => 500 ] );
        }
        $result = RM_Spot::create_from_payload( $req->get_json_params() );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return new WP_REST_Response( $result, 200 );
    }

    /**
     * Test route: exercise RM_Hotel::create_from_payload with a JSON payload
     * and return the result. Used by tests/importer/09-camp-with-hotel.sh.
     *
     * Temporary — removed in Task 16.
     */
    public static function test_hotel_create( WP_REST_Request $req ) {
        if ( ! class_exists( 'RM_Hotel' ) ) {
            return new WP_Error( 'RM_HOTEL_MISSING', 'RM_Hotel class not loaded', [ 'status' => 500 ] );
        }
        $result = RM_Hotel::create_from_payload( $req->get_json_params() );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return new WP_REST_Response( $result, 200 );
    }
}
