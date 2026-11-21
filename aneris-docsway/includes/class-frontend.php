<?php
/**
 * Shortcodes, assets and the automatic page features on doc pages.
 */

defined( 'ABSPATH' ) || exit;

class Docsway_Frontend {

	private static $enqueued = false;

	public static function init() {
		add_shortcode( 'docsway_menu', array( __CLASS__, 'sc_menu' ) );
		add_shortcode( 'docsway_search', array( __CLASS__, 'sc_search' ) );
		add_shortcode( 'docsway_search_results', array( 'Docsway_Search', 'results_shortcode' ) );
		add_shortcode( 'docsway_toc', array( __CLASS__, 'sc_toc' ) );
		add_shortcode( 'docsway_prev_next', array( __CLASS__, 'sc_prev_next' ) );
		add_shortcode( 'docsway_breadcrumbs', array( __CLASS__, 'sc_breadcrumbs' ) );
		add_shortcode( 'docsway_search_home', array( __CLASS__, 'sc_search_home' ) );

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/** The chosen style is a body class, so it reaches every component and the Ctrl+K window. */
	public static function body_class( $classes ) {
		$classes[] = 'docsway-style-' . sanitize_html_class( Docsway_Settings::get( 'style' ) );
		return $classes;
	}

	/** Accent colour and the text colour that sits on it. */
	/** CSS variables from Settings. Every value is checked here, as it is printed. */
	public static function inline_css() {
		$accent = sanitize_hex_color( Docsway_Settings::get( 'accent' ) );
		$accent = $accent ? $accent : '#2563eb';
		$on     = sanitize_hex_color( Docsway_Settings::on_accent() );
		$on     = $on ? $on : '#ffffff';
		$width  = absint( Docsway_Settings::get( 'max_width' ) );
		return ':root{--docsway-accent:' . $accent . ';--docsway-accent-base:' . $accent . ';--docsway-on-accent:' . $on . ';}'
			. '.docsway-layout,.docsway-topbar,.docsway-docs-footer{--docsway-max-width:' . ( $width ? $width : 1280 ) . 'px;}';
	}

	public static function sc_menu( $atts ) {
		$a = shortcode_atts( array( 'title' => '' ), $atts, 'docsway_menu' );
		return Docsway_Render::menu( $a );
	}

	public static function sc_search( $atts ) {
		$a = shortcode_atts( array( 'placeholder' => '', 'button' => 'yes' ), $atts, 'docsway_search' );
		return Docsway_Render::search_box( $a );
	}

	public static function sc_toc( $atts ) {
		$a = shortcode_atts( array( 'title' => '', 'min' => 0 ), $atts, 'docsway_toc' );
		return Docsway_Render::toc( $a );
	}

	public static function sc_prev_next() {
		return Docsway_Render::prev_next();
	}

	public static function sc_breadcrumbs() {
		return Docsway_Render::breadcrumbs();
	}

	public static function sc_search_home( $atts ) {
		$a = shortcode_atts( array( 'title' => '', 'intro' => '', 'topics_title' => '' ), $atts, 'docsway_search_home' );
		return Docsway_Render::search_home( $a );
	}

	/** True on a single doc page or the search results page. */
	public static function is_docs_context() {
		if ( ! is_singular() ) {
			return false;
		}
		$id = (int) get_queried_object_id();
		return Docsway_Tree::is_doc( $id ) || $id === Docsway_Search::results_page_id() || Docsway_Layout::applies();
	}

	public static function register_assets() {
		wp_register_style( 'docsway', DOCSWAY_URL . 'assets/css/frontend.css', array(), docsway_asset_version( 'assets/css/frontend.css' ) );
		wp_add_inline_style( 'docsway', self::inline_css() );

		wp_register_script( 'docsway', DOCSWAY_URL . 'assets/js/frontend.js', array(), docsway_asset_version( 'assets/js/frontend.js' ), true );
		wp_localize_script(
			'docsway',
			'docswayConfig',
			array(
				'api'        => esc_url_raw( rest_url( 'docsway/v1/search' ) ),
				'resultsUrl' => Docsway_Search::results_url(),
				'shortcut'   => (bool) Docsway_Settings::get( 'shortcut' ),
				'copyCode'   => (bool) Docsway_Settings::get( 'copy_code' ),
				'isDoc'      => is_singular() && Docsway_Tree::is_doc( get_queried_object_id() ),
				'placeholder' => Docsway_Settings::placeholder(),
				'quick'      => array_values( (array) Docsway_Settings::get( 'quick_searches' ) ),
				'perPage'    => (int) Docsway_Settings::get( 'per_page' ),
				'feedbackApi' => esc_url_raw( rest_url( 'docsway/v1/feedback' ) ),
				'headingLinks' => (bool) Docsway_Settings::get( 'heading_links' ),
				'i18n'       => array(
					'noResults' => __( 'No results. Try a different word.', 'aneris-docsway' ),
					'searching' => __( 'Searching…', 'aneris-docsway' ),
					'seeAll'    => __( 'See all results', 'aneris-docsway' ),
					'copy'      => __( 'Copy', 'aneris-docsway' ),
					'copied'    => __( 'Copied', 'aneris-docsway' ),
					'close'     => __( 'Close', 'aneris-docsway' ),
					'navHint'   => __( '↑ ↓ to move · Enter to open · Esc to close', 'aneris-docsway' ),
					'results'   => __( 'Search results', 'aneris-docsway' ),
					'popular'   => __( 'Popular searches', 'aneris-docsway' ),
					/* translators: 1: number of results, 2: search query */
					'oneResult' => __( '%1$s result for “%2$s”', 'aneris-docsway' ),
					/* translators: 1: number of results, 2: search query */
					'manyResults' => __( '%1$s results for “%2$s”', 'aneris-docsway' ),
					'noMatch'   => __( 'Nothing matched. Try a shorter or different word.', 'aneris-docsway' ),
					'linkTo'    => __( 'Copy a link to this section', 'aneris-docsway' ),
					'linkCopied' => __( 'Link copied', 'aneris-docsway' ),
					'pages'     => __( 'Search results pages', 'aneris-docsway' ),
					'prev'      => __( '← Previous', 'aneris-docsway' ),
					'next'      => __( 'Next →', 'aneris-docsway' ),
					'bestAnswer' => __( 'Best answer', 'aneris-docsway' ),
					'alsoIn'    => __( 'Also covered in', 'aneris-docsway' ),
					'allResults' => __( 'All results', 'aneris-docsway' ),
					'readSection' => __( 'Read this section', 'aneris-docsway' ),
					'openDoc'   => __( 'Open the document', 'aneris-docsway' ),
					'allTopics' => __( 'All topics', 'aneris-docsway' ),
					'topics'    => __( 'Narrow results by topic', 'aneris-docsway' ),
					/* translators: 1: what the reader typed, 2: the corrected search */
					'corrected' => __( 'Nothing matched “%1$s”, so these are results for %2$s.', 'aneris-docsway' ),
					'partial'   => __( 'No page has all of your words, so these pages match some of them.', 'aneris-docsway' ),
					/* translators: %1$s: corrected search */
					'showingFor' => __( 'Showing results for %1$s', 'aneris-docsway' ),
					/* translators: %1$s: search words */
					'highlighted' => __( 'Highlighting “%1$s” on this page', 'aneris-docsway' ),
					'clear'     => __( 'Clear', 'aneris-docsway' ),
				),
			)
		);

		// Auto light and dark: apply the reader's saved choice in the head, before the page is drawn,
		// so a reader who chose dark never sees a flash of light.
		if ( 'auto' === Docsway_Settings::get( 'style' ) ) {
			wp_register_script( 'docsway-scheme', false, array(), DOCSWAY_VERSION, false );
			wp_enqueue_script( 'docsway-scheme' );
			wp_add_inline_script( 'docsway-scheme', "try{var w=localStorage.getItem('docsway-scheme');if(w==='dark'||w==='light')document.documentElement.classList.add('docsway-scheme-'+w)}catch(e){}" );
		}

		if ( self::is_docs_context() ) {
			self::enqueue();
		}
	}

	public static function enqueue() {
		if ( self::$enqueued ) {
			return;
		}
		if ( ! wp_style_is( 'docsway', 'registered' ) ) {
			return; // Called before assets exist, e.g. in the editor; the block editor loads its own copy.
		}
		self::$enqueued = true;
		wp_enqueue_style( 'docsway' );
		wp_enqueue_script( 'docsway' );
	}

	/** Adds heading anchors and the automatic features to doc pages. */
	public static function filter_content( $content ) {
		if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = (int) get_the_ID();
		if ( $post_id !== (int) get_queried_object_id() || ! Docsway_Tree::is_doc( $post_id ) ) {
			return $content;
		}

		$content = Docsway_Headings::add_ids( $content );
		$before  = '';
		$after   = '';
		// The docs layout places breadcrumbs and previous / next itself, and the contents too when it has a column for them.
		$layout = Docsway_Layout::rendering();

		if ( ! $layout && Docsway_Settings::get( 'crumbs_auto' ) && false === strpos( $content, 'docsway-crumbs' ) ) {
			$before .= Docsway_Render::breadcrumbs( array( 'post_id' => $post_id ) );
		}
		if ( ! $layout ) {
			$before .= Docsway_Render::page_meta( $post_id );
			$after  .= Docsway_Render::feedback( $post_id );
		}
		if ( ( ! $layout || 'content' === Docsway_Settings::get( 'toc_position' ) ) && Docsway_Settings::get( 'toc_auto' ) && false === strpos( $content, 'docsway-toc' ) ) {
			$before .= Docsway_Render::toc( array( 'post_id' => $post_id, 'content' => $content ) );
		}
		if ( ! $layout && Docsway_Settings::get( 'prevnext_auto' ) && false === strpos( $content, 'docsway-pager' ) ) {
			$after .= Docsway_Render::prev_next( array( 'post_id' => $post_id ) );
		}
		return $before . $content . $after;
	}
}
