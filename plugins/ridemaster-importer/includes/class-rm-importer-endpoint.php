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

        // Explicit coach↔spot link (rel_id 19).
        // The main plugin's auto_link_coach_to_spot hook on save_post_product fires
        // during wp_insert_post, BEFORE apply_meta_from_data writes the rel_id 20
        // and 18 relations — so the hook doesn't find them. We write rel 19
        // ourselves here, idempotently (existence check on the unique constraint).
        if ( $coach_post_id && $spot_id ) {
            global $wpdb;
            $table = $wpdb->prefix . 'jet_rel_default';
            $existing_rel = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE rel_id = %d AND parent_object_id = %d AND child_object_id = %d",
                19, $coach_post_id, $spot_id
            ) );
            if ( ! $existing_rel ) {
                $wpdb->insert( $table, [
                    'rel_id'           => 19,
                    'parent_object_id' => $coach_post_id,
                    'child_object_id'  => $spot_id,
                ], [ '%d', '%d', '%d' ] );
            }
        }

        // Flush deferred stock writes synchronously (without triggering unrelated shutdown handlers).
        self::flush_camp_stock_meta( $camp_id, (int) $camp['max_spots'] );

        // ----- Spot images -----
        if ( $spot_id && ! empty( $payload['spot']['data']['images'] ) ) {
            self::attach_entity_images( $spot_id, $payload['spot']['data']['images'], '_thumbnail_id', 'spot_gallery', $warnings, $rollback );
        }

        // ----- Hotel images -----
        if ( $hotel_id && ! empty( $payload['hotel']['data']['images'] ) ) {
            self::attach_entity_images( $hotel_id, $payload['hotel']['data']['images'], '_thumbnail_id', 'accommodation_photos', $warnings, $rollback );
        }

        // ----- Camp images -----
        $featured = $camp['featured_image'] ?? null;
        $gallery  = $camp['gallery']        ?? [];
        $all_camp_images = $featured ? array_merge( [ $featured ], $gallery ) : $gallery;
        $img_result = $all_camp_images
            ? self::attach_entity_images( $camp_id, $all_camp_images, '_thumbnail_id', '_product_image_gallery', $warnings, $rollback )
            : [ 'imported' => 0, 'failed' => 0 ];
        $images_imported = $img_result['imported'];
        $images_failed   = $img_result['failed'];

        // ----- Stripe blocker warning -----
        // If the linked coach hasn't completed Stripe onboarding, the camp may
        // be hidden from the public site (the main plugin's blocker forces draft
        // in some flows). Surface this as a warning so the importer knows.
        if ( $coach_post_id ) {
            $linked_user_id = self::get_user_id_for_coach_post( $coach_post_id );
            if ( $linked_user_id ) {
                $stripe = get_user_meta( $linked_user_id, 'stripe_onboarding_complete', true );
                if ( $stripe !== '1' ) {
                    $warnings[] = 'Coach Stripe onboarding is incomplete — camp may be hidden from public.';
                }
            }
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
     * Look up the WP user whose coach_post_id usermeta points at this coach CPT.
     * Returns 0 if no user is linked.
     */
    private static function get_user_id_for_coach_post( int $coach_post_id ): int {
        $users = get_users( [
            'meta_key'   => 'coach_post_id',
            'meta_value' => $coach_post_id,
            'number'     => 1,
            'fields'     => 'ID',
        ] );
        return $users ? (int) $users[0] : 0;
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
     * Attach a list of images to a post (camp, spot, hotel).
     *
     * First successful image becomes the featured image (_thumbnail_id via
     * set_post_thumbnail). Subsequent successful images go into a CSV postmeta
     * key (e.g. _product_image_gallery for camps, spot_gallery for spots,
     * accommodation_photos for hotels).
     *
     * Each successful attachment is tracked in the rollback so it is cleaned
     * up if a later step fails. Failures are pushed as warnings.
     *
     * @param int                   $parent_id          Post ID to attach to.
     * @param array                 $images             Image descriptor list (url|base64, filename, alt, title, role).
     * @param string                $featured_meta_key  Currently always '_thumbnail_id'; passed for clarity at call sites.
     * @param string                $gallery_meta_key   CSV postmeta key for the gallery.
     * @param array                 $warnings           Reference; failure messages are appended.
     * @param RM_Importer_Rollback  $rollback           Rollback tracker.
     * @return array { imported: int, failed: int }
     */
    private static function attach_entity_images(
        int $parent_id,
        array $images,
        string $featured_meta_key,
        string $gallery_meta_key,
        array &$warnings,
        RM_Importer_Rollback $rollback
    ): array {
        unset( $featured_meta_key ); // currently always _thumbnail_id; reserved for future override.

        $imported     = 0;
        $failed       = 0;
        $featured_set = false;
        $gallery_ids  = [];

        foreach ( $images as $item ) {
            $result = RM_Importer_Images::import( $item, $parent_id );
            if ( empty( $result['attachment_id'] ) ) {
                $warnings[] = "Image for post {$parent_id}: " . ( $result['error'] ?? 'unknown error' );
                $failed++;
                continue;
            }
            $rollback->track_attachment( (int) $result['attachment_id'] );
            $imported++;

            if ( ! $featured_set ) {
                set_post_thumbnail( $parent_id, (int) $result['attachment_id'] );
                $featured_set = true;
            } else {
                $gallery_ids[] = (int) $result['attachment_id'];
            }
        }

        if ( $gallery_ids ) {
            update_post_meta( $parent_id, $gallery_meta_key, implode( ',', $gallery_ids ) );
        }

        return [ 'imported' => $imported, 'failed' => $failed ];
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
