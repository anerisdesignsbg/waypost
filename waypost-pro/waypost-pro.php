<?php
/**
 * Plugin Name:       Waypost Pro
 * Description:       Adds document search (PDF, Word and more), synonyms and topic filters to Waypost.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  waypost
 * Author:            Aneris Designs
 * Author URI:        https://anerisdesigns.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       waypost-pro
 */

defined( 'ABSPATH' ) || exit;

define( 'WAYPOST_PRO_VERSION', '1.0.0' );
define( 'WAYPOST_PRO_FILE', __FILE__ );
define( 'WAYPOST_PRO_DIR', plugin_dir_path( __FILE__ ) );

/** The oldest version of the free Waypost plugin that has the hooks Pro uses. */
define( 'WAYPOST_PRO_NEEDS', '1.0.0' );

// Licensing and updates (Freemius). Starts straight away, as Freemius requires.
require_once WAYPOST_PRO_DIR . 'includes/freemius.php';

// Pro loads before the free plugin (plugins load in name order), so it waits for
// plugins_loaded and starts just before the free plugin does.
add_action( 'plugins_loaded', 'waypost_pro_boot', 5 );

function waypost_pro_boot() {
	if ( ! defined( 'WAYPOST_VERSION' ) || version_compare( WAYPOST_VERSION, WAYPOST_PRO_NEEDS, '<' ) ) {
		add_action( 'admin_notices', 'waypost_pro_missing_notice' );
		return;
	}
	if ( ! waypost_pro_can_run() ) {
		return; // No active licence: Freemius shows its own activation notice and page.
	}
	load_plugin_textdomain( 'waypost-pro', false, dirname( plugin_basename( WAYPOST_PRO_FILE ) ) . '/languages' );

	require_once WAYPOST_PRO_DIR . 'includes/class-documents.php';
	require_once WAYPOST_PRO_DIR . 'includes/class-pro.php';

	Waypost_Pro_Documents::init();
	Waypost_Pro::init();
}

function waypost_pro_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'Waypost Pro needs the free Waypost plugin, version 1.0.0 or newer. Install and activate it under Plugins, Add New.', 'waypost-pro' ) . '</p></div>';
}

register_deactivation_hook( __FILE__, 'waypost_pro_deactivate' );

function waypost_pro_deactivate() {
	wp_clear_scheduled_hook( 'waypost_read_documents' );
	delete_transient( 'waypost_tree' ); // Documents leave the menu.
}
