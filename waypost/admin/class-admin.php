<?php
/**
 * Admin screens: Dashboard, Structure, Settings, Search insights, Help.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Admin {

	const CAP = 'manage_options';

	public static $hooks = array();

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'parent_file', array( __CLASS__, 'highlight_sections_menu' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WAYPOST_FILE ), array( __CLASS__, 'action_links' ) );
		add_action( 'admin_notices', array( __CLASS__, 'setup_notice' ) );

		add_action( 'admin_post_waypost_save_settings', array( __CLASS__, 'save_settings' ) );
		add_action( 'admin_post_waypost_clear_log', array( __CLASS__, 'clear_log' ) );

		foreach ( array( 'save_order', 'reset_order', 'flush', 'save_link', 'assign', 'add_section' ) as $a ) {
			add_action( 'wp_ajax_waypost_' . $a, array( __CLASS__, 'ajax_' . $a ) );
		}
	}

	// ── Menu ────────────────────────────────────────────────────────────────

	public static function menu() {
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" d="M9 1h2v3h5l2.5 2.5L16 9h-5v2h4l1.5 1.5L15 14h-4v5H9v-5H4l-1.5-1.5L4 11h5V9H4V4h5V1Z"/></svg>' );

		self::$hooks['dashboard'] = add_menu_page( __( 'Waypost', 'waypost' ), __( 'Waypost', 'waypost' ), self::CAP, 'waypost', array( __CLASS__, 'page_dashboard' ), $icon, 58 );
		add_submenu_page( 'waypost', __( 'Waypost dashboard', 'waypost' ), __( 'Dashboard', 'waypost' ), self::CAP, 'waypost', array( __CLASS__, 'page_dashboard' ) );
		self::$hooks['structure'] = add_submenu_page( 'waypost', __( 'Docs structure', 'waypost' ), __( 'Structure', 'waypost' ), self::CAP, 'waypost-structure', array( __CLASS__, 'page_structure' ) );
		if ( Waypost_Taxonomy::is_ready() ) {
			add_submenu_page( 'waypost', __( 'Doc sections', 'waypost' ), __( 'Sections', 'waypost' ), self::CAP, Waypost_Taxonomy::edit_url() );
		}
		self::$hooks['settings'] = add_submenu_page( 'waypost', __( 'Waypost settings', 'waypost' ), __( 'Settings', 'waypost' ), self::CAP, 'waypost-settings', array( __CLASS__, 'page_settings' ) );
		self::$hooks['insights'] = add_submenu_page( 'waypost', __( 'Search insights', 'waypost' ), __( 'Search insights', 'waypost' ), self::CAP, 'waypost-insights', array( __CLASS__, 'page_insights' ) );
		self::$hooks['help']     = add_submenu_page( 'waypost', __( 'Help and tutorials', 'waypost' ), __( 'Help', 'waypost' ), self::CAP, 'waypost-help', array( 'Waypost_Help', 'page' ) );

		foreach ( self::$hooks as $key => $hook ) {
			add_action( 'load-' . $hook, function () use ( $key ) {
				Waypost_Help::add_tabs( $key );
			} );
		}
	}

	public static function highlight_sections_menu( $parent ) {
		global $current_screen, $submenu_file;
		if ( $current_screen && 'edit-tags' === $current_screen->base && $current_screen->taxonomy === Waypost_Settings::taxonomy() ) {
			$submenu_file = Waypost_Taxonomy::edit_url(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			return 'waypost';
		}
		return $parent;
	}

	public static function action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=waypost' ) ) . '">' . esc_html__( 'Dashboard', 'waypost' ) . '</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=waypost-help' ) ) . '">' . esc_html__( 'Help', 'waypost' ) . '</a>'
		);
		return $links;
	}

	public static function setup_notice() {
		if ( Waypost_Settings::get( 'setup_done' ) || ! current_user_can( self::CAP ) ) {
			return;
		}
		$screen = get_current_screen();
		$is_wizard = isset( $_GET['page'] ) && Waypost_Wizard::SLUG === $_GET['page']; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $screen || $is_wizard || ( 'plugins' !== $screen->id && ! in_array( $screen->id, self::$hooks, true ) ) ) {
			return;
		}
		echo '<div class="notice notice-info waypost-notice"><p><strong>' . esc_html__( 'Waypost is installed.', 'waypost' ) . '</strong> '
			. esc_html__( 'The setup wizard takes about three minutes and gets your docs menu and search working.', 'waypost' )
			. '</p><p><a class="button button-primary" href="' . esc_url( Waypost_Wizard::url() ) . '">' . esc_html__( 'Start setup', 'waypost' ) . '</a></p></div>';
	}

	public static function assets( $hook ) {
		$ours = in_array( $hook, self::$hooks, true ) || false !== strpos( $hook, 'waypost' );
		if ( ! $ours ) {
			return;
		}
		// The front-end styles power the style preview.
		wp_enqueue_style( 'waypost-preview', WAYPOST_URL . 'assets/css/frontend.css', array(), WAYPOST_VERSION );
		wp_add_inline_style( 'waypost-preview', Waypost_Frontend::inline_css() );
		wp_enqueue_style( 'waypost-admin', WAYPOST_URL . 'assets/css/admin.css', array( 'waypost-preview' ), WAYPOST_VERSION );
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'waypost-admin', WAYPOST_URL . 'assets/js/admin.js', array( 'jquery', 'jquery-ui-sortable' ), WAYPOST_VERSION, true );
		wp_localize_script(
			'waypost-admin',
			'waypostAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'waypost_admin' ),
				'i18n'    => array(
					'saving'     => __( 'Saving…', 'waypost' ),
					'saved'      => __( 'Saved', 'waypost' ),
					'failed'     => __( 'Something went wrong. Reload the page and try again.', 'waypost' ),
					'confirmReset' => __( 'Click again to confirm. This puts every section back in alphabetical order.', 'waypost' ),
					'copied'     => __( 'Copied', 'waypost' ),
					'pickPages'  => __( 'Tick at least one page first.', 'waypost' ),
					'pickSection' => __( 'Choose a section first.', 'waypost' ),
					'nameNeeded' => __( 'Give the section a name.', 'waypost' ),
					'unsaved'    => __( 'You have unsaved order changes.', 'waypost' ),
				),
			)
		);
	}

	// ── Shared bits ─────────────────────────────────────────────────────────

	public static function header( $title, $sub = '' ) {
		echo '<div class="wrap waypost-wrap">';
		echo '<header class="wpst-head"><div><h1 class="wpst-head__title">' . esc_html( $title ) . '</h1>';
		if ( $sub ) {
			echo '<p class="wpst-head__sub">' . esc_html( $sub ) . '</p>';
		}
		echo '</div><a class="wpst-head__help" href="' . esc_url( admin_url( 'admin.php?page=waypost-help' ) ) . '"><span class="dashicons dashicons-editor-help" aria-hidden="true"></span>' . esc_html__( 'Help and tutorials', 'waypost' ) . '</a></header>';
		echo '<hr class="wp-header-end">';
	}

	public static function footer() {
		echo '</div>';
	}

	/** Small "how does this work?" link pointing into the Help page. */
	public static function help_link( $topic, $label = '' ) {
		return '<a class="wpst-helplink" href="' . esc_url( admin_url( 'admin.php?page=waypost-help#' . $topic ) ) . '">'
			. '<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>'
			. esc_html( $label ? $label : __( 'How this works', 'waypost' ) ) . '</a>';
	}

	/** Style cards, accent colour and a live preview. Used by Settings and the setup wizard. */
	public static function style_picker( array $o ) {
		?>
		<fieldset class="wpst-styles">
			<legend class="wpst-styles__legend"><?php esc_html_e( 'Style', 'waypost' ); ?></legend>
			<?php foreach ( Waypost_Settings::styles() as $key => $s ) : ?>
				<label class="wpst-style">
					<input type="radio" name="style" value="<?php echo esc_attr( $key ); ?>" data-accent="<?php echo esc_attr( $s['accent'] ); ?>" <?php checked( $o['style'], $key ); ?>>
					<span class="wpst-style__sample waypost-style-<?php echo esc_attr( $key ); ?>" style="--wpst-dot: <?php echo esc_attr( $s['accent'] ); ?>" aria-hidden="true">Aa<i></i></span>
					<span class="wpst-style__text"><strong><?php echo esc_html( $s['label'] ); ?></strong><small><?php echo esc_html( $s['desc'] ); ?></small></span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p class="wpst-accent">
			<label for="wpst-accent"><strong><?php esc_html_e( 'Accent colour', 'waypost' ); ?></strong></label>
			<input type="color" id="wpst-accent" name="accent" value="<?php echo esc_attr( $o['accent'] ); ?>">
			<span class="description"><?php esc_html_e( 'Used for the current page, links and buttons. Picking a style also picks a matching colour; you can change it afterwards.', 'waypost' ); ?></span>
		</p>
		<p class="wpst-look-label"><strong><?php esc_html_e( 'Preview', 'waypost' ); ?></strong> <span class="wpst-muted"><?php esc_html_e( 'Sample pages. With “Follow my theme”, your site uses your theme’s fonts and colours instead of these.', 'waypost' ); ?></span></p>
		<?php
		echo Waypost_Render::style_preview( $o['style'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
	}

	public static function copy_field( $text ) {
		return '<span class="wpst-copy"><code>' . esc_html( $text ) . '</code><button type="button" class="button button-small wpst-copy__btn" data-copy="' . esc_attr( $text ) . '">' . esc_html__( 'Copy', 'waypost' ) . '</button></span>';
	}

	private static function terms() {
		$terms = get_terms( array( 'taxonomy' => Waypost_Settings::taxonomy(), 'hide_empty' => false ) );
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
		$tax        = Waypost_Settings::taxonomy();
		$ready      = taxonomy_exists( $tax );
		$term_count = $ready ? (int) wp_count_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ) : 0;
		$doc_count  = 0;
		if ( $ready && $term_count ) {
			$q = new WP_Query(
				array(
					'post_type'      => Waypost_Settings::post_types(),
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'tax_query'      => array( array( 'taxonomy' => $tax, 'operator' => 'EXISTS' ) ),
				)
			);
			$doc_count = (int) $q->found_posts;
		}
		return array(
			'configured' => (bool) Waypost_Settings::get( 'setup_done' ) && $ready,
			'sections'   => $term_count,
			'docs'       => $doc_count,
			'results'    => '' !== Waypost_Search::results_url(),
			'placed'     => self::menu_is_placed(),
		);
	}

	/** Looks for the menu in content, Divi Theme Builder layouts and block widgets. */
	public static function menu_is_placed() {
		global $wpdb;
		$found = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND (post_content LIKE '%[waypost_menu%' OR post_content LIKE '%wp:waypost/menu%' OR post_content LIKE '%et_pb_waypost_menu%') LIMIT 1"
		);
		if ( $found ) {
			return true;
		}
		$widgets = wp_json_encode( get_option( 'widget_block', array() ) ) . wp_json_encode( get_option( 'widget_text', array() ) );
		return false !== strpos( $widgets, 'waypost/menu' ) || false !== strpos( $widgets, 'waypost_menu' );
	}

	// ── Dashboard ───────────────────────────────────────────────────────────

	public static function page_dashboard() {
		$s     = self::status();
		$log   = get_option( Waypost_Search::LOG_OPTION, array() );
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
				'title' => __( 'Choose what counts as documentation', 'waypost' ),
				'text'  => __( 'Pick the content types that hold your docs and how they are grouped.', 'waypost' ),
				'url'   => Waypost_Wizard::url( 'content' ),
				'cta'   => __( 'Open the wizard', 'waypost' ),
				'help'  => 'getting-started',
			),
			array(
				'done'  => $s['sections'] > 0,
				'title' => __( 'Create your sections', 'waypost' ),
				'text'  => __( 'Sections are the headings in your menu, such as Getting started or Troubleshooting.', 'waypost' ),
				'url'   => Waypost_Wizard::url( 'sections' ),
				'cta'   => __( 'Add sections', 'waypost' ),
				'help'  => 'sections',
			),
			array(
				'done'  => $s['docs'] > 0,
				'title' => __( 'Put pages into sections', 'waypost' ),
				'text'  => __( 'Only pages that belong to a section appear in the menu and in docs search.', 'waypost' ),
				'url'   => admin_url( 'admin.php?page=waypost-structure&tab=assign' ),
				'cta'   => __( 'Assign pages', 'waypost' ),
				'help'  => 'assigning',
			),
			array(
				'done'  => $s['results'],
				'title' => __( 'Set up the search results page', 'waypost' ),
				'text'  => __( 'Instant results work everywhere. A results page shows the full list when someone presses Enter.', 'waypost' ),
				'url'   => Waypost_Wizard::url( 'features' ),
				'cta'   => __( 'Set up search', 'waypost' ),
				'help'  => 'search',
			),
			array(
				'done'  => $s['placed'],
				'title' => __( 'Add the menu to your docs layout', 'waypost' ),
				'text'  => __( 'Place the Docs Menu in a sidebar with the block, the Divi module or the shortcode.', 'waypost' ),
				'url'   => admin_url( 'admin.php?page=waypost-help#placing' ),
				'cta'   => __( 'Show me how', 'waypost' ),
				'help'  => 'placing',
			),
		);
		$done = count( array_filter( wp_list_pluck( $steps, 'done' ) ) );

		self::header( __( 'Waypost', 'waypost' ), __( 'Documentation built from the pages you already have.', 'waypost' ) );
		?>
		<div class="wpst-grid wpst-grid--dash">
			<section class="wpst-card wpst-card--checklist">
				<div class="wpst-card__head">
					<h2><?php esc_html_e( 'Setup checklist', 'waypost' ); ?></h2>
					<span class="wpst-progress" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: done, 2: total */ __( '%1$d of %2$d steps done', 'waypost' ), $done, count( $steps ) ) ); ?>">
						<span class="wpst-progress__bar" style="width:<?php echo (int) round( $done / count( $steps ) * 100 ); ?>%"></span>
					</span>
					<span class="wpst-muted"><?php echo esc_html( sprintf( /* translators: 1: done, 2: total */ __( '%1$d of %2$d done', 'waypost' ), $done, count( $steps ) ) ); ?></span>
				</div>
				<ol class="wpst-steps">
					<?php foreach ( $steps as $st ) : ?>
						<li class="wpst-step<?php echo $st['done'] ? ' is-done' : ''; ?>">
							<span class="wpst-step__mark" aria-hidden="true"><?php echo $st['done'] ? '&#10003;' : ''; ?></span>
							<div class="wpst-step__body">
								<strong><?php echo esc_html( $st['title'] ); ?></strong>
								<span class="screen-reader-text"><?php echo $st['done'] ? esc_html__( '(done)', 'waypost' ) : esc_html__( '(to do)', 'waypost' ); ?></span>
								<p><?php echo esc_html( $st['text'] ); ?></p>
							</div>
							<div class="wpst-step__actions">
								<a class="button<?php echo $st['done'] ? '' : ' button-primary'; ?>" href="<?php echo esc_url( $st['url'] ); ?>"><?php echo esc_html( $st['cta'] ); ?></a>
								<?php echo self::help_link( $st['help'], __( 'Tutorial', 'waypost' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							</div>
						</li>
					<?php endforeach; ?>
				</ol>
				<p class="wpst-card__foot"><a href="<?php echo esc_url( Waypost_Wizard::url() ); ?>"><?php esc_html_e( 'Run the setup wizard again', 'waypost' ); ?></a></p>
			</section>

			<div class="wpst-stack">
				<section class="wpst-card">
					<h2><?php esc_html_e( 'At a glance', 'waypost' ); ?></h2>
					<dl class="wpst-stats">
						<div><dt><?php esc_html_e( 'Sections', 'waypost' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $s['sections'] ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Doc pages', 'waypost' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $s['docs'] ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Searches logged', 'waypost' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $total ) ); ?></dd></div>
						<div><dt><?php esc_html_e( 'With no results', 'waypost' ); ?></dt><dd class="<?php echo $fail ? 'is-warn' : ''; ?>"><?php echo esc_html( number_format_i18n( $fail ) ); ?></dd></div>
					</dl>
					<?php if ( $fail ) : ?>
						<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=waypost-insights' ) ); ?>"><?php esc_html_e( 'See what people could not find →', 'waypost' ); ?></a></p>
					<?php endif; ?>
				</section>

				<section class="wpst-card">
					<h2><?php esc_html_e( 'Shortcodes', 'waypost' ); ?></h2>
					<p class="wpst-muted"><?php esc_html_e( 'Paste these into a Divi Text module, a Shortcode block or a widget. Blocks with the same names are in the block inserter under “Docs”.', 'waypost' ); ?></p>
					<table class="wpst-codes">
						<tr><th><?php esc_html_e( 'Sidebar menu', 'waypost' ); ?></th><td><?php echo self::copy_field( '[waypost_menu]' ); // phpcs:ignore ?></td></tr>
						<tr><th><?php esc_html_e( 'Search box', 'waypost' ); ?></th><td><?php echo self::copy_field( '[waypost_search]' ); // phpcs:ignore ?></td></tr>
						<tr><th><?php esc_html_e( 'On this page', 'waypost' ); ?></th><td><?php echo self::copy_field( '[waypost_toc]' ); // phpcs:ignore ?></td></tr>
						<tr><th><?php esc_html_e( 'Previous / next', 'waypost' ); ?></th><td><?php echo self::copy_field( '[waypost_prev_next]' ); // phpcs:ignore ?></td></tr>
						<tr><th><?php esc_html_e( 'Breadcrumbs', 'waypost' ); ?></th><td><?php echo self::copy_field( '[waypost_breadcrumbs]' ); // phpcs:ignore ?></td></tr>
						<tr><th><?php esc_html_e( 'Results list', 'waypost' ); ?></th><td><?php echo self::copy_field( '[waypost_search_results]' ); // phpcs:ignore ?></td></tr>
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
			'order'  => __( 'Menu order', 'waypost' ),
			'assign' => __( 'Assign pages', 'waypost' ),
			'links'  => __( 'Section links', 'waypost' ),
		);
		if ( ! isset( $tabs[ $tab ] ) ) {
			$tab = 'order';
		}

		self::header( __( 'Docs structure', 'waypost' ), __( 'Decide what appears in the menu and in which order.', 'waypost' ) );

		if ( ! Waypost_Taxonomy::is_ready() ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'The category system Waypost is set to use no longer exists. Choose another one in Settings.', 'waypost' ) . '</p></div>';
			self::footer();
			return;
		}

		echo '<nav class="nav-tab-wrapper wpst-tabs">';
		foreach ( $tabs as $slug => $label ) {
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=waypost-structure&tab=' . $slug ) ),
				$tab === $slug ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';

		call_user_func( array( __CLASS__, 'tab_' . $tab ) );
		self::footer();
	}

	private static function tab_order() {
		$tree = Waypost_Tree::build( true );
		?>
		<div class="wpst-grid wpst-grid--side">
			<section class="wpst-card">
				<div class="wpst-card__head">
					<h2><?php esc_html_e( 'Drag to reorder', 'waypost' ); ?></h2>
					<?php echo self::help_link( 'ordering' ); // phpcs:ignore ?>
				</div>
				<?php if ( ! $tree ) : ?>
					<div class="wpst-empty">
						<p><strong><?php esc_html_e( 'Nothing to order yet.', 'waypost' ); ?></strong></p>
						<p><?php esc_html_e( 'Create a section and put a page in it, then come back here.', 'waypost' ); ?></p>
						<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=waypost-structure&tab=assign' ) ); ?>"><?php esc_html_e( 'Assign pages', 'waypost' ); ?></a></p>
					</div>
				<?php else : ?>
					<div class="wpst-order" id="waypost-order"><?php self::order_list( $tree, null ); ?></div>
					<div class="wpst-bar">
						<button type="button" class="button button-primary" id="waypost-save-order"><?php esc_html_e( 'Save order', 'waypost' ); ?></button>
						<button type="button" class="button" id="waypost-reset-order"><?php esc_html_e( 'Reset to A–Z', 'waypost' ); ?></button>
						<button type="button" class="button-link" id="waypost-flush"><?php esc_html_e( 'Rebuild menu cache', 'waypost' ); ?></button>
						<span class="wpst-status" id="waypost-order-status" aria-live="polite"></span>
					</div>
				<?php endif; ?>
			</section>
			<aside class="wpst-card wpst-card--tip">
				<h2><?php esc_html_e( 'Tips', 'waypost' ); ?></h2>
				<ul class="wpst-list">
					<li><?php esc_html_e( 'Drag the handle on the left of a row. Items move within their own group only.', 'waypost' ); ?></li>
					<li><?php esc_html_e( 'To move a section under another section, change its Parent on the Sections screen.', 'waypost' ); ?></li>
					<li><?php esc_html_e( 'To move a page to another section, use Assign pages.', 'waypost' ); ?></li>
					<li><?php esc_html_e( 'Greyed rows are empty sections. They stay hidden on the site until they contain a page.', 'waypost' ); ?></li>
				</ul>
				<p><a class="button" href="<?php echo esc_url( Waypost_Taxonomy::edit_url() ); ?>"><?php esc_html_e( 'Edit sections', 'waypost' ); ?></a></p>
			</aside>
		</div>
		<?php
	}

	private static function order_list( array $nodes, $parent ) {
		echo '<ul class="wpst-sortable" data-parent="' . esc_attr( null === $parent ? 0 : $parent ) . '">';
		foreach ( $nodes as $n ) {
			$key   = null === $parent ? 'term_' . $n['id'] : 'child_' . $parent . '_' . $n['type'] . $n['id'];
			$empty = 'term' === $n['type'] && ! $n['children'] && ! $n['url'];
			$edit  = 'term' === $n['type'] ? get_edit_term_link( $n['id'], Waypost_Settings::taxonomy() ) : get_edit_post_link( $n['id'] );
			echo '<li class="wpst-node wpst-node--' . esc_attr( $n['type'] ) . ( $empty ? ' is-empty' : '' ) . '" data-key="' . esc_attr( $key ) . '">';
			echo '<div class="wpst-node__row"><span class="wpst-node__handle dashicons dashicons-move" aria-hidden="true"></span>';
			echo '<span class="wpst-node__title">' . esc_html( $n['title'] ) . '</span>';
			echo '<span class="wpst-badge wpst-badge--' . esc_attr( $n['type'] ) . '">' . ( 'term' === $n['type'] ? esc_html__( 'Section', 'waypost' ) : esc_html__( 'Page', 'waypost' ) ) . '</span>';
			if ( 'term' === $n['type'] && $n['url'] ) {
				echo '<span class="wpst-badge wpst-badge--link" title="' . esc_attr__( 'This heading links to a page', 'waypost' ) . '">' . esc_html__( 'Linked', 'waypost' ) . '</span>';
			}
			if ( $empty ) {
				echo '<span class="wpst-muted wpst-node__note">' . esc_html__( 'empty, hidden on site', 'waypost' ) . '</span>';
			}
			if ( $edit ) {
				echo '<a class="wpst-node__edit" href="' . esc_url( $edit ) . '"><span class="screen-reader-text">' . esc_html__( 'Edit', 'waypost' ) . '</span><span class="dashicons dashicons-edit" aria-hidden="true"></span></a>';
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
		$tax    = Waypost_Settings::taxonomy();
		$filter = isset( $_GET['show'] ) ? sanitize_key( $_GET['show'] ) : 'unassigned'; // phpcs:ignore WordPress.Security.NonceVerification
		$args   = array(
			'post_type'      => Waypost_Settings::post_types(),
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
		$posts    = get_posts( $args );
		$has_secs = (bool) self::terms();
		?>
		<div class="wpst-grid wpst-grid--side">
			<section class="wpst-card">
				<div class="wpst-card__head">
					<h2><?php esc_html_e( 'Assign pages to a section', 'waypost' ); ?></h2>
					<?php echo self::help_link( 'assigning' ); // phpcs:ignore ?>
				</div>
				<p class="wpst-muted"><?php esc_html_e( 'Tick pages, choose a section, then press Add. A page can sit in more than one section.', 'waypost' ); ?></p>

				<div class="wpst-filter">
					<?php
					foreach ( array( 'unassigned' => __( 'Not in docs yet', 'waypost' ), 'assigned' => __( 'Already in docs', 'waypost' ), 'all' => __( 'All', 'waypost' ) ) as $k => $label ) {
						printf( '<a href="%s" class="%s">%s</a>', esc_url( admin_url( 'admin.php?page=waypost-structure&tab=assign&show=' . $k ) ), $k === $filter ? 'is-current' : '', esc_html( $label ) );
					}
					?>
					<input type="search" class="wpst-filter__search" id="waypost-assign-filter" placeholder="<?php esc_attr_e( 'Filter by title…', 'waypost' ); ?>">
				</div>

				<?php if ( ! $posts ) : ?>
					<div class="wpst-empty"><p><?php esc_html_e( 'No pages match this view.', 'waypost' ); ?></p></div>
				<?php else : ?>
					<div class="wpst-scroll"><table class="widefat striped wpst-assign" id="waypost-assign">
						<thead><tr>
							<td class="check-column"><input type="checkbox" id="waypost-assign-all" aria-label="<?php esc_attr_e( 'Select all', 'waypost' ); ?>"></td>
							<th><?php esc_html_e( 'Title', 'waypost' ); ?></th>
							<th><?php esc_html_e( 'Sections', 'waypost' ); ?></th>
							<th><?php esc_html_e( 'Status', 'waypost' ); ?></th>
						</tr></thead>
						<tbody>
						<?php foreach ( $posts as $p ) :
							$names = wp_get_object_terms( $p->ID, $tax, array( 'fields' => 'names' ) );
							$names = is_wp_error( $names ) ? array() : $names;
							?>
							<tr data-title="<?php echo esc_attr( mb_strtolower( $p->post_title ) ); ?>">
								<th class="check-column"><input type="checkbox" value="<?php echo (int) $p->ID; ?>" aria-label="<?php echo esc_attr( $p->post_title ); ?>"></th>
								<td><strong><?php echo esc_html( $p->post_title ? $p->post_title : __( '(no title)', 'waypost' ) ); ?></strong> <span class="wpst-muted"><?php echo esc_html( get_post_type_object( $p->post_type )->labels->singular_name ); ?></span></td>
								<td class="wpst-assign__terms"><?php echo $names ? esc_html( implode( ', ', $names ) ) : '<span class="wpst-muted">–</span>'; ?></td>
								<td><?php echo esc_html( get_post_status_object( $p->post_status )->label ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table></div>
				<?php endif; ?>

				<div class="wpst-bar">
					<label for="waypost-assign-term" class="screen-reader-text"><?php esc_html_e( 'Section', 'waypost' ); ?></label>
					<select id="waypost-assign-term"><?php echo self::section_options( 0, __( 'Choose a section…', 'waypost' ) ); // phpcs:ignore ?></select>
					<button type="button" class="button button-primary" data-assign="add"><?php esc_html_e( 'Add to section', 'waypost' ); ?></button>
					<button type="button" class="button" data-assign="move"><?php esc_html_e( 'Move to section', 'waypost' ); ?></button>
					<button type="button" class="button-link wpst-danger" data-assign="remove"><?php esc_html_e( 'Remove from docs', 'waypost' ); ?></button>
					<span class="wpst-status" id="waypost-assign-status" aria-live="polite"></span>
				</div>
			</section>

			<aside class="wpst-card wpst-card--tip">
				<h2><?php esc_html_e( 'New section', 'waypost' ); ?></h2>
				<?php if ( ! $has_secs ) : ?>
					<p><?php esc_html_e( 'You have no sections yet. Create the first one here.', 'waypost' ); ?></p>
				<?php endif; ?>
				<p><label for="waypost-new-name"><?php esc_html_e( 'Name', 'waypost' ); ?></label><br><input type="text" id="waypost-new-name" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Getting started', 'waypost' ); ?>"></p>
				<p><label for="waypost-new-parent"><?php esc_html_e( 'Inside', 'waypost' ); ?></label><br><select id="waypost-new-parent"><?php echo self::section_options( 0, __( 'Top level', 'waypost' ) ); // phpcs:ignore ?></select></p>
				<p><button type="button" class="button" id="waypost-add-section"><?php esc_html_e( 'Create section', 'waypost' ); ?></button> <span class="wpst-status" id="waypost-section-status" aria-live="polite"></span></p>
				<hr>
				<p class="wpst-muted"><?php esc_html_e( 'Move replaces a page’s current sections with the one you choose. Remove takes the page out of the docs menu and search but does not delete it.', 'waypost' ); ?></p>
			</aside>
		</div>
		<?php
	}

	private static function tab_links() {
		$terms = self::terms();
		?>
		<section class="wpst-card">
			<div class="wpst-card__head">
				<h2><?php esc_html_e( 'Make a section heading clickable', 'waypost' ); ?></h2>
				<?php echo self::help_link( 'section-links' ); // phpcs:ignore ?>
			</div>
			<p class="wpst-muted"><?php esc_html_e( 'By default a section heading only opens and closes its list. Link it to an overview page and the heading becomes a link too. That page is then shown as the heading instead of as an item inside the list.', 'waypost' ); ?></p>
			<?php if ( ! $terms ) : ?>
				<div class="wpst-empty"><p><?php esc_html_e( 'No sections yet.', 'waypost' ); ?></p></div>
			<?php else : ?>
				<table class="widefat striped wpst-links">
					<thead><tr><th><?php esc_html_e( 'Section', 'waypost' ); ?></th><th><?php esc_html_e( 'Heading links to', 'waypost' ); ?></th><th></th></tr></thead>
					<tbody>
					<?php
					$docs = self::all_docs_by_term();
					foreach ( $terms as $t ) :
						$linked = (int) get_term_meta( $t->term_id, Waypost_Tree::LINK_META, true );
						$parent = $t->parent ? get_term( $t->parent ) : null;
						?>
						<tr data-term="<?php echo (int) $t->term_id; ?>">
							<td><strong><?php echo esc_html( $t->name ); ?></strong><?php echo $parent && ! is_wp_error( $parent ) ? ' <span class="wpst-muted">' . esc_html( sprintf( /* translators: %s: parent section */ __( 'in %s', 'waypost' ), $parent->name ) ) . '</span>' : ''; ?></td>
							<td>
								<label class="screen-reader-text" for="waypost-link-<?php echo (int) $t->term_id; ?>"><?php esc_html_e( 'Linked page', 'waypost' ); ?></label>
								<select id="waypost-link-<?php echo (int) $t->term_id; ?>" class="wpst-link-select">
									<option value="0"><?php esc_html_e( 'Nothing (heading only)', 'waypost' ); ?></option>
									<?php
									$in_section = isset( $docs[ $t->term_id ] ) ? $docs[ $t->term_id ] : array();
									if ( $in_section ) {
										echo '<optgroup label="' . esc_attr__( 'Pages in this section', 'waypost' ) . '">';
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
							<td><button type="button" class="button wpst-link-save"><?php esc_html_e( 'Save', 'waypost' ); ?></button> <span class="wpst-status" aria-live="polite"></span></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p class="wpst-muted"><?php esc_html_e( 'Only pages already inside a section are offered, so the heading always points somewhere relevant.', 'waypost' ); ?></p>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function all_docs_by_term() {
		$terms = self::terms();
		return $terms ? Waypost_Tree::posts_by_term( wp_list_pluck( $terms, 'term_id' ) ) : array();
	}

	// ── Settings ────────────────────────────────────────────────────────────

	public static function page_settings() {
		$o     = Waypost_Settings::all();
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		$cands = Waypost_Taxonomy::candidates();
		$pages = get_pages( array( 'post_status' => 'publish' ) );

		self::header( __( 'Waypost settings', 'waypost' ) );
		if ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'waypost' ) . '</p></div>';
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpst-settings">
			<input type="hidden" name="action" value="waypost_save_settings">
			<?php wp_nonce_field( 'waypost_settings' ); ?>

			<section class="wpst-card" id="content">
				<div class="wpst-card__head"><h2><?php esc_html_e( 'Content', 'waypost' ); ?></h2><?php echo self::help_link( 'getting-started' ); // phpcs:ignore ?></div>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Docs live in', 'waypost' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Content types', 'waypost' ); ?></legend>
								<?php foreach ( $types as $type ) : ?>
									<label class="wpst-check"><input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $o['post_types'], true ) ); ?>> <?php echo esc_html( $type->labels->name ); ?></label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Most sites keep docs in Pages. Tick more types if your docs are spread across them.', 'waypost' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Group docs with', 'waypost' ); ?></th>
						<td>
							<fieldset>
								<label class="wpst-radio"><input type="radio" name="taxonomy_mode" value="own" <?php checked( 'own', $o['taxonomy_mode'] ); ?>> <strong><?php esc_html_e( 'Waypost doc sections', 'waypost' ); ?></strong> <span class="wpst-muted"><?php esc_html_e( '(recommended)', 'waypost' ); ?></span></label>
								<label class="wpst-radio"><input type="radio" name="taxonomy_mode" value="existing" <?php checked( 'existing', $o['taxonomy_mode'] ); ?> <?php disabled( ! $cands ); ?>> <strong><?php esc_html_e( 'A category system I already have', 'waypost' ); ?></strong></label>
								<div class="wpst-indent">
									<select name="taxonomy" <?php disabled( ! $cands ); ?>>
										<?php foreach ( $cands as $slug => $c ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $o['taxonomy'], $slug ); ?>><?php echo esc_html( sprintf( '%s (%s, %d)', $c['label'], $slug, $c['terms'] ) ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Useful when you already organised docs with a custom taxonomy, for example one made with CPT UI.', 'waypost' ); ?></p>
								</div>
							</fieldset>
						</td>
					</tr>
				</table>
			</section>

			<section class="wpst-card" id="navigation">
				<div class="wpst-card__head"><h2><?php esc_html_e( 'Navigation', 'waypost' ); ?></h2><?php echo self::help_link( 'page-features' ); // phpcs:ignore ?></div>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Menu behaviour', 'waypost' ); ?></th>
						<td><label class="wpst-check"><input type="checkbox" name="accordion" value="1" <?php checked( $o['accordion'] ); ?>> <?php esc_html_e( 'Keep one top-level section open at a time', 'waypost' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Add to every doc page', 'waypost' ); ?></th>
						<td>
							<label class="wpst-check"><input type="checkbox" name="toc_auto" value="1" <?php checked( $o['toc_auto'] ); ?>> <?php esc_html_e( '“On this page” contents at the top', 'waypost' ); ?></label>
							<label class="wpst-check"><input type="checkbox" name="prevnext_auto" value="1" <?php checked( $o['prevnext_auto'] ); ?>> <?php esc_html_e( 'Previous and next links at the bottom', 'waypost' ); ?></label>
							<label class="wpst-check"><input type="checkbox" name="crumbs_auto" value="1" <?php checked( $o['crumbs_auto'] ); ?>> <?php esc_html_e( 'Breadcrumbs at the top', 'waypost' ); ?></label>
							<label class="wpst-check"><input type="checkbox" name="copy_code" value="1" <?php checked( $o['copy_code'] ); ?>> <?php esc_html_e( 'Copy button on code blocks', 'waypost' ); ?></label>
							<p class="description"><?php esc_html_e( 'Untick any of these if you place the block, Divi module or shortcode yourself, for example in a Theme Builder sidebar.', 'waypost' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Contents shows', 'waypost' ); ?></th>
						<td>
							<?php foreach ( array( 2, 3, 4 ) as $lvl ) : ?>
								<label class="wpst-check wpst-check--inline"><input type="checkbox" name="toc_levels[]" value="<?php echo (int) $lvl; ?>" <?php checked( in_array( $lvl, $o['toc_levels'], true ) ); ?>> H<?php echo (int) $lvl; ?></label>
							<?php endforeach; ?>
							<p><label><?php esc_html_e( 'Only when a page has at least', 'waypost' ); ?> <input type="number" name="toc_min" min="1" max="10" value="<?php echo (int) $o['toc_min']; ?>" class="small-text"> <?php esc_html_e( 'headings', 'waypost' ); ?></label></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpst-home"><?php esc_html_e( 'Docs home page', 'waypost' ); ?></label></th>
						<td>
							<select id="wpst-home" name="home_page"><option value="0"><?php esc_html_e( 'None', 'waypost' ); ?></option>
								<?php foreach ( $pages as $p ) : ?><option value="<?php echo (int) $p->ID; ?>" <?php selected( $o['home_page'], $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option><?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'Used as the first breadcrumb.', 'waypost' ); ?></p>
						</td>
					</tr>
				</table>
			</section>

			<section class="wpst-card" id="search">
				<div class="wpst-card__head"><h2><?php esc_html_e( 'Search', 'waypost' ); ?></h2><?php echo self::help_link( 'search' ); // phpcs:ignore ?></div>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Full results show', 'waypost' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Where full results show', 'waypost' ); ?></legend>
								<label class="wpst-radio"><input type="radio" name="results_target" value="home" <?php checked( 'home', $o['results_target'] ); ?>> <strong><?php esc_html_e( 'On the docs home page, inside the search home', 'waypost' ); ?></strong> <span class="wpst-muted"><?php esc_html_e( '(recommended)', 'waypost' ); ?></span></label>
								<p class="description wpst-indent"><?php esc_html_e( 'Results appear below the search box on the same page, so your layout and sidebar menu stay in place. Uses the Docs home page set under Navigation, which needs the Docs Search Home block, module or shortcode.', 'waypost' ); ?></p>
								<?php if ( 'home' === $o['results_target'] && ! Waypost_Search::results_page_id() ) : ?>
									<p class="wpst-danger wpst-indent"><?php esc_html_e( 'Choose a Docs home page under Navigation, or results have nowhere to show.', 'waypost' ); ?></p>
								<?php endif; ?>
								<label class="wpst-radio"><input type="radio" name="results_target" value="page" <?php checked( 'page', $o['results_target'] ); ?>> <strong><?php esc_html_e( 'On a separate results page', 'waypost' ); ?></strong></label>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpst-results"><?php esc_html_e( 'Results page', 'waypost' ); ?></label></th>
						<td>
							<select id="wpst-results" name="results_page"><option value="0"><?php esc_html_e( 'None (instant results only)', 'waypost' ); ?></option>
								<?php foreach ( $pages as $p ) : ?><option value="<?php echo (int) $p->ID; ?>" <?php selected( $o['results_page'], $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option><?php endforeach; ?>
							</select>
							<p class="description"><?php echo wp_kses( sprintf( /* translators: %s: shortcode */ __( 'Only used with a separate results page. It must contain %s; the setup wizard can create it for you.', 'waypost' ), '<code>[waypost_search_results]</code>' ), array( 'code' => array() ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpst-ph"><?php esc_html_e( 'Placeholder text', 'waypost' ); ?></label></th>
						<td><input type="text" id="wpst-ph" name="placeholder" class="regular-text" value="<?php echo esc_attr( $o['placeholder'] ); ?>" placeholder="<?php esc_attr_e( 'Search documentation…', 'waypost' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpst-per"><?php esc_html_e( 'Results per page', 'waypost' ); ?></label></th>
						<td><input type="number" id="wpst-per" name="per_page" min="5" max="50" class="small-text" value="<?php echo (int) $o['per_page']; ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Extras', 'waypost' ); ?></th>
						<td>
							<label class="wpst-check"><input type="checkbox" name="shortcut" value="1" <?php checked( $o['shortcut'] ); ?>> <?php esc_html_e( 'Open search with Ctrl+K, Cmd+K or / on doc pages', 'waypost' ); ?></label>
							<label class="wpst-check"><input type="checkbox" name="hide_extra_search" value="1" <?php checked( $o['hide_extra_search'] ); ?>> <?php esc_html_e( 'On the results page, hide other docs search boxes, such as one in a sidebar', 'waypost' ); ?></label>
							<label class="wpst-check"><input type="checkbox" name="log_searches" value="1" <?php checked( $o['log_searches'] ); ?>> <?php esc_html_e( 'Log search terms for Search insights', 'waypost' ); ?></label>
							<p class="description"><?php esc_html_e( 'Only the words searched and how many results they found are stored. No IP addresses or user details.', 'waypost' ); ?></p>
						</td>
					</tr>
				</table>
			</section>

			<section class="wpst-card" id="search-home">
				<div class="wpst-card__head"><h2><?php esc_html_e( 'Search home', 'waypost' ); ?></h2><?php echo self::help_link( 'search' ); // phpcs:ignore ?></div>
				<p><?php echo wp_kses( __( 'A welcoming start page for your docs: a heading, a large search box, quick search buttons and topic cards. Add it with the <strong>Docs Search Home</strong> block, the <strong>Waypost Search Home</strong> Divi module or this shortcode:', 'waypost' ), array( 'strong' => array() ) ); ?></p>
				<?php echo self::copy_field( '[waypost_search_home]' ); // phpcs:ignore ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wpst-home-title"><?php esc_html_e( 'Heading', 'waypost' ); ?></label></th>
						<td><input type="text" id="wpst-home-title" name="home_title" class="regular-text" value="<?php echo esc_attr( $o['home_title'] ); ?>" placeholder="<?php esc_attr_e( 'How can we help?', 'waypost' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="wpst-home-intro"><?php esc_html_e( 'Intro line', 'waypost' ); ?></label></th>
						<td><input type="text" id="wpst-home-intro" name="home_intro" class="large-text" value="<?php echo esc_attr( $o['home_intro'] ); ?>" placeholder="<?php esc_attr_e( 'Optional, for example: Search the guides or pick a topic below.', 'waypost' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Quick searches', 'waypost' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'Up to six buttons under the search box. One click runs the search. Questions people really ask work best. They also appear in the Ctrl+K search window.', 'waypost' ); ?></p>
							<table class="wpst-rows" id="wpst-quick">
								<thead><tr><th>#</th><th><?php esc_html_e( 'Button text', 'waypost' ); ?></th><th><?php esc_html_e( 'Searches for', 'waypost' ); ?> <span class="wpst-muted"><?php esc_html_e( '(optional)', 'waypost' ); ?></span></th></tr></thead>
								<tbody>
								<?php for ( $i = 0; $i < Waypost_Settings::MAX_ITEMS; $i++ ) : $q = isset( $o['quick_searches'][ $i ] ) ? $o['quick_searches'][ $i ] : array( 'label' => '', 'query' => '' ); ?>
									<tr>
										<td><?php echo (int) ( $i + 1 ); ?></td>
										<td><input type="text" name="quick_searches[<?php echo (int) $i; ?>][label]" value="<?php echo esc_attr( $q['label'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: row number */ __( 'Button %d text', 'waypost' ), $i + 1 ) ); ?>" placeholder="<?php esc_attr_e( 'How do I install it?', 'waypost' ); ?>"></td>
										<td><input type="text" name="quick_searches[<?php echo (int) $i; ?>][query]" value="<?php echo esc_attr( $q['query'] === $q['label'] ? '' : $q['query'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: row number */ __( 'Button %d search words', 'waypost' ), $i + 1 ) ); ?>" placeholder="<?php esc_attr_e( 'install', 'waypost' ); ?>"></td>
									</tr>
								<?php endfor; ?>
								</tbody>
							</table>
							<p class="description"><?php esc_html_e( 'Leave “Searches for” empty to search for the button text itself. Short keywords usually find more pages than whole questions.', 'waypost' ); ?></p>
							<?php
							$popular = array();
							foreach ( Waypost_Search::log_rows( false, 20 ) as $term => $row ) {
								if ( (int) $row['hits'] > 0 ) {
									$popular[] = (string) $term;
								}
							}
							if ( $popular ) :
								?>
								<p><button type="button" class="button" id="wpst-quick-fill" data-terms="<?php echo esc_attr( wp_json_encode( array_slice( $popular, 0, Waypost_Settings::MAX_ITEMS ) ) ); ?>"><?php esc_html_e( 'Fill empty rows from Search insights', 'waypost' ); ?></button> <span class="description"><?php esc_html_e( 'Uses the most searched words that found results.', 'waypost' ); ?></span></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wpst-topics-title"><?php esc_html_e( 'Topic cards heading', 'waypost' ); ?></label></th>
						<td><input type="text" id="wpst-topics-title" name="topics_title" class="regular-text" value="<?php echo esc_attr( $o['topics_title'] ); ?>" placeholder="<?php esc_attr_e( 'Popular topics', 'waypost' ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Topic cards', 'waypost' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'Up to six shortcuts to your most useful pages, such as an install guide or quick start. A card needs a title and a page.', 'waypost' ); ?></p>
							<table class="wpst-rows" id="wpst-topics">
								<thead><tr><th>#</th><th><?php esc_html_e( 'Icon', 'waypost' ); ?></th><th><?php esc_html_e( 'Title', 'waypost' ); ?></th><th><?php esc_html_e( 'Short description', 'waypost' ); ?></th><th><?php esc_html_e( 'Links to', 'waypost' ); ?></th></tr></thead>
								<tbody>
								<?php for ( $i = 0; $i < Waypost_Settings::MAX_ITEMS; $i++ ) : $tp = isset( $o['topics'][ $i ] ) ? $o['topics'][ $i ] : array( 'icon' => 'book', 'title' => '', 'text' => '', 'page' => 0 ); ?>
									<tr>
										<td><?php echo (int) ( $i + 1 ); ?></td>
										<td>
											<select name="topics[<?php echo (int) $i; ?>][icon]" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: row number */ __( 'Card %d icon', 'waypost' ), $i + 1 ) ); ?>">
												<?php foreach ( Waypost_Render::icons() as $key => $icon ) : ?>
													<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $tp['icon'], $key ); ?>><?php echo esc_html( $icon[0] ); ?></option>
												<?php endforeach; ?>
											</select>
										</td>
										<td><input type="text" name="topics[<?php echo (int) $i; ?>][title]" value="<?php echo esc_attr( $tp['title'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: row number */ __( 'Card %d title', 'waypost' ), $i + 1 ) ); ?>" placeholder="<?php esc_attr_e( 'Install guide', 'waypost' ); ?>"></td>
										<td><input type="text" name="topics[<?php echo (int) $i; ?>][text]" value="<?php echo esc_attr( $tp['text'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: row number */ __( 'Card %d description', 'waypost' ), $i + 1 ) ); ?>" placeholder="<?php esc_attr_e( 'System requirements and setup', 'waypost' ); ?>"></td>
										<td>
											<select name="topics[<?php echo (int) $i; ?>][page]" aria-label="<?php echo esc_attr( sprintf( /* translators: %d: row number */ __( 'Card %d page', 'waypost' ), $i + 1 ) ); ?>">
												<option value="0"><?php esc_html_e( 'Choose a page…', 'waypost' ); ?></option>
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

			<section class="wpst-card" id="appearance">
				<div class="wpst-card__head"><h2><?php esc_html_e( 'Appearance', 'waypost' ); ?></h2><?php echo self::help_link( 'styling' ); // phpcs:ignore ?></div>
				<table class="form-table" role="presentation">
					<tr>
						<td colspan="2" class="wpst-flush"><?php self::style_picker( $o ); ?></td>
					</tr>
				</table>
			</section>

			<section class="wpst-card" id="advanced">
				<h2><?php esc_html_e( 'Advanced', 'waypost' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'When deleting the plugin', 'waypost' ); ?></th>
						<td><label class="wpst-check"><input type="checkbox" name="delete_on_uninstall" value="1" <?php checked( $o['delete_on_uninstall'] ); ?>> <?php esc_html_e( 'Also delete Waypost sections, menu order and settings', 'waypost' ); ?></label>
						<p class="description"><?php esc_html_e( 'Your pages are never deleted.', 'waypost' ); ?></p></td>
					</tr>
				</table>
			</section>

			<p class="submit wpst-submit"><button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save settings', 'waypost' ); ?></button></p>
		</form>
		<?php
		self::footer();
	}

	public static function save_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to change these settings.', 'waypost' ) );
		}
		check_admin_referer( 'waypost_settings' );
		$in = wp_unslash( $_POST );
		$changes = array(
			'post_types'    => isset( $in['post_types'] ) ? (array) $in['post_types'] : array( 'page' ),
			'taxonomy_mode' => isset( $in['taxonomy_mode'] ) ? $in['taxonomy_mode'] : 'own',
			'taxonomy'      => isset( $in['taxonomy'] ) ? $in['taxonomy'] : '',
			'toc_levels'    => isset( $in['toc_levels'] ) ? (array) $in['toc_levels'] : array( 2 ),
		);
		foreach ( array( 'accordion', 'toc_auto', 'prevnext_auto', 'crumbs_auto', 'copy_code', 'shortcut', 'log_searches', 'hide_extra_search', 'delete_on_uninstall' ) as $k ) {
			$changes[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
		}
		foreach ( array( 'home_page', 'results_page', 'results_target', 'toc_min', 'per_page', 'placeholder', 'style', 'accent', 'home_title', 'home_intro', 'topics_title' ) as $k ) {
			if ( isset( $in[ $k ] ) ) {
				$changes[ $k ] = $in[ $k ];
			}
		}
		$changes['quick_searches'] = isset( $in['quick_searches'] ) ? (array) $in['quick_searches'] : array();
		$changes['topics']         = isset( $in['topics'] ) ? (array) $in['topics'] : array();
		Waypost_Settings::update( $changes );
		wp_safe_redirect( admin_url( 'admin.php?page=waypost-settings&saved=1' ) );
		exit;
	}

	// ── Search insights ─────────────────────────────────────────────────────

	public static function page_insights() {
		$failed = Waypost_Search::log_rows( true, 30 );
		$top    = Waypost_Search::log_rows( false, 30 );
		self::header( __( 'Search insights', 'waypost' ), __( 'What your readers look for, and what they cannot find.', 'waypost' ) );
		if ( ! Waypost_Settings::get( 'log_searches' ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Search logging is switched off in Settings, so this list is not updating.', 'waypost' ) . '</p></div>';
		}
		?>
		<div class="wpst-grid wpst-grid--two">
			<section class="wpst-card">
				<div class="wpst-card__head"><h2><?php esc_html_e( 'Searches with no results', 'waypost' ); ?></h2><?php echo self::help_link( 'insights' ); // phpcs:ignore ?></div>
				<p class="wpst-muted"><?php esc_html_e( 'Each of these is a question your docs did not answer. Write a page for it, or add the word people used to an existing page.', 'waypost' ); ?></p>
				<?php self::log_table( $failed, true ); ?>
			</section>
			<section class="wpst-card">
				<h2><?php esc_html_e( 'Most searched', 'waypost' ); ?></h2>
				<p class="wpst-muted"><?php esc_html_e( 'Popular topics are worth keeping up to date and linking from your docs home page.', 'waypost' ); ?></p>
				<?php self::log_table( $top, false ); ?>
			</section>
		</div>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpst-clear">
			<input type="hidden" name="action" value="waypost_clear_log">
			<?php wp_nonce_field( 'waypost_clear_log' ); ?>
			<label class="wpst-check wpst-check--inline"><input type="checkbox" name="confirm" value="1" required> <?php esc_html_e( 'I want to delete the search log', 'waypost' ); ?></label>
			<button type="submit" class="button"><?php esc_html_e( 'Clear log', 'waypost' ); ?></button>
		</form>
		<?php
		self::footer();
	}

	private static function log_table( array $rows, $failed ) {
		if ( ! $rows ) {
			echo '<div class="wpst-empty"><p>' . ( $failed ? esc_html__( 'Nothing here. Every logged search found something.', 'waypost' ) : esc_html__( 'No searches logged yet.', 'waypost' ) ) . '</p></div>';
			return;
		}
		echo '<table class="widefat striped wpst-log"><thead><tr><th>' . esc_html__( 'Search', 'waypost' ) . '</th><th class="num">' . esc_html__( 'Times', 'waypost' ) . '</th>';
		if ( ! $failed ) {
			echo '<th class="num">' . esc_html__( 'Results', 'waypost' ) . '</th>';
		}
		echo '<th>' . esc_html__( 'Last searched', 'waypost' ) . '</th></tr></thead><tbody>';
		$results = Waypost_Search::results_url();
		foreach ( $rows as $q => $r ) {
			$label = $results ? '<a href="' . esc_url( add_query_arg( 'dq', rawurlencode( $q ), $results ) ) . '" target="_blank" rel="noopener">' . esc_html( $q ) . '</a>' : esc_html( $q );
			echo '<tr><td>' . $label . '</td><td class="num">' . esc_html( number_format_i18n( $r['count'] ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( ! $failed ) {
				echo '<td class="num">' . ( 0 === (int) $r['hits'] ? '<span class="wpst-pill wpst-pill--warn">0</span>' : esc_html( number_format_i18n( $r['hits'] ) ) ) . '</td>';
			}
			/* translators: %s: time ago */
			echo '<td>' . esc_html( sprintf( __( '%s ago', 'waypost' ), human_time_diff( $r['last'] ) ) ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public static function clear_log() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'waypost' ) );
		}
		check_admin_referer( 'waypost_clear_log' );
		delete_option( Waypost_Search::LOG_OPTION );
		wp_safe_redirect( admin_url( 'admin.php?page=waypost-insights' ) );
		exit;
	}

	// ── AJAX ────────────────────────────────────────────────────────────────

	private static function guard() {
		check_ajax_referer( 'waypost_admin', 'nonce' );
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( __( 'You are not allowed to do this.', 'waypost' ), 403 );
		}
	}

	public static function ajax_save_order() {
		self::guard();
		$data = json_decode( wp_unslash( isset( $_POST['order'] ) ? $_POST['order'] : '' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( ! is_array( $data ) ) {
			wp_send_json_error( __( 'The order could not be read.', 'waypost' ) );
		}
		$map = array();
		foreach ( $data as $row ) {
			$key = isset( $row['key'] ) ? sanitize_key( $row['key'] ) : '';
			if ( $key && preg_match( '/^(term_\d+|child_\d+_(term|post)\d+)$/', $key ) ) {
				$map[ $key ] = (int) $row['position'];
			}
		}
		// Keep page-level keys from before, merge the new ones.
		update_option( Waypost_Tree::ORDER_OPTION, $map, false );
		Waypost_Tree::flush();
		wp_send_json_success();
	}

	public static function ajax_reset_order() {
		self::guard();
		delete_option( Waypost_Tree::ORDER_OPTION );
		Waypost_Tree::flush();
		wp_send_json_success();
	}

	public static function ajax_flush() {
		self::guard();
		Waypost_Tree::flush();
		wp_send_json_success();
	}

	public static function ajax_save_link() {
		self::guard();
		$term = isset( $_POST['term'] ) ? absint( $_POST['term'] ) : 0;
		$post = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0;
		if ( ! $term || ! get_term( $term, Waypost_Settings::taxonomy() ) ) {
			wp_send_json_error( __( 'That section no longer exists.', 'waypost' ) );
		}
		if ( $post ) {
			update_term_meta( $term, Waypost_Tree::LINK_META, $post );
		} else {
			delete_term_meta( $term, Waypost_Tree::LINK_META );
		}
		Waypost_Tree::flush();
		wp_send_json_success();
	}

	public static function ajax_assign() {
		self::guard();
		$tax  = Waypost_Settings::taxonomy();
		$mode = isset( $_POST['mode'] ) ? sanitize_key( $_POST['mode'] ) : 'add';
		$term = isset( $_POST['term'] ) ? absint( $_POST['term'] ) : 0;
		$ids  = isset( $_POST['ids'] ) ? array_filter( array_map( 'absint', (array) $_POST['ids'] ) ) : array();
		if ( ! $ids ) {
			wp_send_json_error( __( 'Tick at least one page first.', 'waypost' ) );
		}
		if ( 'remove' !== $mode && ( ! $term || ! get_term( $term, $tax ) ) ) {
			wp_send_json_error( __( 'Choose a section first.', 'waypost' ) );
		}
		$names = array();
		foreach ( $ids as $id ) {
			if ( ! current_user_can( 'edit_post', $id ) || ! in_array( get_post_type( $id ), Waypost_Settings::post_types(), true ) ) {
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
		Waypost_Tree::flush();
		wp_send_json_success( array( 'names' => $names ) );
	}

	public static function ajax_add_section() {
		self::guard();
		$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$parent = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
		if ( '' === $name ) {
			wp_send_json_error( __( 'Give the section a name.', 'waypost' ) );
		}
		$res = wp_insert_term( $name, Waypost_Settings::taxonomy(), array( 'parent' => $parent ) );
		if ( is_wp_error( $res ) ) {
			wp_send_json_error( $res->get_error_message() );
		}
		wp_send_json_success(
			array(
				'id'      => $res['term_id'],
				'options' => self::section_options( $res['term_id'], __( 'Choose a section…', 'waypost' ) ),
				'parents' => self::section_options( 0, __( 'Top level', 'waypost' ) ),
			)
		);
	}
}
