<?php
/**
 * The Aneris Docsway landing page. A full page of its own, so the theme cannot
 * change it; wp_head() and wp_footer() still run for the site icon and admin bar.
 */

defined( 'ABSPATH' ) || exit;

$docsway_site = Docsway_Site::config();
$docsway_offer = Docsway_Site::offer_running();

/** A small outline icon on a 24 x 24 grid. */
$docsway_icon = function ( $name ) {
	$paths = array(
		'search'   => 'M11 19a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm10 2-4.35-4.35',
		'spark'    => 'M12 3l1.9 5.6L19.5 10l-5.6 1.9L12 17.5l-1.9-5.6L4.5 10l5.6-1.4L12 3Zm7 12 .8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8L19 15Z',
		'type'     => 'M4 7V5h16v2M9 19h6M12 5v14',
		'keyboard' => 'M3 6h18v12H3zM7 10h.01M11 10h.01M15 10h.01M7 14h10',
		'mark'     => 'M9 11l3 3 8-8M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11',
		'list'     => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
		'palette'  => 'M12 21a9 9 0 1 1 9-9c0 1.7-1.3 3-3 3h-1.8a2 2 0 0 0-1.4 3.4A1.5 1.5 0 0 1 13.7 21H12ZM7.5 11h.01M10 7.5h.01M14 7.5h.01M16.5 11h.01',
		'chart'    => 'M4 20V4M4 20h16M8 16v-5M12 16V8M16 16v-3',
		'thumb'    => 'M7 10v11H4V10h3Zm0 0 4-7a2 2 0 0 1 2 2v4h5.5a2 2 0 0 1 2 2.3l-1.2 7A2 2 0 0 1 17.3 21H7',
		'blocks'   => 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z',
		'shield'   => 'M12 3l8 3v6c0 4.5-3.4 8-8 9-4.6-1-8-4.5-8-9V6l8-3Zm-3 9 2 2 4-4',
		'wand'     => 'M15 4V2M15 10V8M11 6H9M21 6h-2M18.5 3.5 17 5M18.5 8.5 17 7M3 21l11-11',
		'file'     => 'M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-6-6Zm0 0v6h6M8 13h8M8 17h5',
		'tag'      => 'M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8ZM7.5 7.5h.01',
		'filter'   => 'M3 5h18l-7 8v6l-4 2v-8L3 5Z',
		'check'    => 'M5 12l4 4 10-10',
		'arrow'    => 'M5 12h14M13 6l6 6-6 6',
	);
	$d = isset( $paths[ $name ] ) ? $paths[ $name ] : $paths['check'];
	return '<svg class="ads-ico" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="' . esc_attr( $d ) . '"/></svg>';
};
$docsway_svg = array(
	'svg'  => array( 'class' => true, 'viewbox' => true, 'aria-hidden' => true, 'focusable' => true ),
	'path' => array( 'd' => true ),
);
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'ads-body' ); ?>>
<?php wp_body_open(); ?>
<a class="ads-skip" href="#ads-main">Skip to content</a>

<header class="ads-header" id="ads-top">
	<div class="ads-wrap ads-header__inner">
		<a class="ads-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<img src="<?php echo esc_url( Docsway_Site::img( 'icon.svg' ) ); ?>" alt="" width="36" height="36">
			<span><span class="ads-brand__pre">Aneris</span> Docsway</span>
		</a>
		<button class="ads-nav-toggle" type="button" aria-expanded="false" aria-controls="ads-nav">
			<span class="screen-reader-text">Menu</span><span class="ads-burger" aria-hidden="true"></span>
		</button>
		<nav class="ads-nav" id="ads-nav" aria-label="Main">
			<a href="#features">Features</a>
			<a href="#how">How it works</a>
			<a href="#pricing">Pricing</a>
			<a href="#faq">FAQ</a>
			<a href="<?php echo esc_url( $docsway_site['demo'] ); ?>">Live demo</a>
			<a class="ads-btn ads-btn--small ads-btn--light" href="<?php echo esc_url( $docsway_site['wporg'] ); ?>">Download free</a>
		</nav>
	</div>
</header>

<main id="ads-main">

	<section class="ads-hero">
		<div class="ads-wrap ads-hero__inner">
			<div class="ads-hero__copy">
				<p class="ads-eyebrow">Free WordPress plugin</p>
				<h1>Turn your WordPress pages into a docs site that <span class="ads-glow">answers questions</span></h1>
				<p class="ads-lead">Aneris Docsway builds a sidebar menu, smart search, contents and page navigation around the pages you already have. Nothing moves, nothing breaks, and setup takes about five minutes.</p>
				<div class="ads-actions">
					<a class="ads-btn ads-btn--light" href="<?php echo esc_url( $docsway_site['wporg'] ); ?>">Download free</a>
					<a class="ads-btn ads-btn--ghost" href="<?php echo esc_url( $docsway_site['demo'] ); ?>">See the live demo <?php echo wp_kses( $docsway_icon( 'arrow' ), $docsway_svg ); ?></a>
				</div>
				<ul class="ads-trust">
					<li><?php echo wp_kses( $docsway_icon( 'check' ), $docsway_svg ); ?> No migration, no URL changes</li>
					<li><?php echo wp_kses( $docsway_icon( 'check' ), $docsway_svg ); ?> Divi, block and classic themes</li>
					<li><?php echo wp_kses( $docsway_icon( 'check' ), $docsway_svg ); ?> Runs on your site, nothing to sign up for</li>
				</ul>
			</div>
			<div class="ads-hero__art" aria-hidden="true">
				<div class="ads-browser ads-hero__browser">
					<div class="ads-browser__bar"><i></i><i></i><i></i><span>yoursite.com/docs/?dq=how do I share an album</span></div>
					<img src="<?php echo esc_url( Docsway_Site::img( '03-best-answer.jpg' ) ); ?>" alt="" width="1600" height="1000" fetchpriority="high">
				</div>
				<div class="ads-phone ads-hero__phone">
					<img src="<?php echo esc_url( Docsway_Site::img( '09-mobile.jpg' ) ); ?>" alt="" width="1600" height="3463">
				</div>
				<div class="ads-chip ads-chip--answer"><?php echo wp_kses( $docsway_icon( 'spark' ), $docsway_svg ); ?> Best answer</div>
				<div class="ads-chip ads-chip--kbd"><kbd>Ctrl</kbd> <kbd>K</kbd></div>
			</div>
		</div>
	</section>

	<section class="ads-try" aria-labelledby="ads-try-title">
		<div class="ads-wrap ads-try__inner">
			<div>
				<h2 id="ads-try-title">Try it now</h2>
				<p>Ask a question about Docsway and see real results on our docs site, which runs on Docsway.</p>
			</div>
			<form class="ads-try__form" action="<?php echo esc_url( $docsway_site['demo'] ); ?>" method="get" role="search">
				<label class="screen-reader-text" for="ads-try-q">Ask a question about Docsway</label>
				<?php echo wp_kses( $docsway_icon( 'search' ), $docsway_svg ); ?>
				<input id="ads-try-q" type="search" name="dq" placeholder="How do I change the style?" required>
				<button class="ads-btn" type="submit">Search the docs</button>
			</form>
			<p class="ads-try__examples">Or try:
				<?php foreach ( array( 'order the menu', 'shortcodes', 'Divi', 'dark mode' ) as $docsway_q ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'dq', rawurlencode( $docsway_q ), $docsway_site['demo'] ) ); ?>"><?php echo esc_html( $docsway_q ); ?></a>
				<?php endforeach; ?>
			</p>
		</div>
	</section>

	<section class="ads-section" id="how" aria-labelledby="ads-how-title">
		<div class="ads-wrap">
			<p class="ads-kicker">How it works</p>
			<h2 class="ads-title" id="ads-how-title">A docs site in three steps</h2>
			<p class="ads-intro">Most documentation tools make you move your content somewhere new. Docsway works the other way round.</p>
			<ol class="ads-steps">
				<li><span class="ads-steps__num">1</span><h3>Put pages into sections</h3><p>Tick a section on any page, or let the setup wizard sort them. Pages stay where they are, built with Divi, the block editor or anything else.</p></li>
				<li><span class="ads-steps__num">2</span><h3>Docsway builds the site</h3><p>A sidebar menu, contents, breadcrumbs and previous and next links appear around your pages, in your theme or with a docs bar of its own.</p></li>
				<li><span class="ads-steps__num">3</span><h3>Readers find answers</h3><p>Smart search takes them to the exact section that answers their question, and Search insights shows you what they could not find.</p></li>
			</ol>
		</div>
	</section>

	<section class="ads-section ads-section--tint" id="features" aria-labelledby="ads-features-title">
		<div class="ads-wrap">
			<p class="ads-kicker">Features</p>
			<h2 class="ads-title" id="ads-features-title">Everything a docs site needs, on the site you already have</h2>

			<div class="ads-feature">
				<div class="ads-feature__copy">
					<h3>Search that answers, not just lists</h3>
					<p>Ask "How do I share an album?" and Docsway shows the section that answers it, with a short summary and a link straight to it. Results appear as readers type, from anywhere with Ctrl+K or Cmd+K.</p>
					<ul class="ads-ticks">
						<li><?php echo wp_kses( $docsway_icon( 'check' ), $docsway_svg ); ?> Understands questions and other forms of a word</li>
						<li><?php echo wp_kses( $docsway_icon( 'check' ), $docsway_svg ); ?> Fixes typing mistakes: "dokcer" finds "docker"</li>
						<li><?php echo wp_kses( $docsway_icon( 'check' ), $docsway_svg ); ?> Searches only your docs, never blog posts or shop pages</li>
					</ul>
				</div>
				<div class="ads-browser"><div class="ads-browser__bar"><i></i><i></i><i></i></div><img src="<?php echo esc_url( Docsway_Site::img( '04-instant-search.jpg' ) ); ?>" alt="The Ctrl+K search window showing the best answer above other results" width="1600" height="1000" loading="lazy"></div>
			</div>

			<div class="ads-feature ads-feature--flip">
				<div class="ads-feature__copy">
					<h3>Readers land on the right paragraph</h3>
					<p>The page a reader opens scrolls to the matching section and highlights their search words, so they never have to hunt for the answer twice.</p>
				</div>
				<div class="ads-browser"><div class="ads-browser__bar"><i></i><i></i><i></i></div><img src="<?php echo esc_url( Docsway_Site::img( '05-highlights.jpg' ) ); ?>" alt="A doc page with the searched words highlighted" width="1600" height="1000" loading="lazy"></div>
			</div>

			<div class="ads-feature">
				<div class="ads-feature__copy">
					<h3>A complete docs site in one click</h3>
					<p>A sidebar menu built from your sections, sections inside sections, an "On this page" column, breadcrumbs and previous and next links, all inside your own theme or with a clean docs bar of its own.</p>
				</div>
				<div class="ads-browser"><div class="ads-browser__bar"><i></i><i></i><i></i></div><img src="<?php echo esc_url( Docsway_Site::img( '01-docs-page.jpg' ) ); ?>" alt="A doc page with the sidebar menu, contents and breadcrumbs" width="1600" height="1000" loading="lazy"></div>
			</div>

			<div class="ads-feature ads-feature--flip">
				<div class="ads-feature__copy">
					<h3>Seven styles, any colour</h3>
					<p>Follow your theme, or pick Modern, Minimalist, Classic, Light, Dark, or Auto light and dark with a switch for readers. Choose any accent colour.</p>
				</div>
				<div class="ads-stack">
					<div class="ads-browser"><div class="ads-browser__bar"><i></i><i></i><i></i></div><img src="<?php echo esc_url( Docsway_Site::img( '08-classic-style.jpg' ) ); ?>" alt="The Classic style" width="1600" height="1000" loading="lazy"></div>
					<div class="ads-browser"><div class="ads-browser__bar"><i></i><i></i><i></i></div><img src="<?php echo esc_url( Docsway_Site::img( '07-dark-style.jpg' ) ); ?>" alt="The Dark style" width="1600" height="1000" loading="lazy"></div>
				</div>
			</div>

			<div class="ads-grid">
				<div class="ads-card"><?php echo wp_kses( $docsway_icon( 'wand' ), $docsway_svg ); ?><h3>Five-minute setup</h3><p>A wizard sorts your pages into sections and sets up the docs home.</p></div>
				<div class="ads-card"><?php echo wp_kses( $docsway_icon( 'chart' ), $docsway_svg ); ?><h3>Search insights</h3><p>Popular searches and, more usefully, the searches that found nothing.</p></div>
				<div class="ads-card"><?php echo wp_kses( $docsway_icon( 'thumb' ), $docsway_svg ); ?><h3>Page feedback</h3><p>"Was this page helpful?" buttons, with the least helpful pages first.</p></div>
				<div class="ads-card"><?php echo wp_kses( $docsway_icon( 'list' ), $docsway_svg ); ?><h3>Drag-and-drop order</h3><p>Arrange sections and pages by dragging, with links to other sites too.</p></div>
				<div class="ads-card"><?php echo wp_kses( $docsway_icon( 'blocks' ), $docsway_svg ); ?><h3>Blocks, modules, shortcodes</h3><p>Place the menu, search or contents anywhere, in any builder.</p></div>
				<div class="ads-card"><?php echo wp_kses( $docsway_icon( 'shield' ), $docsway_svg ); ?><h3>Private by design</h3><p>Everything runs on your site. Nothing is sent to us or anyone else.</p></div>
			</div>
		</div>
	</section>

	<section class="ads-section ads-pro" aria-labelledby="ads-pro-title">
		<div class="ads-wrap ads-pro__inner">
			<div class="ads-pro__copy">
				<p class="ads-kicker ads-kicker--light">Aneris Docsway Pro</p>
				<h2 class="ads-title" id="ads-pro-title">For docs that go beyond web pages</h2>
				<ul class="ads-pro__list">
					<li><?php echo wp_kses( $docsway_icon( 'file' ), $docsway_svg ); ?><div><strong>Search inside documents.</strong> PDF, Word, PowerPoint, Excel and text files from the Media Library join your sections, and search finds the words inside them.</div></li>
					<li><?php echo wp_kses( $docsway_icon( 'tag' ), $docsway_svg ); ?><div><strong>Synonyms.</strong> Teach search your readers' own words, for example "pic" for "photo".</div></li>
					<li><?php echo wp_kses( $docsway_icon( 'filter' ), $docsway_svg ); ?><div><strong>Topic filters.</strong> Readers narrow results to one section, with a count for each.</div></li>
				</ul>
				<a class="ads-btn ads-btn--light" href="#pricing">See Pro prices</a>
			</div>
			<div class="ads-browser"><div class="ads-browser__bar"><i></i><i></i><i></i></div><img src="<?php echo esc_url( Docsway_Site::img( '06-documents.jpg' ) ); ?>" alt="Search results with a PDF file found by the words inside it" width="1600" height="1000" loading="lazy"></div>
		</div>
	</section>

	<section class="ads-section" aria-labelledby="ads-compare-title">
		<div class="ads-wrap ads-wrap--narrow">
			<h2 class="ads-title" id="ads-compare-title">Free and Pro</h2>
			<div class="ads-table-wrap">
				<table class="ads-compare">
					<thead><tr><th scope="col">Feature</th><th scope="col">Free</th><th scope="col">Pro</th></tr></thead>
					<tbody>
						<?php
						$docsway_rows = array(
							array( 'Docs layout, sidebar menu, contents, breadcrumbs', true, true ),
							array( 'Smart search with best answers and typo fixes', true, true ),
							array( 'Seven styles and any accent colour', true, true ),
							array( 'Search insights and page feedback', true, true ),
							array( 'Blocks, Divi modules and shortcodes', true, true ),
							array( 'Search inside PDF, Word, PowerPoint and Excel files', false, true ),
							array( 'Synonyms', false, true ),
							array( 'Topic filters for search results', false, true ),
							array( 'Email support and automatic updates', false, true ),
						);
						foreach ( $docsway_rows as $docsway_row ) :
							?>
							<tr>
								<th scope="row"><?php echo esc_html( $docsway_row[0] ); ?></th>
								<?php foreach ( array( 1, 2 ) as $docsway_col ) : ?>
									<td>
										<?php if ( $docsway_row[ $docsway_col ] ) : ?>
											<span class="ads-yes"><?php echo wp_kses( $docsway_icon( 'check' ), $docsway_svg ); ?><span class="screen-reader-text">Included</span></span>
										<?php else : ?>
											<span class="ads-no"><span aria-hidden="true">&ndash;</span><span class="screen-reader-text">Not included</span></span>
										<?php endif; ?>
									</td>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>

	<section class="ads-section ads-section--tint" id="pricing" aria-labelledby="ads-pricing-title">
		<div class="ads-wrap">
			<p class="ads-kicker">Pricing</p>
			<h2 class="ads-title" id="ads-pricing-title">Start free. Go Pro when your docs need more.</h2>
			<p class="ads-intro">The free plugin is complete on its own. Pro adds documents, synonyms and topic filters.</p>
			<?php if ( $docsway_offer ) : ?>
				<p class="ads-offer"><?php echo wp_kses( $docsway_icon( 'spark' ), $docsway_svg ); ?> Launch offer: <strong><?php echo (int) $docsway_site['coupon_off']; ?>% off</strong> with code <code><?php echo esc_html( $docsway_site['coupon'] ); ?></code>, until <?php echo esc_html( $docsway_site['coupon_day'] ); ?>.</p>
			<?php endif; ?>
			<div class="ads-cycle" role="group" aria-label="Billing">
				<button type="button" class="ads-cycle__btn is-on" data-cycle="annual" aria-pressed="true">Yearly</button>
				<button type="button" class="ads-cycle__btn" data-cycle="lifetime" aria-pressed="false">Lifetime</button>
			</div>
			<div class="ads-prices">
				<div class="ads-price ads-price--free">
					<h3>Free</h3>
					<p class="ads-price__amount"><span>$0</span></p>
					<p class="ads-price__note">Forever, on WordPress.org</p>
					<ul class="ads-ticks">
						<li><?php echo wp_kses( $docsway_icon( 'check' ), $docsway_svg ); ?> The complete docs site</li>
						<li><?php echo wp_kses( $docsway_icon( 'check' ), $docsway_svg ); ?> Smart search and insights</li>
						<li><?php echo wp_kses( $docsway_icon( 'check' ), $docsway_svg ); ?> Unlimited pages and sites</li>
					</ul>
					<a class="ads-btn ads-btn--outline" href="<?php echo esc_url( $docsway_site['wporg'] ); ?>">Download free</a>
				</div>
				<?php foreach ( $docsway_site['plans'] as $docsway_plan ) : ?>
					<div class="ads-price<?php echo empty( $docsway_plan['popular'] ) ? '' : ' ads-price--popular'; ?>">
						<?php if ( ! empty( $docsway_plan['popular'] ) ) : ?>
							<p class="ads-price__badge">Most popular</p>
						<?php endif; ?>
						<h3>Pro, <?php echo esc_html( 1 === (int) $docsway_plan['sites'] ? '1 site' : $docsway_plan['sites'] . ' sites' ); ?></h3>
						<p class="ads-price__amount" data-annual="<?php echo esc_attr( '$' . $docsway_plan['annual'] ); ?>" data-lifetime="<?php echo esc_attr( '$' . $docsway_plan['lifetime'] ); ?>"><span><?php echo esc_html( '$' . $docsway_plan['annual'] ); ?></span></p>
						<p class="ads-price__note" data-annual="per year" data-lifetime="one payment, updates for life">per year</p>
						<ul class="ads-ticks">
							<li><?php echo wp_kses( $docsway_icon( 'check' ), $docsway_svg ); ?> Everything in Free</li>
							<li><?php echo wp_kses( $docsway_icon( 'check' ), $docsway_svg ); ?> Documents, synonyms, topic filters</li>
							<li><?php echo wp_kses( $docsway_icon( 'check' ), $docsway_svg ); ?> Email support and updates</li>
						</ul>
						<a class="ads-btn<?php echo empty( $docsway_plan['popular'] ) ? ' ads-btn--outline' : ''; ?>" href="<?php echo esc_url( Docsway_Site::checkout_url( 'annual', $docsway_plan['sites'] ) ); ?>" data-annual="<?php echo esc_url( Docsway_Site::checkout_url( 'annual', $docsway_plan['sites'] ) ); ?>" data-lifetime="<?php echo esc_url( Docsway_Site::checkout_url( 'lifetime', $docsway_plan['sites'] ) ); ?>">Buy Pro</a>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="ads-guarantee"><?php echo wp_kses( $docsway_icon( 'shield' ), $docsway_svg ); ?> 30-day money-back guarantee, no questions asked. Secure checkout by Freemius, with VAT and sales tax handled for you. If a licence expires, Pro keeps working; only updates and support stop.</p>
		</div>
	</section>

	<section class="ads-section" id="faq" aria-labelledby="ads-faq-title">
		<div class="ads-wrap ads-wrap--narrow">
			<p class="ads-kicker">FAQ</p>
			<h2 class="ads-title" id="ads-faq-title">Questions, answered</h2>
			<?php
			$docsway_faq = array(
				'Do I have to move my pages?'                => 'No. Docsway never moves or copies content, and page addresses stay the same. You choose which pages belong to the docs by putting them into sections.',
				'Does it work with Divi?'                    => 'Yes, including pages built with the Divi Builder. It adds Divi modules too; on Divi 5, use the shortcodes in a Code module.',
				'Does search need a separate service?'       => 'No. Everything runs on your own site, with nothing to sign up for or set up.',
				'Can it search inside PDFs?'                 => 'Yes, with Docsway Pro. Scanned PDFs are pictures of text, so add a short description to them and search will find that.',
				'What happens when a Pro licence expires?'   => 'Pro keeps working. Only updates and support stop until you renew.',
				'Can I get a refund?'                        => 'Yes. If Docsway Pro is not right for you, ask within 30 days for a full refund, no questions asked.',
				'Is any personal data stored?'               => 'Search insights store only the words searched and how many results they found. Nothing is sent to Aneris Designs.',
			);
			foreach ( $docsway_faq as $docsway_question => $docsway_answer ) :
				?>
				<details class="ads-faq">
					<summary><?php echo esc_html( $docsway_question ); ?></summary>
					<p><?php echo esc_html( $docsway_answer ); ?></p>
				</details>
			<?php endforeach; ?>
		</div>
	</section>

	<section class="ads-cta" aria-labelledby="ads-cta-title">
		<div class="ads-wrap ads-cta__inner">
			<h2 id="ads-cta-title">Give your readers docs they can actually search</h2>
			<p>Free on WordPress.org. Set up in about five minutes.</p>
			<div class="ads-actions ads-actions--center">
				<a class="ads-btn ads-btn--light" href="<?php echo esc_url( $docsway_site['wporg'] ); ?>">Download free</a>
				<a class="ads-btn ads-btn--ghost" href="<?php echo esc_url( $docsway_site['demo'] ); ?>">See the live demo</a>
			</div>
		</div>
	</section>

</main>

<footer class="ads-footer">
	<div class="ads-wrap ads-footer__inner">
		<div class="ads-footer__brand">
			<a class="ads-brand ads-brand--dark" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<img src="<?php echo esc_url( Docsway_Site::img( 'icon.svg' ) ); ?>" alt="" width="32" height="32">
				<span><span class="ads-brand__pre">Aneris</span> Docsway</span>
			</a>
			<p>A documentation plugin for WordPress by <a href="<?php echo esc_url( $docsway_site['company'] ); ?>">Aneris Designs</a>.</p>
		</div>
		<nav class="ads-footer__links" aria-label="Footer">
			<a href="<?php echo esc_url( $docsway_site['wporg'] ); ?>">WordPress.org</a>
			<a href="<?php echo esc_url( $docsway_site['demo'] ); ?>">Live demo and docs</a>
			<a href="#pricing">Pricing</a>
			<a href="#faq">FAQ</a>
		</nav>
		<p class="ads-footer__copy">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Aneris Designs</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
