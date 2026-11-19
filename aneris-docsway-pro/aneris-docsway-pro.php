<?php
/**
 * Plugin Name:       Aneris Docsway Pro
 * Description:       Adds document search (PDF, Word and more), synonyms and topic filters to Docsway.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  aneris-docsway
 * Author:            Aneris Designs
 * Author URI:        https://anerisdesigns.co
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aneris-docsway-pro
 */

defined( 'ABSPATH' ) || exit;

define( 'DOCSWAY_PRO_VERSION', '1.0.0' );
define( 'DOCSWAY_PRO_FILE', __FILE__ );
define( 'DOCSWAY_PRO_DIR', plugin_dir_path( __FILE__ ) );

/** The oldest version of the free Docsway plugin that has the hooks Pro uses. */
define( 'DOCSWAY_PRO_NEEDS', '1.0.0' );

// Licensing and updates (Freemius). Starts straight away, as Freemius requires.
require_once DOCSWAY_PRO_DIR . 'includes/freemius.php';

// Pro loads before the free plugin (plugins load in name order), so it waits for
// plugins_loaded and starts just before the free plugin does.
add_action( 'plugins_loaded', 'docsway_pro_boot', 5 );

function docsway_pro_boot() {
	if ( ! defined( 'DOCSWAY_VERSION' ) || version_compare( DOCSWAY_VERSION, DOCSWAY_PRO_NEEDS, '<' ) ) {
		add_action( 'admin_notices', 'docsway_pro_missing_notice' );
		return;
	}
	if ( ! docsway_pro_can_run() ) {
		return; // No active licence: Freemius shows its own activation notice and page.
	}
	load_plugin_textdomain( 'aneris-docsway-pro', false, dirname( plugin_basename( DOCSWAY_PRO_FILE ) ) . '/languages' );

	require_once DOCSWAY_PRO_DIR . 'includes/class-documents.php';
	require_once DOCSWAY_PRO_DIR . 'includes/class-pro.php';

	Docsway_Pro_Documents::init();
	Docsway_Pro::init();
}

function docsway_pro_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'Docsway Pro needs the free Docsway plugin, version 1.0.0 or newer. Install and activate it under Plugins, Add New.', 'aneris-docsway-pro' ) . '</p></div>';
}

register_deactivation_hook( __FILE__, 'docsway_pro_deactivate' );

function docsway_pro_deactivate() {
	wp_clear_scheduled_hook( 'docsway_read_documents' );
	delete_transient( 'docsway_tree' ); // Documents leave the menu.
}
