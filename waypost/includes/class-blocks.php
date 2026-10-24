<?php
/**
 * Block editor blocks. Rendering happens on the server through the same
 * methods as the shortcodes, so no build step is needed.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Blocks {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	public static function register() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		wp_register_script(
			'waypost-blocks',
			WAYPOST_URL . 'assets/js/blocks.js',
			array( 'wp-blocks', 'wp-element', 'wp-server-side-render', 'wp-block-editor', 'wp-components', 'wp-i18n' ),
			WAYPOST_VERSION,
			true
		);
		wp_register_style( 'waypost-editor', WAYPOST_URL . 'assets/css/frontend.css', array(), WAYPOST_VERSION );

		$common = array(
			'editor_script' => 'waypost-blocks',
			'editor_style'  => 'waypost-editor',
		);

		register_block_type(
			'waypost/menu',
			$common + array(
				'attributes'      => array( 'title' => array( 'type' => 'string', 'default' => '' ) ),
				'render_callback' => function ( $a ) {
					return Waypost_Render::menu( array( 'title' => $a['title'] ) );
				},
			)
		);
		register_block_type(
			'waypost/search',
			$common + array(
				'attributes'      => array(
					'placeholder' => array( 'type' => 'string', 'default' => '' ),
					'button'      => array( 'type' => 'boolean', 'default' => true ),
				),
				'render_callback' => function ( $a ) {
					return Waypost_Render::search_box( array( 'placeholder' => $a['placeholder'], 'button' => $a['button'] ? 'yes' : 'no' ) );
				},
			)
		);
		register_block_type(
			'waypost/search-home',
			$common + array(
				'attributes'      => array( 'title' => array( 'type' => 'string', 'default' => '' ) ),
				'render_callback' => function ( $a ) {
					return Waypost_Render::search_home( array( 'title' => $a['title'] ) );
				},
			)
		);
		register_block_type(
			'waypost/toc',
			$common + array(
				'attributes'      => array( 'title' => array( 'type' => 'string', 'default' => '' ) ),
				'uses_context'    => array( 'postId' ),
				'render_callback' => function ( $a, $content, $block ) {
					$pid = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : 0;
					$out = Waypost_Render::toc( array( 'title' => $a['title'], 'post_id' => $pid, 'min' => 1 ) );
					return $out ? $out : self::editor_note( __( 'Table of contents: add headings to this page and they will be listed here.', 'waypost' ) );
				},
			)
		);
		register_block_type(
			'waypost/prev-next',
			$common + array(
				'uses_context'    => array( 'postId' ),
				'render_callback' => function ( $a, $content, $block ) {
					$pid = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : 0;
					$out = Waypost_Render::prev_next( array( 'post_id' => $pid ) );
					return $out ? $out : self::editor_note( __( 'Previous and next links appear here once this page is in a doc section.', 'waypost' ) );
				},
			)
		);
		register_block_type(
			'waypost/breadcrumbs',
			$common + array(
				'uses_context'    => array( 'postId' ),
				'render_callback' => function ( $a, $content, $block ) {
					$pid = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : 0;
					$out = Waypost_Render::breadcrumbs( array( 'post_id' => $pid ) );
					return $out ? $out : self::editor_note( __( 'Breadcrumbs appear here once this page is in a doc section.', 'waypost' ) );
				},
			)
		);
	}

	/** Placeholder shown only inside the editor preview. */
	private static function editor_note( $text ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '<p class="waypost-empty">' . esc_html( $text ) . '</p>';
		}
		return '';
	}
}
