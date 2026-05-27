<?php
/**
 * Plugin Name: RideMaster Importer
 * Description: Conversational import of camps from external coach websites into Ridemaster. Depends on the RideMaster plugin.
 * Version: 0.1.0
 * Author: RideMaster
 * Text Domain: ridemaster-importer
 * Requires Plugins: ridemaster
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RM_IMPORTER_VERSION', '0.1.0' );
define( 'RM_IMPORTER_DIR', plugin_dir_path( __FILE__ ) );
define( 'RM_IMPORTER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Hard dependency check on the main ridemaster plugin.
 * Runs early (plugins_loaded) so it covers REST requests too — admin_init
 * is not fired during REST API calls.
 */
add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'RM_Camp' ) ) {
        // Deactivate self + warn (admin context only — REST just won't have routes).
        add_action( 'admin_init', function () {
            deactivate_plugins( plugin_basename( __FILE__ ) );
        } );
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>RideMaster Importer</strong> requires the <strong>RideMaster</strong> plugin to be active.</p></div>';
        } );
        return;
    }

    require_once RM_IMPORTER_DIR . 'includes/class-rm-importer-validator.php';
    require_once RM_IMPORTER_DIR . 'includes/class-rm-importer-rollback.php';
    require_once RM_IMPORTER_DIR . 'includes/class-rm-importer-images.php';
    require_once RM_IMPORTER_DIR . 'includes/class-rm-importer-endpoint.php';
    add_action( 'rest_api_init', [ 'RM_Importer_Endpoint', 'register_routes' ] );

    require_once RM_IMPORTER_DIR . 'includes/class-rm-importer-debug.php';
    add_action( 'rest_api_init', [ 'RM_Importer_Debug', 'register_routes' ] );
}, 20 );
