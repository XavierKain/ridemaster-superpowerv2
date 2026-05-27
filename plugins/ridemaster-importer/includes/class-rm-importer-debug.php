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
}
