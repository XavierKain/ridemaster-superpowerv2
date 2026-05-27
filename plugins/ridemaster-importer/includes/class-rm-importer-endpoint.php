<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RM_Importer_Endpoint {

    const NAMESPACE = 'ridemaster/v1';

    public static function register_routes() {
        register_rest_route( self::NAMESPACE, '/import-camp', [
            'methods'             => 'POST',
            'callback'            => [ __CLASS__, 'handle_import' ],
            'permission_callback' => [ __CLASS__, 'check_permission' ],
        ] );
    }

    public static function check_permission( $request ) {
        if ( ! current_user_can( 'edit_others_posts' ) ) {
            return new WP_Error(
                'INSUFFICIENT_PERMISSIONS',
                'You need edit_others_posts capability.',
                [ 'status' => 403 ]
            );
        }
        return true;
    }

    public static function handle_import( WP_REST_Request $request ) {

        // Lightweight ping for plumbing tests.
        $payload = $request->get_json_params();
        if ( $payload === null || $payload === [] ) {
            return new WP_REST_Response( [
                'status'  => 'pong',
                'version' => RM_IMPORTER_VERSION,
            ], 200 );
        }

        if ( ! is_array( $payload ) ) {
            return new WP_Error( 'INVALID_PAYLOAD', 'Body must be a JSON object', [ 'status' => 400 ] );
        }

        // Schema validation.
        $valid = RM_Importer_Validator::validate( $payload );
        if ( is_wp_error( $valid ) ) {
            return $valid;
        }

        // Idempotency check.
        $existing = self::find_existing_by_source_url( $payload['import_source_url'] );
        if ( $existing && empty( $payload['force_overwrite'] ) ) {
            return new WP_Error(
                'DUPLICATE_IMPORT',
                'A camp from this URL already exists',
                [
                    'status'           => 409,
                    'existing_camp_id' => $existing,
                    'existing_edit_url'=> admin_url( "post.php?post={$existing}&action=edit" ),
                ]
            );
        }

        // Build camp payload for RM_Camp::create_from_payload.
        $camp = $payload['camp'];
        $camp_payload = [
            'title'             => $camp['title'],
            'description_html'  => $camp['description_html'] ?? '',
            'price'             => (string) $camp['price_eur'],
            'max_spots'         => (int) $camp['max_spots'],
            'start_date'        => $camp['start_date'],
            'end_date'          => $camp['end_date'],
            'schedule'          => $camp['schedule'] ?? '',
            'included'          => $camp['included'] ?? [],
            'not_included'      => $camp['not_included'] ?? [],
            'sport'             => $camp['sport'] ?? '',
            'level'             => $camp['level'] ?? [],
            'languages'         => $camp['languages'] ?? [],
            'camp_status'       => $camp['camp_status'] ?? 'open',
            'coach_post_id'     => $payload['coach']['existing_post_id'] ?? 0,
            'spot_id'           => $payload['spot']['existing_post_id'] ?? 0,
            'hotel_id'          => $payload['hotel']['existing_post_id'] ?? 0,
            'import_source_url' => $payload['import_source_url'],
            'check_stripe'      => false,  // imports skip Stripe check for example data
        ];

        $camp_id = RM_Camp::create_from_payload( $camp_payload );
        if ( is_wp_error( $camp_id ) ) {
            return new WP_Error( 'IMPORT_FAILED', 'Camp creation failed: ' . $camp_id->get_error_message(), [ 'status' => 500 ] );
        }

        // Flush the shutdown-deferred stock writes immediately so the response
        // reflects final state. Use a focused approach (NOT do_action('shutdown'))
        // to avoid triggering unrelated shutdown handlers (cron spawn, etc.).
        self::flush_camp_stock_meta( $camp_id, (int) $camp['max_spots'] );

        return new WP_REST_Response( [
            'status'    => 'success',
            'camp_id'   => $camp_id,
            'edit_url'  => admin_url( "post.php?post={$camp_id}&action=edit" ),
            'public_url'=> get_permalink( $camp_id ),
            'created'   => [
                'camp' => [ 'id' => $camp_id, 'images_imported' => 0, 'images_failed' => 0 ],
            ],
            'warnings'  => [],
        ], 200 );
    }

    /**
     * Find an existing camp by its _import_source_url meta.
     *
     * @return int|null Camp post ID, or null if none.
     */
    private static function find_existing_by_source_url( string $url ) {
        $q = new WP_Query( [
            'post_type'      => 'product',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'no_found_rows'  => true,  // existence check only — skip SQL_CALC_FOUND_ROWS
            'meta_query'     => [
                [ 'key' => '_import_source_url', 'value' => esc_url_raw( $url ) ],
            ],
        ] );
        return $q->posts ? (int) $q->posts[0] : null;
    }

    /**
     * Immediately apply the stock meta that apply_meta_from_data defers to
     * the `shutdown` hook. We call this synchronously after camp creation in
     * the import flow so the API response and any subsequent code (image
     * handling, idempotency lookups) see the final stock state without firing
     * unrelated shutdown handlers.
     */
    private static function flush_camp_stock_meta( int $camp_id, int $stock_qty ): void {
        update_post_meta( $camp_id, '_stock', $stock_qty );
        update_post_meta( $camp_id, '_manage_stock', 'yes' );
        update_post_meta( $camp_id, '_stock_status', $stock_qty > 0 ? 'instock' : 'outofstock' );
        if ( function_exists( 'wc_delete_product_transients' ) ) {
            wc_delete_product_transients( $camp_id );
        }
    }
}
