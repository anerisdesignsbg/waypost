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
		return WAYPOST_DIR . ( wp_is_block_theme() ? 'templates/docs-block.php' : 'templates/docs.php' );
	}

	/**
	 * Block themes: the theme's header and footer template parts, taken from the
	 * page template it would have used, so a customised header or footer shows too.
	 * Returns array( header HTML, footer HTML ).
	 */
	public static function block_chrome() {
		global $_wp_current_template_content;
		$header = null;
		$footer = null;
		foreach ( parse_blocks( (string) $_wp_current_template_content ) as $block ) {
			if ( 'core/template-part' !== $block['blockName'] ) {
				continue;
			}
			$area = self::part_area( $block['attrs'] );
			if ( 'header' === $area && ! $header ) {
				$header = $block; // The first header.
			} elseif ( 'footer' === $area ) {
				$footer = $block; // The last footer.
			}
		}
		$fallback = function ( $slug ) {
			return parse_blocks( '<!-- wp:template-part {"slug":"' . $slug . '","tagName":"' . $slug . '"} /-->' )[0];
		};
		return array(
			render_block( $header ? $header : $fallback( 'header' ) ),
			render_block( $footer ? $footer : $fallback( 'footer' ) ),
		);
	}

	private static function part_area( array $attrs ) {
		if ( ! empty( $attrs['area'] ) ) {
			return $attrs['area'];
		}
		if ( empty( $attrs['slug'] ) ) {
			return '';
		}
		$theme = ! empty( $attrs['theme'] ) ? $attrs['theme'] : get_stylesheet();
		$part  = get_block_template( $theme . '//' . $attrs['slug'], 'wp_template_part' );
		if ( $part && ! empty( $part->area ) && 'uncategorized' !== $part->area ) {
			return $part->area;
		}
		return in_array( $attrs['slug'], array( 'header', 'footer' ), true ) ? $attrs['slug'] : '';
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
