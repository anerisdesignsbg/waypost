<?php
/**
 * Divi Builder modules.
 *
 * The module file is included on et_builder_ready, when ET_Builder_Module
 * exists. It creates each module once, which is how Divi registers them.
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
