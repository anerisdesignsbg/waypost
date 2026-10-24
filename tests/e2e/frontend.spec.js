// Front-end behaviour on the seeded docs site, as a logged-out visitor.
const { test, expect } = require( '@playwright/test' );

test.use( { storageState: { cookies: [], origins: [] } } );

test.describe( 'Docs menu', () => {
	test( 'highlights the current page and opens its sections', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const menu = page.locator( '.waypost-menu' ).first();
		await expect( menu.locator( '.is-current' ) ).toHaveText( 'Installing on Ubuntu' );
		await expect( menu.locator( '.is-ancestor > .waypost-menu__row' ).first() ).toContainText( 'Installation' );
		await expect( menu.locator( '.is-current' ) ).toBeVisible();
	} );

	test( 'hides empty sections, drafts and private pages', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const menu = page.locator( '.waypost-menu' ).first();
		await expect( menu ).not.toContainText( 'Coming soon' );
		await expect( menu ).not.toContainText( 'Beta features' );
		await expect( menu ).not.toContainText( 'Internal: support playbook' );
	} );

	test( 'shows top-level sections in the saved order', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const tops = await page.locator( '.waypost-menu' ).first().locator( '.waypost-menu__list--root > li > .waypost-menu__row > :first-child' ).allTextContents();
		expect( tops.map( ( t ) => t.trim() ).slice( 0, 3 ) ).toEqual( [ 'Introduction', 'Installation', 'Configuration' ] );
	} );

	test( 'a linked section heading is a link', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const intro = page.locator( '.waypost-menu' ).first().locator( '.waypost-menu__item--top', { hasText: 'Introduction' } ).first();
		await expect( intro.locator( '> .waypost-menu__row a' ) ).toHaveAttribute( 'href', /welcome-to-magpie/ );
	} );

	test( 'accordion keeps one top-level section open', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const menu = page.locator( '.waypost-menu' ).first();
		await menu.locator( '.waypost-menu__item--top', { hasText: 'Sharing' } ).locator( '> .waypost-menu__row .waypost-menu__toggle' ).click();
		const install = menu.locator( '.waypost-menu__item--top', { hasText: 'Installation' } ).locator( '> .waypost-menu__list' );
		await expect( install ).toBeHidden();
	} );

	test( 'collapses behind a button on phones', async ( { browser } ) => {
		const ctx = await browser.newContext( { viewport: { width: 390, height: 844 } } );
		const page = await ctx.newPage();
		await page.goto( '/watch-folders/' );
		const menu = page.locator( '.waypost-menu' ).first();
		await expect( menu.locator( '.waypost-menu__list--root' ) ).toBeHidden();
		await menu.locator( '.waypost-menu__mobile' ).click();
		await expect( menu.locator( '.waypost-menu__list--root' ) ).toBeVisible();
		await ctx.close();
	} );
} );

test.describe( 'Page features', () => {
	test( 'breadcrumbs follow the section trail', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const crumbs = ( await page.locator( '.waypost-crumbs li' ).allTextContents() ).map( ( t ) => t.trim() );
		expect( crumbs ).toEqual( [ 'Documentation', 'Installation', 'Linux', 'Installing on Ubuntu' ] );
	} );

	test( 'contents list matches the headings and their anchors', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const hrefs = await page.locator( '.waypost-toc a' ).evaluateAll( ( as ) => as.map( ( a ) => a.getAttribute( 'href' ).slice( 1 ) ) );
		expect( hrefs.length ).toBeGreaterThanOrEqual( 3 );
		for ( const id of hrefs ) {
			await expect( page.locator( `[id="${ id }"]` ) ).toHaveCount( 1 );
		}
	} );

	test( 'no contents box on a page without headings', async ( { page } ) => {
		await page.goto( '/release-notes/' );
		await expect( page.locator( '.waypost-toc' ) ).toHaveCount( 0 );
	} );

	test( 'tricky headings get unique, predictable anchors', async ( { page } ) => {
		await page.goto( '/edge-case-tricky-headings/' );
		for ( const id of [ 'overview', 'overview-2', 'custom-anchor', 'using-config-flags' ] ) {
			await expect( page.locator( `[id="${ id }"]` ) ).toHaveCount( 1 );
		}
	} );

	test( 'long pages list every heading', async ( { page } ) => {
		await page.goto( '/glossary/' );
		await expect( page.locator( '.waypost-toc a' ) ).toHaveCount( 26 * 3 );
	} );

	test( 'previous and next links follow menu order', async ( { page } ) => {
		await page.goto( '/installing-on-debian/' );
		await expect( page.locator( '.waypost-pager__link--prev' ) ).toContainText( 'Installing on CentOS Stream' ); // Pages sort A to Z inside a section.
		await expect( page.locator( '.waypost-pager__link--next' ) ).toContainText( 'Installing on Rocky Linux' );
	} );

	test( 'non-doc pages get nothing added', async ( { page } ) => {
		await page.goto( '/about-us/' );
		await expect( page.locator( '.waypost-toc, .waypost-pager, .waypost-crumbs' ) ).toHaveCount( 0 );
	} );

	test( 'code blocks get a copy button', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const pres = await page.locator( '.entry-content pre' ).count();
		await expect( page.locator( '.waypost-copy' ) ).toHaveCount( pres );
	} );
} );

test.describe( 'Search', () => {
	test( 'instant results only include docs', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const input = page.locator( '.waypost-search__input' ).first();
		await input.fill( 'docker' );
		const panel = page.locator( '.waypost-search__panel' ).first();
		await expect( panel.locator( '.waypost-hit__title', { hasText: 'Installing with Docker' } ) ).toBeVisible();
		await expect( panel ).not.toContainText( 'About us' );
		await expect( panel ).not.toContainText( 'Blog:' );
	} );

	test( 'arrow keys move through results', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const input = page.locator( '.waypost-search__input' ).first();
		await input.fill( 'album' );
		await expect( page.locator( '.waypost-search__panel .waypost-hit' ).first() ).toBeVisible();
		await input.press( 'ArrowDown' );
		await expect( page.locator( '.waypost-search__panel .waypost-hit.is-active' ) ).toHaveCount( 1 );
	} );

	test( 'markup typed into search is shown as text', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		await page.locator( '.waypost-search__input' ).first().fill( '<img src=x onerror=alert(1)>' );
		await page.waitForTimeout( 800 );
		await expect( page.locator( '.waypost-search__panel img' ) ).toHaveCount( 0 );
	} );

	test( 'Ctrl+K opens the search window and Esc closes it', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		await page.locator( 'body' ).click( { position: { x: 5, y: 5 } } );
		await page.keyboard.press( 'Control+k' );
		const modal = page.locator( '.waypost-modal' );
		await expect( modal ).toBeVisible();
		await page.keyboard.type( 'lightroom' );
		await expect( modal.locator( '.waypost-hit__title', { hasText: 'Lightroom integration' } ) ).toBeVisible();
		await page.keyboard.press( 'Escape' );
		await expect( modal ).toBeHidden();
	} );

	test( 'matching headings link straight to the section', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		await page.locator( '.waypost-search__input' ).first().fill( 'heading with its own' );
		await expect( page.locator( '.waypost-hit--heading' ).first() ).toHaveAttribute( 'href', /#custom-anchor$/ );
	} );

	test( 'Vietnamese text is searchable', async ( { page } ) => {
		await page.goto( '/search-results/?dq=' + encodeURIComponent( 'cài đặt' ) );
		await expect( page.locator( '.waypost-result__title' ).first() ).toContainText( 'Cài đặt' );
	} );

	test( 'results page lists docs and paginates', async ( { page } ) => {
		await page.goto( '/search-results/?dq=album' );
		await expect( page.locator( '.waypost-results__count' ) ).toContainText( 'results' );
		await expect( page.locator( '.waypost-result' ) ).toHaveCount( 10 );
		await expect( page.locator( '.waypost-results__pages' ) ).toBeVisible();
		await expect( page.locator( '.waypost-results' ) ).not.toContainText( 'About us' );
	} );

	test( 'results page explains when nothing matched', async ( { page } ) => {
		await page.goto( '/search-results/?dq=zzqxnothing' );
		await expect( page.locator( '.waypost-results__none' ) ).toBeVisible();
	} );
} );
