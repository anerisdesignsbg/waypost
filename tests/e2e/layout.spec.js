// The Waypost docs layout. The seed turns it on: sidebar left, page width 1280px, contents in a column.
const { test, expect } = require( '@playwright/test' );

const DEFAULTS = { layout: 'waypost', sidebar: 'left', width: 'contained', toc_position: 'side', max_width: 1280, style: 'theme' };

async function settings( page, values ) {
	await page.goto( '/wp-admin/admin.php?page=waypost-settings' );
	for ( const [ name, value ] of Object.entries( values ) ) {
		if ( 'max_width' === name ) {
			await page.fill( 'input[name=max_width]', String( value ) );
		} else {
			await page.locator( `input[name=${ name }][value=${ value }]` ).check( { force: true } );
		}
	}
	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
}

const box = async ( locator ) => locator.boundingBox();

test.describe( 'Docs layout', () => {
	test( 'doc pages use the docs layout', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const layout = page.locator( '.waypost-layout' );
		await expect( layout ).toHaveCount( 1 );
		await expect( layout ).toHaveClass( /waypost-layout--sidebar-left/ );
		await expect( page.locator( 'body' ) ).toHaveClass( /waypost-layout-page/ );
		await expect( page.locator( '.waypost-layout__title' ) ).toHaveText( 'Installing on Ubuntu' );
		await expect( page.locator( '.waypost-layout__main .waypost-crumbs' ) ).toBeVisible();
		await expect( page.locator( '.waypost-layout__sidebar form[data-waypost-search]' ) ).toBeVisible();
		await expect( page.locator( '.waypost-layout__sidebar .waypost-menu' ) ).toBeVisible();
		await expect( page.locator( '.waypost-layout__toc .waypost-toc' ) ).toBeVisible();
		await expect( page.locator( '.waypost-layout__main .waypost-pager' ) ).toBeVisible();
		await expect( page.locator( '#secondary' ) ).toHaveCount( 0 ); // The theme's own sidebar is not used.
		await expect( page.locator( '.waypost-layout__inner' ) ).toHaveCSS( 'max-width', '1280px' );

		const side = await box( page.locator( '.waypost-layout__sidebar' ) );
		const main = await box( page.locator( '.waypost-layout__main' ) );
		const toc = await box( page.locator( '.waypost-layout__toc' ) );
		expect( side.x ).toBeLessThan( main.x );
		expect( toc.x ).toBeGreaterThan( main.x );

		const scroll = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
		expect( scroll ).toBe( 0 ); // Full-width background without a sideways scrollbar.
	} );

	test( 'the docs home and results page use it too', async ( { page } ) => {
		await page.goto( '/documentation/' );
		await expect( page.locator( '.waypost-layout--home' ) ).toHaveCount( 1 );
		await expect( page.locator( '.waypost-layout__title' ) ).toHaveCount( 0 );
		await expect( page.locator( '.waypost-layout__main .waypost-home' ) ).toBeVisible();
		await expect( page.locator( '.waypost-layout__sidebar form[data-waypost-search]' ) ).toHaveCount( 0 ); // The home has the big box.
		await expect( page.locator( '.waypost-layout__sidebar .waypost-menu' ) ).toBeVisible();

		await page.goto( '/search-results/?dq=album' );
		await expect( page.locator( '.waypost-layout--results .waypost-layout__title' ) ).toHaveText( 'Search results' );
		await expect( page.locator( '.waypost-result' ) ).toHaveCount( 10 );
	} );

	test( 'a search result opens in the layout', async ( { page } ) => {
		await page.goto( '/documentation/?dq=album' );
		await page.locator( '.waypost-home__list .waypost-result__title a' ).first().click();
		await expect( page.locator( '.waypost-layout--doc' ) ).toHaveCount( 1 );
	} );

	test( 'pages that are not docs keep the theme template', async ( { page } ) => {
		await page.goto( '/about-us/' );
		await expect( page.locator( '.waypost-layout' ) ).toHaveCount( 0 );
	} );

	test( 'medium screens move the contents to the top of the page', async ( { page } ) => {
		await page.setViewportSize( { width: 1024, height: 800 } );
		await page.goto( '/installing-on-ubuntu/' );
		await expect( page.locator( '.waypost-layout__toc' ) ).toBeHidden();
		await expect( page.locator( '.waypost-layout__toc-inline .waypost-toc' ) ).toBeVisible();
	} );

	test( 'phones get one column with the menu folded away', async ( { page } ) => {
		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( '/installing-on-ubuntu/' );
		const side = await box( page.locator( '.waypost-layout__sidebar' ) );
		const main = await box( page.locator( '.waypost-layout__main' ) );
		expect( side.y ).toBeLessThan( main.y );
		await expect( page.locator( '.waypost-layout__sidebar .waypost-menu__list--root' ) ).toBeHidden();
		await expect( page.locator( '.waypost-menu__mobile' ) ).toBeVisible();
		const scroll = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
		expect( scroll ).toBe( 0 );
	} );

	test( 'the settings diagram follows the options', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=waypost-settings' );
		const diagram = page.locator( '.wpst-diagram' );
		await page.locator( 'input[name=sidebar][value=right]' ).check( { force: true } );
		await page.locator( 'input[name=width][value=full]' ).check( { force: true } );
		await page.locator( 'input[name=toc_position][value=content]' ).check( { force: true } );
		await expect( diagram ).toHaveAttribute( 'data-sidebar', 'right' );
		await expect( diagram ).toHaveAttribute( 'data-width', 'full' );
		await expect( diagram ).toHaveAttribute( 'data-toc', 'content' );
		await expect( page.locator( '.wpst-layout__max' ) ).toBeHidden();
		await page.locator( 'input[name=layout][value=theme]' ).check( { force: true } );
		await expect( page.locator( '.wpst-layout' ) ).toHaveClass( /is-theme/ );
	} );

	test.describe( 'options', () => {
		test.afterEach( async ( { page } ) => {
			await settings( page, DEFAULTS );
		} );

		test( 'sidebar on the right, full width', async ( { page } ) => {
			await settings( page, { sidebar: 'right', width: 'full' } );
			await page.goto( '/installing-on-ubuntu/' );
			await expect( page.locator( '.waypost-layout' ) ).toHaveClass( /waypost-layout--sidebar-right/ );
			await expect( page.locator( '.waypost-layout__inner' ) ).toHaveCSS( 'max-width', 'none' );
			const side = await box( page.locator( '.waypost-layout__sidebar' ) );
			const main = await box( page.locator( '.waypost-layout__main' ) );
			const toc = await box( page.locator( '.waypost-layout__toc' ) );
			expect( side.x ).toBeGreaterThan( main.x );
			expect( toc.x ).toBeLessThan( main.x ); // Contents sit opposite the menu.
		} );

		test( 'contents at the top of the page', async ( { page } ) => {
			await settings( page, { toc_position: 'content' } );
			await page.goto( '/installing-on-ubuntu/' );
			await expect( page.locator( '.waypost-layout__toc' ) ).toHaveCount( 0 );
			await expect( page.locator( '.waypost-layout__content .waypost-toc' ) ).toHaveCount( 1 );
		} );

		test( 'page width setting', async ( { page } ) => {
			await settings( page, { max_width: 1000 } );
			await page.goto( '/installing-on-ubuntu/' );
			await expect( page.locator( '.waypost-layout__inner' ) ).toHaveCSS( 'max-width', '1000px' );
		} );

		test( 'a style colours the whole docs area', async ( { page } ) => {
			await settings( page, { style: 'dark' } );
			await page.goto( '/installing-on-ubuntu/' );
			await expect( page.locator( '.waypost-layout' ) ).toHaveCSS( 'background-color', 'rgb(13, 17, 23)' );
			await expect( page.locator( '.waypost-layout__content pre' ).first() ).toHaveCSS( 'background-color', 'rgb(22, 27, 34)' );
		} );

		test( 'my theme’s layout leaves pages to the theme', async ( { page } ) => {
			await settings( page, { layout: 'theme' } );
			await page.goto( '/installing-on-ubuntu/' );
			await expect( page.locator( '.waypost-layout' ) ).toHaveCount( 0 );
			await expect( page.locator( '#secondary .waypost-menu' ) ).toBeVisible(); // The theme's sidebar widget.
			await expect( page.locator( '.entry-content .waypost-crumbs' ) ).toHaveCount( 1 ); // Added to the content instead.
			await expect( page.locator( '.entry-content .waypost-pager' ) ).toHaveCount( 1 );
			await expect( page.locator( '.entry-content .waypost-meta' ) ).toHaveCount( 1 );
			await expect( page.locator( '.entry-content [data-waypost-feedback]' ) ).toHaveCount( 1 );
		} );
	} );
} );
