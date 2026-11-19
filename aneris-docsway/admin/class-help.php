<?php
/**
 * Help and tutorials page, plus the contextual Help tabs on each screen.
 */

defined( 'ABSPATH' ) || exit;

class Docsway_Help {

	public static function init() {}

	private static function url( $topic ) {
		return admin_url( 'admin.php?page=docsway-help#' . $topic );
	}

	/** Contextual help (the "Help" tab at the top right of each Docsway screen). */
	public static function add_tabs( $key ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}
		$tabs = array(
			'dashboard' => array(
				'overview' => array( __( 'Overview', 'aneris-docsway' ), '<p>' . esc_html__( 'The checklist shows what is left to do before your docs are ready. Each step links to the screen that does it and to a short tutorial.', 'aneris-docsway' ) . '</p><p>' . esc_html__( 'Docsway builds a documentation area from pages you already have. You put pages into sections, and Docsway turns that into a sidebar menu, a docs-only search, a table of contents, previous and next links and breadcrumbs.', 'aneris-docsway' ) . '</p>' ),
			),
			'structure' => array(
				'order'  => array( __( 'Menu order', 'aneris-docsway' ), '<p>' . esc_html__( 'Drag rows by their handle to change the order in the menu, then press Save order. Items only move within their own group.', 'aneris-docsway' ) . '</p><p>' . esc_html__( 'To nest one section inside another, edit the section and set its Parent.', 'aneris-docsway' ) . '</p>' ),
				'assign' => array( __( 'Assign pages', 'aneris-docsway' ), '<p>' . esc_html__( 'Tick pages, choose a section and press Add to section. Move replaces existing sections. Remove from docs takes the page out of the menu and docs search without deleting it.', 'aneris-docsway' ) . '</p><p>' . esc_html__( 'You can also set sections while editing a page, in the Doc sections panel of the editor sidebar.', 'aneris-docsway' ) . '</p>' ),
				'links'  => array( __( 'Section links', 'aneris-docsway' ), '<p>' . esc_html__( 'Link a section heading to an overview page so the heading itself becomes clickable. Without a link the heading just opens and closes its list.', 'aneris-docsway' ) . '</p>' ),
			),
			'settings'  => array(
				'content'  => array( __( 'Content', 'aneris-docsway' ), '<p>' . esc_html__( 'Choose which content types hold docs and whether to group them with Docsway sections or a taxonomy you already use.', 'aneris-docsway' ) . '</p>' ),
				'features' => array( __( 'Page features', 'aneris-docsway' ), '<p>' . esc_html__( 'Automatic features are added to every page that belongs to a section. Turn them off if you place the blocks or Divi modules yourself, so they do not appear twice.', 'aneris-docsway' ) . '</p>' ),
			),
			'insights'  => array(
				'insights' => array( __( 'Using insights', 'aneris-docsway' ), '<p>' . esc_html__( 'Searches with no results are the most useful list here. Each one is a gap in your docs or a word your readers use that your pages do not.', 'aneris-docsway' ) . '</p>' ),
			),
			'help'      => array(),
		);
		foreach ( isset( $tabs[ $key ] ) ? $tabs[ $key ] : array() as $id => $tab ) {
			$screen->add_help_tab( array( 'id' => 'docsway-' . $id, 'title' => $tab[0], 'content' => $tab[1] ) );
		}
		$screen->set_help_sidebar(
			'<p><strong>' . esc_html__( 'More help', 'aneris-docsway' ) . '</strong></p>'
			. '<p><a href="' . esc_url( admin_url( 'admin.php?page=docsway-help' ) ) . '">' . esc_html__( 'Tutorials', 'aneris-docsway' ) . '</a></p>'
			. '<p><a href="' . esc_url( Docsway_Wizard::url() ) . '">' . esc_html__( 'Setup wizard', 'aneris-docsway' ) . '</a></p>'
		);
	}

	public static function page() {
		$topics = array(
			'getting-started'  => __( 'Getting started', 'aneris-docsway' ),
			'sections'         => __( 'Sections', 'aneris-docsway' ),
			'assigning'        => __( 'Adding pages to the docs', 'aneris-docsway' ),
			'ordering'         => __( 'Ordering the menu', 'aneris-docsway' ),
			'section-links'    => __( 'Clickable section headings', 'aneris-docsway' ),
			'layout'           => __( 'The docs layout', 'aneris-docsway' ),
			'placing'          => __( 'Placing the menu and search', 'aneris-docsway' ),
			'search'           => __( 'Search', 'aneris-docsway' ),
			'page-features'    => __( 'Contents, previous / next, breadcrumbs', 'aneris-docsway' ),
			'insights'         => __( 'Search insights', 'aneris-docsway' ),
			'styling'          => __( 'Styling', 'aneris-docsway' ),
			'existing'         => __( 'Using a taxonomy you already have', 'aneris-docsway' ),
			'troubleshooting'  => __( 'Troubleshooting', 'aneris-docsway' ),
		);
		/** Add-ons add topics (id => label) and print their articles on docsway_help_articles. */
		$topics = apply_filters( 'docsway_help_topics', $topics );

		Docsway_Admin::header( __( 'Help and tutorials', 'aneris-docsway' ), __( 'Short guides for everything Docsway does.', 'aneris-docsway' ) );
		?>
		<div class="dsw-help">
			<nav class="dsw-help__nav" aria-label="<?php esc_attr_e( 'Help topics', 'aneris-docsway' ); ?>">
				<ul>
					<?php foreach ( $topics as $id => $label ) : ?>
						<li><a href="#<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></a></li>
					<?php endforeach; ?>
				</ul>
				<a class="button button-primary" href="<?php echo esc_url( Docsway_Wizard::url() ); ?>"><?php esc_html_e( 'Open setup wizard', 'aneris-docsway' ); ?></a>
			</nav>

			<div class="dsw-help__body">

				<article class="dsw-card dsw-topic" id="getting-started">
					<h2><?php esc_html_e( 'Getting started', 'aneris-docsway' ); ?></h2>
					<p><?php esc_html_e( 'Docsway turns ordinary pages into a documentation area. Nothing is moved or copied, and page addresses stay exactly as they are.', 'aneris-docsway' ); ?></p>
					<p><strong><?php esc_html_e( 'The idea in one sentence:', 'aneris-docsway' ); ?></strong> <?php esc_html_e( 'you put pages into sections, and Docsway builds the menu, search and page navigation from those sections.', 'aneris-docsway' ); ?></p>
					<h3><?php esc_html_e( 'Five steps to a working docs area', 'aneris-docsway' ); ?></h3>
					<ol class="dsw-tut">
						<li><strong><?php esc_html_e( 'Run the setup wizard.', 'aneris-docsway' ); ?></strong> <?php esc_html_e( 'It asks where your docs live and creates your first sections.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Put pages into sections.', 'aneris-docsway' ); ?></strong> <?php esc_html_e( 'Use Structure, then Assign pages, or the Doc sections panel when editing a page.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Set the order.', 'aneris-docsway' ); ?></strong> <?php esc_html_e( 'Drag sections and pages into place on Structure, then Menu order.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Place the menu.', 'aneris-docsway' ); ?></strong> <?php esc_html_e( 'Add the Docs Menu block, Divi module or shortcode to the sidebar of your doc pages.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Check a doc page.', 'aneris-docsway' ); ?></strong> <?php esc_html_e( 'You should see the menu with the current page highlighted, contents at the top and previous and next links at the bottom.', 'aneris-docsway' ); ?></li>
					</ol>
				</article>

				<article class="dsw-card dsw-topic" id="sections">
					<h2><?php esc_html_e( 'Sections', 'aneris-docsway' ); ?></h2>
					<p><?php esc_html_e( 'Sections are the headings in your docs menu. They work like categories: a section can contain pages and other sections, and a page can belong to more than one section.', 'aneris-docsway' ); ?></p>
					<div class="dsw-example">
						<pre>Getting started
  Installation
  First steps
Configuration
  Email settings
Troubleshooting</pre>
						<p class="dsw-muted"><?php esc_html_e( 'Each line is a section. Indented lines are sections inside the one above.', 'aneris-docsway' ); ?></p>
					</div>
					<h3><?php esc_html_e( 'Create sections', 'aneris-docsway' ); ?></h3>
					<ul class="dsw-list">
						<li><?php esc_html_e( 'Quickest: the setup wizard lets you type an outline like the one above and creates every section in one go.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'One at a time: Structure, then Assign pages, has a New section box.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'Full control: Docsway, then Sections, is the standard WordPress category screen where you can rename, set parents and delete.', 'aneris-docsway' ); ?></li>
					</ul>
					<p class="dsw-note"><?php esc_html_e( 'An empty section is hidden on the site, so you can prepare sections before writing their pages.', 'aneris-docsway' ); ?></p>
				</article>

				<article class="dsw-card dsw-topic" id="assigning">
					<h2><?php esc_html_e( 'Adding pages to the docs', 'aneris-docsway' ); ?></h2>
					<p><?php esc_html_e( 'A page becomes part of the docs as soon as it belongs to a section. Only those pages appear in the menu and in docs search.', 'aneris-docsway' ); ?></p>
					<h3><?php esc_html_e( 'Several pages at once', 'aneris-docsway' ); ?></h3>
					<ol class="dsw-tut">
						<li><?php esc_html_e( 'Go to Docsway, Structure, Assign pages.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'The list starts with pages that are not in the docs yet. Use the filter box to find pages by title.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'Tick the pages, choose a section at the bottom and press Add to section.', 'aneris-docsway' ); ?></li>
					</ol>
					<p><?php esc_html_e( 'Move to section replaces a page’s current sections. Remove from docs clears its sections without touching the page itself.', 'aneris-docsway' ); ?></p>
					<h3><?php esc_html_e( 'While editing a page', 'aneris-docsway' ); ?></h3>
					<p><?php esc_html_e( 'Open the page in the editor. In the right-hand sidebar, find the Doc sections panel and tick a section. With Divi, the panel is in the WordPress editor screen, not inside the Visual Builder.', 'aneris-docsway' ); ?></p>
				</article>

				<article class="dsw-card dsw-topic" id="ordering">
					<h2><?php esc_html_e( 'Ordering the menu', 'aneris-docsway' ); ?></h2>
					<ol class="dsw-tut">
						<li><?php esc_html_e( 'Go to Docsway, Structure, Menu order.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'Drag a row by the handle on its left. Sections and pages inside the same section can be mixed in any order.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'Press Save order. The menu on the site updates straight away.', 'aneris-docsway' ); ?></li>
					</ol>
					<p><?php esc_html_e( 'New pages and sections appear at the end of their group, in A to Z order, until you move them. Reset to A–Z clears all custom ordering.', 'aneris-docsway' ); ?></p>
					<p class="dsw-note"><?php esc_html_e( 'Dragging only changes the order within a level. To move a section under a different parent, edit the section on the Sections screen.', 'aneris-docsway' ); ?></p>
				</article>

				<article class="dsw-card dsw-topic" id="section-links">
					<h2><?php esc_html_e( 'Clickable section headings', 'aneris-docsway' ); ?></h2>
					<p><?php esc_html_e( 'A section heading normally just opens and closes its list. If the section has an overview page, you can link the heading to it.', 'aneris-docsway' ); ?></p>
					<ol class="dsw-tut">
						<li><?php esc_html_e( 'Make sure the overview page is in that section.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'Go to Structure, Section links.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'Choose the page next to the section and press Save.', 'aneris-docsway' ); ?></li>
					</ol>
					<p><?php esc_html_e( 'The linked page now appears as the heading rather than as an item inside the list, so it is not shown twice.', 'aneris-docsway' ); ?></p>
				</article>

				<article class="dsw-card dsw-topic" id="placing">
					<h2><?php esc_html_e( 'Placing the menu and search', 'aneris-docsway' ); ?></h2>
					<p><?php esc_html_e( 'The menu usually goes in a sidebar that appears on every doc page. Pick the method that matches how your site is built.', 'aneris-docsway' ); ?></p>

					<h3><?php esc_html_e( 'Divi Theme Builder', 'aneris-docsway' ); ?></h3>
					<ol class="dsw-tut">
						<li><?php esc_html_e( 'Go to Divi, Theme Builder and add a new template.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'Under “Use on”, choose the doc pages. If your docs share a parent page, “Children of” that page is the simplest choice.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'Build a custom body with two columns: a narrow one for the sidebar and a wide one with the Post Content module.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'In the sidebar column, add the Docsway Docs Search and Docsway Docs Menu modules.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'Optionally add Docsway On This Page in a third column, and turn off the automatic contents in Settings so it is not shown twice.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'Save the template. The modules show a placeholder in the builder and the real menu on the site.', 'aneris-docsway' ); ?></li>
					</ol>
					<p class="dsw-note"><?php echo wp_kses( __( '<strong>Divi 5:</strong> the Docsway modules are not in the Divi 5 module list yet. Add a Code module instead and type the shortcodes, for example <code>[docsway_search]</code> and <code>[docsway_menu]</code>. Layouts that already use the Docsway modules keep working.', 'aneris-docsway' ), array( 'strong' => array(), 'code' => array() ) ); ?></p>

					<h3><?php esc_html_e( 'Block editor and block themes', 'aneris-docsway' ); ?></h3>
					<p><?php esc_html_e( 'In the block inserter, search for “Docs”. You will find Docs Menu, Docs Search, Docs: On This Page, Docs: Previous / Next and Docs: Breadcrumbs. In a block theme, add them to the Page template in the Site Editor. They also work in sidebar widget areas.', 'aneris-docsway' ); ?></p>

					<h3><?php esc_html_e( 'Shortcodes (any theme or builder)', 'aneris-docsway' ); ?></h3>
					<table class="dsw-codes">
						<tr><td><?php echo Docsway_Admin::copy_field( '[docsway_menu]' ); // phpcs:ignore ?></td><td><?php esc_html_e( 'Sidebar menu. Optional: title="Docs".', 'aneris-docsway' ); ?></td></tr>
						<tr><td><?php echo Docsway_Admin::copy_field( '[docsway_search]' ); // phpcs:ignore ?></td><td><?php esc_html_e( 'Search box with instant results. Optional: placeholder="…" button="no".', 'aneris-docsway' ); ?></td></tr>
						<tr><td><?php echo Docsway_Admin::copy_field( '[docsway_toc]' ); // phpcs:ignore ?></td><td><?php esc_html_e( 'On this page contents. Optional: title="…" min="2".', 'aneris-docsway' ); ?></td></tr>
						<tr><td><?php echo Docsway_Admin::copy_field( '[docsway_prev_next]' ); // phpcs:ignore ?></td><td><?php esc_html_e( 'Previous and next links.', 'aneris-docsway' ); ?></td></tr>
						<tr><td><?php echo Docsway_Admin::copy_field( '[docsway_breadcrumbs]' ); // phpcs:ignore ?></td><td><?php esc_html_e( 'Breadcrumbs with search engine markup.', 'aneris-docsway' ); ?></td></tr>
						<tr><td><?php echo Docsway_Admin::copy_field( '[docsway_search_results]' ); // phpcs:ignore ?></td><td><?php esc_html_e( 'Full results list. Put it on your search results page only.', 'aneris-docsway' ); ?></td></tr>
					</table>
					<p><?php esc_html_e( 'In a PHP template:', 'aneris-docsway' ); ?> <code>&lt;?php echo do_shortcode( '[docsway_menu]' ); ?&gt;</code></p>
				</article>

				<article class="dsw-card dsw-topic" id="search">
					<h2><?php esc_html_e( 'Search', 'aneris-docsway' ); ?></h2>
					<p><?php esc_html_e( 'Docsway search only looks at doc pages, so readers are not shown blog posts or shop pages.', 'aneris-docsway' ); ?></p>
					<ul class="dsw-list">
						<li><strong><?php esc_html_e( 'Instant results', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'appear under the box as people type, with matching words highlighted.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Jump to a heading', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'when a heading on a page matches, it is listed under the result and links straight to that part of the page.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Keyboard', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'arrow keys move through results, Enter opens one, Esc closes the list.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Search anywhere', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'on doc pages, Ctrl+K (Cmd+K on Mac) or the / key opens a search window.', 'aneris-docsway' ); ?></li>
					</ul>
					<h3><?php esc_html_e( 'Where full results show', 'aneris-docsway' ); ?></h3>
					<p><?php esc_html_e( 'When someone presses Enter, they see a full list of results. Choose where in Settings, Search, Full results show:', 'aneris-docsway' ); ?></p>
					<ul class="dsw-list">
						<li><strong><?php esc_html_e( 'On the docs home page', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'results appear inside the search home, below the search box, without leaving the page. Your layout and sidebar menu stay in place, quick searches stay on top and the topic cards step aside until the reader goes back. Search boxes anywhere else on the site send readers here too. Set the Docs home page under Navigation.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'On a separate results page', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'a page of its own that lists results. The setup wizard can create it.', 'aneris-docsway' ); ?></li>
					</ul>
					<p><?php esc_html_e( 'Either way the address includes the search words, so readers can share a link to their results and the Back button works.', 'aneris-docsway' ); ?></p>
					<h3><?php esc_html_e( 'A separate results page', 'aneris-docsway' ); ?></h3>
					<p><?php esc_html_e( 'The setup wizard creates this page for you. To make one yourself:', 'aneris-docsway' ); ?></p>
					<ol class="dsw-tut">
						<li><?php esc_html_e( 'Create a page called Search results.', 'aneris-docsway' ); ?></li>
						<li><?php echo wp_kses( sprintf( /* translators: %s: shortcode */ __( 'Add the shortcode %s to it and publish.', 'aneris-docsway' ), '<code>[docsway_search_results]</code>' ), array( 'code' => array() ) ); ?></li>
						<li><?php esc_html_e( 'Choose it as the Results page in Docsway, Settings, Search.', 'aneris-docsway' ); ?></li>
					</ol>
					<p><?php esc_html_e( 'Without a results page, search still works: Enter simply keeps the instant results open.', 'aneris-docsway' ); ?></p>
					<p><?php esc_html_e( 'On the results page, other docs search boxes (for example one in a sidebar) are hidden, because the page has its own. Untick this in Settings, Search if you prefer to keep them.', 'aneris-docsway' ); ?></p>

					<h3><?php esc_html_e( 'A search home for your docs', 'aneris-docsway' ); ?></h3>
					<p><?php esc_html_e( 'The search home is a start page that helps people find answers quickly: a heading, a large search box, up to six quick search buttons and up to six topic cards.', 'aneris-docsway' ); ?></p>
					<ol class="dsw-tut">
						<li><?php esc_html_e( 'In Settings, Search home, write a heading and add a few quick searches. Use the questions people really ask; Search insights shows you what they type.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'Add topic cards for your most useful pages, such as an install guide, a quick start or your API reference. Pick an icon, a title, one short line and the page.', 'aneris-docsway' ); ?></li>
						<li><?php echo wp_kses( sprintf( /* translators: %s: shortcode */ __( 'Put it on your docs home page with the Docs Search Home block, the Docsway Search Home Divi module or %s.', 'aneris-docsway' ), '<code>[docsway_search_home]</code>' ), array( 'code' => array() ) ); ?></li>
						<li><?php esc_html_e( 'A Docs Menu block below it gives readers the full list of pages too.', 'aneris-docsway' ); ?></li>
					</ol>
					<p><?php esc_html_e( 'Quick searches also appear in the Ctrl+K search window before anything is typed.', 'aneris-docsway' ); ?></p>

					<h3><?php esc_html_e( 'How search understands readers', 'aneris-docsway' ); ?></h3>
					<p><?php esc_html_e( 'Docsway reads every doc page section by section, so it can point readers to the exact part of a page that answers them. There is no index to build and no extra plugin to install: new and edited pages are searchable as soon as they are published.', 'aneris-docsway' ); ?></p>
					<ul class="dsw-list">
						<li><strong><?php esc_html_e( 'Questions work', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'words like “how do I” are ignored, so “How do I share an album?” searches for share and album.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Other forms of a word', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( '“installing”, “installed” and “installation” all find “install”.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Typing mistakes', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'when nothing matches, Docsway tries the closest words from your page titles and headings, so “dokcer” finds “docker”, and says so above the results.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Best answer', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'the section that answers the search best is shown on top with a short summary and a link straight to it, followed by other pages that cover the same thing.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Matching sections', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'each result lists up to three headings that hold the search, each with a line of text, linking to that part of the page.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Highlights on the page', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'when a reader opens a result, the words they searched for are highlighted and the section briefly lights up. A Clear button removes the highlights.', 'aneris-docsway' ); ?></li>
					</ul>
				</article>

				<?php do_action( 'docsway_help_articles' ); ?>

				<article class="dsw-card dsw-topic" id="page-features">
					<h2><?php esc_html_e( 'Contents, previous / next, breadcrumbs', 'aneris-docsway' ); ?></h2>
					<p><?php esc_html_e( 'These are added to every doc page automatically. Switch each one on or off in Settings, Navigation.', 'aneris-docsway' ); ?></p>
					<ul class="dsw-list">
						<li><strong><?php esc_html_e( 'On this page', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'lists the headings on the page and highlights the one you are reading. It appears when a page has at least three headings; you can change that number and which heading levels count.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Previous and next', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'follow the menu order, so readers can go through the docs like a book.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Breadcrumbs', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'show the sections above the current page. Set a Docs home page in Settings to start the trail there. Search engines can read them too.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Copy buttons', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'appear on code blocks when you hover over them.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Last updated and reading time', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'a short line under the title with the date the page last changed and how long it takes to read. Switch each on or off in Settings, Navigation.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Heading links', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'a # appears next to headings on hover. Clicking it copies a link straight to that section, handy for support replies.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Was this page helpful?', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'Yes and No buttons at the bottom of each doc page. The answers appear in Search insights.', 'aneris-docsway' ); ?></li>
					</ul>
					<p class="dsw-note"><?php esc_html_e( 'If you place any of these yourself with a block or Divi module, Docsway notices and does not add a second copy to the page content. In a Theme Builder sidebar it cannot see your module, so switch the automatic version off.', 'aneris-docsway' ); ?></p>
				</article>

				<article class="dsw-card dsw-topic" id="insights">
					<h2><?php esc_html_e( 'Search insights', 'aneris-docsway' ); ?></h2>
					<p><?php esc_html_e( 'Docsway keeps a simple tally of what people search for in your docs and whether they found anything.', 'aneris-docsway' ); ?></p>
					<ol class="dsw-tut">
						<li><?php esc_html_e( 'Open Docsway, Search insights once a week.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'Start with Searches with no results. Click a search to try it yourself.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'If the answer exists, add the reader’s word to that page, for example in a heading. If it does not, that is your next page to write.', 'aneris-docsway' ); ?></li>
					</ol>
					<p><?php esc_html_e( 'Only the search words, a count and the number of results are stored. No personal data is kept.', 'aneris-docsway' ); ?></p>
					<h3><?php esc_html_e( 'Page feedback', 'aneris-docsway' ); ?></h3>
					<p><?php esc_html_e( 'Below the searches, Page feedback lists the answers to “Was this page helpful?”, least helpful first. Start improving the pages at the top. Only the counts are kept: to stop repeat votes, a scrambled code made from the reader’s address and the page is held for one day and then deleted.', 'aneris-docsway' ); ?></p>
				</article>

				<article class="dsw-card dsw-topic" id="layout">
					<h2><?php esc_html_e( 'The docs layout', 'aneris-docsway' ); ?></h2>
					<p><?php esc_html_e( 'With the Docsway docs layout, you do not need to build anything. Every doc page, your docs home and the search results page get a complete docs layout, between your theme’s own header and footer. Pages that are not docs are never touched.', 'aneris-docsway' ); ?></p>
					<ul class="dsw-list">
						<li><strong><?php esc_html_e( 'Sidebar', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'search and the docs menu, on the left or the right. It stays in view as readers scroll and folds into a button on phones.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'The page', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'breadcrumbs, the title, your content and previous and next links.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'On this page', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'in a column of its own, opposite the menu, or at the top of the page. On smaller screens the column moves to the top.', 'aneris-docsway' ); ?></li>
						<li><strong><?php esc_html_e( 'Width', 'aneris-docsway' ); ?></strong>: <?php esc_html_e( 'within a page width you choose, or the full width of the screen.', 'aneris-docsway' ); ?></li>
					</ul>
					<p><?php esc_html_e( 'The style you pick (Modern, Classic, Dark and so on) colours the whole docs area, including headings, links and code blocks. With Follow my theme, the page keeps your theme’s fonts and colours.', 'aneris-docsway' ); ?></p>
					<p><?php esc_html_e( 'Choose the layout in Settings, Appearance, or in the setup wizard. Prefer your own design? Pick My theme’s layout and place the blocks, Divi modules or shortcodes yourself (see Placing the menu and search).', 'aneris-docsway' ); ?></p>
					<p><?php esc_html_e( 'Header and footer: keep your theme’s, or switch to the Docsway docs bar. The docs bar replaces the theme’s header and footer on docs pages with a clean bar holding your logo (or site name), search, the light / dark switch and a link back to your site, so your docs feel like a separate docs site.', 'aneris-docsway' ); ?></p>
					<p><?php esc_html_e( 'It works with classic themes, Divi included, and with block themes such as Twenty Twenty-Five. In a block theme it uses the header and footer from your page template, including any changes you made to them in the Site Editor.', 'aneris-docsway' ); ?></p>
					<p class="dsw-note"><?php esc_html_e( 'Page builders keep working inside the layout: Divi, for example, still builds the page content, and its Visual Builder opens as normal. If your docs home was built with the menu on it, remove that menu, because the layout already shows one in the sidebar.', 'aneris-docsway' ); ?></p>
				</article>

				<article class="dsw-card dsw-topic" id="styling">
					<h2><?php esc_html_e( 'Styling', 'aneris-docsway' ); ?></h2>
					<p><?php esc_html_e( 'Pick a style in Settings, Appearance. Follow my theme uses your theme’s fonts and text colour. Modern, Minimalist and Classic each bring their own fonts and colours. Light and Dark put the menu and other parts on their own panels, so they stay readable on any background. Each style has a matching accent colour, which you can change.', 'aneris-docsway' ); ?></p>
					<p><?php esc_html_e( 'Auto light and dark follows each reader’s device: light by day, dark if their system is set to dark. A sun and moon switch lets readers choose, and the choice is remembered in their browser. The accent colour is lightened in dark mode so links stay easy to read.', 'aneris-docsway' ); ?></p>
					<p><?php esc_html_e( 'To fine-tune a style, override these CSS variables on body in your theme or in Divi, Theme Options, Custom CSS:', 'aneris-docsway' ); ?></p>
					<pre class="dsw-pre">body {
  --docsway-accent: #2563eb;     /* current page, links, buttons */
  --docsway-text: #1d2333;       /* main text (inherit = theme) */
  --docsway-font: Georgia, serif; /* font for all components */
  --docsway-muted: #64708a;      /* secondary text */
  --docsway-border: #e2e6ee;     /* lines and outlines */
  --docsway-surface: #ffffff;    /* search box and dropdown background */
  --docsway-soft: #f4f6fa;       /* hover and panel backgrounds */
  --docsway-radius: 8px;         /* corner rounding */
  --docsway-indent: 14px;        /* indent per menu level */
  --docsway-font-size: 15px;     /* base size for all components */
  --docsway-speed: 200ms;        /* open and close animation */
}</pre>
					<p><?php esc_html_e( 'Every component has its own class to target: .docsway-menu, .docsway-search, .docsway-toc, .docsway-pager, .docsway-crumbs and .docsway-results.', 'aneris-docsway' ); ?></p>
				</article>

				<article class="dsw-card dsw-topic" id="existing">
					<h2><?php esc_html_e( 'Using a taxonomy you already have', 'aneris-docsway' ); ?></h2>
					<p><?php esc_html_e( 'If your docs are already grouped with a custom taxonomy, for example one created with CPT UI, Docsway can use it directly instead of its own sections.', 'aneris-docsway' ); ?></p>
					<ol class="dsw-tut">
						<li><?php esc_html_e( 'Go to Settings, Content.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'Choose “A category system I already have” and pick it from the list.', 'aneris-docsway' ); ?></li>
						<li><?php esc_html_e( 'Save. The menu is rebuilt from that taxonomy straight away.', 'aneris-docsway' ); ?></li>
					</ol>
					<p class="dsw-note"><?php esc_html_e( 'Keep the plugin that registers the taxonomy active. If it is deactivated, Docsway shows a warning on the Structure screen.', 'aneris-docsway' ); ?></p>
				</article>

				<article class="dsw-card dsw-topic" id="troubleshooting">
					<h2><?php esc_html_e( 'Troubleshooting', 'aneris-docsway' ); ?></h2>
					<dl class="dsw-faq">
						<dt><?php esc_html_e( 'The menu says there are no sections.', 'aneris-docsway' ); ?></dt>
						<dd><?php esc_html_e( 'Sections only show when they contain at least one published page. Check Structure, Assign pages, “Already in docs”.', 'aneris-docsway' ); ?></dd>
						<dt><?php esc_html_e( 'A page is missing from the menu.', 'aneris-docsway' ); ?></dt>
						<dd><?php esc_html_e( 'It must be published and in a section. Drafts and private pages are left out. If it was just changed, press Rebuild menu cache on Menu order.', 'aneris-docsway' ); ?></dd>
						<dt><?php esc_html_e( 'Contents or previous / next appear twice.', 'aneris-docsway' ); ?></dt>
						<dd><?php esc_html_e( 'You have placed them in a template and the automatic version is still on. Switch the automatic one off in Settings, Navigation.', 'aneris-docsway' ); ?></dd>
						<dt><?php esc_html_e( 'A search result does not jump to the right heading.', 'aneris-docsway' ); ?></dt>
						<dd><?php esc_html_e( 'Two headings with the same text get numbered anchors. Give headings distinct wording, or add your own id to the heading.', 'aneris-docsway' ); ?></dd>
						<dt><?php esc_html_e( 'Search finds nothing.', 'aneris-docsway' ); ?></dt>
						<dd><?php esc_html_e( 'Search only covers published pages that are in a section and match the content types in Settings. Words need at least two letters.', 'aneris-docsway' ); ?></dd>
						<dt><?php esc_html_e( 'The Divi module shows a grey box.', 'aneris-docsway' ); ?></dt>
						<dd><?php esc_html_e( 'That is expected inside the Visual Builder. View the page on the site to see the real menu.', 'aneris-docsway' ); ?></dd>
					</dl>
				</article>

			</div>
		</div>
		<?php
		Docsway_Admin::footer();
	}
}
