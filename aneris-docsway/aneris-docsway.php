<?php
/**
 * Plugin Name:       Aneris Docsway
 * Description:       Turn the pages you already have into a documentation site: sidebar menu, instant search, table of contents, previous and next links and breadcrumbs. No content migration, no URL changes.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Aneris Designs
 * Author URI:        https://anerisdesigns.co
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aneris-docsway
 */

defined( 'ABSPATH' ) || exit;

define( 'DOCSWAY_VERSION', '1.0.0' );
define( 'DOCSWAY_FILE', __FILE__ );
define( 'DOCSWAY_DIR', plugin_dir_path( __FILE__ ) );
define( 'DOCSWAY_URL', plugin_dir_url( __FILE__ ) );
define( 'DOCSWAY_OWN_TAXONOMY', 'docsway_section' );

/**
 * Version string for a plugin asset: the plugin version plus the file's last
 * change, so browsers fetch a new copy whenever the file changes.
 */
function docsway_asset_version( $file ) {
	$path = DOCSWAY_DIR . $file;
	return DOCSWAY_VERSION . ( file_exists( $path ) ? '.' . filemtime( $path ) : '' );
}

require_once DOCSWAY_DIR . 'includes/class-settings.php';
require_once DOCSWAY_DIR . 'includes/class-taxonomy.php';
require_once DOCSWAY_DIR . 'includes/class-tree.php';
require_once DOCSWAY_DIR . 'includes/class-headings.php';
require_once DOCSWAY_DIR . 'includes/class-render.php';
require_once DOCSWAY_DIR . 'includes/class-match.php';
require_once DOCSWAY_DIR . 'includes/class-search.php';
require_once DOCSWAY_DIR . 'includes/class-frontend.php';
require_once DOCSWAY_DIR . 'includes/class-layout.php';
require_once DOCSWAY_DIR . 'includes/class-feedback.php';
require_once DOCSWAY_DIR . 'includes/class-blocks.php';
require_once DOCSWAY_DIR . 'includes/class-divi.php';

if ( is_admin() ) {
	require_once DOCSWAY_DIR . 'admin/class-admin.php';
	require_once DOCSWAY_DIR . 'admin/class-help.php';
	require_once DOCSWAY_DIR . 'admin/class-wizard.php';
}

add_action( 'plugins_loaded', 'docsway_boot' );

function docsway_boot() {
	Docsway_Taxonomy::init();
	Docsway_Tree::init();
	Docsway_Search::init();
	Docsway_Frontend::init();
	Docsway_Layout::init();
	Docsway_Feedback::init();
	Docsway_Blocks::init();
	Docsway_Divi::init();

	if ( is_admin() ) {
		Docsway_Admin::init();
		Docsway_Help::init();
		Docsway_Wizard::init();
	}
}

register_activation_hook( __FILE__, 'docsway_activate' );
register_deactivation_hook( __FILE__, 'docsway_deactivate' );

function docsway_activate() {
	if ( false === get_option( Docsway_Settings::OPTION ) ) {
		add_option( Docsway_Settings::OPTION, Docsway_Settings::defaults() );
	}
	if ( ! Docsway_Settings::get( 'setup_done' ) ) {
		set_transient( 'docsway_activation_redirect', 1, 60 );
	}
	delete_transient( Docsway_Tree::CACHE_KEY );
}

function docsway_deactivate() {
	delete_transient( Docsway_Tree::CACHE_KEY );
}
