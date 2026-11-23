<?php
/**
 * The product site: sets up a fresh WordPress (home page, title, icon, sample
 * content removed), shows the landing page on the front page with its own
 * template, and sends every other front-end address to it.
 */

defined( 'ABSPATH' ) || exit;

class Docsway_Site {

	const HOME_META = '_docsway_site_home';

	/** Links and offers used on the page. */
	public static function config() {
		$checkout = 'https://checkout.freemius.com/plugin/44694/plan/78781/';
		return apply_filters(
			'docsway_site_config',
			array(
				'wporg'      => 'https://wordpress.org/plugins/aneris-docsway/',
				'demo'       => 'https://anerisdesigns.co/docsway-demo/',
				'company'    => 'https://anerisdesigns.co/',
				'checkout'   => $checkout,
				'coupon'     => 'LAUNCH30',
				'coupon_off' => 30,
				// The coupon runs until the start of 24 January 2027 (GMT); the offer hides itself after that.
				'coupon_end' => '2027-01-24 00:00:00',
				'coupon_day' => '23 January 2027',
				'plans'      => array(
					array( 'sites' => 1, 'annual' => 49, 'lifetime' => 149 ),
					array( 'sites' => 5, 'annual' => 99, 'lifetime' => 299, 'popular' => true ),
					array( 'sites' => 25, 'annual' => 199, 'lifetime' => 599 ),
				),
			)
		);
	}

	public static function checkout_url( $cycle, $sites ) {
		return add_query_arg( array( 'billing_cycle' => $cycle, 'licenses' => (int) $sites ), self::config()['checkout'] );
	}

	public static function offer_running() {
		return time() < strtotime( self::config()['coupon_end'] . ' UTC' );
	}

	public static function img( $file ) {
		return DOCSWAY_SITE_URL . 'assets/img/' . $file;
	}

	public static function init() {
		add_filter( 'template_include', array( __CLASS__, 'template' ), 100 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect' ), 1 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 100 );
		add_action( 'wp_head', array( __CLASS__, 'meta' ), 1 );
		add_filter( 'pre_get_document_title', array( __CLASS__, 'title' ) );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_docsway_site_setup', array( __CLASS__, 'handle_setup' ) );
	}

	public static function home_id() {
		$ids = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'meta_key' => self::HOME_META, 'fields' => 'ids', 'posts_per_page' => 1 ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		return $ids ? (int) $ids[0] : 0;
	}

	private static function is_landing() {
		$home = self::home_id();
		return $home && is_front_page() && (int) get_queried_object_id() === $home;
	}

	/** Builds the site. Safe to run again: it reuses the home page and only adds what is missing. */
	public static function setup() {
		$home = self::home_id();
		if ( ! $home ) {
			$home = wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => 'Aneris Docsway',
					'post_name'    => 'home',
					'post_content' => '<p>Turn the pages you already have into a documentation site with smart search. This page is drawn by the Aneris Docsway Site plugin.</p>',
				)
			);
			if ( is_wp_error( $home ) || ! $home ) {
				return;
			}
			update_post_meta( $home, self::HOME_META, 1 );
		}
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $home );
		update_option( 'blogname', 'Aneris Docsway' );
		update_option( 'blogdescription', 'Documentation site and smart search for WordPress' );
		if ( '' === (string) get_option( 'permalink_structure' ) ) {
			update_option( 'permalink_structure', '/%postname%/' );
		}
		if ( ! get_option( 'docsway_site_built' ) ) {
			foreach ( array( array( 'hello-world', 'post' ), array( 'sample-page', 'page' ) ) as $sample ) {
				$p = get_page_by_path( $sample[0], OBJECT, $sample[1] );
				if ( $p ) {
					wp_trash_post( $p->ID );
				}
			}
		}
		if ( ! get_option( 'site_icon' ) ) {
			$icon = self::upload_icon();
			if ( $icon ) {
				update_option( 'site_icon', $icon );
			}
		}
		update_option( 'docsway_site_built', time(), false );
		flush_rewrite_rules();
	}

	private static function upload_icon() {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( 'docsway-icon.png' );
		if ( ! $tmp || ! copy( DOCSWAY_SITE_DIR . 'assets/img/icon.png', $tmp ) ) {
			return 0;
		}
		$id = media_handle_sideload( array( 'name' => 'docsway-icon.png', 'tmp_name' => $tmp ), 0, 'Aneris Docsway' );
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return 0;
		}
		return (int) $id;
	}

	public static function template( $template ) {
		return self::is_landing() ? DOCSWAY_SITE_DIR . 'templates/landing.php' : $template;
	}

	/** Every other front-end address (old links, sample content, archives, feeds, search) goes to the landing page. */
	public static function redirect() {
		if ( self::is_landing() || ! self::home_id() || is_robots() || is_favicon() || is_preview() || is_customize_preview() || get_query_var( 'sitemap' ) ) {
			return;
		}
		wp_safe_redirect( home_url( '/' ), 302, 'Aneris Docsway' );
		exit;
	}

	/** The landing page uses only its own styles, so the theme cannot change it. */
	public static function assets() {
		if ( ! self::is_landing() ) {
			return;
		}
		$keep  = array( 'admin-bar', 'dashicons' );
		$theme = array( get_template_directory_uri(), get_stylesheet_directory_uri() );
		foreach ( wp_styles()->queue as $handle ) {
			$src = isset( wp_styles()->registered[ $handle ] ) ? (string) wp_styles()->registered[ $handle ]->src : '';
			$own = false;
			foreach ( $theme as $base ) {
				$own = $own || ( '' !== $src && 0 === strpos( $src, $base ) );
			}
			if ( ! in_array( $handle, $keep, true ) && ( $own || in_array( $handle, array( 'global-styles', 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'core-block-supports' ), true ) ) ) {
				wp_dequeue_style( $handle );
			}
		}
		wp_enqueue_style( 'docsway-site', DOCSWAY_SITE_URL . 'assets/site.css', array(), DOCSWAY_SITE_VERSION );
		wp_enqueue_script( 'docsway-site', DOCSWAY_SITE_URL . 'assets/site.js', array(), DOCSWAY_SITE_VERSION, true );
	}

	public static function title( $title ) {
		return self::is_landing() ? 'Aneris Docsway: documentation site and smart search for WordPress' : $title;
	}

	public static function meta() {
		if ( ! self::is_landing() ) {
			return;
		}
		$desc = 'Turn the pages you already have into a docs site with a sidebar menu and smart search that answers questions. Free on WordPress.org. No migration, no URL changes.';
		printf( '<meta name="description" content="%s">' . "\n", esc_attr( $desc ) );
		printf( '<meta property="og:type" content="website">' . "\n" );
		printf( '<meta property="og:title" content="%s">' . "\n", esc_attr( 'Aneris Docsway: docs your readers can actually search' ) );
		printf( '<meta property="og:description" content="%s">' . "\n", esc_attr( $desc ) );
		printf( '<meta property="og:url" content="%s">' . "\n", esc_url( home_url( '/' ) ) );
		printf( '<meta property="og:image" content="%s">' . "\n", esc_url( self::img( 'social-share.jpg' ) ) );
		printf( '<meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">' . "\n" );
		printf( '<meta name="twitter:card" content="summary_large_image">' . "\n" );
		printf( '<meta name="theme-color" content="#312e81">' . "\n" );
	}

	public static function menu() {
		add_management_page( 'Docsway site', 'Docsway site', 'manage_options', 'docsway-site', array( __CLASS__, 'page' ) );
	}

	public static function page() {
		?>
		<div class="wrap">
			<h1>Aneris Docsway site</h1>
			<?php if ( isset( $_GET['done'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification -- display only. ?>
				<div class="notice notice-success"><p>The site is set up. <a href="<?php echo esc_url( home_url( '/' ) ); ?>">View it</a></p></div>
			<?php endif; ?>
			<p>This plugin draws the landing page on the front page and sends every other address on this site to it. Prices, links and the launch offer are set in the plugin.</p>
			<p>Setting up again makes sure the home page exists and is the front page, and resets the site title. Nothing else is changed.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="docsway_site_setup">
				<?php wp_nonce_field( 'docsway_site_setup' ); ?>
				<p><button type="submit" class="button button-primary">Set up again</button> <a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>">View the site</a></p>
			</form>
		</div>
		<?php
	}

	public static function handle_setup() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'aneris-docsway-site' ) );
		}
		check_admin_referer( 'docsway_site_setup' );
		self::setup();
		wp_safe_redirect( admin_url( 'tools.php?page=docsway-site&done=1' ) );
		exit;
	}
}
