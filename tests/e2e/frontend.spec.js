// Front-end behaviour on the seeded docs site, as a logged-out visitor.
const { test, expect } = require( '@playwright/test' );

test.use( { storageState: { cookies: [], origins: [] } } );

test.describe( 'Docs menu', () => {
	test( 'highlights the current page and opens its sections', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const menu = page.locator( '.docsway-menu' ).first();
		await expect( menu.locator( '.is-current' ) ).toHaveText( 'Installing on Ubuntu' );
		await expect( menu.locator( '.is-ancestor > .docsway-menu__row' ).first() ).toContainText( 'Installation' );
		await expect( menu.locator( '.is-current' ) ).toBeVisible();
	} );

	test( 'hides empty sections, drafts and private pages', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const menu = page.locator( '.docsway-menu' ).first();
		await expect( menu ).not.toContainText( 'Coming soon' );
		await expect( menu ).not.toContainText( 'Beta features' );
		await expect( menu ).not.toContainText( 'Internal: support playbook' );
	} );

	test( 'shows top-level sections in the saved order', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const tops = await page.locator( '.docsway-menu' ).first().locator( '.docsway-menu__list--root > li > .docsway-menu__row > :first-child' ).allTextContents();
		expect( tops.map( ( t ) => t.trim() ).slice( 0, 3 ) ).toEqual( [ 'Introduction', 'Installation', 'Configuration' ] );
	} );

	test( 'a linked section heading is a link', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const intro = page.locator( '.docsway-menu' ).first().locator( '.docsway-menu__item--top', { hasText: 'Introduction' } ).first();
		await expect( intro.locator( '> .docsway-menu__row a' ) ).toHaveAttribute( 'href', /welcome-to-magpie/ );
	} );

	test( 'accordion keeps one top-level section open', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const menu = page.locator( '.docsway-menu' ).first();
		await menu.locator( '.docsway-menu__item--top', { hasText: 'Sharing' } ).locator( '> .docsway-menu__row .docsway-menu__toggle' ).click();
		const install = menu.locator( '.docsway-menu__item--top', { hasText: 'Installation' } ).locator( '> .docsway-menu__list' );
		await expect( install ).toBeHidden();
	} );

	test( 'collapses behind a button on phones', async ( { browser } ) => {
		const ctx = await browser.newContext( { viewport: { width: 390, height: 844 } } );
		const page = await ctx.newPage();
		await page.goto( '/watch-folders/' );
		const menu = page.locator( '.docsway-menu' ).first();
		await expect( menu.locator( '.docsway-menu__list--root' ) ).toBeHidden();
		await menu.locator( '.docsway-menu__mobile' ).click();
		await expect( menu.locator( '.docsway-menu__list--root' ) ).toBeVisible();
		await ctx.close();
	} );
} );

test.describe( 'Page features', () => {
	test( 'breadcrumbs follow the section trail', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const crumbs = ( await page.locator( '.docsway-crumbs li' ).allTextContents() ).map( ( t ) => t.trim() );
		expect( crumbs ).toEqual( [ 'Documentation', 'Installation', 'Linux', 'Installing on Ubuntu' ] );
	} );

	test( 'contents list matches the headings and their anchors', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const hrefs = await page.locator( '.docsway-toc a' ).evaluateAll( ( as ) => as.map( ( a ) => a.getAttribute( 'href' ).slice( 1 ) ) );
		expect( hrefs.length ).toBeGreaterThanOrEqual( 3 );
		for ( const id of hrefs ) {
			await expect( page.locator( `[id="${ id }"]` ) ).toHaveCount( 1 );
		}
	} );

	test( 'no contents box on a page without headings', async ( { page } ) => {
		await page.goto( '/release-notes/' );
		await expect( page.locator( '.docsway-toc' ) ).toHaveCount( 0 );
	} );

	test( 'tricky headings get unique, predictable anchors', async ( { page } ) => {
		await page.goto( '/edge-case-tricky-headings/' );
		for ( const id of [ 'overview', 'overview-2', 'custom-anchor', 'using-config-flags' ] ) {
			await expect( page.locator( `[id="${ id }"]` ) ).toHaveCount( 1 );
		}
	} );

	test( 'long pages list every heading', async ( { page } ) => {
		await page.goto( '/glossary/' );
		await expect( page.locator( '.docsway-toc:visible a' ) ).toHaveCount( 26 * 3 );
	} );

	test( 'previous and next links follow menu order', async ( { page } ) => {
		await page.goto( '/installing-on-debian/' );
		await expect( page.locator( '.docsway-pager__link--prev' ) ).toContainText( 'Installing on CentOS Stream' ); // Pages sort A to Z inside a section.
		await expect( page.locator( '.docsway-pager__link--next' ) ).toContainText( 'Installing on Rocky Linux' );
	} );

	test( 'non-doc pages get nothing added', async ( { page } ) => {
		await page.goto( '/about-us/' );
		await expect( page.locator( '.docsway-toc, .docsway-pager, .docsway-crumbs' ) ).toHaveCount( 0 );
	} );

	test( 'code blocks get a copy button', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const pres = await page.locator( '.entry-content pre' ).count();
		await expect( page.locator( '.docsway-copy' ) ).toHaveCount( pres );
	} );
} );

test.describe( 'Search', () => {
	test( 'instant results only include docs', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const input = page.locator( '.docsway-search__input' ).first();
		await input.fill( 'docker' );
		const panel = page.locator( '.docsway-search__panel' ).first();
		await expect( panel.locator( '.docsway-hit__title', { hasText: 'Installing with Docker' } ) ).toBeVisible();
		await expect( panel ).not.toContainText( 'About us' );
		await expect( panel ).not.toContainText( 'Blog:' );
	} );

	test( 'arrow keys move through results', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const input = page.locator( '.docsway-search__input' ).first();
		await input.fill( 'album' );
		await expect( page.locator( '.docsway-search__panel .docsway-hit' ).first() ).toBeVisible();
		await input.press( 'ArrowDown' );
		await expect( page.locator( '.docsway-search__panel .docsway-hit.is-active' ) ).toHaveCount( 1 );
	} );

	test( 'markup typed into search is shown as text', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		await page.locator( '.docsway-search__input' ).first().fill( '<img src=x onerror=alert(1)>' );
		await page.waitForTimeout( 800 );
		await expect( page.locator( '.docsway-search__panel img' ) ).toHaveCount( 0 );
	} );

	test( 'Ctrl+K opens the search window and Esc closes it', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		await page.locator( 'body' ).click( { position: { x: 5, y: 5 } } );
		await page.keyboard.press( 'Control+k' );
		const modal = page.locator( '.docsway-modal' );
		await expect( modal ).toBeVisible();
		await page.keyboard.type( 'lightroom' );
		await expect( modal.locator( '.docsway-hit__title', { hasText: 'Lightroom integration' } ) ).toBeVisible();
		await page.keyboard.press( 'Escape' );
		await expect( modal ).toBeHidden();
	} );

	test( 'matching headings link straight to the section', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		await page.locator( '.docsway-search__input' ).first().fill( 'heading with its own' );
		// Either the best answer or a heading below a result links to the section.
		await expect( page.locator( '.docsway-search__panel a[href$="#custom-anchor"]' ).first() ).toBeVisible();
	} );

	test( 'Vietnamese text is searchable', async ( { page } ) => {
		await page.goto( '/search-results/?dq=' + encodeURIComponent( 'cài đặt' ) );
		await expect( page.locator( '.docsway-result__title' ).first() ).toContainText( 'Cài đặt' );
	} );

	test( 'results page lists docs and paginates', async ( { page } ) => {
		await page.goto( '/search-results/?dq=album' );
		await expect( page.locator( '.docsway-results__count' ) ).toContainText( 'results' );
		await expect( page.locator( '.docsway-result' ) ).toHaveCount( 10 );
		await expect( page.locator( '.docsway-results__pages' ) ).toBeVisible();
		await expect( page.locator( '.docsway-results' ) ).not.toContainText( 'About us' );
	} );

	test( 'results page shows only its own search box', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		await expect( page.locator( 'form[data-docsway-search]' ) ).toHaveCount( 1 ); // The sidebar box.
		await page.goto( '/search-results/?dq=album' );
		await expect( page.locator( 'form[data-docsway-search]' ) ).toHaveCount( 1 );
		await expect( page.locator( '.docsway-results form[data-docsway-search]' ) ).toHaveCount( 1 );
	} );

	test( 'search home quick searches run a search', async ( { page } ) => {
		await page.goto( '/documentation/' );
		const home = page.locator( '.docsway-home' );
		await expect( home.locator( '.docsway-home__title' ) ).toHaveText( 'Search the Magpie docs' );
		await expect( home.locator( '.docsway-chip' ) ).toHaveCount( 5 );
		await expect( home.locator( '.docsway-card' ) ).toHaveCount( 6 );
		await expect( home.locator( '.docsway-card', { hasText: 'Install guide' } ) ).toHaveAttribute( 'href', /system-requirements/ );

		await home.locator( '.docsway-chip', { hasText: 'Docker' } ).click();
		await expect( home.locator( '.docsway-search__input' ) ).toHaveValue( 'docker' );
		await expect( home.locator( '.docsway-home__list .docsway-result__title', { hasText: 'Installing with Docker' } ) ).toBeVisible();
	} );

	test( 'search home lists full results on the same page', async ( { page } ) => {
		await page.goto( '/documentation/' );
		const home = page.locator( '.docsway-home' );
		await home.locator( '.docsway-search__input' ).fill( 'album' );
		await home.locator( '.docsway-search__input' ).press( 'Enter' );
		await expect( page ).toHaveURL( /\/documentation\/\?dq=album$/ );
		await expect( home.locator( '.docsway-home__list .docsway-result' ) ).toHaveCount( 10 );
		await expect( home.locator( '.docsway-card' ).first() ).toBeHidden();
		await expect( page.locator( '.docsway-menu' ).first() ).toBeVisible(); // The rest of the page stays.

		await home.locator( '.docsway-results__pages a.page-numbers', { hasText: '2' } ).click();
		await expect( page ).toHaveURL( /dq=album&pg=2/ );
		await expect( home.locator( '.docsway-results__pages .current' ) ).toHaveText( '2' );
		await page.goBack();
		await expect( home.locator( '.docsway-results__pages .current' ) ).toHaveText( '1' );

		await home.locator( '.docsway-home__back a' ).click();
		await expect( page ).toHaveURL( /\/documentation\/$/ );
		await expect( home.locator( '.docsway-card' ).first() ).toBeVisible();
	} );

	test( 'a shared results link opens with the results listed', async ( { page } ) => {
		await page.goto( '/documentation/?dq=album&pg=2' );
		const home = page.locator( '.docsway-home' );
		await expect( home ).toHaveClass( /is-searching/ );
		await expect( home.locator( '.docsway-search__input' ) ).toHaveValue( 'album' );
		await expect( home.locator( '.docsway-results__pages .current' ) ).toHaveText( '2' );
		await expect( page.locator( 'form[data-docsway-search]' ) ).toHaveCount( 1 ); // Sidebar box hidden while results show.
	} );

	test( 'other search boxes send full results to the docs home', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		await expect( page.locator( 'form[data-docsway-search]' ).first() ).toHaveAttribute( 'action', /\/documentation\/$/ );
	} );

	test( 'Ctrl+K lists the quick searches before typing', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		await page.locator( 'body' ).click( { position: { x: 5, y: 5 } } );
		await page.keyboard.press( 'Control+k' );
		const modal = page.locator( '.docsway-modal' );
		await expect( modal.locator( '.docsway-search__suggest-title' ) ).toBeVisible();
		await expect( modal.locator( '.docsway-chip' ) ).toHaveCount( 5 );
		await page.keyboard.press( 'ArrowDown' );
		await page.keyboard.press( 'Enter' );
		await expect( modal.locator( '.docsway-search__input' ) ).toHaveValue( 'install' );
		await expect( modal.locator( '.docsway-hit' ).first() ).toBeVisible();
	} );

	test( 'Divi 5 pages are found by their text, not their layout data', async ( { request } ) => {
		const search = async ( q ) => ( await ( await request.get( '/wp-json/docsway/v1/search?q=' + encodeURIComponent( q ) ) ).json() );

		const accented = await search( 'lumière' );
		const hit = accented.results.find( ( r ) => r.title === 'Edge case: Divi 5 content' );
		expect( hit ).toBeTruthy();
		expect( hit.excerpt ).toContain( 'Lumière keeps every café photo safe.' );
		expect( hit.headings.map( ( h ) => h.text ) ).toContain( 'Ảnh động và Lumière' );

		for ( const layoutWord of [ 'builderVersion', 'innerContent', 'desktop' ] ) {
			const found = ( await search( layoutWord ) ).results.map( ( r ) => r.title );
			expect( found, layoutWord ).not.toContain( 'Edge case: Divi 5 content' );
		}
	} );

	test( 'results page explains when nothing matched', async ( { page } ) => {
		await page.goto( '/search-results/?dq=zzqxnothing' );
		await expect( page.locator( '.docsway-results__none' ) ).toBeVisible();
	} );
} );
