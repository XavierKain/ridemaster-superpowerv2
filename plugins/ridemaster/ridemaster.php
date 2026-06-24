<?php
/**
 * Plugin Name: RideMaster
 * Description: All-in-one RideMaster plugin — coach management, camp creation, authentication, admin tools, data integrity, frontend inline editing, and UI customizations.
 * Version: 2.5.0
 * Author: RideMaster
 * Text Domain: ridemaster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Composer autoload (Stripe SDK).
$rm_autoload = plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
if ( file_exists( $rm_autoload ) ) {
	require_once $rm_autoload;
}

// Constants
define( 'RM_VERSION', '2.6.0' );
define( 'RM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load modules
require_once RM_PLUGIN_DIR . 'includes/class-coach.php';
require_once RM_PLUGIN_DIR . 'includes/class-camp.php';
require_once RM_PLUGIN_DIR . 'includes/class-auth.php';
require_once RM_PLUGIN_DIR . 'includes/class-admin.php';
require_once RM_PLUGIN_DIR . 'includes/class-cleanup.php';
require_once RM_PLUGIN_DIR . 'includes/class-inline-edit.php';
require_once RM_PLUGIN_DIR . 'includes/class-payments.php';
require_once RM_PLUGIN_DIR . 'includes/class-hotel.php';
require_once RM_PLUGIN_DIR . 'includes/class-spot.php';
require_once RM_PLUGIN_DIR . 'includes/class-payout-cron.php';
require_once RM_PLUGIN_DIR . 'includes/class-cancellation.php';
require_once RM_PLUGIN_DIR . 'includes/ui-tweaks.php';

// Instantiate modules
new RM_Coach();
new RM_Camp();
new RM_Auth();
new RM_Admin();
new RM_Cleanup();
new RM_Inline_Edit();
new RM_Payments();
new RM_Hotel();
new RM_Payout_Cron();
new RM_Cancellation();

// Deactivate the old standalone UI Tweaks plugin if still active.
add_action( 'admin_init', function() {
	if ( is_plugin_active( 'ridemaster-ui-tweaks/ridemaster-ui-tweaks.php' ) ) {
		deactivate_plugins( 'ridemaster-ui-tweaks/ridemaster-ui-tweaks.php' );
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-info is-dismissible"><p><strong>RideMaster:</strong> The standalone "RideMaster UI Tweaks" plugin has been deactivated — its functionality is now built into RideMaster v2.0.0.</p></div>';
		} );
	}
} );
