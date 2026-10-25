<?php
/**
 * Settings storage, defaults and sanitising.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Settings {

	const OPTION = 'waypost_settings';

	/** Most quick searches and topic cards on the search home. */
	const MAX_ITEMS = 6;

	private static $cache = null;

	public static function defaults() {
		return array(
			'setup_done'      => 0,
			'taxonomy_mode'   => 'own',          // own | existing
			'taxonomy'        => WAYPOST_OWN_TAXONOMY,
			'post_types'      => array( 'page' ),
			'home_page'       => 0,
			'results_page'    => 0,
			'results_target'  => 'page',         // page: the results page | home: inside the search home on the docs home page
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
			'hide_extra_search' => 1,            // results page: hide other search boxes, it has its own
			'home_title'      => '',
			'home_intro'      => '',
			'topics_title'    => '',
			'quick_searches'  => array(),        // up to 6 of array( label, query )
			'topics'          => array(),        // up to 6 of array( icon, title, text, page )
			'layout'          => 'theme',        // theme: the theme's own page template | waypost: the Waypost docs layout
			'sidebar'         => 'left',         // left | right
			'width'           => 'contained',    // contained | full
			'max_width'       => 1280,           // px, when contained
			'toc_position'    => 'side',         // side: a column of its own | content: at the top of the page
			'chrome'          => 'theme',        // header and footer on docs pages: theme | waypost (a standalone docs bar)
			'show_updated'    => 1,              // "Last updated" date on doc pages
			'show_reading'    => 0,              // reading time on doc pages
			'heading_links'   => 1,              // link icons on headings
			'feedback'        => 1,              // "Was this helpful?" on doc pages
			'documents'       => 1,              // search inside PDF, Word and other files placed in doc sections
			'documents_menu'  => 1,              // list those files in the docs menu
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
			'auto'    => array(
				'label'  => __( 'Auto light and dark', 'waypost' ),
				'desc'   => __( 'Light or dark to match each reader’s device, with a switch they can use. Best with the Waypost docs layout.', 'waypost' ),
				'accent' => '#2563eb', // Lightened automatically in dark mode.
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

		$out['results_target'] = ( isset( $in['results_target'] ) && 'home' === $in['results_target'] ) ? 'home' : 'page';
		$choices = array(
			'layout'       => array( 'theme', 'waypost' ),
			'sidebar'      => array( 'left', 'right' ),
			'width'        => array( 'contained', 'full' ),
			'toc_position' => array( 'side', 'content' ),
			'chrome'       => array( 'theme', 'waypost' ),
		);
		foreach ( $choices as $k => $allowed ) {
			$out[ $k ] = ( isset( $in[ $k ] ) && in_array( $in[ $k ], $allowed, true ) ) ? $in[ $k ] : $d[ $k ];
		}
		$out['max_width'] = isset( $in['max_width'] ) ? max( 760, min( 2400, absint( $in['max_width'] ) ) ) : $d['max_width'];
		foreach ( array( 'home_page', 'results_page' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? absint( $in[ $k ] ) : 0;
		}
		foreach ( array( 'accordion', 'toc_auto', 'prevnext_auto', 'crumbs_auto', 'copy_code', 'shortcut', 'log_searches', 'hide_extra_search', 'show_updated', 'show_reading', 'heading_links', 'feedback', 'documents', 'documents_menu', 'delete_on_uninstall' ) as $k ) {
			$out[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}

		$levels = isset( $in['toc_levels'] ) ? array_map( 'absint', (array) $in['toc_levels'] ) : $d['toc_levels'];
		$levels = array_values( array_intersect( array( 2, 3, 4 ), $levels ) );
		$out['toc_levels'] = $levels ? $levels : array( 2 );
		$out['toc_min']    = isset( $in['toc_min'] ) ? max( 1, min( 10, absint( $in['toc_min'] ) ) ) : $d['toc_min'];
		$out['per_page']   = isset( $in['per_page'] ) ? max( 5, min( 50, absint( $in['per_page'] ) ) ) : $d['per_page'];

		$out['placeholder'] = isset( $in['placeholder'] ) ? sanitize_text_field( $in['placeholder'] ) : '';
		foreach ( array( 'home_title', 'home_intro', 'topics_title' ) as $k ) {
			$out[ $k ] = isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : '';
		}

		$out['quick_searches'] = array();
		foreach ( isset( $in['quick_searches'] ) ? (array) $in['quick_searches'] : array() as $row ) {
			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			$query = isset( $row['query'] ) ? sanitize_text_field( $row['query'] ) : '';
			if ( '' === $label && '' === $query ) {
				continue;
			}
			$out['quick_searches'][] = array( 'label' => '' !== $label ? $label : $query, 'query' => '' !== $query ? $query : $label );
		}
		$out['quick_searches'] = array_slice( $out['quick_searches'], 0, self::MAX_ITEMS );

		$out['topics'] = array();
		$icons         = Waypost_Render::icons();
		foreach ( isset( $in['topics'] ) ? (array) $in['topics'] : array() as $row ) {
			$title = isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
			$page  = isset( $row['page'] ) ? absint( $row['page'] ) : 0;
			if ( '' === $title || ! $page ) {
				continue;
			}
			$icon            = isset( $row['icon'] ) && is_string( $row['icon'] ) && isset( $icons[ $row['icon'] ] ) ? $row['icon'] : 'book';
			$out['topics'][] = array(
				'icon'  => $icon,
				'title' => $title,
				'text'  => isset( $row['text'] ) ? sanitize_text_field( $row['text'] ) : '',
				'page'  => $page,
			);
		}
		$out['topics'] = array_slice( $out['topics'], 0, self::MAX_ITEMS );
		$accent             = isset( $in['accent'] ) ? sanitize_hex_color( $in['accent'] ) : '';
		$out['accent']      = $accent ? $accent : $d['accent'];
		$out['style']       = ( isset( $in['style'] ) && is_string( $in['style'] ) && array_key_exists( $in['style'], self::styles() ) ) ? $in['style'] : $d['style'];

		return $out;
	}
}
