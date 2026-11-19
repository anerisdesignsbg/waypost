<?php
/**
 * Registers the built-in "Doc sections" taxonomy, or attaches an existing
 * taxonomy to the chosen content types.
 */

defined( 'ABSPATH' ) || exit;

class Docsway_Taxonomy {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 5 );
		add_action( 'init', array( __CLASS__, 'attach_existing' ), 99 );
	}

	public static function register() {
		$types = Docsway_Settings::post_types();
		$own   = 'own' === Docsway_Settings::get( 'taxonomy_mode' );

		register_taxonomy(
			DOCSWAY_OWN_TAXONOMY,
			$own ? $types : array(),
			array(
				'labels'            => array(
					'name'              => __( 'Doc sections', 'aneris-docsway' ),
					'singular_name'     => __( 'Doc section', 'aneris-docsway' ),
					'menu_name'         => __( 'Doc sections', 'aneris-docsway' ),
					'all_items'         => __( 'All sections', 'aneris-docsway' ),
					'parent_item'       => __( 'Parent section', 'aneris-docsway' ),
					'parent_item_colon' => __( 'Parent section:', 'aneris-docsway' ),
					'edit_item'         => __( 'Edit section', 'aneris-docsway' ),
					'update_item'       => __( 'Update section', 'aneris-docsway' ),
					'add_new_item'      => __( 'Add new section', 'aneris-docsway' ),
					'new_item_name'     => __( 'New section name', 'aneris-docsway' ),
					'search_items'      => __( 'Search sections', 'aneris-docsway' ),
					'not_found'         => __( 'No sections yet.', 'aneris-docsway' ),
					'back_to_items'     => __( '← Back to sections', 'aneris-docsway' ),
				),
				'description'       => __( 'Groups pages into the documentation menu.', 'aneris-docsway' ),
				'hierarchical'      => true,
				'public'            => false,
				'show_ui'           => $own,
				'show_in_menu'      => false, // Reached through the Docsway menu instead.
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
		if ( 'existing' !== Docsway_Settings::get( 'taxonomy_mode' ) ) {
			return;
		}
		$tax = Docsway_Settings::taxonomy();
		if ( ! taxonomy_exists( $tax ) ) {
			return;
		}
		foreach ( Docsway_Settings::post_types() as $type ) {
			if ( ! is_object_in_taxonomy( $type, $tax ) ) {
				register_taxonomy_for_object_type( $tax, $type );
			}
		}
	}

	/** Hierarchical taxonomies (other than ours) that could hold a docs structure. */
	public static function candidates() {
		$out = array();
		foreach ( get_taxonomies( array( 'hierarchical' => true, 'show_ui' => true ), 'objects' ) as $tax ) {
			if ( DOCSWAY_OWN_TAXONOMY === $tax->name ) {
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
		return taxonomy_exists( Docsway_Settings::taxonomy() );
	}

	/** Edit screen URL for the sections taxonomy. */
	public static function edit_url() {
		$types = Docsway_Settings::post_types();
		return add_query_arg(
			array(
				'taxonomy'  => Docsway_Settings::taxonomy(),
				'post_type' => $types[0],
			),
			admin_url( 'edit-tags.php' )
		);
	}
}
