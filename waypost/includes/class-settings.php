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
			'accent'          => '#2563eb',
			'delete_on_uninstall' => 0,
		);
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

		return $out;
	}
}
