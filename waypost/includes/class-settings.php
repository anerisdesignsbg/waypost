<?php
/**
 * Settings storage, defaults and sanitising.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Settings {

	const OPTION = 'waypost_settings';

	private static $cache = null;

	public static function defaults() {
		return array(
			'setup_done'      => 0,
			'taxonomy_mode'   => 'own',          // own | existing
			'taxonomy'        => WAYPOST_OWN_TAXONOMY,
			'post_types'      => array( 'page' ),
			'home_page'       => 0,
			'results_page'    => 0,
			'accordion'       => 1,              // only one top-level section open at a time
			'toc_auto'        => 1,
			'toc_levels'      => array( 2, 3 ),
			'toc_min'         => 3,
			'prevnext_auto'   => 1,
			'crumbs_auto'     => 0,
			'copy_code'       => 1,
			'shortcut'        => 1,              // Ctrl/Cmd + K search
			'placeholder'     => '',
			'per_page'        => 10,
			'log_searches'    => 1,
			'style'           => 'theme',        // a key from styles()
			'accent'          => '#2563eb',
			'delete_on_uninstall' => 0,
		);
	}

	/** Ready-made looks. Each has a matching .waypost-style-{key} block in frontend.css. */
	public static function styles() {
		return array(
			'theme'   => array(
				'label'  => __( 'Follow my theme', 'waypost' ),
				'desc'   => __( 'Uses your theme’s fonts and text colours. Only the accent colour is added.', 'waypost' ),
				'accent' => '#2563eb',
			),
			'modern'  => array(
				'label'  => __( 'Modern', 'waypost' ),
				'desc'   => __( 'Clean sans-serif type, rounded corners and soft shadows.', 'waypost' ),
				'accent' => '#4f46e5',
			),
			'minimal' => array(
				'label'  => __( 'Minimalist', 'waypost' ),
				'desc'   => __( 'No boxes or backgrounds. Thin lines and plenty of space.', 'waypost' ),
				'accent' => '#111827',
			),
			'classic' => array(
				'label'  => __( 'Classic', 'waypost' ),
				'desc'   => __( 'Serif type on warm paper tones, like a printed manual.', 'waypost' ),
				'accent' => '#9a3412',
			),
			'light'   => array(
				'label'  => __( 'Light', 'waypost' ),
				'desc'   => __( 'Dark text on white panels. Stays readable on busy or coloured themes.', 'waypost' ),
				'accent' => '#2563eb',
			),
			'dark'    => array(
				'label'  => __( 'Dark', 'waypost' ),
				'desc'   => __( 'Light text on dark panels. Made for sites with a dark background.', 'waypost' ),
				'accent' => '#60a5fa',
			),
		);
	}

	/** Black or white, whichever reads better on the accent colour. */
	public static function on_accent() {
		$hex = ltrim( (string) self::get( 'accent' ), '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		$lin = function ( $c ) {
			$c = hexdec( $c ) / 255;
			return $c <= 0.03928 ? $c / 12.92 : pow( ( $c + 0.055 ) / 1.055, 2.4 );
		};
		$l = 0.2126 * $lin( substr( $hex, 0, 2 ) ) + 0.7152 * $lin( substr( $hex, 2, 2 ) ) + 0.0722 * $lin( substr( $hex, 4, 2 ) );
		// Contrast with white is 1.05 / (l + 0.05); with near-black #111 about (l + 0.05) / 0.056.
		return ( 1.05 / ( $l + 0.05 ) ) >= ( ( $l + 0.05 ) / 0.056 ) ? '#ffffff' : '#111111';
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved       = get_option( self::OPTION, array() );
			self::$cache = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	public static function update( array $changes ) {
		$merged = array_merge( self::all(), $changes );
		$clean  = self::sanitize( $merged );
		update_option( self::OPTION, $clean );
		self::$cache = null;
		delete_transient( Waypost_Tree::CACHE_KEY );
		return $clean;
	}

	/** The taxonomy that holds the docs structure. */
	public static function taxonomy() {
		return 'existing' === self::get( 'taxonomy_mode' ) ? (string) self::get( 'taxonomy' ) : WAYPOST_OWN_TAXONOMY;
	}

	public static function post_types() {
		$types = (array) self::get( 'post_types' );
		$types = array_values( array_filter( $types, 'post_type_exists' ) );
		return $types ? $types : array( 'page' );
	}

	public static function placeholder() {
		$p = trim( (string) self::get( 'placeholder' ) );
		return '' !== $p ? $p : __( 'Search documentation…', 'waypost' );
	}

	public static function sanitize( $in ) {
		$d   = self::defaults();
		$out = array();

		$out['setup_done']    = empty( $in['setup_done'] ) ? 0 : 1;
		$out['taxonomy_mode'] = ( isset( $in['taxonomy_mode'] ) && 'existing' === $in['taxonomy_mode'] ) ? 'existing' : 'own';
		$tax                  = isset( $in['taxonomy'] ) ? sanitize_key( $in['taxonomy'] ) : $d['taxonomy'];
		$out['taxonomy']      = 'existing' === $out['taxonomy_mode'] && $tax ? $tax : WAYPOST_OWN_TAXONOMY;

		$types = isset( $in['post_types'] ) ? (array) $in['post_types'] : $d['post_types'];
		$types = array_values( array_unique( array_filter( array_map( 'sanitize_key', $types ) ) ) );
		$out['post_types'] = $types ? $types : array( 'page' );

		foreach ( array( 'home_page', 'results_page' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? absint( $in[ $k ] ) : 0;
		}
		foreach ( array( 'accordion', 'toc_auto', 'prevnext_auto', 'crumbs_auto', 'copy_code', 'shortcut', 'log_searches', 'delete_on_uninstall' ) as $k ) {
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}

		$levels = isset( $in['toc_levels'] ) ? array_map( 'absint', (array) $in['toc_levels'] ) : $d['toc_levels'];
		$levels = array_values( array_intersect( array( 2, 3, 4 ), $levels ) );
		$out['toc_levels'] = $levels ? $levels : array( 2 );
		$out['toc_min']    = isset( $in['toc_min'] ) ? max( 1, min( 10, absint( $in['toc_min'] ) ) ) : $d['toc_min'];
		$out['per_page']   = isset( $in['per_page'] ) ? max( 5, min( 50, absint( $in['per_page'] ) ) ) : $d['per_page'];

		$out['placeholder'] = isset( $in['placeholder'] ) ? sanitize_text_field( $in['placeholder'] ) : '';
		$accent             = isset( $in['accent'] ) ? sanitize_hex_color( $in['accent'] ) : '';
		$out['accent']      = $accent ? $accent : $d['accent'];
		$out['style']       = ( isset( $in['style'] ) && is_string( $in['style'] ) && array_key_exists( $in['style'], self::styles() ) ) ? $in['style'] : $d['style'];

		return $out;
	}
}
