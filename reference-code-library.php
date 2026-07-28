<?php
/**
 * Plugin Name: Reference Code Library
 * Description: Build, brand, import, export, and publish a searchable reference library of code examples without executing the stored code.
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Paul Washington
 * License: GPL-2.0-or-later
 * Text Domain: reference-code-library
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RCL_VERSION', '2.0.0' );
define( 'RCL_FILE', __FILE__ );
define( 'RCL_DIR', plugin_dir_path( __FILE__ ) );
define( 'RCL_URL', plugin_dir_url( __FILE__ ) );

if ( defined( 'MOA_LIBRARY_VERSION' ) || class_exists( 'MOA_Library', false ) ) {
    add_action(
        'admin_notices',
        static function() {
            if ( current_user_can( 'activate_plugins' ) ) {
                echo '<div class="notice notice-error"><p><strong>Reference Code Library:</strong> Deactivate the original Missouri Accessibility Library plugin before using version 2. Existing code entries will remain in WordPress.</p></div>';
            }
        }
    );
    return;
}

require_once RCL_DIR . 'includes/class-rcl-library.php';
require_once RCL_DIR . 'includes/class-rcl-importer.php';
require_once RCL_DIR . 'includes/class-rcl-exporter.php';
require_once RCL_DIR . 'includes/class-rcl-admin.php';

register_activation_hook( __FILE__, array( 'RCL_Library', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'RCL_Library', 'deactivate' ) );

RCL_Library::instance();
RCL_Admin::instance();
