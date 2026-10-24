<?php
/**
 * The Waypost docs layout: doc pages, the docs home and the results page use
 * Waypost's own page template instead of the theme's, between the theme's
 * header and footer. Off unless Settings, Appearance, Layout says so.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Layout {

	/** True while the layout template renders, so the_content does not add parts the layout places itself. */
	private static $rendering = false;

	public static function init() {
		// After Divi's Theme Builder (98) and preview templates (99).
		add_filter( 'template_include', array( __CLASS__, 'template' ), 100 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	public static function enabled() {
		return 'waypost' === Waypost_Settings::get( 'layout' );
	}

	/** What the current page is to the docs: 'doc', 'home', 'results' or '' when it is not a docs page. */
	public static function page_kind() {
		if ( ! is_singular() ) {
			return '';
		}
		$id = (int) get_queried_object_id();
		if ( ! $id ) {
			return '';
		}
		if ( $id === (int) Waypost_Settings::get( 'home_page' ) ) {
			return 'home';
		}
		if ( $id === (int) Waypost_Settings::get( 'results_page' ) ) {
			return 'results';
		}
		return Waypost_Tree::is_doc( $id ) ? 'doc' : '';
	}

	/** Page builders and previews draw their own screens; leave those alone. */
	private static function is_builder_request() {
		// phpcs:disable WordPress.Security.NonceVerification -- read-only checks.
		return isset( $_GET['et_fb'] ) || isset( $_GET['elementor-preview'] ) || isset( $_GET['fl_builder'] ) || isset( $_GET['ct_builder'] ) || is_customize_preview();
		// phpcs:enable
	}

	public static function applies() {
		return self::enabled() && '' !== self::page_kind() && ! self::is_builder_request() && ! post_password_required();
	}

	public static function template( $template ) {
		if ( ! self::applies() ) {
			return $template;
		}
		return WAYPOST_DIR . 'templates/docs.php';
	}

	public static function body_class( $classes ) {
		if ( self::applies() ) {
			$classes[] = 'waypost-layout-page';
		}
		return $classes;
	}

	public static function rendering() {
		return self::$rendering;
	}

	/** Main area of the layout, between the theme's header and footer. */
	public static function render() {
		self::$rendering = true;
		$html            = Waypost_Render::layout( self::page_kind() );
		self::$rendering = false;
		return $html;
	}
}
