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

        // force_overwrite=true → delete the existing camp and proceed as a fresh import.
        // We do NOT touch the linked coach/spot/hotel — they remain. Attachments owned
        // by the old camp are also deleted (wp_delete_post true cleans up _thumbnail_id
        // attachments and gallery attachments via WP's media cascade).
        if ( $existing && ! empty( $payload['force_overwrite'] ) ) {
            wp_delete_post( $existing, true );
        }

        $warnings = [];

        $rollback = new RM_Importer_Rollback();

        // ----- Resolve coach -----
        $coach_post_id = 0;
        $coach_result  = null;

        if ( ! empty( $payload['coach']['existing_post_id'] ) ) {
            $coach_post_id = (int) $payload['coach']['existing_post_id'];
        } elseif ( ! empty( $payload['coach']['data'] ) || ! empty( $payload['coach']['match_by'] ) ) {
            $coach_result = RM_Coach::create_from_payload( $payload['coach'] );
            if ( is_wp_error( $coach_result ) ) {
                return new WP_Error(
                    'IMPORT_FAILED',
                    'Coach resolution failed: ' . $coach_result->get_error_message(),
                    [ 'status' => 500, 'step' => 'coach' ]
                );
            }
            $coach_post_id = (int) $coach_result['post_id'];
            if ( ! empty( $coach_result['was_new_post'] ) ) {
                $rollback->track_coach_post( (int) $coach_result['post_id'] );
            }
            if ( ! empty( $coach_result['was_new_user'] ) ) {
                $rollback->track_user( (int) $coach_result['user_id'] );
            }
        }

        // ----- Resolve spot -----
        $spot_id     = 0;
        $spot_result = null;

        if ( ! empty( $payload['spot']['existing_post_id'] ) ) {
            $spot_id = (int) $payload['spot']['existing_post_id'];
        } elseif ( ! empty( $payload['spot']['data'] ) || ! empty( $payload['spot']['match_by'] ) ) {
            $spot_result = RM_Spot::create_from_payload( $payload['spot'] );
            if ( is_wp_error( $spot_result ) ) {
                return new WP_Error(
                    'IMPORT_FAILED',
                    'Spot resolution failed: ' . $spot_result->get_error_message(),
                    [ 'status' => 500, 'step' => 'spot' ]
                );
            }
            $spot_id = (int) $spot_result['post_id'];
            if ( ! empty( $spot_result['was_new'] ) ) {
                $rollback->track_spot( (int) $spot_result['post_id'] );
            }
        }

        // ----- Resolve hotel -----
        $hotel_id     = 0;
        $hotel_result = null;

        if ( ! empty( $payload['hotel']['existing_post_id'] ) ) {
            $hotel_id = (int) $payload['hotel']['existing_post_id'];
        } elseif ( ! empty( $payload['hotel']['data'] ) || ! empty( $payload['hotel']['match_by'] ) ) {
            $hotel_result = RM_Hotel::create_from_payload( $payload['hotel'] );
            if ( is_wp_error( $hotel_result ) ) {
                return new WP_Error(
                    'IMPORT_FAILED',
                    'Hotel resolution failed: ' . $hotel_result->get_error_message(),
                    [ 'status' => 500, 'step' => 'hotel' ]
                );
            }
            $hotel_id = (int) $hotel_result['post_id'];
            if ( ! empty( $hotel_result['was_new'] ) ) {
                $rollback->track_hotel( (int) $hotel_result['post_id'] );
            }
        }

        // ----- Build camp payload -----
        // $resolved collects the IDs that subsequent tasks (spot, hotel)
        // will populate via the same inline-or-existing dispatch pattern.
        $camp = $payload['camp'];
        $resolved = [
            'coach_post_id' => $coach_post_id,
            'spot_id'       => $spot_id,
            'hotel_id'      => $hotel_id,
        ];
        $camp_payload = self::build_camp_payload( $camp, $payload, $resolved );

        // ----- Create the camp -----
        $camp_id = RM_Camp::create_from_payload( $camp_payload );
        if ( is_wp_error( $camp_id ) ) {
            $rolled = $rollback->rollback();
            return new WP_Error(
                'IMPORT_FAILED',
                'Camp creation failed: ' . $camp_id->get_error_message(),
                [ 'status' => 500, 'step' => 'camp', 'rolled_back' => $rolled ]
            );
        }

        // Camp itself is tracked AFTER the success check so it can be rolled
        // back if a later step (image processing, Yoast) fatal-errors. Currently
        // images and Yoast only emit warnings, not hard failures.
        $rollback->track_camp( (int) $camp_id );

        // Flush deferred stock writes synchronously (without triggering unrelated shutdown handlers).
        self::flush_camp_stock_meta( $camp_id, (int) $camp['max_spots'] );

        // ----- Images -----
        $images_imported = 0;
        $images_failed   = 0;

        $featured = $camp['featured_image'] ?? null;
        $gallery  = $camp['gallery']        ?? [];

        if ( $featured ) {
            $result = RM_Importer_Images::import( $featured, $camp_id );
            if ( $result['attachment_id'] ) {
                set_post_thumbnail( $camp_id, $result['attachment_id'] );
                $images_imported++;
                $rollback->track_attachment( (int) $result['attachment_id'] );
            } else {
                $images_failed++;
                $warnings[] = "Featured image: {$result['error']}";
            }
        }

        $gallery_ids = [];
        foreach ( $gallery as $item ) {
            $result = RM_Importer_Images::import( $item, $camp_id );
            if ( $result['attachment_id'] ) {
                $gallery_ids[] = $result['attachment_id'];
                $images_imported++;
                $rollback->track_attachment( (int) $result['attachment_id'] );
            } else {
                $images_failed++;
                $warnings[] = "Gallery image: {$result['error']}";
            }
        }

        if ( ! empty( $gallery_ids ) ) {
            update_post_meta( $camp_id, '_product_image_gallery', implode( ',', $gallery_ids ) );
        }

        // ----- Yoast SEO -----
        if ( ! empty( $camp['yoast']['focus_keyword'] ) ) {
            update_post_meta( $camp_id, '_yoast_wpseo_focuskw', sanitize_text_field( $camp['yoast']['focus_keyword'] ) );
        }
        if ( ! empty( $camp['yoast']['meta_description'] ) ) {
            update_post_meta( $camp_id, '_yoast_wpseo_metadesc', sanitize_text_field( $camp['yoast']['meta_description'] ) );
        }

        return new WP_REST_Response( [
            'status'    => 'success',
            'camp_id'   => $camp_id,
            'edit_url'  => admin_url( "post.php?post={$camp_id}&action=edit" ),
            'public_url'=> get_permalink( $camp_id ),
            'created'   => [
                'coach' => $coach_result
                    ? [
                        'id'           => (int) $coach_result['user_id'],
                        'post_id'      => (int) $coach_result['post_id'],
                        'was_new'      => (bool) $coach_result['was_new'],
                        'was_new_user' => (bool) $coach_result['was_new_user'],
                        'was_new_post' => (bool) $coach_result['was_new_post'],
                    ]
                    : ( $coach_post_id ? [ 'post_id' => $coach_post_id, 'was_new' => false ] : null ),
                'spot'  => $spot_result
                    ? [ 'id' => (int) $spot_result['post_id'], 'was_new' => (bool) $spot_result['was_new'] ]
                    : ( $spot_id ? [ 'id' => $spot_id, 'was_new' => false ] : null ),
                'hotel' => $hotel_result
                    ? [ 'id' => (int) $hotel_result['post_id'], 'was_new' => (bool) $hotel_result['was_new'] ]
                    : ( $hotel_id ? [ 'id' => $hotel_id, 'was_new' => false ] : null ),
                'camp'  => [ 'id' => $camp_id, 'images_imported' => $images_imported, 'images_failed' => $images_failed ],
            ],
            'warnings'  => $warnings,
        ], 200 );
    }

    /**
     * Translate the public REST payload shape into the shape expected by
     * RM_Camp::create_from_payload. Keeps handle_import readable as more
     * entities are wired in (spot, hotel, images).
     *
     * @param array $camp     The `camp` block from the payload.
     * @param array $payload  The full payload (for top-level fields like import_source_url).
     * @param array $resolved Pre-resolved entity IDs: ['coach_post_id'=>int, 'spot_id'=>int, 'hotel_id'=>int].
     */
    private static function build_camp_payload( array $camp, array $payload, array $resolved ): array {
        return [
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
            'coach_post_id'     => $resolved['coach_post_id'] ?? 0,
            'spot_id'           => $resolved['spot_id'] ?? 0,
            'hotel_id'          => $resolved['hotel_id'] ?? 0,
            'import_source_url' => $payload['import_source_url'],
            'check_stripe'      => false,  // imports skip Stripe check for example data
        ];
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
