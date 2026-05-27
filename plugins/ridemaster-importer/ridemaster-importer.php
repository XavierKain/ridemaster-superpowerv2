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
 * If absent, deactivate self and show admin notice.
 */
add_action( 'admin_init', function () {
    if ( ! class_exists( 'RM_Camp' ) ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>RideMaster Importer</strong> requires the <strong>RideMaster</strong> plugin to be active.</p></div>';
        } );
    }
} );

require_once RM_IMPORTER_DIR . 'includes/class-rm-importer-endpoint.php';

add_action( 'rest_api_init', [ 'RM_Importer_Endpoint', 'register_routes' ] );
