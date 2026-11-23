<?php
/**
 * Plugin Name:       Aneris Docsway Site
 * Description:       The Aneris Docsway product site: a landing page with features, a live search demo, pricing and Buy buttons. Activate it on a fresh WordPress and it sets everything up.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Aneris Designs
 * Author URI:        https://anerisdesigns.co
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aneris-docsway-site
 */

defined( 'ABSPATH' ) || exit;

define( 'DOCSWAY_SITE_VERSION', '1.0.0' );
define( 'DOCSWAY_SITE_DIR', plugin_dir_path( __FILE__ ) );
define( 'DOCSWAY_SITE_URL', plugin_dir_url( __FILE__ ) );

require_once DOCSWAY_SITE_DIR . 'includes/class-site.php';

register_activation_hook( __FILE__, array( 'Docsway_Site', 'setup' ) );
Docsway_Site::init();
