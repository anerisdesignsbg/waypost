<?php
/**
 * Shortcodes, assets and the automatic page features on doc pages.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Frontend {

	private static $enqueued = false;

	public static function init() {
		add_shortcode( 'waypost_menu', array( __CLASS__, 'sc_menu' ) );
		add_shortcode( 'waypost_search', array( __CLASS__, 'sc_search' ) );
		add_shortcode( 'waypost_search_results', array( 'Waypost_Search', 'results_shortcode' ) );
		add_shortcode( 'waypost_toc', array( __CLASS__, 'sc_toc' ) );
		add_shortcode( 'waypost_prev_next', array( __CLASS__, 'sc_prev_next' ) );
		add_shortcode( 'waypost_breadcrumbs', array( __CLASS__, 'sc_breadcrumbs' ) );
		add_shortcode( 'waypost_search_home', array( __CLASS__, 'sc_search_home' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_head', array( __CLASS__, 'scheme_script' ), 1 );
	}

	/**
	 * Auto light and dark: apply the reader's saved choice before the page is drawn,
	 * so a reader who chose dark never sees a flash of light.
	 */
	public static function scheme_script() {
		if ( 'auto' !== Waypost_Settings::get( 'style' ) ) {
			return;
		}
		echo "<script>try{var w=localStorage.getItem('waypost-scheme');if(w==='dark'||w==='light')document.documentElement.classList.add('waypost-scheme-'+w)}catch(e){}</script>\n";
	}

	/** The chosen style is a body class, so it reaches every component and the Ctrl+K window. */
	public static function body_class( $classes ) {
		$classes[] = 'waypost-style-' . sanitize_html_class( Waypost_Settings::get( 'style' ) );
		return $classes;
	}

	/** Accent colour and the text colour that sits on it. */
	public static function inline_css() {
		$accent = Waypost_Settings::get( 'accent' );
		return ':root{--waypost-accent:' . $accent . ';--waypost-accent-base:' . $accent . ';--waypost-on-accent:' . Waypost_Settings::on_accent() . ';}';
	}

	public static function sc_menu( $atts ) {
		$a = shortcode_atts( array( 'title' => '' ), $atts, 'waypost_menu' );
		return Waypost_Render::menu( $a );
	}

	public static function sc_search( $atts ) {
		$a = shortcode_atts( array( 'placeholder' => '', 'button' => 'yes' ), $atts, 'waypost_search' );
		return Waypost_Render::search_box( $a );
	}

	public static function sc_toc( $atts ) {
		$a = shortcode_atts( array( 'title' => '', 'min' => 0 ), $atts, 'waypost_toc' );
		return Waypost_Render::toc( $a );
	}

	public static function sc_prev_next() {
		return Waypost_Render::prev_next();
	}

	public static function sc_breadcrumbs() {
		return Waypost_Render::breadcrumbs();
	}

	public static function sc_search_home( $atts ) {
		$a = shortcode_atts( array( 'title' => '', 'intro' => '', 'topics_title' => '' ), $atts, 'waypost_search_home' );
		return Waypost_Render::search_home( $a );
	}

	/** True on a single doc page or the search results page. */
	public static function is_docs_context() {
		if ( ! is_singular() ) {
			return false;
		}
		$id = (int) get_queried_object_id();
		return Waypost_Tree::is_doc( $id ) || $id === Waypost_Search::results_page_id() || Waypost_Layout::applies();
	}

	public static function register_assets() {
		wp_register_style( 'waypost', WAYPOST_URL . 'assets/css/frontend.css', array(), waypost_asset_version( 'assets/css/frontend.css' ) );
		wp_add_inline_style( 'waypost', self::inline_css() );

		wp_register_script( 'waypost', WAYPOST_URL . 'assets/js/frontend.js', array(), waypost_asset_version( 'assets/js/frontend.js' ), true );
		wp_localize_script(
			'waypost',
			'waypostConfig',
			array(
				'api'        => esc_url_raw( rest_url( 'waypost/v1/search' ) ),
				'resultsUrl' => Waypost_Search::results_url(),
				'shortcut'   => (bool) Waypost_Settings::get( 'shortcut' ),
				'copyCode'   => (bool) Waypost_Settings::get( 'copy_code' ),
				'isDoc'      => is_singular() && Waypost_Tree::is_doc( get_queried_object_id() ),
				'placeholder' => Waypost_Settings::placeholder(),
				'quick'      => array_values( (array) Waypost_Settings::get( 'quick_searches' ) ),
				'perPage'    => (int) Waypost_Settings::get( 'per_page' ),
				'feedbackApi' => esc_url_raw( rest_url( 'waypost/v1/feedback' ) ),
				'headingLinks' => (bool) Waypost_Settings::get( 'heading_links' ),
				'i18n'       => array(
					'noResults' => __( 'No results. Try a different word.', 'waypost' ),
					'searching' => __( 'Searching…', 'waypost' ),
					'seeAll'    => __( 'See all results', 'waypost' ),
					'copy'      => __( 'Copy', 'waypost' ),
					'copied'    => __( 'Copied', 'waypost' ),
					'close'     => __( 'Close', 'waypost' ),
					'navHint'   => __( '↑ ↓ to move · Enter to open · Esc to close', 'waypost' ),
					'results'   => __( 'Search results', 'waypost' ),
					'popular'   => __( 'Popular searches', 'waypost' ),
					/* translators: 1: number of results, 2: search query */
					'oneResult' => __( '%1$s result for “%2$s”', 'waypost' ),
					/* translators: 1: number of results, 2: search query */
					'manyResults' => __( '%1$s results for “%2$s”', 'waypost' ),
					'noMatch'   => __( 'Nothing matched. Try a shorter or different word.', 'waypost' ),
					'linkTo'    => __( 'Copy a link to this section', 'waypost' ),
					'linkCopied' => __( 'Link copied', 'waypost' ),
					'pages'     => __( 'Search results pages', 'waypost' ),
					'prev'      => __( '← Previous', 'waypost' ),
					'next'      => __( 'Next →', 'waypost' ),
				),
			)
		);

		if ( self::is_docs_context() ) {
			self::enqueue();
		}
	}

	public static function enqueue() {
		if ( self::$enqueued ) {
			return;
		}
		if ( ! wp_style_is( 'waypost', 'registered' ) ) {
			return; // Called before assets exist, e.g. in the editor; the block editor loads its own copy.
		}
		self::$enqueued = true;
		wp_enqueue_style( 'waypost' );
		wp_enqueue_script( 'waypost' );
	}

	/** Adds heading anchors and the automatic features to doc pages. */
	public static function filter_content( $content ) {
		if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = (int) get_the_ID();
		if ( $post_id !== (int) get_queried_object_id() || ! Waypost_Tree::is_doc( $post_id ) ) {
			return $content;
		}

		$content = Waypost_Headings::add_ids( $content );
		$before  = '';
		$after   = '';
		// The docs layout places breadcrumbs and previous / next itself, and the contents too when it has a column for them.
		$layout = Waypost_Layout::rendering();

		if ( ! $layout && Waypost_Settings::get( 'crumbs_auto' ) && false === strpos( $content, 'waypost-crumbs' ) ) {
			$before .= Waypost_Render::breadcrumbs( array( 'post_id' => $post_id ) );
		}
		if ( ! $layout ) {
			$before .= Waypost_Render::page_meta( $post_id );
			$after  .= Waypost_Render::feedback( $post_id );
		}
		if ( ( ! $layout || 'content' === Waypost_Settings::get( 'toc_position' ) ) && Waypost_Settings::get( 'toc_auto' ) && false === strpos( $content, 'waypost-toc' ) ) {
			$before .= Waypost_Render::toc( array( 'post_id' => $post_id, 'content' => $content ) );
		}
		if ( ! $layout && Waypost_Settings::get( 'prevnext_auto' ) && false === strpos( $content, 'waypost-pager' ) ) {
			$after .= Waypost_Render::prev_next( array( 'post_id' => $post_id ) );
		}
		return $before . $content . $after;
	}
}
