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
     * Find or create a spot CPT post from a payload.
     *
     * Match strategy: by exact title (case-insensitive). Country is stored
     * as the spot_country meta if provided.
     *
     * @param array $payload {
     *     @type array $match_by         { name, country? }
     *     @type bool  $create_if_missing
     *     @type array $data             { name, country, description, sport[], level[], water_type[] }
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

        if ( ! empty( $existing ) ) {
            return [ 'post_id' => (int) $existing[0], 'was_new' => false ];
        }

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

        // Country meta.
        if ( ! empty( $data['country'] ) ) {
            update_post_meta( $post_id, 'spot_country', sanitize_text_field( $data['country'] ) );
        }

        // Taxonomies.
        if ( ! empty( $data['sport'] ) ) {
            wp_set_object_terms( $post_id, array_map( 'sanitize_title', (array) $data['sport'] ), 'sport' );
        }
        if ( ! empty( $data['level'] ) ) {
            wp_set_object_terms( $post_id, array_map( 'sanitize_title', (array) $data['level'] ), 'level' );
        }
        if ( ! empty( $data['water_type'] ) ) {
            wp_set_object_terms( $post_id, array_map( 'sanitize_title', (array) $data['water_type'] ), 'water-type' );
        }

        return [ 'post_id' => $post_id, 'was_new' => true ];
    }
}
