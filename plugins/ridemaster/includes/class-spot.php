<?php
/**
 * RM_Spot — Spot CPT helper (importer support).
 *
 * The spot CPT itself is registered by JetEngine. This class provides a
 * single static method that the importer plugin uses to create or match a
 * spot from external data.
 *
 * @package RideMaster
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RM_Spot {

    /**
     * JetEngine map field hash prefix used across all spot posts. The map
     * field stores three sub-keys: `_lat`, `_lng`, `_hash`. The hash is a
     * per-record fingerprint computed as md5( "$lat,$lng" ).
     */
    const MAP_FIELD_PREFIX = '55e07da3ca3ec7d16f5d403530822bca_';

    /**
     * Find or create a spot CPT post from a payload.
     *
     * Match strategy: by exact title (case-insensitive).
     *
     * Image handling is NOT performed here to avoid a circular dependency on
     * the importer plugin's RM_Importer_Images class. The endpoint processes
     * `data.images` after this method returns.
     *
     * UPDATE BEHAVIOR: when a spot is matched-existing, meta + taxonomies
     * from `data` are still applied (overwriting). The importer is the source
     * of truth for example data; users edit afterward.
     *
     * @param array $payload {
     *     @type array $match_by         { name, country? }
     *     @type bool  $create_if_missing
     *     @type array $data             {
     *         name, country, region, description,
     *         latitude, longitude, wind_direction, best_season,
     *         water_temperature, airport, time_zone, currency,
     *         language_text, wetsuit,
     *         sport[], level[], water_type[]
     *     }
     * }
     * @return array|WP_Error { 'post_id' => int, 'was_new' => bool }
     */
    public static function create_from_payload( array $payload ) {

        $data       = $payload['data'] ?? [];
        $match      = $payload['match_by'] ?? [];
        $can_create = ! empty( $payload['create_if_missing'] );

        $name = $data['name'] ?? $match['name'] ?? '';
        if ( empty( $name ) ) {
            return new WP_Error( 'INVALID_PAYLOAD', 'spot.data.name or spot.match_by.name is required', [ 'status' => 400 ] );
        }

        // Match by exact title (case-insensitive).
        $existing = get_posts( [
            'post_type'      => 'spot',
            'post_status'    => 'any',
            'posts_per_page' => 2,
            'title'          => $name,
            'fields'         => 'ids',
        ] );

        $was_new = false;
        $post_id = 0;

        if ( ! empty( $existing ) ) {
            $post_id = (int) $existing[0];
        } else {
            if ( ! $can_create ) {
                return new WP_Error( 'SPOT_NOT_FOUND', "No spot with name '$name'", [ 'status' => 404 ] );
            }

            $post_id = wp_insert_post( [
                'post_type'    => 'spot',
                'post_title'   => sanitize_text_field( $name ),
                'post_content' => isset( $data['description'] ) ? wp_kses_post( $data['description'] ) : '',
                'post_status'  => 'publish',
            ], true );

            if ( is_wp_error( $post_id ) ) {
                return $post_id;
            }
            $was_new = true;
        }

        // ---- Meta (always apply when present in data, even on update) ----
        self::apply_meta( (int) $post_id, $data );

        // ---- Taxonomies (always apply when present in data, even on update) ----
        if ( isset( $data['sport'] ) ) {
            wp_set_object_terms( $post_id, array_map( 'sanitize_title', (array) $data['sport'] ), 'sport' );
        }
        if ( isset( $data['level'] ) ) {
            wp_set_object_terms( $post_id, array_map( 'sanitize_title', (array) $data['level'] ), 'level' );
        }
        if ( isset( $data['water_type'] ) ) {
            wp_set_object_terms( $post_id, array_map( 'sanitize_title', (array) $data['water_type'] ), 'water-type' );
        }

        return [ 'post_id' => (int) $post_id, 'was_new' => $was_new ];
    }

    /**
     * Write all scalar/text meta fields for a spot from a `data` payload.
     * Only writes keys that are present in `$data` (so partial updates work).
     */
    private static function apply_meta( int $post_id, array $data ): void {

        // Simple text meta (string keyed → meta key map).
        $text_map = [
            'country'           => 'spot_country',
            'region'            => 'spot_region',
            'wind_direction'    => 'spot_wind_direction',
            'best_season'       => 'spot_best_season',
            'water_temperature' => 'water_temperature',
            'airport'           => 'airport',
            'time_zone'         => 'time_zone',
            'currency'          => 'currency',
            'language_text'     => 'language',
            'wetsuit'           => 'wetsuit',
        ];

        foreach ( $text_map as $data_key => $meta_key ) {
            if ( array_key_exists( $data_key, $data ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( (string) $data[ $data_key ] ) );
            }
        }

        // Geo: lat + lng. Write all four keys atomically (combined spot_location
        // string + JetEngine map field's _lat / _lng / _hash sub-keys).
        if ( isset( $data['latitude'] ) && isset( $data['longitude'] ) && $data['latitude'] !== '' && $data['longitude'] !== '' ) {
            $lat = (string) $data['latitude'];
            $lng = (string) $data['longitude'];

            update_post_meta( $post_id, 'spot_location', $lat . ',' . $lng );
            update_post_meta( $post_id, self::MAP_FIELD_PREFIX . 'lat',  $lat );
            update_post_meta( $post_id, self::MAP_FIELD_PREFIX . 'lng',  $lng );
            update_post_meta( $post_id, self::MAP_FIELD_PREFIX . 'hash', md5( $lat . ',' . $lng ) );
        }
    }
}
