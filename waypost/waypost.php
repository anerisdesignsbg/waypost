<?php
/**
 * Plugin Name:       Waypost
 * Plugin URI:        https://anerisdesigns.com/waypost
 * Description:       Turn the pages you already have into a documentation site: sidebar menu, instant search, table of contents, previous and next links and breadcrumbs. No content migration, no URL changes.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Aneris Designs
 * Author URI:        https://anerisdesigns.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       waypost
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'WAYPOST_VERSION', '1.0.0' );
define( 'WAYPOST_FILE', __FILE__ );
define( 'WAYPOST_DIR', plugin_dir_path( __FILE__ ) );
define( 'WAYPOST_URL', plugin_dir_url( __FILE__ ) );
define( 'WAYPOST_OWN_TAXONOMY', 'waypost_section' );

/**
 * Version string for a plugin asset: the plugin version plus the file's last
 * change, so browsers fetch a new copy whenever the file changes.
 */
function waypost_asset_version( $file ) {
	$path = WAYPOST_DIR . $file;
	return WAYPOST_VERSION . ( file_exists( $path ) ? '.' . filemtime( $path ) : '' );
}

require_once WAYPOST_DIR . 'includes/class-settings.php';
require_once WAYPOST_DIR . 'includes/class-taxonomy.php';
require_once WAYPOST_DIR . 'includes/class-tree.php';
require_once WAYPOST_DIR . 'includes/class-headings.php';
require_once WAYPOST_DIR . 'includes/class-render.php';
require_once WAYPOST_DIR . 'includes/class-search.php';
require_once WAYPOST_DIR . 'includes/class-frontend.php';
require_once WAYPOST_DIR . 'includes/class-layout.php';
require_once WAYPOST_DIR . 'includes/class-feedback.php';
require_once WAYPOST_DIR . 'includes/class-blocks.php';
require_once WAYPOST_DIR . 'includes/class-divi.php';

if ( is_admin() ) {
	require_once WAYPOST_DIR . 'admin/class-admin.php';
	require_once WAYPOST_DIR . 'admin/class-help.php';
	require_once WAYPOST_DIR . 'admin/class-wizard.php';
}

add_action( 'plugins_loaded', 'waypost_boot' );

function waypost_boot() {
	load_plugin_textdomain( 'waypost', false, dirname( plugin_basename( WAYPOST_FILE ) ) . '/languages' );

	Waypost_Taxonomy::init();
	Waypost_Tree::init();
	Waypost_Search::init();
	Waypost_Frontend::init();
	Waypost_Layout::init();
	Waypost_Feedback::init();
	Waypost_Blocks::init();
	Waypost_Divi::init();

	if ( is_admin() ) {
		Waypost_Admin::init();
		Waypost_Help::init();
		Waypost_Wizard::init();
	}
}

register_activation_hook( __FILE__, 'waypost_activate' );
register_deactivation_hook( __FILE__, 'waypost_deactivate' );

function waypost_activate() {
	if ( false === get_option( Waypost_Settings::OPTION ) ) {
		add_option( Waypost_Settings::OPTION, Waypost_Settings::defaults() );
	}
	if ( ! Waypost_Settings::get( 'setup_done' ) ) {
		set_transient( 'waypost_activation_redirect', 1, 60 );
	}
	delete_transient( Waypost_Tree::CACHE_KEY );
}

function waypost_deactivate() {
	delete_transient( Waypost_Tree::CACHE_KEY );
}
