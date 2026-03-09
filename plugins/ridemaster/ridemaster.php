<?php
/**
 * Plugin Name: RideMaster
 * Description: Core business logic for RideMaster — coach management, camp creation, authentication, admin tools, data integrity, and frontend inline editing.
 * Version: 1.0.0
 * Author: RideMaster
 * Text Domain: ridemaster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Constants
define( 'RM_VERSION', '1.0.0' );
define( 'RM_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load modules
require_once RM_PLUGIN_DIR . 'includes/class-coach.php';
require_once RM_PLUGIN_DIR . 'includes/class-camp.php';
require_once RM_PLUGIN_DIR . 'includes/class-auth.php';
require_once RM_PLUGIN_DIR . 'includes/class-admin.php';
require_once RM_PLUGIN_DIR . 'includes/class-cleanup.php';
require_once RM_PLUGIN_DIR . 'includes/class-inline-edit.php';

// Instantiate modules
new RM_Coach();
new RM_Camp();
new RM_Auth();
new RM_Admin();
new RM_Cleanup();
new RM_Inline_Edit();
