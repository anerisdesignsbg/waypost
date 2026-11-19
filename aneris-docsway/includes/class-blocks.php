<?php
/**
 * Block editor blocks. Rendering happens on the server through the same
 * methods as the shortcodes, so no build step is needed.
 */

defined( 'ABSPATH' ) || exit;

class Docsway_Blocks {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'docsway-blocks',
			DOCSWAY_URL . 'assets/js/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			docsway_asset_version( 'assets/js/blocks.js' ),
			true
		);
		wp_register_style( 'docsway-editor', DOCSWAY_URL . 'assets/css/frontend.css', array(), docsway_asset_version( 'assets/css/frontend.css' ) );

		$common = array(
			'editor_script' => 'docsway-blocks',
			'editor_style'  => 'docsway-editor',
		);

		register_block_type(
			'docsway/menu',
			$common + array(
				'attributes'      => array( 'title' => array( 'type' => 'string', 'default' => '' ) ),
				'render_callback' => function ( $a ) {
					return Docsway_Render::menu( array( 'title' => $a['title'] ) );
				},
			)
		);
		register_block_type(
			'docsway/search',
			$common + array(
				'attributes'      => array(
					'placeholder' => array( 'type' => 'string', 'default' => '' ),
					'button'      => array( 'type' => 'boolean', 'default' => true ),
				),
				'render_callback' => function ( $a ) {
					return Docsway_Render::search_box( array( 'placeholder' => $a['placeholder'], 'button' => $a['button'] ? 'yes' : 'no' ) );
				},
			)
		);
		register_block_type(
			'docsway/search-home',
			$common + array(
				'attributes'      => array( 'title' => array( 'type' => 'string', 'default' => '' ) ),
				'render_callback' => function ( $a ) {
					return Docsway_Render::search_home( array( 'title' => $a['title'] ) );
				},
			)
		);
		register_block_type(
			'docsway/toc',
			$common + array(
				'attributes'      => array( 'title' => array( 'type' => 'string', 'default' => '' ) ),
				'uses_context'    => array( 'postId' ),
				'render_callback' => function ( $a, $content, $block ) {
					$pid = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : 0;
					$out = Docsway_Render::toc( array( 'title' => $a['title'], 'post_id' => $pid, 'min' => 1 ) );
					return $out ? $out : self::editor_note( __( 'Table of contents: add headings to this page and they will be listed here.', 'aneris-docsway' ) );
				},
			)
		);
		register_block_type(
			'docsway/prev-next',
			$common + array(
				'uses_context'    => array( 'postId' ),
				'render_callback' => function ( $a, $content, $block ) {
					$pid = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : 0;
					$out = Docsway_Render::prev_next( array( 'post_id' => $pid ) );
					return $out ? $out : self::editor_note( __( 'Previous and next links appear here once this page is in a doc section.', 'aneris-docsway' ) );
				},
			)
		);
		register_block_type(
			'docsway/breadcrumbs',
			$common + array(
				'uses_context'    => array( 'postId' ),
				'render_callback' => function ( $a, $content, $block ) {
					$pid = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : 0;
					$out = Docsway_Render::breadcrumbs( array( 'post_id' => $pid ) );
					return $out ? $out : self::editor_note( __( 'Breadcrumbs appear here once this page is in a doc section.', 'aneris-docsway' ) );
				},
			)
		);
	}

	/** Placeholder shown only inside the editor preview. */
	private static function editor_note( $text ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '<p class="docsway-empty">' . esc_html( $text ) . '</p>';
		}
		return '';
	}
}
