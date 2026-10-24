<?php
/**
 * First-run setup wizard.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Wizard {

	const SLUG = 'waypost-setup';

	public static function steps() {
		return array(
			'welcome'  => __( 'Welcome', 'waypost' ),
			'content'  => __( 'Your docs', 'waypost' ),
			'sections' => __( 'Sections', 'waypost' ),
			'assign'   => __( 'Pages', 'waypost' ),
			'features' => __( 'Extras', 'waypost' ),
			'done'     => __( 'Done', 'waypost' ),
		);
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ) );
		add_action( 'admin_post_waypost_wizard', array( __CLASS__, 'save' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
	}

	public static function url( $step = 'welcome' ) {
		return admin_url( 'admin.php?page=' . self::SLUG . '&step=' . $step );
	}

	public static function register() {
		// Hidden page: reachable by URL, not listed in the menu.
		$hook = add_submenu_page( 'options.php', __( 'Waypost setup', 'waypost' ), __( 'Waypost setup', 'waypost' ), Waypost_Admin::CAP, self::SLUG, array( __CLASS__, 'render' ) );
		Waypost_Admin::$hooks['wizard'] = $hook;
	}

	public static function body_class( $classes ) {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		return self::SLUG === $page ? $classes . ' waypost-wizard-screen' : $classes;
	}

	public static function maybe_redirect() {
		if ( ! get_transient( 'waypost_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'waypost_activation_redirect' );
		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) || ! current_user_can( Waypost_Admin::CAP ) ) { // phpcs:ignore WordPress.Security.NonceVerification
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

		if ( 'done' === $step && ! Waypost_Settings::get( 'setup_done' ) ) {
			Waypost_Settings::update( array( 'setup_done' => 1 ) );
		}
		?>
		<div class="wrap wpst-wizard">
			<div class="wpst-wizard__top">
				<span class="wpst-wizard__brand"><?php esc_html_e( 'Waypost', 'waypost' ); ?></span>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=waypost' ) ); ?>" class="wpst-wizard__exit"><?php esc_html_e( 'Exit setup', 'waypost' ); ?></a>
			</div>
			<hr class="wp-header-end">
			<ol class="wpst-stepper" aria-label="<?php esc_attr_e( 'Setup progress', 'waypost' ); ?>">
				<?php foreach ( $keys as $i => $k ) : ?>
					<li class="<?php echo $i < $pos ? 'is-done' : ( $i === $pos ? 'is-current' : '' ); ?>"<?php echo $i === $pos ? ' aria-current="step"' : ''; ?>>
						<span class="wpst-stepper__num"><?php echo $i < $pos ? '&#10003;' : (int) ( $i + 1 ); ?></span>
						<span class="wpst-stepper__label"><?php echo esc_html( $steps[ $k ] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>

			<div class="wpst-wizard__card">
				<?php if ( isset( $_GET['msg'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
					<div class="notice notice-success inline"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['msg'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification ?></p></div>
				<?php endif; ?>
				<?php
				if ( 'welcome' === $step || 'done' === $step ) {
					call_user_func( array( __CLASS__, 'step_' . $step ) );
				} else {
					?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="waypost_wizard">
						<input type="hidden" name="step" value="<?php echo esc_attr( $step ); ?>">
						<?php wp_nonce_field( 'waypost_wizard' ); ?>
						<?php call_user_func( array( __CLASS__, 'step_' . $step ) ); ?>
						<div class="wpst-wizard__nav">
							<a class="button button-link" href="<?php echo esc_url( self::url( self::prev( $step ) ) ); ?>">&larr; <?php esc_html_e( 'Back', 'waypost' ); ?></a>
							<span>
								<a class="button-link wpst-skip" href="<?php echo esc_url( self::url( self::next( $step ) ) ); ?>"><?php esc_html_e( 'Skip this step', 'waypost' ); ?></a>
								<button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Save and continue', 'waypost' ); ?> &rarr;</button>
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
		<h1><?php esc_html_e( 'Let’s set up your documentation', 'waypost' ); ?></h1>
		<p class="wpst-lead"><?php esc_html_e( 'Waypost turns pages you already have into a proper docs area. Nothing is moved and no page address changes.', 'waypost' ); ?></p>
		<div class="wpst-features">
			<div><span class="dashicons dashicons-menu-alt3" aria-hidden="true"></span><strong><?php esc_html_e( 'Sidebar menu', 'waypost' ); ?></strong><p><?php esc_html_e( 'Built from sections you choose, with the current page highlighted.', 'waypost' ); ?></p></div>
			<div><span class="dashicons dashicons-search" aria-hidden="true"></span><strong><?php esc_html_e( 'Docs search', 'waypost' ); ?></strong><p><?php esc_html_e( 'Instant results that jump straight to the right heading.', 'waypost' ); ?></p></div>
			<div><span class="dashicons dashicons-editor-ol" aria-hidden="true"></span><strong><?php esc_html_e( 'Page navigation', 'waypost' ); ?></strong><p><?php esc_html_e( 'Contents, previous and next links and breadcrumbs on every doc page.', 'waypost' ); ?></p></div>
		</div>
		<p><?php esc_html_e( 'This takes about three minutes. You can change everything later.', 'waypost' ); ?></p>
		<div class="wpst-wizard__nav">
			<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=waypost' ) ); ?>"><?php esc_html_e( 'Not now', 'waypost' ); ?></a>
			<a class="button button-primary button-hero" href="<?php echo esc_url( self::url( 'content' ) ); ?>"><?php esc_html_e( 'Start', 'waypost' ); ?> &rarr;</a>
		</div>
		<?php
	}

	private static function step_content() {
		$o     = Waypost_Settings::all();
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		$cands = Waypost_Taxonomy::candidates();
		?>
		<h1><?php esc_html_e( 'Where do your docs live?', 'waypost' ); ?></h1>
		<p class="wpst-lead"><?php esc_html_e( 'Tick the kinds of content that hold your documentation. Most sites use Pages.', 'waypost' ); ?></p>
		<fieldset class="wpst-choices">
			<legend class="screen-reader-text"><?php esc_html_e( 'Content types', 'waypost' ); ?></legend>
			<?php foreach ( $types as $type ) :
				$count = wp_count_posts( $type->name );
				?>
				<label class="wpst-choice">
					<input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $type->name ); ?>" <?php checked( in_array( $type->name, $o['post_types'], true ) ); ?>>
					<span><strong><?php echo esc_html( $type->labels->name ); ?></strong>
					<small><?php echo esc_html( sprintf( /* translators: %s: number */ _n( '%s published', '%s published', (int) $count->publish, 'waypost' ), number_format_i18n( (int) $count->publish ) ) ); ?></small></span>
				</label>
			<?php endforeach; ?>
		</fieldset>

		<h2><?php esc_html_e( 'How should they be grouped?', 'waypost' ); ?></h2>
		<fieldset class="wpst-choices wpst-choices--stack">
			<legend class="screen-reader-text"><?php esc_html_e( 'Grouping', 'waypost' ); ?></legend>
			<label class="wpst-choice">
				<input type="radio" name="taxonomy_mode" value="own" <?php checked( 'own', $o['taxonomy_mode'] ); ?>>
				<span><strong><?php esc_html_e( 'Create Waypost sections', 'waypost' ); ?></strong> <em class="wpst-tag"><?php esc_html_e( 'Recommended', 'waypost' ); ?></em>
				<small><?php esc_html_e( 'A separate set of categories just for your docs. Your existing categories are not touched.', 'waypost' ); ?></small></span>
			</label>
			<label class="wpst-choice<?php echo $cands ? '' : ' is-disabled'; ?>">
				<input type="radio" name="taxonomy_mode" value="existing" <?php checked( 'existing', $o['taxonomy_mode'] ); ?> <?php disabled( ! $cands ); ?>>
				<span><strong><?php esc_html_e( 'Use a category system I already have', 'waypost' ); ?></strong>
				<small>
				<?php
				echo $cands
					? esc_html__( 'Pick this if your docs are already organised with a custom taxonomy, for example one made with CPT UI.', 'waypost' )
					: esc_html__( 'No suitable taxonomies were found on this site.', 'waypost' );
				?>
				</small>
				<?php if ( $cands ) : ?>
					<select name="taxonomy" aria-label="<?php esc_attr_e( 'Taxonomy', 'waypost' ); ?>">
						<?php foreach ( $cands as $slug => $c ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $o['taxonomy'], $slug ); ?>><?php echo esc_html( sprintf( /* translators: 1: name, 2: slug, 3: count */ __( '%1$s (%2$s) · %3$d terms', 'waypost' ), $c['label'], $slug, $c['terms'] ) ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php endif; ?>
				</span>
			</label>
		</fieldset>
		<?php
	}

	private static function step_sections() {
		$tax   = Waypost_Settings::taxonomy();
		$terms = taxonomy_exists( $tax ) ? get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) ) : array();
		$terms = is_wp_error( $terms ) ? array() : $terms;
		?>
		<h1><?php esc_html_e( 'Create your sections', 'waypost' ); ?></h1>
		<p class="wpst-lead"><?php esc_html_e( 'Sections become the headings of your docs menu. Type one per line. Indent a line with two spaces to put it inside the section above.', 'waypost' ); ?></p>

		<?php if ( $terms ) : ?>
			<div class="notice notice-info inline"><p>
				<?php echo esc_html( sprintf( /* translators: %d: number of sections */ _n( 'You already have %d section. Anything you add below is created alongside it.', 'You already have %d sections. Anything you add below is created alongside them.', count( $terms ), 'waypost' ), count( $terms ) ) ); ?>
			</p></div>
		<?php endif; ?>

		<div class="wpst-outline">
			<div>
				<label for="wpst-outline" class="wpst-label"><?php esc_html_e( 'Outline', 'waypost' ); ?></label>
				<textarea id="wpst-outline" name="outline" rows="11" spellcheck="false" class="wpst-mono"><?php echo $terms ? '' : esc_textarea( "Getting started\n  Installation\n  First steps\nConfiguration\nTroubleshooting\nFAQ" ); ?></textarea>
				<p class="description"><?php esc_html_e( 'The example is only a starting point. Edit it, or clear it and skip if you will add sections later.', 'waypost' ); ?></p>
			</div>
			<div>
				<span class="wpst-label"><?php esc_html_e( 'Preview', 'waypost' ); ?></span>
				<ul class="wpst-preview" id="wpst-outline-preview" aria-live="polite"></ul>
			</div>
		</div>
		<?php
	}

	private static function step_assign() {
		$tax   = Waypost_Settings::taxonomy();
		$posts = get_posts(
			array(
				'post_type'      => Waypost_Settings::post_types(),
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$has_terms = taxonomy_exists( $tax ) && wp_count_terms( array( 'taxonomy' => $tax, 'hide_empty' => false ) );
		?>
		<h1><?php esc_html_e( 'Put pages into sections', 'waypost' ); ?></h1>
		<p class="wpst-lead"><?php esc_html_e( 'Choose a section for each page that belongs in your docs. Leave the rest on “Not in docs”.', 'waypost' ); ?></p>

		<?php if ( ! $has_terms ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'There are no sections yet. Go back a step to create some, or skip and do this later.', 'waypost' ); ?></p></div>
		<?php elseif ( ! $posts ) : ?>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'No published content was found in the types you chose.', 'waypost' ); ?></p></div>
		<?php else : ?>
			<p><input type="search" id="waypost-assign-filter" class="regular-text" placeholder="<?php esc_attr_e( 'Filter by title…', 'waypost' ); ?>" aria-label="<?php esc_attr_e( 'Filter by title', 'waypost' ); ?>"></p>
			<div class="wpst-scroll">
				<table class="widefat striped" id="waypost-assign">
					<thead><tr><th><?php esc_html_e( 'Page', 'waypost' ); ?></th><th><?php esc_html_e( 'Section', 'waypost' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $posts as $p ) :
						$current = wp_get_object_terms( $p->ID, $tax, array( 'fields' => 'ids' ) );
						$first   = ( ! is_wp_error( $current ) && $current ) ? (int) $current[0] : 0;
						?>
						<tr data-title="<?php echo esc_attr( mb_strtolower( $p->post_title ) ); ?>">
							<td><label for="wpst-a-<?php echo (int) $p->ID; ?>"><?php echo esc_html( $p->post_title ? $p->post_title : __( '(no title)', 'waypost' ) ); ?></label>
								<?php if ( ! is_wp_error( $current ) && count( $current ) > 1 ) : ?><span class="wpst-muted"><?php echo esc_html( sprintf( /* translators: %d: count */ __( '+%d more', 'waypost' ), count( $current ) - 1 ) ); ?></span><?php endif; ?>
							</td>
							<td><select id="wpst-a-<?php echo (int) $p->ID; ?>" name="assign[<?php echo (int) $p->ID; ?>]"><?php echo Waypost_Admin::section_options( $first, __( 'Not in docs', 'waypost' ) ); // phpcs:ignore ?></select></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="description"><?php esc_html_e( 'This adds pages to sections. To take a page out of the docs later, use Structure, Assign pages.', 'waypost' ); ?></p>
		<?php endif; ?>
		<?php
	}

	private static function step_features() {
		$o       = Waypost_Settings::all();
		$pages   = get_pages( array( 'post_status' => 'publish' ) );
		$results = Waypost_Search::results_url();
		?>
		<h1><?php esc_html_e( 'Search and page extras', 'waypost' ); ?></h1>

		<h2><?php esc_html_e( 'Search results page', 'waypost' ); ?></h2>
		<p><?php esc_html_e( 'Instant results show as people type. When they press Enter, they go to a page listing every result.', 'waypost' ); ?></p>
		<fieldset class="wpst-choices wpst-choices--stack">
			<legend class="screen-reader-text"><?php esc_html_e( 'Results page', 'waypost' ); ?></legend>
			<label class="wpst-choice">
				<input type="radio" name="results_mode" value="create" <?php checked( ! $results ); ?>>
				<span><strong><?php esc_html_e( 'Create a “Search results” page for me', 'waypost' ); ?></strong><small><?php esc_html_e( 'A new published page with the results list already on it.', 'waypost' ); ?></small></span>
			</label>
			<label class="wpst-choice">
				<input type="radio" name="results_mode" value="existing" <?php checked( (bool) $results ); ?>>
				<span><strong><?php esc_html_e( 'Use an existing page', 'waypost' ); ?></strong>
					<small><?php echo wp_kses( __( 'It needs the <code>[waypost_search_results]</code> shortcode.', 'waypost' ), array( 'code' => array() ) ); ?></small>
					<select name="results_page" aria-label="<?php esc_attr_e( 'Results page', 'waypost' ); ?>">
						<option value="0"><?php esc_html_e( 'Choose…', 'waypost' ); ?></option>
						<?php foreach ( $pages as $p ) : ?><option value="<?php echo (int) $p->ID; ?>" <?php selected( $o['results_page'], $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option><?php endforeach; ?>
					</select>
				</span>
			</label>
			<label class="wpst-choice">
				<input type="radio" name="results_mode" value="none">
				<span><strong><?php esc_html_e( 'No results page', 'waypost' ); ?></strong><small><?php esc_html_e( 'Instant results only.', 'waypost' ); ?></small></span>
			</label>
		</fieldset>

		<h2><?php esc_html_e( 'Add to every doc page', 'waypost' ); ?></h2>
		<div class="wpst-toggles">
			<label class="wpst-toggle"><input type="checkbox" name="toc_auto" value="1" <?php checked( $o['toc_auto'] ); ?>><span><strong><?php esc_html_e( 'On this page', 'waypost' ); ?></strong><small><?php esc_html_e( 'Contents list at the top of longer pages.', 'waypost' ); ?></small></span></label>
			<label class="wpst-toggle"><input type="checkbox" name="prevnext_auto" value="1" <?php checked( $o['prevnext_auto'] ); ?>><span><strong><?php esc_html_e( 'Previous and next', 'waypost' ); ?></strong><small><?php esc_html_e( 'Links at the bottom, in menu order.', 'waypost' ); ?></small></span></label>
			<label class="wpst-toggle"><input type="checkbox" name="crumbs_auto" value="1" <?php checked( $o['crumbs_auto'] ); ?>><span><strong><?php esc_html_e( 'Breadcrumbs', 'waypost' ); ?></strong><small><?php esc_html_e( 'Section trail above the content.', 'waypost' ); ?></small></span></label>
			<label class="wpst-toggle"><input type="checkbox" name="shortcut" value="1" <?php checked( $o['shortcut'] ); ?>><span><strong><?php esc_html_e( 'Ctrl+K search', 'waypost' ); ?></strong><small><?php esc_html_e( 'Open search from anywhere on a doc page.', 'waypost' ); ?></small></span></label>
		</div>
		<p class="description"><?php esc_html_e( 'Building a Divi Theme Builder template with these modules in a sidebar? Untick the matching boxes so nothing shows twice.', 'waypost' ); ?></p>

		<h2><?php esc_html_e( 'Look', 'waypost' ); ?></h2>
		<p><?php esc_html_e( 'Pick a ready-made style. You can change it any time in Settings.', 'waypost' ); ?></p>
		<?php Waypost_Admin::style_picker( $o ); ?>

		<h2><label for="wpst-home"><?php esc_html_e( 'Docs home page', 'waypost' ); ?></label> <span class="wpst-muted"><?php esc_html_e( '(optional)', 'waypost' ); ?></span></h2>
		<select id="wpst-home" name="home_page"><option value="0"><?php esc_html_e( 'None', 'waypost' ); ?></option>
			<?php foreach ( $pages as $p ) : ?><option value="<?php echo (int) $p->ID; ?>" <?php selected( $o['home_page'], $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option><?php endforeach; ?>
		</select>
		<p class="description"><?php esc_html_e( 'Shown as the first step in breadcrumbs.', 'waypost' ); ?></p>
		<?php
	}

	private static function step_done() {
		$flat  = Waypost_Tree::flat();
		$first = $flat ? $flat[0]['url'] : '';
		?>
		<div class="wpst-done">
			<span class="wpst-done__icon" aria-hidden="true">&#10003;</span>
			<h1><?php esc_html_e( 'Your docs are set up', 'waypost' ); ?></h1>
			<p class="wpst-lead"><?php esc_html_e( 'One thing left: put the menu where readers will see it.', 'waypost' ); ?></p>
		</div>

		<div class="wpst-place" data-tabs>
			<div class="wpst-place__tabs" role="tablist">
				<button type="button" role="tab" aria-selected="true" aria-controls="wpst-p-divi" id="wpst-t-divi"><?php esc_html_e( 'Divi', 'waypost' ); ?></button>
				<button type="button" role="tab" aria-selected="false" aria-controls="wpst-p-blocks" id="wpst-t-blocks" tabindex="-1"><?php esc_html_e( 'Block editor', 'waypost' ); ?></button>
				<button type="button" role="tab" aria-selected="false" aria-controls="wpst-p-sc" id="wpst-t-sc" tabindex="-1"><?php esc_html_e( 'Anything else', 'waypost' ); ?></button>
			</div>
			<div role="tabpanel" id="wpst-p-divi" aria-labelledby="wpst-t-divi">
				<ol class="wpst-tut">
					<li><?php esc_html_e( 'Open Divi, Theme Builder and add a template for your doc pages.', 'waypost' ); ?></li>
					<li><?php esc_html_e( 'Build a body with a sidebar column and a Post Content module.', 'waypost' ); ?></li>
					<li><?php esc_html_e( 'Add the Waypost Docs Search and Waypost Docs Menu modules to the sidebar.', 'waypost' ); ?></li>
				</ol>
			</div>
			<div role="tabpanel" id="wpst-p-blocks" aria-labelledby="wpst-t-blocks" hidden>
				<ol class="wpst-tut">
					<li><?php esc_html_e( 'Open the template or sidebar where your docs menu should go.', 'waypost' ); ?></li>
					<li><?php esc_html_e( 'Click + and search for “Docs”.', 'waypost' ); ?></li>
					<li><?php esc_html_e( 'Add Docs Search and Docs Menu.', 'waypost' ); ?></li>
				</ol>
			</div>
			<div role="tabpanel" id="wpst-p-sc" aria-labelledby="wpst-t-sc" hidden>
				<p><?php esc_html_e( 'Paste these shortcodes into a widget, page builder text element or template:', 'waypost' ); ?></p>
				<p><?php echo Waypost_Admin::copy_field( '[waypost_search]' ); // phpcs:ignore ?></p>
				<p><?php echo Waypost_Admin::copy_field( '[waypost_menu]' ); // phpcs:ignore ?></p>
			</div>
		</div>

		<div class="wpst-wizard__nav">
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=waypost-help#placing' ) ); ?>"><?php esc_html_e( 'Full placing guide', 'waypost' ); ?></a>
			<span>
				<?php if ( $first ) : ?>
					<a class="button" href="<?php echo esc_url( $first ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View a doc page', 'waypost' ); ?></a>
				<?php endif; ?>
				<a class="button button-primary button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=waypost' ) ); ?>"><?php esc_html_e( 'Go to dashboard', 'waypost' ); ?></a>
			</span>
		</div>
		<?php
	}

	// ── Save ────────────────────────────────────────────────────────────────

	public static function save() {
		if ( ! current_user_can( Waypost_Admin::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to run the setup.', 'waypost' ) );
		}
		check_admin_referer( 'waypost_wizard' );
		$in   = wp_unslash( $_POST );
		$step = isset( $in['step'] ) ? sanitize_key( $in['step'] ) : '';
		$msg  = '';

		switch ( $step ) {
			case 'content':
				Waypost_Settings::update(
					array(
						'post_types'    => isset( $in['post_types'] ) ? (array) $in['post_types'] : array( 'page' ),
						'taxonomy_mode' => isset( $in['taxonomy_mode'] ) ? $in['taxonomy_mode'] : 'own',
						'taxonomy'      => isset( $in['taxonomy'] ) ? $in['taxonomy'] : '',
					)
				);
				// The taxonomy may have changed; register it now so the next steps can use it.
				Waypost_Taxonomy::register();
				break;

			case 'sections':
				$made = self::create_from_outline( isset( $in['outline'] ) ? (string) $in['outline'] : '' );
				if ( $made ) {
					/* translators: %d: number of sections */
					$msg = sprintf( _n( '%d section created.', '%d sections created.', $made, 'waypost' ), $made );
				}
				break;

			case 'assign':
				$tax   = Waypost_Settings::taxonomy();
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
				Waypost_Tree::flush();
				if ( $count ) {
					/* translators: %d: number of pages */
					$msg = sprintf( _n( '%d page added to the docs.', '%d pages added to the docs.', $count, 'waypost' ), $count );
				}
				break;

			case 'features':
				$changes = array(
					'home_page' => isset( $in['home_page'] ) ? absint( $in['home_page'] ) : 0,
				);
				foreach ( array( 'style', 'accent' ) as $k ) {
					if ( isset( $in[ $k ] ) ) {
						$changes[ $k ] = $in[ $k ];
					}
				}
				foreach ( array( 'toc_auto', 'prevnext_auto', 'crumbs_auto', 'shortcut' ) as $k ) {
					$changes[ $k ] = empty( $in[ $k ] ) ? 0 : 1;
				}
				$mode = isset( $in['results_mode'] ) ? sanitize_key( $in['results_mode'] ) : 'none';
				if ( 'create' === $mode ) {
					$id = wp_insert_post(
						array(
							'post_type'    => 'page',
							'post_status'  => 'publish',
							'post_title'   => __( 'Search results', 'waypost' ),
							'post_content' => "<!-- wp:shortcode -->\n[waypost_search_results]\n<!-- /wp:shortcode -->",
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
				Waypost_Settings::update( $changes );
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
		$tax = Waypost_Settings::taxonomy();
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
		Waypost_Tree::flush();
		return $made;
	}
}
