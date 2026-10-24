<?php
/**
 * Divi Builder modules.
 *
 * Follows the loading pattern proven on the original FastNetMon build:
 * module classes are included on et_builder_ready and Divi discovers them
 * itself. Do not instantiate them manually.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Divi {

	public static function init() {
		add_action( 'et_builder_ready', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		if ( ! class_exists( 'ET_Builder_Module' ) || class_exists( 'Waypost_Divi_Menu' ) ) {
			return;
		}
		require_once WAYPOST_DIR . 'includes/divi-modules.php';
	}
}
