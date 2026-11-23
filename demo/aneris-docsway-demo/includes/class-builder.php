<?php
/**
 * Builds the Docsway tutorials docs site from content/tutorials/*.md and
 * content/images/*.png. Safe to run again: everything it made last time
 * (marked with _docsway_demo) is removed first, so pages are rebuilt from
 * the current tutorials.
 */

defined( 'ABSPATH' ) || exit;

class Docsway_Demo_Builder {

	const MARK = '_docsway_demo';

	/** Where the docs bar and footer link back to. */
	const BACK_URL   = 'https://anerisdesigns.co/aneris-docsway/';
	const BACK_LABEL = 'About Aneris Docsway';

	/** Menu sections and the tutorials in each, in reading order. */
	private static function sections() {
		return array(
			'Getting started'       => array( '01-getting-started' ),
			'Organising your docs'  => array( '02-sections-and-pages', '03-menu-order' ),
			'Look and feel'         => array( '04-layout-and-styles', '05-search-home' ),
			'Search'                => array( '06-smart-search', '07-documents' ),
			'Insights and feedback' => array( '08-insights-and-feedback' ),
			'Divi'                  => array( '09-divi' ),
			'Reference'             => array( '10-blocks-and-shortcodes', '11-troubleshooting' ),
		);
	}

	private static $images = array();

	/** @return array|WP_Error counts of what was made */
	public static function build() {
		if ( ! class_exists( 'Docsway_Settings' ) ) {
			return new WP_Error( 'docsway_missing', __( 'Activate the Docsway plugin first.', 'aneris-docsway-demo' ) );
		}
		$dir = DOCSWAY_DEMO_DIR . 'content/';
		if ( ! is_dir( $dir . 'tutorials' ) ) {
			return new WP_Error( 'content_missing', __( 'The tutorials are missing from this plugin (content/tutorials).', 'aneris-docsway-demo' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$first_run = ! get_option( 'docsway_demo_built' );
		self::clean_up();
		self::site_settings( $first_run );

		// Docsway's own sections, holding pages.
		Docsway_Settings::update( array( 'taxonomy_mode' => 'own', 'post_types' => array( 'page' ) ) );
		$tax = Docsway_Settings::taxonomy();

		$md   = new Docsway_Demo_Markdown( array( __CLASS__, 'image' ), array( __CLASS__, 'link' ) );
		$ids  = array();
		$terms = array();
		$order = array();
		$pos   = 0;
		foreach ( self::sections() as $name => $files ) {
			$term = wp_insert_term( $name, $tax );
			if ( is_wp_error( $term ) ) {
				return $term;
			}
			$tid = (int) $term['term_id'];
			update_term_meta( $tid, self::MARK, 1 );
			$terms[ $name ]           = $tid;
			$order[ 'term_' . $tid ] = $pos++;

			foreach ( $files as $i => $file ) {
				$path = $dir . 'tutorials/' . $file . '.md';
				if ( ! is_readable( $path ) ) {
					continue;
				}
				$doc = $md->convert( file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				$id  = wp_insert_post(
					array(
						'post_type'    => 'page',
						'post_status'  => 'publish',
						'post_title'   => $doc['title'],
						'post_content' => $doc['html'],
						'menu_order'   => $i,
					),
					true
				);
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				update_post_meta( $id, self::MARK, 1 );
				wp_set_object_terms( $id, array( $tid ), $tax );
				$ids[ $file ] = $id;
				$order[ 'child_' . $tid . '_post' . $id ] = $i;
			}
		}

		// Links between tutorials point at the pages they became.
		foreach ( $ids as $id ) {
			$content = get_post_field( 'post_content', $id );
			$fixed   = preg_replace_callback(
				'/#docsway-doc-([a-z0-9-]+)/',
				function ( $m ) use ( $ids ) {
					return isset( $ids[ $m[1] ] ) ? get_permalink( $ids[ $m[1] ] ) : '#';
				},
				$content
			);
			if ( $fixed !== $content ) {
				wp_update_post( array( 'ID' => $id, 'post_content' => $fixed ) );
			}
		}
		update_option( Docsway_Tree::ORDER_OPTION, $order, false );

		// The docs home is the site's front page.
		$home = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Docsway documentation',
				'post_content' => '<!-- wp:docsway/search-home /-->',
			)
		);
		update_post_meta( $home, self::MARK, 1 );
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home );

		$page = function ( $file ) use ( $ids ) {
			return isset( $ids[ $file ] ) ? $ids[ $file ] : 0;
		};
		Docsway_Settings::update(
			array(
				'setup_done'     => 1,
				'home_page'      => $home,
				'results_target' => 'home',
				'layout'         => 'docsway',
				'chrome'         => 'docsway',
				'sidebar'        => 'left',
				'width'          => 'contained',
				'max_width'      => 1280,
				'toc_position'   => 'side',
				'style'          => 'modern',
				'accent'         => '#4f46e5',
				'toc_auto'       => 1,
				'prevnext_auto'  => 1,
				'crumbs_auto'    => 1,
				'show_updated'   => 1,
				'show_reading'   => 1,
				'heading_links'  => 1,
				'feedback'       => 1,
				'best_answer'    => 1,
				'back_url'       => self::BACK_URL,
				'back_label'     => self::BACK_LABEL,
				'home_title'     => 'Docsway documentation',
				'home_intro'     => 'Step-by-step guides for setting up and running your docs with Docsway.',
				'topics_title'   => 'Start here',
				'quick_searches' => array(
					array( 'label' => 'How do I set up Docsway?', 'query' => 'setup wizard' ),
					array( 'label' => 'Order the menu', 'query' => 'menu order' ),
					array( 'label' => 'Change the style', 'query' => 'style' ),
					array( 'label' => 'Shortcodes', 'query' => 'shortcode' ),
					array( 'label' => 'Search inside PDFs', 'query' => 'pdf' ),
					array( 'label' => 'Divi', 'query' => 'divi' ),
				),
				'topics'         => array(
					array( 'icon' => 'rocket', 'title' => 'Get started', 'text' => 'Set up your docs in five minutes', 'page' => $page( '01-getting-started' ) ),
					array( 'icon' => 'book', 'title' => 'Sections and pages', 'text' => 'Organise your docs and order the menu', 'page' => $page( '02-sections-and-pages' ) ),
					array( 'icon' => 'layers', 'title' => 'Layout and styles', 'text' => 'Choose how your docs look', 'page' => $page( '04-layout-and-styles' ) ),
					array( 'icon' => 'chart', 'title' => 'Smart search', 'text' => 'How search answers your readers', 'page' => $page( '06-smart-search' ) ),
					array( 'icon' => 'plug', 'title' => 'Divi', 'text' => 'Use Docsway with Divi 4 and Divi 5', 'page' => $page( '09-divi' ) ),
					array( 'icon' => 'help', 'title' => 'Troubleshooting', 'text' => 'Fixes for common questions', 'page' => $page( '11-troubleshooting' ) ),
				),
			)
		);
		Docsway_Tree::flush();
		update_option( 'docsway_demo_built', time(), false );

		return array(
			'sections' => count( $terms ),
			'pages'    => count( $ids ),
			'images'   => count( self::$images ),
			'home'     => get_permalink( $home ),
		);
	}

	/** Removes everything the last build made: pages, images and sections. */
	private static function clean_up() {
		$old = get_posts(
			array(
				'post_type'      => array( 'page', 'attachment' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// Pages from before the rename to Aneris Docsway carry the old mark.
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'OR',
					array( 'key' => self::MARK ),
					array( 'key' => '_waypost_demo' ),
				),
			)
		);
		foreach ( $old as $id ) {
			if ( 'attachment' === get_post_type( $id ) ) {
				wp_delete_attachment( $id, true );
			} else {
				wp_delete_post( $id, true );
			}
		}
		$terms = get_terms(
			array(
				'taxonomy'   => Docsway_Settings::taxonomy(),
				'hide_empty' => false,
				'fields'     => 'ids',
				'meta_key'   => self::MARK, // phpcs:ignore WordPress.DB.SlowDBQuery
			)
		);
		foreach ( is_wp_error( $terms ) ? array() : $terms as $tid ) {
			wp_delete_term( $tid, Docsway_Settings::taxonomy() );
		}
		self::$images = array();
	}

	/** Site title, address style and icon; on the first run also removes WordPress's sample content. */
	private static function site_settings( $first_run ) {
		update_option( 'blogname', 'Aneris Docsway' );
		update_option( 'blogdescription', 'Documentation and tutorials for the Docsway WordPress plugin' );
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			update_option( 'permalink_structure', '/%postname%/' );
		}
		flush_rewrite_rules( false );

		$icon = self::upload( 'docsway-icon.png', 'Docsway' );
		if ( $icon ) {
			update_option( 'site_icon', $icon );
		}

		if ( $first_run ) {
			foreach ( array( array( 'hello-world', 'post' ), array( 'sample-page', 'page' ) ) as $sample ) {
				$p = get_page_by_path( $sample[0], OBJECT, $sample[1] );
				if ( $p ) {
					wp_trash_post( $p->ID );
				}
			}
		}
	}

	/** Image callback for the markdown: uploads a screenshot once and returns a figure. */
	public static function image( $src, $alt ) {
		$id = self::upload( basename( $src ), $alt );
		if ( ! $id ) {
			return '';
		}
		$full = wp_get_attachment_url( $id );
		return '<figure class="wp-block-image size-large"><a href="' . esc_url( $full ) . '">'
			. wp_get_attachment_image( $id, 'large', false, array( 'alt' => $alt ) )
			. '</a><figcaption>' . esc_html( $alt ) . '</figcaption></figure>';
	}

	/** Link callback: links to other tutorials become placeholders, fixed once every page exists. */
	public static function link( $url ) {
		if ( preg_match( '/^([a-z0-9-]+)\.md$/', $url, $m ) ) {
			return '#docsway-doc-' . $m[1];
		}
		return $url;
	}

	/** Uploads content/images/$file into the Media Library (once per build). */
	private static function upload( $file, $title ) {
		if ( isset( self::$images[ $file ] ) ) {
			return self::$images[ $file ];
		}
		$path = DOCSWAY_DEMO_DIR . 'content/images/' . $file;
		if ( ! is_readable( $path ) ) {
			return 0;
		}
		$tmp = wp_tempnam( $file );
		copy( $path, $tmp );
		$id = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), 0, $title );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return 0;
		}
		update_post_meta( $id, self::MARK, 1 );
		self::$images[ $file ] = $id;
		return $id;
	}
}
