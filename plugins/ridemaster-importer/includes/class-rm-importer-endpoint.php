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
        // Pong only at this stage — full handler comes in later tasks.
        return new WP_REST_Response( [
            'status'  => 'pong',
            'version' => RM_IMPORTER_VERSION,
        ], 200 );
    }
}
