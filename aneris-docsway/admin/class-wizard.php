<?php
/**
 * First-run setup wizard.
 */

defined( 'ABSPATH' ) || exit;

class Docsway_Wizard {

	const SLUG = 'docsway-setup';

	public static function steps() {
		return array(
			'welcome'  => __( 'Welcome', 'aneris-docsway' ),
			'content'  => __( 'Your docs', 'aneris-docsway' ),
			'sections' => __( 'Sections', 'aneris-docsway' ),
			'assign'   => __( 'Pages', 'aneris-docsway' ),
			'look'     => __( 'Look', 'aneris-docsway' ),
			'features' => __( 'Extras', 'aneris-docsway' ),
			'done'     => __( 'Done', 'aneris-docsway' ),
		);
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ) );
		add_action( 'admin_post_docsway_wizard', array( __CLASS__, 'save' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
	}

	public static function url( $step = 'welcome' ) {
		return admin_url( 'admin.php?page=' . self::SLUG . '&step=' . $step );
	}

	public static function register() {
		// Hidden page: reachable by URL, not listed in the menu.
		$hook = add_submenu_page( 'options.php', __( 'Docsway setup', 'aneris-docsway' ), __( 'Docsway setup', 'aneris-docsway' ), Docsway_Admin::CAP, self::SLUG, array( __CLASS__, 'render' ) );
		Docsway_Admin::$hooks['wizard'] = $hook;
	}

	public static function body_class( $classes ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return self::SLUG === $page ? $classes . ' docsway-wizard-screen' : $classes;
	}

	public static function maybe_redirect() {
		if ( ! get_transient( 'docsway_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'docsway_activation_redirect' );
		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) || ! current_user_can( Docsway_Admin::CAP ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	private static function current() {
		$step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : 'welcome'; // phpcs:ignore WordPress.Security.NonceVerification
		return array_key_exists( $step, self::steps() ) ? $step : 'welcome';
	}

	private static function next( $step ) {
		$keys = array_keys( self::steps() );
		$i    = array_search( $step, $keys, true );
		return isset( $keys[ $i + 1 ] ) ? $keys[ $i + 1 ] : 'done';
	}

	private static function prev( $step ) {
		$keys = array_keys( self::steps() );
		$i    = array_search( $step, $keys, true );
		return $i > 0 ? $keys[ $i - 1 ] : '';
	}

	// ── Render ──────────────────────────────────────────────────────────────

	public static function render() {
		$step  = self::current();
		$steps = self::steps();
		$keys  = array_keys( $steps );
		$pos   = array_search( $step, $keys, true );

		if ( 'done' === $step && ! Docsway_Settings::get( 'setup_done' ) ) {
			Docsway_Settings::update( array( 'setup_done' => 1 ) );
		}
		?>
		<div class="wrap dsw-wizard">
			<div class="dsw-wizard__top">
				<span class="dsw-wizard__brand"><?php esc_html_e( 'Docsway', 'aneris-docsway' ); ?></span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=docsway' ) ); ?>" class="dsw-wizard__exit"><?php esc_html_e( 'Exit setup', 'aneris-docsway' ); ?></a>
			</div>
			<hr class="wp-header-end">
			<ol class="dsw-stepper" aria-label="<?php esc_attr_e( 'Setup progress', 'aneris-docsway' ); ?>">
				<?php foreach ( $keys as $i => $k ) : ?>
					<li class="<?php echo $i < $pos ? 'is-done' : ( $i === $pos ? 'is-current' : '' ); ?>"<?php echo $i === $pos ? ' aria-current="step"' : ''; ?>>
						<span class="dsw-stepper__num"><?php echo $i < $pos ? '&#10003;' : (int) ( $i + 1 ); ?></span>
						<span class="dsw-stepper__label"><?php echo esc_html( $steps[ $k ] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>

			<div class="dsw-wizard__card">
				<?php if ( isset( $_GET['msg'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
					<div class="notice notice-success inline"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['msg'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification ?></p></div>
				<?php endif; ?>
				<?php
				if ( 'welcome' === $step || 'done' === $step ) {
					call_user_func( array( __CLASS__, 'step_' . $step ) );
				} else {
					?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="docsway_wizard">
						<input type="hidden" name="step" value="<?php echo esc_attr( $step ); ?>">
						<?php wp_nonce_field( 'docsway_wizard' ); ?>
						<?php call_user_func( array( __CLASS__, 'step_' . $step ) ); ?>
						<div class="dsw-wizard__nav">
							<a class="button button-link" href="<?php echo esc_url( self::url( self::prev( $step ) ) ); ?>">&larr; <?php esc_html_e( 'Back', 'aneris-docsway' ); ?></a>
							<span>
								<a class="button-link dsw-skip" href="<?php echo esc_url( self::url( self::next( $step ) ) ); ?>"><?php esc_html_e( 'Skip this step', 'aneris-docsway' ); ?></a>
								<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save and continue', 'aneris-docsway' ); ?> &rarr;</button>
							</span>
						</div>
					</form>
					<?php
				}
				?>
			</div>
		</div>
		<?php
	}

	private static function step_welcome() {
		?>
		<h1><?php esc_html_e( 'Let’s set up your documentation', 'aneris-docsway' ); ?></h1>
		<p class="dsw-lead"><?php esc_html_e( 'Docsway turns pages you already have into a proper docs area. Nothing is moved and no page address changes.', 'aneris-docsway' ); ?></p>
		<div class="dsw-features">
			<div><span class="dashicons dashicons-menu-alt3" aria-hidden="true"></span><strong><?php esc_html_e( 'Sidebar menu', 'aneris-docsway' ); ?></strong><p><?php esc_html_e( 'Built from sections you choose, with the current page highlighted.', 'aneris-docsway' ); ?></p></div>
			<div><span class="dashicons dashicons-search" aria-hidden="true"></span><strong><?php esc_html_e( 'Docs search', 'aneris-docsway' ); ?></strong><p><?php esc_html_e( 'Instant results that jump straight to the right heading.', 'aneris-docsway' ); ?></p></div>
			<div><span class="dashicons dashicons-editor-ol" aria-hidden="true"></span><strong><?php esc_html_e( 'Page navigation', 'aneris-docsway' ); ?></strong><p><?php esc_html_e( 'Contents, previous and next links and breadcrumbs on every doc page.', 'aneris-docsway' ); ?></p></div>
		</div>
		<p><?php esc_html_e( 'This takes about three minutes. You can change everything later.', 'aneris-docsway' ); ?></p>
		<div class="dsw-wizard__nav">
			<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=docsway' ) ); ?>"><?php esc_html_e( 'Not now', 'aneris-docsway' ); ?></a>
			<a class="button button-primary button-hero" href="<?php echo esc_url( self::url( 'content' ) ); ?>"><?php esc_html_e( 'Start', 'aneris-docsway' ); ?> &rarr;</a>
		</div>
		<?php
	}

	private static function step_content() {
		$o     = Docsway_Settings::all();
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		$cands = Docsway_Taxonomy::candidates();
		?>
		<h1><?php esc_html_e( 'Where do your docs live?', 'aneris-docsway' ); ?></h1>
		<p class="dsw-lead"><?php esc_html_e( 'Tick the kinds of content that hold your documentation. Most sites use Pages.', 'aneris-docsway' ); ?></p>
		<fieldset class="dsw-choices">
			<legend class="screen-reader-text"><?php esc_html_e( 'Content types', 'aneris-docsway' ); ?></legend>
			<?php foreach ( $types as $type ) :
				$count = wp_count_posts( $type->name );
				?>
				<label class="dsw-choice">
					<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $o['post_types'], true ) ); ?>>
					<span><strong><?php echo esc_html( $type->labels->name ); ?></strong>
					<small><?php echo esc_html( sprintf( /* translators: %s: number */ _n( '%s published', '%s published', (int) $count->publish, 'aneris-docsway' ), number_format_i18n( (int) $count->publish ) ) ); ?></small></span>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<h2><?php esc_html_e( 'How should they be grouped?', 'aneris-docsway' ); ?></h2>
		<fieldset class="dsw-choices dsw-choices--stack">
			<legend class="screen-reader-text"><?php esc_html_e( 'Grouping', 'aneris-docsway' ); ?></legend>
			<label class="dsw-choice">
				<input type="radio" name="taxonomy_mode" value="own" <?php checked( 'own', $o['taxonomy_mode'] ); ?>>
				<span><strong><?php esc_html_e( 'Create Docsway sections', 'aneris-docsway' ); ?></strong> <em class="dsw-tag"><?php esc_html_e( 'Recommended', 'aneris-docsway' ); ?></em>
				<small><?php esc_html_e( 'A separate set of categories just for your docs. Your existing categories are not touched.', 'aneris-docsway' ); ?></small></span>
			</label>
			<label class="dsw-choice<?php echo $cands ? '' : ' is-disabled'; ?>">
				<input type="radio" name="taxonomy_mode" value="existing" <?php checked( 'existing', $o['taxonomy_mode'] ); ?> <?php disabled( ! $cands ); ?>>
				<span><strong><?php esc_html_e( 'Use a category system I already have', 'aneris-docsway' ); ?></strong>
				<small>
				<?php
				echo $cands
					? esc_html__( 'Pick this if your docs are already organised with a custom taxonomy, for example one made with CPT UI.', 'aneris-docsway' )
					: esc_html__( 'No suitable taxonomies were found on this site.', 'aneris-docsway' );
				?>
				</small>
				<?php if ( $cands ) : ?>
					<select name="taxonomy" aria-label="<?php esc_attr_e( 'Taxonomy', 'aneris-docsway' ); ?>">
						<?php foreach ( $cands as $slug => $c ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $o['taxonomy'], $slug ); ?>><?php echo esc_html( sprintf( /* translators: 1: name, 2: slug, 3: count */ __( '%1$s (%2$s) · %3$d terms', 'aneris-docsway' ), $c['label'], $slug, $c['terms'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
				</span>
			</label>
		</fieldset>
		<?php
	}

	private static function step_sections() {
		$tax   = Docsway_Settings::taxonomy();
		$terms = taxonomy_exists( $tax ) ? get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ) : array();
		$terms = is_wp_error( $terms ) ? array() : $terms;
		?>
		<h1><?php esc_html_e( 'Create your sections', 'aneris-docsway' ); ?></h1>
		<p class="dsw-lead"><?php esc_html_e( 'Sections become the headings of your docs menu. Type one per line. Indent a line with two spaces to put it inside the section above.', 'aneris-docsway' ); ?></p>

		<?php if ( $terms ) : ?>
			<div class="notice notice-info inline"><p>
				<?php echo esc_html( sprintf( /* translators: %d: number of sections */ _n( 'You already have %d section. Anything you add below is created alongside it.', 'You already have %d sections. Anything you add below is created alongside them.', count( $terms ), 'aneris-docsway' ), count( $terms ) ) ); ?>
			</p></div>
		<?php endif; ?>

		<div class="dsw-outline">
			<div>
				<label for="dsw-outline" class="dsw-label"><?php esc_html_e( 'Outline', 'aneris-docsway' ); ?></label>
				<textarea id="dsw-outline" name="outline" rows="11" spellcheck="false" class="dsw-mono"><?php echo $terms ? '' : esc_textarea( "Getting started\n  Installation\n  First steps\nConfiguration\nTroubleshooting\nFAQ" ); ?></textarea>
				<p class="description"><?php esc_html_e( 'The example is only a starting point. Edit it, or clear it and skip if you will add sections later.', 'aneris-docsway' ); ?></p>
			</div>
			<div>
				<span class="dsw-label"><?php esc_html_e( 'Preview', 'aneris-docsway' ); ?></span>
				<ul class="dsw-preview" id="dsw-outline-preview" aria-live="polite"></ul>
			</div>
		</div>
		<?php
	}

	private static function step_assign() {
		$tax   = Docsway_Settings::taxonomy();
		$posts = get_posts(
			array(
				'post_type'      => Docsway_Settings::post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$has_terms = taxonomy_exists( $tax ) && wp_count_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
		?>
		<h1><?php esc_html_e( 'Put pages into sections', 'aneris-docsway' ); ?></h1>
		<p class="dsw-lead"><?php esc_html_e( 'Choose a section for each page that belongs in your docs. Leave the rest on “Not in docs”.', 'aneris-docsway' ); ?></p>

		<?php if ( ! $has_terms ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'There are no sections yet. Go back a step to create some, or skip and do this later.', 'aneris-docsway' ); ?></p></div>
		<?php elseif ( ! $posts ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'No published content was found in the types you chose.', 'aneris-docsway' ); ?></p></div>
		<?php else : ?>
			<p><input type="search" id="docsway-assign-filter" class="regular-text" placeholder="<?php esc_attr_e( 'Filter by title…', 'aneris-docsway' ); ?>" aria-label="<?php esc_attr_e( 'Filter by title', 'aneris-docsway' ); ?>"></p>
			<div class="dsw-scroll">
				<table class="widefat striped" id="docsway-assign">
					<thead><tr><th><?php esc_html_e( 'Page', 'aneris-docsway' ); ?></th><th><?php esc_html_e( 'Section', 'aneris-docsway' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $posts as $p ) :
						$current = wp_get_object_terms( $p->ID, $tax, array( 'fields' => 'ids' ) );
						$first   = ( ! is_wp_error( $current ) && $current ) ? (int) $current[0] : 0;
						?>
						<tr data-title="<?php echo esc_attr( mb_strtolower( $p->post_title ) ); ?>">
							<td><label for="dsw-a-<?php echo (int) $p->ID; ?>"><?php echo esc_html( $p->post_title ? $p->post_title : __( '(no title)', 'aneris-docsway' ) ); ?></label>
								<?php if ( ! is_wp_error( $current ) && count( $current ) > 1 ) : ?><span class="dsw-muted"><?php echo esc_html( sprintf( /* translators: %d: count */ __( '+%d more', 'aneris-docsway' ), count( $current ) - 1 ) ); ?></span><?php endif; ?>
							</td>
							<td><select id="dsw-a-<?php echo (int) $p->ID; ?>" name="assign[<?php echo (int) $p->ID; ?>]"><?php echo wp_kses( Docsway_Admin::section_options( $first, __( 'Not in docs', 'aneris-docsway' ) ), Docsway_Render::allowed_html() ); ?></select></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="description"><?php esc_html_e( 'This adds pages to sections. To take a page out of the docs later, use Structure, Assign pages.', 'aneris-docsway' ); ?></p>
		<?php endif; ?>
		<?php
	}

	private static function step_look() {
		$o = Docsway_Settings::all();
		if ( ! $o['setup_done'] ) {
			$o['layout'] = 'docsway'; // First run: suggest the ready-made layout.
		}
		?>
		<h1><?php esc_html_e( 'Choose how your docs look', 'aneris-docsway' ); ?></h1>
		<p class="dsw-lead"><?php esc_html_e( 'Pick a layout and a style. Everything here can be changed later in Settings, Appearance.', 'aneris-docsway' ); ?></p>
		<h2><?php esc_html_e( 'Layout', 'aneris-docsway' ); ?></h2>
		<?php Docsway_Admin::layout_picker( $o ); ?>
		<h2><?php esc_html_e( 'Style', 'aneris-docsway' ); ?></h2>
		<?php Docsway_Admin::style_picker( $o ); ?>
		<?php
	}

	private static function step_features() {
		$o       = Docsway_Settings::all();
		$pages   = get_pages( array( 'post_status' => 'publish' ) );
		$home    = 'home' === $o['results_target'];
		$results = ! $home && Docsway_Search::results_url();
		?>
		<h1><?php esc_html_e( 'Search and page extras', 'aneris-docsway' ); ?></h1>

		<h2><?php esc_html_e( 'Search results', 'aneris-docsway' ); ?></h2>
		<p><?php esc_html_e( 'Instant results show as people type. When they press Enter, they see a full list of results.', 'aneris-docsway' ); ?></p>
		<fieldset class="dsw-choices dsw-choices--stack">
			<legend class="screen-reader-text"><?php esc_html_e( 'Where full results show', 'aneris-docsway' ); ?></legend>
			<label class="dsw-choice">
				<input type="radio" name="results_mode" value="home" <?php checked( $home ); ?>>
				<span><strong><?php esc_html_e( 'On my docs home page', 'aneris-docsway' ); ?></strong><small><?php esc_html_e( 'Results appear inside the search home, below the search box, so your layout and sidebar stay in place. Choose the docs home page below.', 'aneris-docsway' ); ?></small></span>
			</label>
			<label class="dsw-choice">
				<input type="radio" name="results_mode" value="create" <?php checked( ! $home && ! $results ); ?>>
				<span><strong><?php esc_html_e( 'Create a “Search results” page for me', 'aneris-docsway' ); ?></strong><small><?php esc_html_e( 'A new published page with the results list already on it.', 'aneris-docsway' ); ?></small></span>
			</label>
			<label class="dsw-choice">
				<input type="radio" name="results_mode" value="existing" <?php checked( (bool) $results ); ?>>
				<span><strong><?php esc_html_e( 'Use an existing page', 'aneris-docsway' ); ?></strong>
					<small><?php echo wp_kses( __( 'It needs the <code>[docsway_search_results]</code> shortcode.', 'aneris-docsway' ), array( 'code' => array() ) ); ?></small>
					<select name="results_page" aria-label="<?php esc_attr_e( 'Results page', 'aneris-docsway' ); ?>">
						<option value="0"><?php esc_html_e( 'Choose…', 'aneris-docsway' ); ?></option>
						<?php foreach ( $pages as $p ) : ?><option value="<?php echo (int) $p->ID; ?>" <?php selected( $o['results_page'], $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option><?php endforeach; ?>
					</select>
				</span>
			</label>
			<label class="dsw-choice">
				<input type="radio" name="results_mode" value="none">
				<span><strong><?php esc_html_e( 'No results page', 'aneris-docsway' ); ?></strong><small><?php esc_html_e( 'Instant results only.', 'aneris-docsway' ); ?></small></span>
			</label>
		</fieldset>

		<h2><?php esc_html_e( 'Add to every doc page', 'aneris-docsway' ); ?></h2>
		<div class="dsw-toggles">
			<label class="dsw-toggle"><input type="checkbox" name="toc_auto" value="1" <?php checked( $o['toc_auto'] ); ?>><span><strong><?php esc_html_e( 'On this page', 'aneris-docsway' ); ?></strong><small><?php esc_html_e( 'Contents list at the top of longer pages.', 'aneris-docsway' ); ?></small></span></label>
			<label class="dsw-toggle"><input type="checkbox" name="prevnext_auto" value="1" <?php checked( $o['prevnext_auto'] ); ?>><span><strong><?php esc_html_e( 'Previous and next', 'aneris-docsway' ); ?></strong><small><?php esc_html_e( 'Links at the bottom, in menu order.', 'aneris-docsway' ); ?></small></span></label>
			<label class="dsw-toggle"><input type="checkbox" name="crumbs_auto" value="1" <?php checked( $o['crumbs_auto'] ); ?>><span><strong><?php esc_html_e( 'Breadcrumbs', 'aneris-docsway' ); ?></strong><small><?php esc_html_e( 'Section trail above the content.', 'aneris-docsway' ); ?></small></span></label>
			<label class="dsw-toggle"><input type="checkbox" name="shortcut" value="1" <?php checked( $o['shortcut'] ); ?>><span><strong><?php esc_html_e( 'Ctrl+K search', 'aneris-docsway' ); ?></strong><small><?php esc_html_e( 'Open search from anywhere on a doc page.', 'aneris-docsway' ); ?></small></span></label>
			<label class="dsw-toggle"><input type="checkbox" name="show_updated" value="1" <?php checked( $o['show_updated'] ); ?>><span><strong><?php esc_html_e( 'Last updated', 'aneris-docsway' ); ?></strong><small><?php esc_html_e( 'The date a page last changed, under its title.', 'aneris-docsway' ); ?></small></span></label>
			<label class="dsw-toggle"><input type="checkbox" name="feedback" value="1" <?php checked( $o['feedback'] ); ?>><span><strong><?php esc_html_e( 'Was this helpful?', 'aneris-docsway' ); ?></strong><small><?php esc_html_e( 'Yes and No buttons; see the results in Search insights.', 'aneris-docsway' ); ?></small></span></label>
		</div>
		<p class="description"><?php esc_html_e( 'Building a Divi Theme Builder template with these modules in a sidebar? Untick the matching boxes so nothing shows twice.', 'aneris-docsway' ); ?></p>

		<?php $has_home = $o['home_page'] && 'publish' === get_post_status( $o['home_page'] ); ?>
		<h2><?php esc_html_e( 'Docs home page', 'aneris-docsway' ); ?></h2>
		<p><?php esc_html_e( 'The start page of your docs: a large search box, quick searches and topic cards. It is also the first step in breadcrumbs.', 'aneris-docsway' ); ?></p>
		<fieldset class="dsw-choices dsw-choices--stack">
			<legend class="screen-reader-text"><?php esc_html_e( 'Docs home page', 'aneris-docsway' ); ?></legend>
			<label class="dsw-choice">
				<input type="radio" name="home_mode" value="create" <?php checked( ! $has_home ); ?>>
				<span><strong><?php esc_html_e( 'Create a “Documentation” page for me', 'aneris-docsway' ); ?></strong><small><?php esc_html_e( 'A new published page with the search home already on it. Change its heading, quick searches and topic cards in Settings, Search home.', 'aneris-docsway' ); ?></small></span>
			</label>
			<label class="dsw-choice">
				<input type="radio" name="home_mode" value="existing" <?php checked( $has_home ); ?>>
				<span><strong><?php esc_html_e( 'Use an existing page', 'aneris-docsway' ); ?></strong>
					<small><?php esc_html_e( 'With the Docsway docs layout, the search home is added to it automatically if it is not there yet.', 'aneris-docsway' ); ?></small>
					<select id="dsw-home" name="home_page" aria-label="<?php esc_attr_e( 'Docs home page', 'aneris-docsway' ); ?>">
						<option value="0"><?php esc_html_e( 'Choose…', 'aneris-docsway' ); ?></option>
						<?php foreach ( $pages as $p ) : ?><option value="<?php echo (int) $p->ID; ?>" <?php selected( $o['home_page'], $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option><?php endforeach; ?>
					</select>
				</span>
			</label>
			<label class="dsw-choice">
				<input type="radio" name="home_mode" value="none">
				<span><strong><?php esc_html_e( 'No docs home page', 'aneris-docsway' ); ?></strong><small><?php esc_html_e( 'Readers start on your doc pages. Full search results then need a separate results page.', 'aneris-docsway' ); ?></small></span>
			</label>
		</fieldset>
		<?php
	}

	/**
	 * A published "Documentation" page holding the search home. With the theme's own
	 * layout it gets the docs menu too, since there is no Docsway sidebar to show it.
	 */
	private static function create_docs_home() {
		$content = '<!-- wp:docsway/search-home /-->';
		if ( 'docsway' !== Docsway_Settings::get( 'layout' ) ) {
			$content .= "\n" . '<!-- wp:docsway/menu {"title":"' . esc_attr__( 'All documentation', 'aneris-docsway' ) . '"} /-->';
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => __( 'Documentation', 'aneris-docsway' ),
				'post_content' => $content,
			)
		);
		if ( ! $id || is_wp_error( $id ) ) {
			return 0;
		}
		update_post_meta( $id, '_docsway_created', 1 );
		return (int) $id;
	}

	private static function step_done() {
		$flat  = Docsway_Tree::flat();
		$first = $flat ? $flat[0]['url'] : '';
		?>
		<div class="dsw-done">
			<span class="dsw-done__icon" aria-hidden="true">&#10003;</span>
			<h1><?php esc_html_e( 'Your docs are set up', 'aneris-docsway' ); ?></h1>
			<p class="dsw-lead"><?php esc_html_e( 'One thing left: put the menu where readers will see it.', 'aneris-docsway' ); ?></p>
		</div>

		<div class="dsw-place" data-tabs>
			<div class="dsw-place__tabs" role="tablist">
				<button type="button" role="tab" aria-selected="true" aria-controls="dsw-p-divi" id="dsw-t-divi"><?php esc_html_e( 'Divi', 'aneris-docsway' ); ?></button>
				<button type="button" role="tab" aria-selected="false" aria-controls="dsw-p-blocks" id="dsw-t-blocks" tabindex="-1"><?php esc_html_e( 'Block editor', 'aneris-docsway' ); ?></button>
				<button type="button" role="tab" aria-selected="false" aria-controls="dsw-p-sc" id="dsw-t-sc" tabindex="-1"><?php esc_html_e( 'Anything else', 'aneris-docsway' ); ?></button>
			</div>
			<div role="tabpanel" id="dsw-p-divi" aria-labelledby="dsw-t-divi">
				<ol class="dsw-tut">
					<li><?php esc_html_e( 'Open Divi, Theme Builder and add a template for your doc pages.', 'aneris-docsway' ); ?></li>
					<li><?php esc_html_e( 'Build a body with a sidebar column and a Post Content module.', 'aneris-docsway' ); ?></li>
					<li><?php esc_html_e( 'Add the Docsway Docs Search and Docsway Docs Menu modules to the sidebar.', 'aneris-docsway' ); ?></li>
				</ol>
				<p class="description"><?php echo wp_kses( __( 'On Divi 5, add a Code module with <code>[docsway_search]</code> and <code>[docsway_menu]</code> instead. The Docsway modules are not in the Divi 5 module list yet.', 'aneris-docsway' ), array( 'code' => array() ) ); ?></p>
			</div>
			<div role="tabpanel" id="dsw-p-blocks" aria-labelledby="dsw-t-blocks" hidden>
				<ol class="dsw-tut">
					<li><?php esc_html_e( 'Open the template or sidebar where your docs menu should go.', 'aneris-docsway' ); ?></li>
					<li><?php esc_html_e( 'Click + and search for “Docs”.', 'aneris-docsway' ); ?></li>
					<li><?php esc_html_e( 'Add Docs Search and Docs Menu.', 'aneris-docsway' ); ?></li>
				</ol>
			</div>
			<div role="tabpanel" id="dsw-p-sc" aria-labelledby="dsw-t-sc" hidden>
				<p><?php esc_html_e( 'Paste these shortcodes into a widget, page builder text element or template:', 'aneris-docsway' ); ?></p>
				<p><?php echo wp_kses( Docsway_Admin::copy_field( '[docsway_search]' ), Docsway_Render::allowed_html() ); ?></p>
				<p><?php echo wp_kses( Docsway_Admin::copy_field( '[docsway_menu]' ), Docsway_Render::allowed_html() ); ?></p>
			</div>
		</div>

		<div class="dsw-wizard__nav">
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=docsway-help#placing' ) ); ?>"><?php esc_html_e( 'Full placing guide', 'aneris-docsway' ); ?></a>
			<span>
				<?php if ( $first ) : ?>
					<a class="button" href="<?php echo esc_url( $first ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View a doc page', 'aneris-docsway' ); ?></a>
				<?php endif; ?>
				<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=docsway' ) ); ?>"><?php esc_html_e( 'Go to dashboard', 'aneris-docsway' ); ?></a>
			</span>
		</div>
		<?php
	}

	// ── Save ────────────────────────────────────────────────────────────────

	public static function save() {
		if ( ! current_user_can( Docsway_Admin::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to run the setup.', 'aneris-docsway' ) );
		}
		check_admin_referer( 'docsway_wizard' );
		$in   = Docsway_Admin::posted();
		$step = isset( $in['step'] ) ? sanitize_key( $in['step'] ) : '';
		$msg  = '';

		switch ( $step ) {
			case 'content':
				Docsway_Settings::update(
					array(
						'post_types'    => isset( $in['post_types'] ) ? (array) $in['post_types'] : array( 'page' ),
						'taxonomy_mode' => isset( $in['taxonomy_mode'] ) ? $in['taxonomy_mode'] : 'own',
						'taxonomy'      => isset( $in['taxonomy'] ) ? $in['taxonomy'] : '',
					)
				);
				// The taxonomy may have changed; register it now so the next steps can use it.
				Docsway_Taxonomy::register();
				break;

			case 'sections':
				$made = self::create_from_outline( isset( $in['outline'] ) ? (string) $in['outline'] : '' );
				if ( $made ) {
					/* translators: %d: number of sections */
					$msg = sprintf( _n( '%d section created.', '%d sections created.', $made, 'aneris-docsway' ), $made );
				}
				break;

			case 'assign':
				$tax   = Docsway_Settings::taxonomy();
				$count = 0;
				foreach ( isset( $in['assign'] ) ? (array) $in['assign'] : array() as $post_id => $term_id ) {
					$post_id = absint( $post_id );
					$term_id = absint( $term_id );
					if ( ! $term_id || ! current_user_can( 'edit_post', $post_id ) ) {
						continue;
					}
					$current = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'ids' ) );
					if ( ! is_wp_error( $current ) && ! in_array( $term_id, array_map( 'intval', $current ), true ) ) {
						wp_set_object_terms( $post_id, array( $term_id ), $tax, true );
						$count++;
					}
				}
				Docsway_Tree::flush();
				if ( $count ) {
					/* translators: %d: number of pages */
					$msg = sprintf( _n( '%d page added to the docs.', '%d pages added to the docs.', $count, 'aneris-docsway' ), $count );
				}
				break;

			case 'look':
				$changes = array();
				foreach ( array( 'layout', 'sidebar', 'width', 'max_width', 'toc_position', 'chrome', 'style', 'accent' ) as $k ) {
					if ( isset( $in[ $k ] ) ) {
						$changes[ $k ] = $in[ $k ];
					}
				}
				Docsway_Settings::update( $changes );
				break;

			case 'features':
				$home_mode = isset( $in['home_mode'] ) ? sanitize_key( $in['home_mode'] ) : 'existing';
				$changes   = array( 'home_page' => 0 );
				if ( 'create' === $home_mode ) {
					$changes['home_page'] = self::create_docs_home();
				} elseif ( 'existing' === $home_mode ) {
					$changes['home_page'] = isset( $in['home_page'] ) ? absint( $in['home_page'] ) : 0;
				}
				foreach ( array( 'toc_auto', 'prevnext_auto', 'crumbs_auto', 'shortcut', 'show_updated', 'feedback' ) as $k ) {
					$changes[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
				}
				$mode                      = isset( $in['results_mode'] ) ? sanitize_key( $in['results_mode'] ) : 'none';
				$changes['results_target'] = 'home' === $mode ? 'home' : 'page';
				if ( 'home' === $mode ) {
					// Keep any results page; it is simply not used while results show on the docs home.
				} elseif ( 'create' === $mode ) {
					$id = wp_insert_post(
						array(
							'post_type'    => 'page',
							'post_status'  => 'publish',
							'post_title'   => __( 'Search results', 'aneris-docsway' ),
							'post_content' => "<!-- wp:shortcode -->\n[docsway_search_results]\n<!-- /wp:shortcode -->",
						)
					);
					if ( $id && ! is_wp_error( $id ) ) {
						$changes['results_page'] = $id;
					}
				} elseif ( 'existing' === $mode ) {
					$changes['results_page'] = isset( $in['results_page'] ) ? absint( $in['results_page'] ) : 0;
				} else {
					$changes['results_page'] = 0;
				}
				Docsway_Settings::update( $changes );
				break;
		}

		$url = self::url( self::next( $step ) );
		if ( $msg ) {
			$url = add_query_arg( 'msg', rawurlencode( $msg ), $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/** Creates terms from an indented outline. Returns how many were created. */
	public static function create_from_outline( $text ) {
		$tax = Docsway_Settings::taxonomy();
		if ( ! taxonomy_exists( $tax ) ) {
			return 0;
		}
		$stack = array(); // depth => term_id
		$made  = 0;
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}
			$expanded = str_replace( "\t", '  ', $line );
			$depth    = (int) floor( ( strlen( $expanded ) - strlen( ltrim( $expanded ) ) ) / 2 );
			$name     = sanitize_text_field( trim( $line, " \t-*•" ) );
			if ( '' === $name ) {
				continue;
			}
			$depth  = min( $depth, count( $stack ) );
			$parent = $depth > 0 && isset( $stack[ $depth - 1 ] ) ? $stack[ $depth - 1 ] : 0;

			$exists = term_exists( $name, $tax, $parent );
			if ( $exists ) {
				$id = (int) ( is_array( $exists ) ? $exists['term_id'] : $exists );
			} else {
				$res = wp_insert_term( $name, $tax, array( 'parent' => $parent ) );
				if ( is_wp_error( $res ) ) {
					continue;
				}
				$id = (int) $res['term_id'];
				$made++;
			}
			$stack[ $depth ] = $id;
			$stack           = array_slice( $stack, 0, $depth + 1 );
		}
		Docsway_Tree::flush();
		return $made;
	}
}
