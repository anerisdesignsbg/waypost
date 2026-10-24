<?php
/**
 * Registers the built-in "Doc sections" taxonomy, or attaches an existing
 * taxonomy to the chosen content types.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Taxonomy {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
		add_action( 'init', array( __CLASS__, 'attach_existing' ), 99 );
	}

	public static function register() {
		$types = Waypost_Settings::post_types();
		$own   = 'own' === Waypost_Settings::get( 'taxonomy_mode' );

		register_taxonomy(
			WAYPOST_OWN_TAXONOMY,
			$own ? $types : array(),
			array(
				'labels'            => array(
					'name'              => __( 'Doc sections', 'waypost' ),
					'singular_name'     => __( 'Doc section', 'waypost' ),
					'menu_name'         => __( 'Doc sections', 'waypost' ),
					'all_items'         => __( 'All sections', 'waypost' ),
					'parent_item'       => __( 'Parent section', 'waypost' ),
					'parent_item_colon' => __( 'Parent section:', 'waypost' ),
					'edit_item'         => __( 'Edit section', 'waypost' ),
					'update_item'       => __( 'Update section', 'waypost' ),
					'add_new_item'      => __( 'Add new section', 'waypost' ),
					'new_item_name'     => __( 'New section name', 'waypost' ),
					'search_items'      => __( 'Search sections', 'waypost' ),
					'not_found'         => __( 'No sections yet.', 'waypost' ),
					'back_to_items'     => __( '← Back to sections', 'waypost' ),
				),
				'description'       => __( 'Groups pages into the documentation menu.', 'waypost' ),
				'hierarchical'      => true,
				'public'            => false,
				'show_ui'           => $own,
				'show_in_menu'      => false, // Reached through the Waypost menu instead.
				'show_in_nav_menus' => false,
				'show_in_rest'      => true,  // Needed for the block editor sidebar panel.
				'show_admin_column' => $own,
				'show_tagcloud'     => false,
				'rewrite'           => false,
				'query_var'         => false,
			)
		);
	}

	/** Make sure an existing taxonomy is attached to every chosen content type. */
	public static function attach_existing() {
		if ( 'existing' !== Waypost_Settings::get( 'taxonomy_mode' ) ) {
			return;
		}
		$tax = Waypost_Settings::taxonomy();
		if ( ! taxonomy_exists( $tax ) ) {
			return;
		}
		foreach ( Waypost_Settings::post_types() as $type ) {
			if ( ! is_object_in_taxonomy( $type, $tax ) ) {
				register_taxonomy_for_object_type( $tax, $type );
			}
		}
	}

	/** Hierarchical taxonomies (other than ours) that could hold a docs structure. */
	public static function candidates() {
		$out = array();
		foreach ( get_taxonomies( array( 'hierarchical' => true, 'show_ui' => true ), 'objects' ) as $tax ) {
			if ( WAYPOST_OWN_TAXONOMY === $tax->name ) {
				continue;
			}
			$count = wp_count_terms( array( 'taxonomy' => $tax->name, 'hide_empty' => false ) );
			$out[ $tax->name ] = array(
				'label'   => $tax->labels->name,
				'types'   => $tax->object_type,
				'terms'   => is_wp_error( $count ) ? 0 : (int) $count,
			);
		}
		return $out;
	}

	public static function is_ready() {
		return taxonomy_exists( Waypost_Settings::taxonomy() );
	}

	/** Edit screen URL for the sections taxonomy. */
	public static function edit_url() {
		$types = Waypost_Settings::post_types();
		return add_query_arg(
			array(
				'taxonomy'  => Waypost_Settings::taxonomy(),
				'post_type' => $types[0],
			),
			admin_url( 'edit-tags.php' )
		);
	}
}
