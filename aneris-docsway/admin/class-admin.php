<?php
/**
 * Admin screens: Dashboard, Structure, Settings, Search insights, Help.
 */

defined( 'ABSPATH' ) || exit;

class Docsway_Admin {

	const CAP = 'manage_options';

	public static $hooks = array();

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'parent_file', array( __CLASS__, 'highlight_sections_menu' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( DOCSWAY_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'admin_notices', array( __CLASS__, 'setup_notice' ) );

		add_action( 'admin_post_docsway_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_docsway_clear_log', array( __CLASS__, 'clear_log' ) );
		add_action( 'admin_post_docsway_reset_feedback', array( __CLASS__, 'reset_feedback' ) );

		foreach ( array( 'save_order', 'reset_order', 'flush', 'save_link', 'assign', 'add_section' ) as $a ) {
			add_action( 'wp_ajax_docsway_' . $a, array( __CLASS__, 'ajax_' . $a ) );
		}
	}

	// ── Menu ────────────────────────────────────────────────────────────────

	public static function menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M9 1h2v3h5l2.5 2.5L16 9h-5v2h4l1.5 1.5L15 14h-4v5H9v-5H4l-1.5-1.5L4 11h5V9H4V4h5V1Z"/></svg>' );

		self::$hooks['dashboard'] = add_menu_page( __( 'Docsway', 'aneris-docsway' ), __( 'Docsway', 'aneris-docsway' ), self::CAP, 'docsway', array( __CLASS__, 'page_dashboard' ), $icon, 58 );
		add_submenu_page( 'docsway', __( 'Docsway dashboard', 'aneris-docsway' ), __( 'Dashboard', 'aneris-docsway' ), self::CAP, 'docsway', array( __CLASS__, 'page_dashboard' ) );
		self::$hooks['structure'] = add_submenu_page( 'docsway', __( 'Docs structure', 'aneris-docsway' ), __( 'Structure', 'aneris-docsway' ), self::CAP, 'docsway-structure', array( __CLASS__, 'page_structure' ) );
		if ( Docsway_Taxonomy::is_ready() ) {
			add_submenu_page( 'docsway', __( 'Doc sections', 'aneris-docsway' ), __( 'Sections', 'aneris-docsway' ), self::CAP, Docsway_Taxonomy::edit_url() );
		}
		self::$hooks['settings'] = add_submenu_page( 'docsway', __( 'Docsway settings', 'aneris-docsway' ), __( 'Settings', 'aneris-docsway' ), self::CAP, 'docsway-settings', array( __CLASS__, 'page_settings' ) );
		self::$hooks['insights'] = add_submenu_page( 'docsway', __( 'Search insights', 'aneris-docsway' ), __( 'Search insights', 'aneris-docsway' ), self::CAP, 'docsway-insights', array( __CLASS__, 'page_insights' ) );
		self::$hooks['help']     = add_submenu_page( 'docsway', __( 'Help and tutorials', 'aneris-docsway' ), __( 'Help', 'aneris-docsway' ), self::CAP, 'docsway-help', array( 'Docsway_Help', 'page' ) );

		foreach ( self::$hooks as $key => $hook ) {
			add_action( 'load-' . $hook, function () use ( $key ) {
				Docsway_Help::add_tabs( $key );
			} );
		}
	}

	public static function highlight_sections_menu( $parent ) {
		global $current_screen, $submenu_file;
		if ( $current_screen && 'edit-tags' === $current_screen->base && $current_screen->taxonomy === Docsway_Settings::taxonomy() ) {
			$submenu_file = Docsway_Taxonomy::edit_url(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			return 'docsway';
		}
		return $parent;
	}

	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=docsway' ) ) . '">' . esc_html__( 'Dashboard', 'aneris-docsway' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=docsway-help' ) ) . '">' . esc_html__( 'Help', 'aneris-docsway' ) . '</a>'
		);
		return $links;
	}

	public static function setup_notice() {
		if ( Docsway_Settings::get( 'setup_done' ) || ! current_user_can( self::CAP ) ) {
			return;
		}
		$screen = get_current_screen();
		$is_wizard = isset( $_GET['page'] ) && Docsway_Wizard::SLUG === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $screen || $is_wizard || ( 'plugins' !== $screen->id && ! in_array( $screen->id, self::$hooks, true ) ) ) {
			return;
		}
		echo '<div class="notice notice-info docsway-notice"><p><strong>' . esc_html__( 'Docsway is installed.', 'aneris-docsway' ) . '</strong> '
			. esc_html__( 'The setup wizard takes about three minutes and gets your docs menu and search working.', 'aneris-docsway' )
			. '</p><p><a class="button button-primary" href="' . esc_url( Docsway_Wizard::url() ) . '">' . esc_html__( 'Start setup', 'aneris-docsway' ) . '</a></p></div>';
	}

	public static function assets( $hook ) {
		$ours = in_array( $hook, self::$hooks, true ) || false !== strpos( $hook, 'docsway' );
		if ( ! $ours ) {
			return;
		}
		// The front-end styles power the style preview.
		wp_enqueue_style( 'docsway-preview', DOCSWAY_URL . 'assets/css/frontend.css', array(), docsway_asset_version( 'assets/css/frontend.css' ) );
		wp_add_inline_style( 'docsway-preview', Docsway_Frontend::inline_css() );
		wp_enqueue_style( 'docsway-admin', DOCSWAY_URL . 'assets/css/admin.css', array( 'docsway-preview' ), docsway_asset_version( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'docsway-admin', DOCSWAY_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), docsway_asset_version( 'assets/js/admin.js' ), true );
		wp_localize_script(
			'docsway-admin',
			'docswayAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'docsway_admin' ),
				'i18n'    => array(
					'saving'     => __( 'Saving…', 'aneris-docsway' ),
					'saved'      => __( 'Saved', 'aneris-docsway' ),
					'failed'     => __( 'Something went wrong. Reload the page and try again.', 'aneris-docsway' ),
					'confirmReset' => __( 'Click again to confirm. This puts every section back in alphabetical order.', 'aneris-docsway' ),
					'copied'     => __( 'Copied', 'aneris-docsway' ),
					'pickPages'  => __( 'Tick at least one page first.', 'aneris-docsway' ),
					'pickSection' => __( 'Choose a section first.', 'aneris-docsway' ),
					'nameNeeded' => __( 'Give the section a name.', 'aneris-docsway' ),
					'unsaved'    => __( 'You have unsaved order changes.', 'aneris-docsway' ),
				),
			)
		);
	}

	// ── Shared bits ─────────────────────────────────────────────────────────

	public static function header( $title, $sub = '' ) {
		echo '<div class="wrap docsway-wrap">';
		echo '<header class="dsw-head"><div><h1 class="dsw-head__title">' . esc_html( $title ) . '</h1>';
		if ( $sub ) {
			echo '<p class="dsw-head__sub">' . esc_html( $sub ) . '</p>';
		}
		echo '</div><a class="dsw-head__help" href="' . esc_url( admin_url( 'admin.php?page=docsway-help' ) ) . '"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span>' . esc_html__( 'Help and tutorials', 'aneris-docsway' ) . '</a></header>';
		echo '<hr class="wp-header-end">';
	}

	public static function footer() {
		echo '</div>';
	}

	/** Small "how does this work?" link pointing into the Help page. */
	public static function help_link( $topic, $label = '' ) {
		return '<a class="dsw-helplink" href="' . esc_url( admin_url( 'admin.php?page=docsway-help#' . $topic ) ) . '">'
			. '<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>'
			. esc_html( $label ? $label : __( 'How this works', 'aneris-docsway' ) ) . '</a>';
	}

	/** Layout choice, sidebar side, width and contents position, with a live diagram. Used by Settings and the setup wizard. */
	public static function layout_picker( array $o ) {
		$radio = function ( $name, $value, $label ) use ( $o ) {
			return '<label class="dsw-seg"><input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" ' . checked( $o[ $name ], $value, false ) . '><span>' . esc_html( $label ) . '</span></label>';
		};
		?>
		<div class="dsw-layout" data-dsw-layout>
			<fieldset class="dsw-layout__choice">
				<legend class="screen-reader-text"><?php esc_html_e( 'Page layout', 'aneris-docsway' ); ?></legend>
				<label class="dsw-style">
					<input type="radio" name="layout" value="docsway" <?php checked( $o['layout'], 'docsway' ); ?>>
					<span class="dsw-style__text"><strong><?php esc_html_e( 'Docsway docs layout', 'aneris-docsway' ); ?></strong><small><?php esc_html_e( 'Ready in seconds. Doc pages, your docs home and search results get a complete docs layout with a sidebar menu, between your theme’s header and footer.', 'aneris-docsway' ); ?></small></span>
				</label>
				<label class="dsw-style">
					<input type="radio" name="layout" value="theme" <?php checked( $o['layout'], 'theme' ); ?>>
					<span class="dsw-style__text"><strong><?php esc_html_e( 'My theme’s layout', 'aneris-docsway' ); ?></strong><small><?php esc_html_e( 'Pages keep your theme’s template. You place the menu and search yourself with blocks, Divi modules or shortcodes.', 'aneris-docsway' ); ?></small></span>
				</label>
			</fieldset>
			<div class="dsw-layout__body">
				<table class="form-table dsw-layout__options" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Header and footer', 'aneris-docsway' ); ?></th>
						<td>
							<fieldset class="dsw-segs"><legend class="screen-reader-text"><?php esc_html_e( 'Header and footer', 'aneris-docsway' ); ?></legend><?php echo wp_kses( $radio( 'chrome', 'theme', __( 'Your theme’s', 'aneris-docsway' ) ) . $radio( 'chrome', 'docsway', __( 'Docsway docs bar', 'aneris-docsway' ) ), Docsway_Render::allowed_html() );?></fieldset>
							<p class="description"><?php esc_html_e( 'The docs bar gives docs pages a header of their own, with your logo, search, a light / dark switch (with Auto light and dark) and a link back to your site, like a separate docs site.', 'aneris-docsway' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Sidebar', 'aneris-docsway' ); ?></th>
						<td><fieldset class="dsw-segs"><legend class="screen-reader-text"><?php esc_html_e( 'Sidebar', 'aneris-docsway' ); ?></legend><?php echo wp_kses( $radio( 'sidebar', 'left', __( 'Left', 'aneris-docsway' ) ) . $radio( 'sidebar', 'right', __( 'Right', 'aneris-docsway' ) ), Docsway_Render::allowed_html() );?></fieldset></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Width', 'aneris-docsway' ); ?></th>
						<td>
							<fieldset class="dsw-segs"><legend class="screen-reader-text"><?php esc_html_e( 'Width', 'aneris-docsway' ); ?></legend><?php echo wp_kses( $radio( 'width', 'contained', __( 'Within a page width', 'aneris-docsway' ) ) . $radio( 'width', 'full', __( 'Full width', 'aneris-docsway' ) ), Docsway_Render::allowed_html() );?></fieldset>
							<p class="dsw-layout__max"><label><?php esc_html_e( 'Page width', 'aneris-docsway' ); ?> <input type="number" name="max_width" min="760" max="2400" step="10" value="<?php echo (int) $o['max_width']; ?>" class="small-text"> px</label></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'On this page contents', 'aneris-docsway' ); ?></th>
						<td>
							<fieldset class="dsw-segs"><legend class="screen-reader-text"><?php esc_html_e( 'On this page contents', 'aneris-docsway' ); ?></legend><?php echo wp_kses( $radio( 'toc_position', 'side', __( 'Own column', 'aneris-docsway' ) ) . $radio( 'toc_position', 'content', __( 'Top of the page', 'aneris-docsway' ) ), Docsway_Render::allowed_html() );?></fieldset>
							<p class="description"><?php esc_html_e( 'The column sits opposite the menu on wide screens and moves to the top of the page on smaller ones.', 'aneris-docsway' ); ?></p>
						</td>
					</tr>
				</table>
				<div class="dsw-diagram" aria-hidden="true" data-sidebar="<?php echo esc_attr( $o['sidebar'] ); ?>" data-width="<?php echo esc_attr( $o['width'] ); ?>" data-toc="<?php echo esc_attr( $o['toc_position'] ); ?>" data-chrome="<?php echo esc_attr( $o['chrome'] ); ?>">
					<span class="dsw-diagram__bar dsw-diagram__bar--top"><span class="dsw-diagram__theme"><?php esc_html_e( 'Your header', 'aneris-docsway' ); ?></span><span class="dsw-diagram__own"><?php esc_html_e( 'Logo · Docs · Search · Back to site', 'aneris-docsway' ); ?></span></span>
					<span class="dsw-diagram__body">
						<span class="dsw-diagram__side"><i></i><i></i><i></i><i></i><i></i></span>
						<span class="dsw-diagram__main"><b></b><i></i><i></i><i></i><i></i><i></i><i></i></span>
						<span class="dsw-diagram__toc"><i></i><i></i><i></i></span>
					</span>
					<span class="dsw-diagram__bar"><span class="dsw-diagram__theme"><?php esc_html_e( 'Your footer', 'aneris-docsway' ); ?></span><span class="dsw-diagram__own"><?php esc_html_e( 'Docs footer', 'aneris-docsway' ); ?></span></span>
				</div>
			</div>
		</div>
		<?php
	}

	/** Style cards, accent colour and a live preview. Used by Settings and the setup wizard. */
	public static function style_picker( array $o ) {
		?>
		<fieldset class="dsw-styles">
			<legend class="dsw-styles__legend"><?php esc_html_e( 'Style', 'aneris-docsway' ); ?></legend>
			<?php foreach ( Docsway_Settings::styles() as $key => $s ) : ?>
				<label class="dsw-style">
					<input type="radio" name="style" value="<?php echo esc_attr( $key ); ?>" data-accent="<?php echo esc_attr( $s['accent'] ); ?>" <?php checked( $o['style'], $key ); ?>>
					<span class="dsw-style__sample docsway-style-<?php echo esc_attr( $key ); ?>" style="--dsw-dot: <?php echo esc_attr( $s['accent'] ); ?>" aria-hidden="true">Aa<i></i></span>
					<span class="dsw-style__text"><strong><?php echo esc_html( $s['label'] ); ?></strong><small><?php echo esc_html( $s['desc'] ); ?></small></span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p class="dsw-accent">
			<label for="dsw-accent"><strong><?php esc_html_e( 'Accent colour', 'aneris-docsway' ); ?></strong></label>
			<input type="color" id="dsw-accent" name="accent" value="<?php echo esc_attr( $o['accent'] ); ?>">
			<span class="description"><?php esc_html_e( 'Used for the current page, links and buttons. Picking a style also picks a matching colour; you can change it afterwards.', 'aneris-docsway' ); ?></span>
		</p>
		<p class="dsw-look-label"><strong><?php esc_html_e( 'Preview', 'aneris-docsway' ); ?></strong> <span class="dsw-muted"><?php esc_html_e( 'Sample pages. With “Follow my theme”, your site uses your theme’s fonts and colours instead of these.', 'aneris-docsway' ); ?></span></p>
		<?php
		echo wp_kses( Docsway_Render::style_preview( $o['style'] ), Docsway_Render::allowed_html() );
	}

	public static function copy_field( $text ) {
		return '<span class="dsw-copy"><code>' . esc_html( $text ) . '</code><button type="button" class="button button-small dsw-copy__btn" data-copy="' . esc_attr( $text ) . '">' . esc_html__( 'Copy', 'aneris-docsway' ) . '</button></span>';
	}

	private static function terms() {
		$terms = get_terms( array( 'taxonomy' => Docsway_Settings::taxonomy(), 'hide_empty' => false ) );
		return is_wp_error( $terms ) ? array() : $terms;
	}

	/** Terms as an indented <option> list. */
	public static function section_options( $selected = 0, $none = '' ) {
		$terms = self::terms();
		$kids  = array();
		foreach ( $terms as $t ) {
			$kids[ $t->parent ][] = $t;
		}
		$out = $none ? '<option value="0">' . esc_html( $none ) . '</option>' : '';
		$walk = function ( $parent, $depth ) use ( &$walk, &$out, $kids, $selected ) {
			if ( empty( $kids[ $parent ] ) ) {
				return;
			}
			usort( $kids[ $parent ], function ( $a, $b ) { return strnatcasecmp( $a->name, $b->name ); } );
			foreach ( $kids[ $parent ] as $t ) {
				$out .= '<option value="' . (int) $t->term_id . '"' . selected( $selected, $t->term_id, false ) . '>' . str_repeat( '– ', $depth ) . esc_html( $t->name ) . '</option>';
				$walk( $t->term_id, $depth + 1 );
			}
		};
		$walk( 0, 0 );
		return $out;
	}

	// ── Status for the dashboard checklist ──────────────────────────────────

	public static function status() {
		$tax        = Docsway_Settings::taxonomy();
		$ready      = taxonomy_exists( $tax );
		$term_count = $ready ? (int) wp_count_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ) : 0;
		$doc_count  = 0;
		if ( $ready && $term_count ) {
			$q = new WP_Query(
				array(
					'post_type'      => Docsway_Settings::post_types(),
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'tax_query'      => array( array( 'taxonomy' => $tax, 'operator' => 'EXISTS' ) ),
				)
			);
			$doc_count = (int) $q->found_posts;
		}
		return array(
			'configured' => (bool) Docsway_Settings::get( 'setup_done' ) && $ready,
			'sections'   => $term_count,
			'docs'       => $doc_count,
			'results'    => '' !== Docsway_Search::results_url(),
			'placed'     => self::menu_is_placed(),
		);
	}

	/** Looks for the menu in content, Divi Theme Builder layouts and block widgets. */
	public static function menu_is_placed() {
		global $wpdb;
		$found = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND (post_content LIKE '%[docsway_menu%' OR post_content LIKE '%wp:docsway/menu%' OR post_content LIKE '%et_pb_docsway_menu%') LIMIT 1"
		);
		if ( $found ) {
			return true;
		}
		$widgets = wp_json_encode( get_option( 'widget_block', array() ) ) . wp_json_encode( get_option( 'widget_text', array() ) );
		return false !== strpos( $widgets, 'docsway/menu' ) || false !== strpos( $widgets, 'docsway_menu' );
	}

	// ── Dashboard ───────────────────────────────────────────────────────────

	public static function page_dashboard() {
		$s     = self::status();
		$log   = get_option( Docsway_Search::LOG_OPTION, array() );
		$total = 0;
		$fail  = 0;
		foreach ( (array) $log as $r ) {
			$total += (int) $r['count'];
			if ( 0 === (int) $r['hits'] ) {
				$fail += (int) $r['count'];
			}
		}

		$steps = array(
			array(
				'done'  => $s['configured'],
				'title' => __( 'Choose what counts as documentation', 'aneris-docsway' ),
				'text'  => __( 'Pick the content types that hold your docs and how they are grouped.', 'aneris-docsway' ),
				'url'   => Docsway_Wizard::url( 'content' ),
				'cta'   => __( 'Open the wizard', 'aneris-docsway' ),
				'help'  => 'getting-started',
			),
			array(
				'done'  => $s['sections'] > 0,
				'title' => __( 'Create your sections', 'aneris-docsway' ),
				'text'  => __( 'Sections are the headings in your menu, such as Getting started or Troubleshooting.', 'aneris-docsway' ),
				'url'   => Docsway_Wizard::url( 'sections' ),
				'cta'   => __( 'Add sections', 'aneris-docsway' ),
				'help'  => 'sections',
			),
			array(
				'done'  => $s['docs'] > 0,
				'title' => __( 'Put pages into sections', 'aneris-docsway' ),
				'text'  => __( 'Only pages that belong to a section appear in the menu and in docs search.', 'aneris-docsway' ),
				'url'   => admin_url( 'admin.php?page=docsway-structure&tab=assign' ),
				'cta'   => __( 'Assign pages', 'aneris-docsway' ),
				'help'  => 'assigning',
			),
			array(
				'done'  => $s['results'],
				'title' => __( 'Set up the search results page', 'aneris-docsway' ),
				'text'  => __( 'Instant results work everywhere. A results page shows the full list when someone presses Enter.', 'aneris-docsway' ),
				'url'   => Docsway_Wizard::url( 'features' ),
				'cta'   => __( 'Set up search', 'aneris-docsway' ),
				'help'  => 'search',
			),
			array(
				'done'  => $s['placed'],
				'title' => __( 'Add the menu to your docs layout', 'aneris-docsway' ),
				'text'  => __( 'Place the Docs Menu in a sidebar with the block, the Divi module or the shortcode.', 'aneris-docsway' ),
				'url'   => admin_url( 'admin.php?page=docsway-help#placing' ),
				'cta'   => __( 'Show me how', 'aneris-docsway' ),
				'help'  => 'placing',
			),
		);
		$done = count( array_filter( wp_list_pluck( $steps, 'done' ) ) );

		self::header( __( 'Docsway', 'aneris-docsway' ), __( 'Documentation built from the pages you already have.', 'aneris-docsway' ) );
		?>
		<div class="dsw-grid dsw-grid--dash">
			<section class="dsw-card dsw-card--checklist">
				<div class="dsw-card__head">
					<h2><?php esc_html_e( 'Setup checklist', 'aneris-docsway' ); ?></h2>
					<span class="dsw-progress" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: done, 2: total */ __( '%1$d of %2$d steps done', 'aneris-docsway' ), $done, count( $steps ) ) ); ?>">
						<span class="dsw-progress__bar" style="width:<?php echo (int) round( $done / count( $steps ) * 100 ); ?>%"></span>
					</span>
					<span class="dsw-muted"><?php echo esc_html( sprintf( /* translators: 1: done, 2: total */ __( '%1$d of %2$d done', 'aneris-docsway' ), $done, count( $steps ) ) ); ?></span>
				</div>
				<ol class="dsw-steps">
					<?php foreach ( $steps as $st ) : ?>
						<li class="dsw-step<?php echo $st['done'] ? ' is-done' : ''; ?>">
							<span class="dsw-step__mark" aria-hidden="true"><?php echo $st['done'] ? '&#10003;' : ''; ?></span>
							<div class="dsw-step__body">
								<strong><?php echo esc_html( $st['title'] ); ?></strong>
								<span class="screen-reader-text"><?php echo $st['done'] ? esc_html__( '(done)', 'aneris-docsway' ) : esc_html__( '(to do)', 'aneris-docsway' ); ?></span>
								<p><?php echo esc_html( $st['text'] ); ?></p>
							</div>
							<div class="dsw-step__actions">
								<a class="button<?php echo $st['done'] ? '' : ' button-primary'; ?>" href="<?php echo esc_url( $st['url'] ); ?>"><?php echo esc_html( $st['cta'] ); ?></a>
								<?php echo wp_kses( self::help_link( $st['help'], __( 'Tutorial', 'aneris-docsway' ) ), Docsway_Render::allowed_html() ); ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ol>
				<p class="dsw-card__foot"><a href="<?php echo esc_url( Docsway_Wizard::url() ); ?>"><?php esc_html_e( 'Run the setup wizard again', 'aneris-docsway' ); ?></a></p>
			</section>

			<div class="dsw-stack">
				<section class="dsw-card">
					<h2><?php esc_html_e( 'At a glance', 'aneris-docsway' ); ?></h2>
					<dl class="dsw-stats">
						<div><dt><?php esc_html_e( 'Sections', 'aneris-docsway' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $s['sections'] ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Doc pages', 'aneris-docsway' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $s['docs'] ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Searches logged', 'aneris-docsway' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $total ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'With no results', 'aneris-docsway' ); ?></dt><dd class="<?php echo $fail ? 'is-warn' : ''; ?>"><?php echo esc_html( number_format_i18n( $fail ) ); ?></dd></div>
					</dl>
					<?php if ( $fail ) : ?>
						<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=docsway-insights' ) ); ?>"><?php esc_html_e( 'See what people could not find →', 'aneris-docsway' ); ?></a></p>
					<?php endif; ?>
				</section>

				<section class="dsw-card">
					<h2><?php esc_html_e( 'Shortcodes', 'aneris-docsway' ); ?></h2>
					<p class="dsw-muted"><?php esc_html_e( 'Paste these into a Divi Text module, a Shortcode block or a widget. Blocks with the same names are in the block inserter under “Docs”.', 'aneris-docsway' ); ?></p>
					<table class="dsw-codes">
						<tr><th><?php esc_html_e( 'Sidebar menu', 'aneris-docsway' ); ?></th><td><?php echo wp_kses( self::copy_field( '[docsway_menu]' ), Docsway_Render::allowed_html() ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Search box', 'aneris-docsway' ); ?></th><td><?php echo wp_kses( self::copy_field( '[docsway_search]' ), Docsway_Render::allowed_html() ); ?></td></tr>
						<tr><th><?php esc_html_e( 'On this page', 'aneris-docsway' ); ?></th><td><?php echo wp_kses( self::copy_field( '[docsway_toc]' ), Docsway_Render::allowed_html() ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Previous / next', 'aneris-docsway' ); ?></th><td><?php echo wp_kses( self::copy_field( '[docsway_prev_next]' ), Docsway_Render::allowed_html() ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Breadcrumbs', 'aneris-docsway' ); ?></th><td><?php echo wp_kses( self::copy_field( '[docsway_breadcrumbs]' ), Docsway_Render::allowed_html() ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Results list', 'aneris-docsway' ); ?></th><td><?php echo wp_kses( self::copy_field( '[docsway_search_results]' ), Docsway_Render::allowed_html() ); ?></td></tr>
					</table>
				</section>
			</div>
		</div>
		<?php
		self::footer();
	}

	// ── Structure ───────────────────────────────────────────────────────────

	public static function page_structure() {
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'order'; // phpcs:ignore WordPress.Security.NonceVerification
		$tabs = array(
			'order'  => __( 'Menu order', 'aneris-docsway' ),
			'assign' => __( 'Assign pages', 'aneris-docsway' ),
			'links'  => __( 'Section links', 'aneris-docsway' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'order';
		}

		self::header( __( 'Docs structure', 'aneris-docsway' ), __( 'Decide what appears in the menu and in which order.', 'aneris-docsway' ) );

		if ( ! Docsway_Taxonomy::is_ready() ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'The category system Docsway is set to use no longer exists. Choose another one in Settings.', 'aneris-docsway' ) . '</p></div>';
			self::footer();
			return;
		}

		echo '<nav class="nav-tab-wrapper dsw-tabs">';
		foreach ( $tabs as $slug => $label ) {
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=docsway-structure&tab=' . $slug ) ),
				$tab === $slug ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';

		call_user_func( array( __CLASS__, 'tab_' . $tab ) );
		self::footer();
	}

	private static function tab_order() {
		$tree = Docsway_Tree::build( true );
		?>
		<div class="dsw-grid dsw-grid--side">
			<section class="dsw-card">
				<div class="dsw-card__head">
					<h2><?php esc_html_e( 'Drag to reorder', 'aneris-docsway' ); ?></h2>
					<?php echo wp_kses( self::help_link( 'ordering' ), Docsway_Render::allowed_html() ); ?>
				</div>
				<?php if ( ! $tree ) : ?>
					<div class="dsw-empty">
						<p><strong><?php esc_html_e( 'Nothing to order yet.', 'aneris-docsway' ); ?></strong></p>
						<p><?php esc_html_e( 'Create a section and put a page in it, then come back here.', 'aneris-docsway' ); ?></p>
						<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=docsway-structure&tab=assign' ) ); ?>"><?php esc_html_e( 'Assign pages', 'aneris-docsway' ); ?></a></p>
					</div>
				<?php else : ?>
					<div class="dsw-order" id="docsway-order"><?php self::order_list( $tree, null ); ?></div>
					<div class="dsw-bar">
						<button type="button" class="button button-primary" id="docsway-save-order"><?php esc_html_e( 'Save order', 'aneris-docsway' ); ?></button>
						<button type="button" class="button" id="docsway-reset-order"><?php esc_html_e( 'Reset to A–Z', 'aneris-docsway' ); ?></button>
						<button type="button" class="button-link" id="docsway-flush"><?php esc_html_e( 'Rebuild menu cache', 'aneris-docsway' ); ?></button>
						<span class="dsw-status" id="docsway-order-status" aria-live="polite"></span>
					</div>
				<?php endif; ?>
			</section>
			<aside class="dsw-card dsw-card--tip">
				<h2><?php esc_html_e( 'Tips', 'aneris-docsway' ); ?></h2>
				<ul class="dsw-list">
					<li><?php esc_html_e( 'Drag the handle on the left of a row. Items move within their own group only.', 'aneris-docsway' ); ?></li>
					<li><?php esc_html_e( 'To move a section under another section, change its Parent on the Sections screen.', 'aneris-docsway' ); ?></li>
					<li><?php esc_html_e( 'To move a page to another section, use Assign pages.', 'aneris-docsway' ); ?></li>
					<li><?php esc_html_e( 'Greyed rows are empty sections. They stay hidden on the site until they contain a page.', 'aneris-docsway' ); ?></li>
				</ul>
				<p><a class="button" href="<?php echo esc_url( Docsway_Taxonomy::edit_url() ); ?>"><?php esc_html_e( 'Edit sections', 'aneris-docsway' ); ?></a></p>
			</aside>
		</div>
		<?php
	}

	private static function order_list( array $nodes, $parent ) {
		echo '<ul class="dsw-sortable" data-parent="' . esc_attr( null === $parent ? 0 : $parent ) . '">';
		foreach ( $nodes as $n ) {
			$key   = null === $parent ? 'term_' . $n['id'] : 'child_' . $parent . '_' . $n['type'] . $n['id'];
			$empty = 'term' === $n['type'] && ! $n['children'] && ! $n['url'];
			$edit  = 'term' === $n['type'] ? get_edit_term_link( $n['id'], Docsway_Settings::taxonomy() ) : get_edit_post_link( $n['id'] );
			echo '<li class="dsw-node dsw-node--' . esc_attr( $n['type'] ) . ( $empty ? ' is-empty' : '' ) . '" data-key="' . esc_attr( $key ) . '">';
			echo '<div class="dsw-node__row"><span class="dsw-node__handle dashicons dashicons-move" aria-hidden="true"></span>';
			echo '<span class="dsw-node__title">' . esc_html( $n['title'] ) . '</span>';
			echo '<span class="dsw-badge dsw-badge--' . esc_attr( $n['type'] ) . '">' . ( 'term' === $n['type'] ? esc_html__( 'Section', 'aneris-docsway' ) : esc_html__( 'Page', 'aneris-docsway' ) ) . '</span>';
			if ( 'term' === $n['type'] && $n['url'] ) {
				echo '<span class="dsw-badge dsw-badge--link" title="' . esc_attr__( 'This heading links to a page', 'aneris-docsway' ) . '">' . esc_html__( 'Linked', 'aneris-docsway' ) . '</span>';
			}
			if ( $empty ) {
				echo '<span class="dsw-muted dsw-node__note">' . esc_html__( 'empty, hidden on site', 'aneris-docsway' ) . '</span>';
			}
			if ( $edit ) {
				echo '<a class="dsw-node__edit" href="' . esc_url( $edit ) . '"><span class="screen-reader-text">' . esc_html__( 'Edit', 'aneris-docsway' ) . '</span><span class="dashicons dashicons-edit" aria-hidden="true"></span></a>';
			}
			echo '</div>';
			if ( $n['children'] ) {
				self::order_list( $n['children'], $n['id'] );
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	private static function tab_assign() {
		$tax    = Docsway_Settings::taxonomy();
		$filter = isset( $_GET['show'] ) ? sanitize_key( $_GET['show'] ) : 'unassigned'; // phpcs:ignore WordPress.Security.NonceVerification
		$args   = array(
			'post_type'      => Docsway_Settings::post_types(),
			'post_status'    => array( 'publish', 'draft', 'private' ),
			'posts_per_page' => 300,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);
		if ( 'unassigned' === $filter ) {
			$args['tax_query'] = array( array( 'taxonomy' => $tax, 'operator' => 'NOT EXISTS' ) );
		} elseif ( 'assigned' === $filter ) {
			$args['tax_query'] = array( array( 'taxonomy' => $tax, 'operator' => 'EXISTS' ) );
		}
		/** Add-ons can list other items, for example documents on their own view. */
		$args     = apply_filters( 'docsway_assign_query_args', $args, $filter );
		$posts    = get_posts( $args );
		$has_secs = (bool) self::terms();
		?>
		<div class="dsw-grid dsw-grid--side">
			<section class="dsw-card">
				<div class="dsw-card__head">
					<h2><?php esc_html_e( 'Assign pages to a section', 'aneris-docsway' ); ?></h2>
					<?php echo wp_kses( self::help_link( 'assigning' ), Docsway_Render::allowed_html() ); ?>
				</div>
				<p class="dsw-muted"><?php esc_html_e( 'Tick pages, choose a section, then press Add. A page can sit in more than one section.', 'aneris-docsway' ); ?></p>

				<div class="dsw-filter">
					<?php
					$views = array( 'unassigned' => __( 'Not in docs yet', 'aneris-docsway' ), 'assigned' => __( 'Already in docs', 'aneris-docsway' ), 'all' => __( 'All', 'aneris-docsway' ) );
					$views = apply_filters( 'docsway_assign_views', $views );
					foreach ( $views as $k => $label ) {
						printf( '<a href="%s" class="%s">%s</a>', esc_url( admin_url( 'admin.php?page=docsway-structure&tab=assign&show=' . $k ) ), $k === $filter ? 'is-current' : '', esc_html( $label ) );
					}
					?>
					<input type="search" class="dsw-filter__search" id="docsway-assign-filter" placeholder="<?php esc_attr_e( 'Filter by title…', 'aneris-docsway' ); ?>">
				</div>

				<?php if ( ! $posts ) : ?>
					<div class="dsw-empty"><p><?php esc_html_e( 'No pages match this view.', 'aneris-docsway' ); ?></p></div>
				<?php else : ?>
					<div class="dsw-scroll"><table class="widefat striped dsw-assign" id="docsway-assign">
						<thead><tr>
							<td class="check-column"><input type="checkbox" id="docsway-assign-all" aria-label="<?php esc_attr_e( 'Select all', 'aneris-docsway' ); ?>"></td>
							<th><?php esc_html_e( 'Title', 'aneris-docsway' ); ?></th>
							<th><?php esc_html_e( 'Sections', 'aneris-docsway' ); ?></th>
							<th><?php esc_html_e( 'Status', 'aneris-docsway' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $posts as $p ) :
							$names = wp_get_object_terms( $p->ID, $tax, array( 'fields' => 'names' ) );
							$names = is_wp_error( $names ) ? array() : $names;
							?>
							<tr data-title="<?php echo esc_attr( mb_strtolower( $p->post_title ) ); ?>">
								<th class="check-column"><input type="checkbox" value="<?php echo (int) $p->ID; ?>" aria-label="<?php echo esc_attr( $p->post_title ); ?>"></th>
								<td><strong><?php echo esc_html( $p->post_title ? $p->post_title : __( '(no title)', 'aneris-docsway' ) ); ?></strong> <span class="dsw-muted"><?php echo esc_html( apply_filters( 'docsway_assign_item_type', get_post_type_object( $p->post_type )->labels->singular_name, $p ) ); ?></span></td>
								<td class="dsw-assign__terms"><?php echo $names ? esc_html( implode( ', ', $names ) ) : '<span class="dsw-muted">–</span>'; ?></td>
								<td><?php echo esc_html( apply_filters( 'docsway_assign_item_status', get_post_status_object( $p->post_status )->label, $p, $names ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table></div>
				<?php endif; ?>

				<div class="dsw-bar">
					<label for="docsway-assign-term" class="screen-reader-text"><?php esc_html_e( 'Section', 'aneris-docsway' ); ?></label>
					<select id="docsway-assign-term"><?php echo wp_kses( self::section_options( 0, __( 'Choose a section…', 'aneris-docsway' ) ), Docsway_Render::allowed_html() ); ?></select>
					<button type="button" class="button button-primary" data-assign="add"><?php esc_html_e( 'Add to section', 'aneris-docsway' ); ?></button>
					<button type="button" class="button" data-assign="move"><?php esc_html_e( 'Move to section', 'aneris-docsway' ); ?></button>
					<button type="button" class="button-link dsw-danger" data-assign="remove"><?php esc_html_e( 'Remove from docs', 'aneris-docsway' ); ?></button>
					<span class="dsw-status" id="docsway-assign-status" aria-live="polite"></span>
				</div>
			</section>

			<aside class="dsw-card dsw-card--tip">
				<h2><?php esc_html_e( 'New section', 'aneris-docsway' ); ?></h2>
				<?php if ( ! $has_secs ) : ?>
					<p><?php esc_html_e( 'You have no sections yet. Create the first one here.', 'aneris-docsway' ); ?></p>
				<?php endif; ?>
				<p><label for="docsway-new-name"><?php esc_html_e( 'Name', 'aneris-docsway' ); ?></label><br><input type="text" id="docsway-new-name" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Getting started', 'aneris-docsway' ); ?>"></p>
				<p><label for="docsway-new-parent"><?php esc_html_e( 'Inside', 'aneris-docsway' ); ?></label><br><select id="docsway-new-parent"><?php echo wp_kses( self::section_options( 0, __( 'Top level', 'aneris-docsway' ) ), Docsway_Render::allowed_html() ); ?></select></p>
				<p><button type="button" class="button" id="docsway-add-section"><?php esc_html_e( 'Create section', 'aneris-docsway' ); ?></button> <span class="dsw-status" id="docsway-section-status" aria-live="polite"></span></p>
				<hr>
				<p class="dsw-muted"><?php esc_html_e( 'Move replaces a page’s current sections with the one you choose. Remove takes the page out of the docs menu and search but does not delete it.', 'aneris-docsway' ); ?></p>
			</aside>
		</div>
		<?php
	}

	private static function tab_links() {
		$terms = self::terms();
		?>
		<section class="dsw-card">
			<div class="dsw-card__head">
				<h2><?php esc_html_e( 'Make a section heading clickable', 'aneris-docsway' ); ?></h2>
				<?php echo wp_kses( self::help_link( 'section-links' ), Docsway_Render::allowed_html() ); ?>
			</div>
			<p class="dsw-muted"><?php esc_html_e( 'By default a section heading only opens and closes its list. Link it to an overview page and the heading becomes a link too. That page is then shown as the heading instead of as an item inside the list.', 'aneris-docsway' ); ?></p>
			<?php if ( ! $terms ) : ?>
				<div class="dsw-empty"><p><?php esc_html_e( 'No sections yet.', 'aneris-docsway' ); ?></p></div>
			<?php else : ?>
				<table class="widefat striped dsw-links">
					<thead><tr><th><?php esc_html_e( 'Section', 'aneris-docsway' ); ?></th><th><?php esc_html_e( 'Heading links to', 'aneris-docsway' ); ?></th><th></th></tr></thead>
					<tbody>
					<?php
					$docs = self::all_docs_by_term();
					foreach ( $terms as $t ) :
						$linked = (int) get_term_meta( $t->term_id, Docsway_Tree::LINK_META, true );
						$parent = $t->parent ? get_term( $t->parent ) : null;
						?>
						<tr data-term="<?php echo (int) $t->term_id; ?>">
							<td><strong><?php echo esc_html( $t->name ); ?></strong><?php echo $parent && ! is_wp_error( $parent ) ? ' <span class="dsw-muted">' . esc_html( sprintf( /* translators: %s: parent section */ __( 'in %s', 'aneris-docsway' ), $parent->name ) ) . '</span>' : ''; ?></td>
							<td>
								<label class="screen-reader-text" for="docsway-link-<?php echo (int) $t->term_id; ?>"><?php esc_html_e( 'Linked page', 'aneris-docsway' ); ?></label>
								<select id="docsway-link-<?php echo (int) $t->term_id; ?>" class="dsw-link-select">
									<option value="0"><?php esc_html_e( 'Nothing (heading only)', 'aneris-docsway' ); ?></option>
									<?php
									$in_section = isset( $docs[ $t->term_id ] ) ? $docs[ $t->term_id ] : array();
									if ( $in_section ) {
										echo '<optgroup label="' . esc_attr__( 'Pages in this section', 'aneris-docsway' ) . '">';
										foreach ( $in_section as $pid => $title ) {
											echo '<option value="' . (int) $pid . '"' . selected( $linked, $pid, false ) . '>' . esc_html( $title ) . '</option>';
										}
										echo '</optgroup>';
									}
									if ( $linked && ! isset( $in_section[ $linked ] ) ) {
										echo '<option value="' . (int) $linked . '" selected>' . esc_html( get_the_title( $linked ) ) . '</option>';
									}
									?>
								</select>
							</td>
							<td><button type="button" class="button dsw-link-save"><?php esc_html_e( 'Save', 'aneris-docsway' ); ?></button> <span class="dsw-status" aria-live="polite"></span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="dsw-muted"><?php esc_html_e( 'Only pages already inside a section are offered, so the heading always points somewhere relevant.', 'aneris-docsway' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function all_docs_by_term() {
		$terms = self::terms();
		return $terms ? Docsway_Tree::posts_by_term( wp_list_pluck( $terms, 'term_id' ) ) : array();
	}

	// ── Settings ────────────────────────────────────────────────────────────

	public static function page_settings() {
		$o     = Docsway_Settings::all();
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		$cands = Docsway_Taxonomy::candidates();
		$pages = get_pages( array( 'post_status' => 'publish' ) );

		self::header( __( 'Docsway settings', 'aneris-docsway' ) );
		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'aneris-docsway' ) . '</p></div>';
		}
		do_action( 'docsway_settings_notices' );
		?>
		<?php
		// One section at a time, picked from the sidebar (admin.js). Without JavaScript all sections show.
		$sections = array(
			'content'     => array( __( 'Content', 'aneris-docsway' ), __( 'Where your docs live', 'aneris-docsway' ) ),
			'navigation'  => array( __( 'Navigation', 'aneris-docsway' ), __( 'Menu and page features', 'aneris-docsway' ) ),
			'search'      => array( __( 'Search', 'aneris-docsway' ), __( 'Results and answers', 'aneris-docsway' ) ),
			'search-home' => array( __( 'Search home', 'aneris-docsway' ), __( 'Quick searches and topics', 'aneris-docsway' ) ),
			'appearance'  => array( __( 'Appearance', 'aneris-docsway' ), __( 'Layout and style', 'aneris-docsway' ) ),
			'advanced'    => array( __( 'Advanced', 'aneris-docsway' ), __( 'Uninstall options', 'aneris-docsway' ) ),
		);
		/** Add-ons such as Docsway Pro add sections: id => array( title, subtitle ). Render them on docsway_settings_panels. */
		$sections = apply_filters( 'docsway_settings_sections', $sections );
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dsw-settings dsw-help" data-dsw-sections>
			<input type="hidden" name="action" value="docsway_save_settings">
			<input type="hidden" name="section" value="">
			<?php wp_nonce_field( 'docsway_settings' ); ?>

			<nav class="dsw-help__nav dsw-sections-nav" aria-label="<?php esc_attr_e( 'Settings sections', 'aneris-docsway' ); ?>">
				<ul>
					<?php foreach ( $sections as $id => $s ) : ?>
						<li><a href="#<?php echo esc_attr( $id ); ?>"><strong><?php echo esc_html( $s[0] ); ?></strong><small><?php echo esc_html( $s[1] ); ?></small></a></li>
					<?php endforeach; ?>
				</ul>
				<a class="button" href="<?php echo esc_url( Docsway_Wizard::url() ); ?>"><?php esc_html_e( 'Open setup wizard', 'aneris-docsway' ); ?></a>
			</nav>

			<div class="dsw-help__body">
			<section class="dsw-card" id="content">
				<div class="dsw-card__head"><h2><?php esc_html_e( 'Content', 'aneris-docsway' ); ?></h2><?php echo wp_kses( self::help_link( 'getting-started' ), Docsway_Render::allowed_html() ); ?></div>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Docs live in', 'aneris-docsway' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Content types', 'aneris-docsway' ); ?></legend>
								<?php foreach ( $types as $type ) : ?>
									<label class="dsw-check"><input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $o['post_types'], true ) ); ?>> <?php echo esc_html( $type->labels->name ); ?></label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Most sites keep docs in Pages. Tick more types if your docs are spread across them.', 'aneris-docsway' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Group docs with', 'aneris-docsway' ); ?></th>
						<td>
							<fieldset>
								<label class="dsw-radio"><input type="radio" name="taxonomy_mode" value="own" <?php checked( 'own', $o['taxonomy_mode'] ); ?>> <strong><?php esc_html_e( 'Docsway doc sections', 'aneris-docsway' ); ?></strong> <span class="dsw-muted"><?php esc_html_e( '(recommended)', 'aneris-docsway' ); ?></span></label>
								<label class="dsw-radio"><input type="radio" name="taxonomy_mode" value="existing" <?php checked( 'existing', $o['taxonomy_mode'] ); ?> <?php disabled( ! $cands ); ?>> <strong><?php esc_html_e( 'A category system I already have', 'aneris-docsway' ); ?></strong></label>
								<div class="dsw-indent">
									<select name="taxonomy" <?php disabled( ! $cands ); ?>>
										<?php foreach ( $cands as $slug => $c ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $o['taxonomy'], $slug ); ?>><?php echo esc_html( sprintf( '%s (%s, %d)', $c['label'], $slug, $c['terms'] ) ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Useful when you already organised docs with a custom taxonomy, for example one made with CPT UI.', 'aneris-docsway' ); ?></p>
								</div>
							</fieldset>
						</td>
					</tr>
				</table>
			</section>

			<section class="dsw-card" id="navigation">
				<div class="dsw-card__head"><h2><?php esc_html_e( 'Navigation', 'aneris-docsway' ); ?></h2><?php echo wp_kses( self::help_link( 'page-features' ), Docsway_Render::allowed_html() ); ?></div>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Menu behaviour', 'aneris-docsway' ); ?></th>
						<td><label class="dsw-check"><input type="checkbox" name="accordion" value="1" <?php checked( $o['accordion'] ); ?>> <?php esc_html_e( 'Keep one top-level section open at a time', 'aneris-docsway' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Add to every doc page', 'aneris-docsway' ); ?></th>
						<td>
							<label class="dsw-check"><input type="checkbox" name="toc_auto" value="1" <?php checked( $o['toc_auto'] ); ?>> <?php esc_html_e( '“On this page” contents at the top', 'aneris-docsway' ); ?></label>
							<label class="dsw-check"><input type="checkbox" name="prevnext_auto" value="1" <?php checked( $o['prevnext_auto'] ); ?>> <?php esc_html_e( 'Previous and next links at the bottom', 'aneris-docsway' ); ?></label>
							<label class="dsw-check"><input type="checkbox" name="crumbs_auto" value="1" <?php checked( $o['crumbs_auto'] ); ?>> <?php esc_html_e( 'Breadcrumbs at the top', 'aneris-docsway' ); ?></label>
							<label class="dsw-check"><input type="checkbox" name="copy_code" value="1" <?php checked( $o['copy_code'] ); ?>> <?php esc_html_e( 'Copy button on code blocks', 'aneris-docsway' ); ?></label>
							<label class="dsw-check"><input type="checkbox" name="show_updated" value="1" <?php checked( $o['show_updated'] ); ?>> <?php esc_html_e( '“Last updated” date under the title', 'aneris-docsway' ); ?></label>
							<label class="dsw-check"><input type="checkbox" name="show_reading" value="1" <?php checked( $o['show_reading'] ); ?>> <?php esc_html_e( 'Reading time under the title', 'aneris-docsway' ); ?></label>
							<label class="dsw-check"><input type="checkbox" name="heading_links" value="1" <?php checked( $o['heading_links'] ); ?>> <?php esc_html_e( 'Link icons on headings, so readers can share a section', 'aneris-docsway' ); ?></label>
							<label class="dsw-check"><input type="checkbox" name="feedback" value="1" <?php checked( $o['feedback'] ); ?>> <?php esc_html_e( '“Was this page helpful?” at the bottom (results in Search insights)', 'aneris-docsway' ); ?></label>
							<p class="description"><?php esc_html_e( 'Untick any of these if you place the block, Divi module or shortcode yourself, for example in a Theme Builder sidebar.', 'aneris-docsway' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Contents shows', 'aneris-docsway' ); ?></th>
						<td>
							<?php foreach ( array( 2, 3, 4 ) as $lvl ) : ?>
								<label class="dsw-check dsw-check--inline"><input type="checkbox" name="toc_levels[]" value="<?php echo (int) $lvl; ?>" <?php checked( in_array( $lvl, $o['toc_levels'], true ) ); ?>> H<?php echo (int) $lvl; ?></label>
							<?php endforeach; ?>
							<p><label><?php esc_html_e( 'Only when a page has at least', 'aneris-docsway' ); ?> <input type="number" name="toc_min" min="1" max="10" value="<?php echo (int) $o['toc_min']; ?>" class="small-text"> <?php esc_html_e( 'headings', 'aneris-docsway' ); ?></label></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsw-home"><?php esc_html_e( 'Docs home page', 'aneris-docsway' ); ?></label></th>
						<td>
							<select id="dsw-home" name="home_page"><option value="0"><?php esc_html_e( 'None', 'aneris-docsway' ); ?></option>
								<?php foreach ( $pages as $p ) : ?><option value="<?php echo (int) $p->ID; ?>" <?php selected( $o['home_page'], $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option><?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Used as the first breadcrumb.', 'aneris-docsway' ); ?></p>
						</td>
					</tr>
				</table>
			</section>

			<section class="dsw-card" id="search">
				<div class="dsw-card__head"><h2><?php esc_html_e( 'Search', 'aneris-docsway' ); ?></h2><?php echo wp_kses( self::help_link( 'search' ), Docsway_Render::allowed_html() ); ?></div>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Full results show', 'aneris-docsway' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Where full results show', 'aneris-docsway' ); ?></legend>
								<label class="dsw-radio"><input type="radio" name="results_target" value="home" <?php checked( 'home', $o['results_target'] ); ?>> <strong><?php esc_html_e( 'On the docs home page, inside the search home', 'aneris-docsway' ); ?></strong> <span class="dsw-muted"><?php esc_html_e( '(recommended)', 'aneris-docsway' ); ?></span></label>
								<p class="description dsw-indent"><?php esc_html_e( 'Results appear below the search box on the same page, so your layout and sidebar menu stay in place. Uses the Docs home page set under Navigation, which needs the Docs Search Home block, module or shortcode.', 'aneris-docsway' ); ?></p>
								<?php if ( 'home' === $o['results_target'] && ! Docsway_Search::results_page_id() ) : ?>
									<p class="dsw-danger dsw-indent"><?php esc_html_e( 'Choose a Docs home page under Navigation, or results have nowhere to show.', 'aneris-docsway' ); ?></p>
								<?php endif; ?>
								<label class="dsw-radio"><input type="radio" name="results_target" value="page" <?php checked( 'page', $o['results_target'] ); ?>> <strong><?php esc_html_e( 'On a separate results page', 'aneris-docsway' ); ?></strong></label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsw-results"><?php esc_html_e( 'Results page', 'aneris-docsway' ); ?></label></th>
						<td>
							<select id="dsw-results" name="results_page"><option value="0"><?php esc_html_e( 'None (instant results only)', 'aneris-docsway' ); ?></option>
								<?php foreach ( $pages as $p ) : ?><option value="<?php echo (int) $p->ID; ?>" <?php selected( $o['results_page'], $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option><?php endforeach; ?>
							</select>
							<p class="description"><?php echo wp_kses( sprintf( /* translators: %s: shortcode */ __( 'Only used with a separate results page. It must contain %s; the setup wizard can create it for you.', 'aneris-docsway' ), '<code>[docsway_search_results]</code>' ), array( 'code' => array() ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsw-ph"><?php esc_html_e( 'Placeholder text', 'aneris-docsway' ); ?></label></th>
						<td><input type="text" id="dsw-ph" name="placeholder" class="regular-text" value="<?php echo esc_attr( $o['placeholder'] ); ?>" placeholder="<?php esc_attr_e( 'Search documentation…', 'aneris-docsway' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="dsw-per"><?php esc_html_e( 'Results per page', 'aneris-docsway' ); ?></label></th>
						<td><input type="number" id="dsw-per" name="per_page" min="5" max="50" class="small-text" value="<?php echo (int) $o['per_page']; ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Extras', 'aneris-docsway' ); ?></th>
						<td>
							<label class="dsw-check"><input type="checkbox" name="shortcut" value="1" <?php checked( $o['shortcut'] ); ?>> <?php esc_html_e( 'Open search with Ctrl+K, Cmd+K or / on doc pages', 'aneris-docsway' ); ?></label>
							<label class="dsw-check"><input type="checkbox" name="hide_extra_search" value="1" <?php checked( $o['hide_extra_search'] ); ?>> <?php esc_html_e( 'On the results page, hide other docs search boxes, such as one in a sidebar', 'aneris-docsway' ); ?></label>
							<label class="dsw-check"><input type="checkbox" name="log_searches" value="1" <?php checked( $o['log_searches'] ); ?>> <?php esc_html_e( 'Log search terms for Search insights', 'aneris-docsway' ); ?></label>
							<p class="description"><?php esc_html_e( 'Only the words searched and how many results they found are stored. No IP addresses or user details.', 'aneris-docsway' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Smart results', 'aneris-docsway' ); ?></th>
						<td>
							<label class="dsw-check"><input type="checkbox" name="best_answer" value="1" <?php checked( $o['best_answer'] ); ?>> <?php esc_html_e( 'Show a best answer on top: the section that answers the search, with other pages that cover it', 'aneris-docsway' ); ?></label>
							<p class="description"><?php esc_html_e( 'Search always ignores question words such as “how do I”, finds other forms of a word (“installing” finds “install”), fixes small typing mistakes, and highlights the words on the page a reader opens.', 'aneris-docsway' ); ?></p>
						</td>
					</tr>
					<?php do_action( 'docsway_settings_search_rows', $o ); ?>
				</table>
			</section>

			<section class="dsw-card" id="search-home">
				<div class="dsw-card__head"><h2><?php esc_html_e( 'Search home', 'aneris-docsway' ); ?></h2><?php echo wp_kses( self::help_link( 'search' ), Docsway_Render::allowed_html() ); ?></div>
				<p><?php echo wp_kses( __( 'A welcoming start page for your docs: a heading, a large search box, quick search buttons and topic cards. Add it with the <strong>Docs Search Home</strong> block, the <strong>Docsway Search Home</strong> Divi module or this shortcode:', 'aneris-docsway' ), array( 'strong' => array() ) ); ?></p>
				<?php echo wp_kses( self::copy_field( '[docsway_search_home]' ), Docsway_Render::allowed_html() ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dsw-home-title"><?php esc_html_e( 'Heading', 'aneris-docsway' ); ?></label></th>
						<td><input type="text" id="dsw-home-title" name="home_title" class="regular-text" value="<?php echo esc_attr( $o['home_title'] ); ?>" placeholder="<?php esc_attr_e( 'How can we help?', 'aneris-docsway' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="dsw-home-intro"><?php esc_html_e( 'Intro line', 'aneris-docsway' ); ?></label></th>
						<td><input type="text" id="dsw-home-intro" name="home_intro" class="large-text" value="<?php echo esc_attr( $o['home_intro'] ); ?>" placeholder="<?php esc_attr_e( 'Optional, for example: Search the guides or pick a topic below.', 'aneris-docsway' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Quick searches', 'aneris-docsway' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'Up to six buttons under the search box. One click runs the search. Questions people really ask work best. They also appear in the Ctrl+K search window.', 'aneris-docsway' ); ?></p>
							<table class="dsw-rows" id="dsw-quick">
								<thead><tr><th>#</th><th><?php esc_html_e( 'Button text', 'aneris-docsway' ); ?></th><th><?php esc_html_e( 'Searches for', 'aneris-docsway' ); ?> <span class="dsw-muted"><?php esc_html_e( '(optional)', 'aneris-docsway' ); ?></span></th></tr></thead>
								<tbody>
								<?php for ( $i = 0; $i < Docsway_Settings::MAX_ITEMS; $i++ ) : $q = isset( $o['quick_searches'][ $i ] ) ? $o['quick_searches'][ $i ] : array( 'label' => '', 'query' => '' ); ?>
									<tr>
										<td><?php echo (int) ( $i + 1 ); ?></td>
										<td><input type="text" name="quick_searches[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $q['label'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: row number */ __( 'Button %d text', 'aneris-docsway' ), $i + 1 ) ); ?>" placeholder="<?php esc_attr_e( 'How do I install it?', 'aneris-docsway' ); ?>"></td>
										<td><input type="text" name="quick_searches[<?php echo (int) $i; ?>][query]" value="<?php echo esc_attr( $q['query'] === $q['label'] ? '' : $q['query'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: row number */ __( 'Button %d search words', 'aneris-docsway' ), $i + 1 ) ); ?>" placeholder="<?php esc_attr_e( 'install', 'aneris-docsway' ); ?>"></td>
									</tr>
								<?php endfor; ?>
								</tbody>
							</table>
							<p class="description"><?php esc_html_e( 'Leave “Searches for” empty to search for the button text itself. Short keywords usually find more pages than whole questions.', 'aneris-docsway' ); ?></p>
							<?php
							$popular = array();
							foreach ( Docsway_Search::log_rows( false, 20 ) as $term => $row ) {
								if ( (int) $row['hits'] > 0 ) {
									$popular[] = (string) $term;
								}
							}
							if ( $popular ) :
								?>
								<p><button type="button" class="button" id="dsw-quick-fill" data-terms="<?php echo esc_attr( wp_json_encode( array_slice( $popular, 0, Docsway_Settings::MAX_ITEMS ) ) ); ?>"><?php esc_html_e( 'Fill empty rows from Search insights', 'aneris-docsway' ); ?></button> <span class="description"><?php esc_html_e( 'Uses the most searched words that found results.', 'aneris-docsway' ); ?></span></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="dsw-topics-title"><?php esc_html_e( 'Topic cards heading', 'aneris-docsway' ); ?></label></th>
						<td><input type="text" id="dsw-topics-title" name="topics_title" class="regular-text" value="<?php echo esc_attr( $o['topics_title'] ); ?>" placeholder="<?php esc_attr_e( 'Popular topics', 'aneris-docsway' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Topic cards', 'aneris-docsway' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'Up to six shortcuts to your most useful pages, such as an install guide or quick start. A card needs a title and a page.', 'aneris-docsway' ); ?></p>
							<table class="dsw-rows" id="dsw-topics">
								<thead><tr><th>#</th><th><?php esc_html_e( 'Icon', 'aneris-docsway' ); ?></th><th><?php esc_html_e( 'Title', 'aneris-docsway' ); ?></th><th><?php esc_html_e( 'Short description', 'aneris-docsway' ); ?></th><th><?php esc_html_e( 'Links to', 'aneris-docsway' ); ?></th></tr></thead>
								<tbody>
								<?php for ( $i = 0; $i < Docsway_Settings::MAX_ITEMS; $i++ ) : $tp = isset( $o['topics'][ $i ] ) ? $o['topics'][ $i ] : array( 'icon' => 'book', 'title' => '', 'text' => '', 'page' => 0 ); ?>
									<tr>
										<td><?php echo (int) ( $i + 1 ); ?></td>
										<td>
											<select name="topics[<?php echo (int) $i; ?>][icon]" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: row number */ __( 'Card %d icon', 'aneris-docsway' ), $i + 1 ) ); ?>">
												<?php foreach ( Docsway_Render::icons() as $key => $icon ) : ?>
													<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $tp['icon'], $key ); ?>><?php echo esc_html( $icon[0] ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
										<td><input type="text" name="topics[<?php echo (int) $i; ?>][title]" value="<?php echo esc_attr( $tp['title'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: row number */ __( 'Card %d title', 'aneris-docsway' ), $i + 1 ) ); ?>" placeholder="<?php esc_attr_e( 'Install guide', 'aneris-docsway' ); ?>"></td>
										<td><input type="text" name="topics[<?php echo (int) $i; ?>][text]" value="<?php echo esc_attr( $tp['text'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: row number */ __( 'Card %d description', 'aneris-docsway' ), $i + 1 ) ); ?>" placeholder="<?php esc_attr_e( 'System requirements and setup', 'aneris-docsway' ); ?>"></td>
										<td>
											<select name="topics[<?php echo (int) $i; ?>][page]" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: row number */ __( 'Card %d page', 'aneris-docsway' ), $i + 1 ) ); ?>">
												<option value="0"><?php esc_html_e( 'Choose a page…', 'aneris-docsway' ); ?></option>
												<?php foreach ( $pages as $p ) : ?><option value="<?php echo (int) $p->ID; ?>" <?php selected( $tp['page'], $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option><?php endforeach; ?>
											</select>
										</td>
									</tr>
								<?php endfor; ?>
								</tbody>
							</table>
						</td>
					</tr>
				</table>
			</section>

			<?php do_action( 'docsway_settings_panels', $o ); ?>

			<section class="dsw-card" id="appearance">
				<div class="dsw-card__head"><h2><?php esc_html_e( 'Appearance', 'aneris-docsway' ); ?></h2><?php echo wp_kses( self::help_link( 'layout' ), Docsway_Render::allowed_html() ); ?></div>
				<h3 class="dsw-subhead"><?php esc_html_e( 'Layout', 'aneris-docsway' ); ?></h3>
				<?php self::layout_picker( $o ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dsw-back-url"><?php esc_html_e( 'Back link', 'aneris-docsway' ); ?></label></th>
						<td>
							<input type="url" id="dsw-back-url" name="back_url" class="regular-text" value="<?php echo esc_attr( $o['back_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
							<label class="screen-reader-text" for="dsw-back-label"><?php esc_html_e( 'Back link text', 'aneris-docsway' ); ?></label>
							<input type="text" id="dsw-back-label" name="back_label" class="regular-text" value="<?php echo esc_attr( $o['back_label'] ); ?>" placeholder="<?php echo esc_attr( sprintf( /* translators: %s: site name */ __( 'Back to %s', 'aneris-docsway' ), get_bloginfo( 'name' ) ) ); ?>">
							<p class="description"><?php esc_html_e( 'Where the Docsway docs bar and footer link back to, and the link text. Leave empty for your home page. Useful when your docs have a site of their own, for example on a subdomain.', 'aneris-docsway' ); ?></p>
						</td>
					</tr>
				</table>
				<h3 class="dsw-subhead"><?php esc_html_e( 'Style', 'aneris-docsway' ); ?> <?php echo wp_kses( self::help_link( 'styling' ), Docsway_Render::allowed_html() ); ?></h3>
				<table class="form-table" role="presentation">
					<tr>
						<td colspan="2" class="dsw-flush"><?php self::style_picker( $o ); ?></td>
					</tr>
				</table>
			</section>

			<section class="dsw-card" id="advanced">
				<h2><?php esc_html_e( 'Advanced', 'aneris-docsway' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'When deleting the plugin', 'aneris-docsway' ); ?></th>
						<td><label class="dsw-check"><input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( $o['delete_on_uninstall'] ); ?>> <?php esc_html_e( 'Also delete Docsway sections, menu order and settings', 'aneris-docsway' ); ?></label>
						<p class="description"><?php esc_html_e( 'Your pages are never deleted.', 'aneris-docsway' ); ?></p></td>
					</tr>
				</table>
			</section>

			<p class="submit dsw-submit"><button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save settings', 'aneris-docsway' ); ?></button></p>
			</div>
		</form>
		<?php do_action( 'docsway_settings_after_form', $o ); ?>
		<?php
		self::footer();
	}

	/**
	 * The submitted form, unslashed and sanitised as plain text (line breaks kept for
	 * multi-line fields). Each value is then cleaned for its own type by
	 * Docsway_Settings::sanitize(). Callers check the nonce and capability first.
	 */
	public static function posted() {
		return map_deep( wp_unslash( $_POST ), 'sanitize_textarea_field' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers run check_admin_referer() first.
	}

	public static function save_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'aneris-docsway' ) );
		}
		check_admin_referer( 'docsway_settings' );
		$in = self::posted();
		$changes = array(
			'post_types'    => isset( $in['post_types'] ) ? (array) $in['post_types'] : array( 'page' ),
			'taxonomy_mode' => isset( $in['taxonomy_mode'] ) ? $in['taxonomy_mode'] : 'own',
			'taxonomy'      => isset( $in['taxonomy'] ) ? $in['taxonomy'] : '',
			'toc_levels'    => isset( $in['toc_levels'] ) ? (array) $in['toc_levels'] : array( 2 ),
		);
		foreach ( array( 'accordion', 'toc_auto', 'prevnext_auto', 'crumbs_auto', 'copy_code', 'shortcut', 'log_searches', 'hide_extra_search', 'show_updated', 'show_reading', 'heading_links', 'feedback', 'best_answer', 'delete_on_uninstall' ) as $k ) {
			$changes[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		foreach ( array( 'home_page', 'results_page', 'results_target', 'toc_min', 'per_page', 'placeholder', 'style', 'accent', 'home_title', 'home_intro', 'topics_title', 'layout', 'sidebar', 'width', 'max_width', 'toc_position', 'chrome', 'back_url', 'back_label' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$changes[ $k ] = $in[ $k ];
			}
		}
		$changes['quick_searches'] = isset( $in['quick_searches'] ) ? (array) $in['quick_searches'] : array();
		$changes['topics']         = isset( $in['topics'] ) ? (array) $in['topics'] : array();
		/** Add-ons add their own fields from the posted form ($in is already sanitised as text). Values are cleaned again by docsway_settings_sanitize. */
		$changes = apply_filters( 'docsway_settings_changes', $changes, $in );
		Docsway_Settings::update( $changes );
		// Back to the section that was open.
		$section = isset( $in['section'] ) ? sanitize_key( $in['section'] ) : '';
		wp_safe_redirect( admin_url( 'admin.php?page=docsway-settings&saved=1' ) . ( $section ? '#' . $section : '' ) );
		exit;
	}

	// ── Search insights ─────────────────────────────────────────────────────

	public static function page_insights() {
		$failed = Docsway_Search::log_rows( true, 30 );
		$top    = Docsway_Search::log_rows( false, 30 );
		self::header( __( 'Search insights', 'aneris-docsway' ), __( 'What your readers look for, and what they cannot find.', 'aneris-docsway' ) );
		if ( ! Docsway_Settings::get( 'log_searches' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Search logging is switched off in Settings, so this list is not updating.', 'aneris-docsway' ) . '</p></div>';
		}
		?>
		<div class="dsw-grid dsw-grid--two">
			<section class="dsw-card">
				<div class="dsw-card__head"><h2><?php esc_html_e( 'Searches with no results', 'aneris-docsway' ); ?></h2><?php echo wp_kses( self::help_link( 'insights' ), Docsway_Render::allowed_html() ); ?></div>
				<p class="dsw-muted"><?php esc_html_e( 'Each of these is a question your docs did not answer. Write a page for it, or add the word people used to an existing page.', 'aneris-docsway' ); ?><?php do_action( 'docsway_insights_no_results_tip' ); ?></p>
				<?php self::log_table( $failed, true ); ?>
			</section>
			<section class="dsw-card">
				<h2><?php esc_html_e( 'Most searched', 'aneris-docsway' ); ?></h2>
				<p class="dsw-muted"><?php esc_html_e( 'Popular topics are worth keeping up to date and linking from your docs home page.', 'aneris-docsway' ); ?></p>
				<?php self::log_table( $top, false ); ?>
			</section>
		</div>
		<?php $votes = Docsway_Feedback::rows(); ?>
		<section class="dsw-card dsw-feedback-card" id="page-feedback">
			<div class="dsw-card__head"><h2><?php esc_html_e( 'Page feedback', 'aneris-docsway' ); ?></h2><?php echo wp_kses( self::help_link( 'insights' ), Docsway_Render::allowed_html() ); ?></div>
			<p class="dsw-muted"><?php esc_html_e( 'Answers to “Was this page helpful?”, least helpful first. Pages near the top are the ones to improve.', 'aneris-docsway' ); ?></p>
			<?php if ( ! Docsway_Settings::get( 'feedback' ) ) : ?>
				<div class="notice notice-warning inline"><p><?php esc_html_e( '“Was this page helpful?” is switched off in Settings, Navigation.', 'aneris-docsway' ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! $votes ) : ?>
				<div class="dsw-empty"><p><?php esc_html_e( 'No answers yet.', 'aneris-docsway' ); ?></p></div>
			<?php else : ?>
				<table class="widefat striped dsw-log">
					<thead><tr><th><?php esc_html_e( 'Page', 'aneris-docsway' ); ?></th><th><?php esc_html_e( 'Helpful', 'aneris-docsway' ); ?></th><th><?php esc_html_e( 'Not helpful', 'aneris-docsway' ); ?></th><th><?php esc_html_e( 'Score', 'aneris-docsway' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $votes as $v ) : $total = $v['yes'] + $v['no']; ?>
						<tr>
							<td><a href="<?php echo esc_url( get_permalink( $v['id'] ) ); ?>"><?php echo esc_html( $v['title'] ); ?></a> <a class="dsw-muted" href="<?php echo esc_url( (string) get_edit_post_link( $v['id'] ) ); ?>"><?php esc_html_e( 'Edit', 'aneris-docsway' ); ?></a></td>
							<td><?php echo (int) $v['yes']; ?></td>
							<td><?php echo (int) $v['no']; ?></td>
							<td><?php echo esc_html( $total ? round( 100 * $v['yes'] / $total ) . '%' : '–' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dsw-clear">
					<input type="hidden" name="action" value="docsway_reset_feedback">
					<?php wp_nonce_field( 'docsway_reset_feedback' ); ?>
					<label class="dsw-check dsw-check--inline"><input type="checkbox" name="confirm" value="1" required> <?php esc_html_e( 'I want to delete all page feedback', 'aneris-docsway' ); ?></label>
					<button type="submit" class="button"><?php esc_html_e( 'Reset feedback', 'aneris-docsway' ); ?></button>
				</form>
			<?php endif; ?>
		</section>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dsw-clear">
			<input type="hidden" name="action" value="docsway_clear_log">
			<?php wp_nonce_field( 'docsway_clear_log' ); ?>
			<label class="dsw-check dsw-check--inline"><input type="checkbox" name="confirm" value="1" required> <?php esc_html_e( 'I want to delete the search log', 'aneris-docsway' ); ?></label>
			<button type="submit" class="button"><?php esc_html_e( 'Clear log', 'aneris-docsway' ); ?></button>
		</form>
		<?php
		self::footer();
	}

	private static function log_table( array $rows, $failed ) {
		if ( ! $rows ) {
			echo '<div class="dsw-empty"><p>' . ( $failed ? esc_html__( 'Nothing here. Every logged search found something.', 'aneris-docsway' ) : esc_html__( 'No searches logged yet.', 'aneris-docsway' ) ) . '</p></div>';
			return;
		}
		echo '<table class="widefat striped dsw-log"><thead><tr><th>' . esc_html__( 'Search', 'aneris-docsway' ) . '</th><th class="num">' . esc_html__( 'Times', 'aneris-docsway' ) . '</th>';
		if ( ! $failed ) {
			echo '<th class="num">' . esc_html__( 'Results', 'aneris-docsway' ) . '</th>';
		}
		echo '<th>' . esc_html__( 'Last searched', 'aneris-docsway' ) . '</th></tr></thead><tbody>';
		$results = Docsway_Search::results_url();
		foreach ( $rows as $q => $r ) {
			$label = $results ? '<a href="' . esc_url( add_query_arg( 'dq', rawurlencode( $q ), $results ) ) . '" target="_blank" rel="noopener">' . esc_html( $q ) . '</a>' : esc_html( $q );
			echo wp_kses( '<tr><td>' . $label . '</td><td class="num">' . esc_html( number_format_i18n( $r['count'] ) ) . '</td>', Docsway_Render::allowed_html() );
			if ( ! $failed ) {
				echo '<td class="num">' . ( 0 === (int) $r['hits'] ? '<span class="dsw-pill dsw-pill--warn">0</span>' : esc_html( number_format_i18n( $r['hits'] ) ) ) . '</td>';
			}
			/* translators: %s: time ago */
			echo '<td>' . esc_html( sprintf( __( '%s ago', 'aneris-docsway' ), human_time_diff( $r['last'] ) ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public static function reset_feedback() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'aneris-docsway' ) );
		}
		check_admin_referer( 'docsway_reset_feedback' );
		Docsway_Feedback::reset();
		wp_safe_redirect( admin_url( 'admin.php?page=docsway-insights' ) );
		exit;
	}

	public static function clear_log() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'aneris-docsway' ) );
		}
		check_admin_referer( 'docsway_clear_log' );
		delete_option( Docsway_Search::LOG_OPTION );
		wp_safe_redirect( admin_url( 'admin.php?page=docsway-insights' ) );
		exit;
	}

	// ── AJAX ────────────────────────────────────────────────────────────────

	public static function ajax_save_order() {
		check_ajax_referer( 'docsway_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( __( 'You are not allowed to do this.', 'aneris-docsway' ), 403 );
		}
		// A JSON list of { key, position }: sanitised as text, decoded, then every key and position is checked below.
		$raw  = isset( $_POST['order'] ) ? sanitize_text_field( wp_unslash( $_POST['order'] ) ) : '';
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			wp_send_json_error( __( 'The order could not be read.', 'aneris-docsway' ) );
		}
		$map = array();
		foreach ( $data as $row ) {
			$key = isset( $row['key'] ) ? sanitize_key( $row['key'] ) : '';
			if ( $key && preg_match( '/^(term_\d+|child_\d+_(term|post)\d+)$/', $key ) ) {
				$map[ $key ] = isset( $row['position'] ) ? absint( $row['position'] ) : 0;
			}
		}
		// Keep page-level keys from before, merge the new ones.
		update_option( Docsway_Tree::ORDER_OPTION, $map, false );
		Docsway_Tree::flush();
		wp_send_json_success();
	}

	public static function ajax_reset_order() {
		check_ajax_referer( 'docsway_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( __( 'You are not allowed to do this.', 'aneris-docsway' ), 403 );
		}
		delete_option( Docsway_Tree::ORDER_OPTION );
		Docsway_Tree::flush();
		wp_send_json_success();
	}

	public static function ajax_flush() {
		check_ajax_referer( 'docsway_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( __( 'You are not allowed to do this.', 'aneris-docsway' ), 403 );
		}
		Docsway_Tree::flush();
		wp_send_json_success();
	}

	public static function ajax_save_link() {
		check_ajax_referer( 'docsway_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( __( 'You are not allowed to do this.', 'aneris-docsway' ), 403 );
		}
		$term = isset( $_POST['term'] ) ? absint( $_POST['term'] ) : 0;
		$post = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0;
		if ( ! $term || ! get_term( $term, Docsway_Settings::taxonomy() ) ) {
			wp_send_json_error( __( 'That section no longer exists.', 'aneris-docsway' ) );
		}
		if ( $post ) {
			update_term_meta( $term, Docsway_Tree::LINK_META, $post );
		} else {
			delete_term_meta( $term, Docsway_Tree::LINK_META );
		}
		Docsway_Tree::flush();
		wp_send_json_success();
	}

	public static function ajax_assign() {
		check_ajax_referer( 'docsway_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( __( 'You are not allowed to do this.', 'aneris-docsway' ), 403 );
		}
		$tax  = Docsway_Settings::taxonomy();
		$mode = isset( $_POST['mode'] ) ? sanitize_key( $_POST['mode'] ) : 'add';
		$term = isset( $_POST['term'] ) ? absint( $_POST['term'] ) : 0;
		$ids  = isset( $_POST['ids'] ) ? array_filter( array_map( 'absint', (array) $_POST['ids'] ) ) : array();
		if ( ! $ids ) {
			wp_send_json_error( __( 'Tick at least one page first.', 'aneris-docsway' ) );
		}
		if ( 'remove' !== $mode && ( ! $term || ! get_term( $term, $tax ) ) ) {
			wp_send_json_error( __( 'Choose a section first.', 'aneris-docsway' ) );
		}
		$names = array();
		foreach ( $ids as $id ) {
			/** Add-ons can allow other items, for example documents. */
			$allowed = apply_filters( 'docsway_can_assign', in_array( get_post_type( $id ), Docsway_Settings::post_types(), true ), $id );
			if ( ! current_user_can( 'edit_post', $id ) || ! $allowed ) {
				continue;
			}
			if ( 'remove' === $mode ) {
				wp_set_object_terms( $id, array(), $tax );
			} else {
				wp_set_object_terms( $id, array( $term ), $tax, 'add' === $mode );
			}
			$n = wp_get_object_terms( $id, $tax, array( 'fields' => 'names' ) );
			$names[ $id ] = is_wp_error( $n ) ? '' : implode( ', ', $n );
		}
		Docsway_Tree::flush();
		wp_send_json_success( array( 'names' => $names ) );
	}

	public static function ajax_add_section() {
		check_ajax_referer( 'docsway_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( __( 'You are not allowed to do this.', 'aneris-docsway' ), 403 );
		}
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$parent = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
		if ( '' === $name ) {
			wp_send_json_error( __( 'Give the section a name.', 'aneris-docsway' ) );
		}
		$res = wp_insert_term( $name, Docsway_Settings::taxonomy(), array( 'parent' => $parent ) );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( $res->get_error_message() );
		}
		wp_send_json_success(
			array(
				'id'      => $res['term_id'],
				'options' => self::section_options( $res['term_id'], __( 'Choose a section…', 'aneris-docsway' ) ),
				'parents' => self::section_options( 0, __( 'Top level', 'aneris-docsway' ) ),
			)
		);
	}
}
