// Admin screens, as an administrator.
const { test, expect } = require( '@playwright/test' );

const unique = () => 'Test ' + Date.now().toString( 36 );

test( 'dashboard checklist is complete on the seeded site', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=docsway' );
	await expect( page.locator( '.dsw-step.is-done' ) ).toHaveCount( 5 );
	await expect( page.locator( '.dsw-stats' ) ).toContainText( 'Doc pages' );
} );

test( 'menu order screen lists sections and greys out empty ones', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=docsway-structure' );
	await expect( page.locator( '.dsw-node--term', { hasText: 'Installation' } ).first() ).toBeVisible();
	await expect( page.locator( '.dsw-node.is-empty', { hasText: 'Coming soon' } ) ).toHaveCount( 1 );
	await page.click( '#docsway-save-order' );
	await expect( page.locator( '#docsway-order-status' ) ).toHaveText( 'Saved' );
} );

test( 'assign pages: create a section, add a page, then remove it', async ( { page } ) => {
	const name = unique();
	await page.goto( '/wp-admin/admin.php?page=docsway-structure&tab=assign' );
	await page.fill( '#docsway-new-name', name );
	await page.click( '#docsway-add-section' );
	await expect( page.locator( '#docsway-section-status' ) ).toHaveText( 'Saved' );

	await page.fill( '#docsway-assign-filter', 'pricing' );
	const row = page.locator( '#docsway-assign tbody tr:visible' ).first();
	await row.locator( 'input[type=checkbox]' ).check();
	await page.selectOption( '#docsway-assign-term', { label: name } );
	await page.click( '[data-assign=add]' );
	await expect( row.locator( '.dsw-assign__terms' ) ).toHaveText( name );

	await row.locator( 'input[type=checkbox]' ).check();
	await page.click( '[data-assign=remove]' );
	await expect( row.locator( '.dsw-assign__terms' ) ).toHaveText( '–' );
} );

test( 'section links can be saved', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=docsway-structure&tab=links' );
	const row = page.locator( 'tr', { hasText: 'Introduction' } ).first();
	await row.locator( '.dsw-link-save' ).click();
	await expect( row.locator( '.dsw-status' ) ).toHaveText( 'Saved' );
} );

test( 'nothing in the wizard or settings spills out of its card', async ( { page } ) => {
	// Elements wider than their card, e.g. a page dropdown sized to a long page title.
	const spills = ( cards ) => page.evaluate( ( selector ) => {
		const out = [];
		document.querySelectorAll( selector ).forEach( ( card ) => {
			if ( ! card.offsetParent ) {
				return;
			}
			const c = card.getBoundingClientRect();
			card.querySelectorAll( '*' ).forEach( ( el ) => {
				const r = el.getBoundingClientRect();
				if ( el.offsetParent && r.width && ( r.right > c.right + 1 || r.left < c.left - 1 ) ) {
					out.push( el.tagName.toLowerCase() + '.' + String( el.className ).split( ' ' )[ 0 ] );
				}
			} );
		} );
		return [ ...new Set( out ) ];
	}, cards );

	for ( const width of [ 1300, 700 ] ) {
		await page.setViewportSize( { width, height: 900 } );
		for ( const step of [ 'welcome', 'content', 'sections', 'assign', 'look', 'features', 'done' ] ) {
			await page.goto( '/wp-admin/admin.php?page=docsway-setup&step=' + step );
			expect( await spills( '.dsw-wizard__card' ), `wizard ${ step } at ${ width }px` ).toEqual( [] );
		}
		for ( const section of [ 'content', 'navigation', 'search', 'search-home', 'appearance', 'advanced' ] ) {
			await page.goto( '/wp-admin/admin.php?page=docsway-settings#' + section );
			expect( await spills( '.dsw-help__body > section.dsw-card' ), `settings ${ section } at ${ width }px` ).toEqual( [] );
		}
	}
} );

test( 'settings show one section at a time and return to it after saving', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=docsway-settings' );
	const visible = () => page.locator( '.dsw-settings .dsw-help__body > section.dsw-card:visible' );
	await expect( visible() ).toHaveCount( 1 );
	await expect( visible() ).toHaveAttribute( 'id', 'content' );

	await page.locator( '.dsw-sections-nav a[href="#search-home"]' ).click();
	await expect( visible() ).toHaveCount( 1 );
	await expect( visible() ).toHaveAttribute( 'id', 'search-home' );
	await expect( page.locator( '.dsw-sections-nav a.is-active' ) ).toContainText( 'Search home' );
	await expect( page ).toHaveURL( /#search-home$/ );

	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
	await expect( page ).toHaveURL( /#search-home$/ ); // WordPress removes saved=1 from the address.
	await expect( visible() ).toHaveAttribute( 'id', 'search-home' );

	await page.goto( '/wp-admin/admin.php?page=docsway-settings#appearance' ); // A link straight to a section.
	await expect( visible() ).toHaveAttribute( 'id', 'appearance' );
} );

test( 'settings save and persist', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=docsway-settings#search' );
	await page.fill( '#dsw-ph', 'Search Magpie docs…' );
	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
	await expect( page.locator( '#dsw-ph' ) ).toHaveValue( 'Search Magpie docs…' );
	await page.fill( '#dsw-ph', '' );
	await page.click( 'button:has-text("Save settings")' );
} );

test( 'style picker previews, saves and styles the front end', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=docsway-settings#appearance' );
	await expect( page.locator( '.dsw-styles .dsw-style' ) ).toHaveCount( 7 );

	await page.locator( 'label.dsw-style:has(input[name=style][value=dark])' ).click();
	await expect( page.locator( '.dsw-look' ) ).toHaveClass( /docsway-style-dark/ );
	await expect( page.locator( '#dsw-accent' ) ).toHaveValue( '#60a5fa' );
	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
	await expect( page.locator( 'input[name=style][value=dark]' ) ).toBeChecked();

	await page.goto( '/installing-on-ubuntu/' );
	await expect( page.locator( 'body' ) ).toHaveClass( /docsway-style-dark/ );
	await expect( page.locator( '.docsway-menu' ).first() ).toHaveCSS( 'background-color', 'rgb(20, 25, 34)' );
	const onAccent = await page.evaluate( () => getComputedStyle( document.documentElement ).getPropertyValue( '--docsway-on-accent' ).trim() );
	expect( onAccent ).toBe( '#111111' ); // Dark text on the light accent.

	await page.goto( '/wp-admin/admin.php?page=docsway-settings#appearance' );
	await page.locator( 'label.dsw-style:has(input[name=style][value=theme])' ).click();
	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( 'input[name=style][value=theme]' ) ).toBeChecked();
	await expect( page.locator( '#dsw-accent' ) ).toHaveValue( '#2563eb' );
} );

test( 'search home settings save and show on the docs home', async ( { page } ) => {
	const title = unique();
	await page.goto( '/wp-admin/admin.php?page=docsway-settings#search-home' );
	const original = await page.inputValue( '#dsw-home-title' );
	await page.fill( '#dsw-home-title', title );
	await page.fill( 'input[name="quick_searches[5][label]"]', 'Grafana dashboards' );
	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
	await expect( page.locator( 'input[name="quick_searches[5][label]"]' ) ).toHaveValue( 'Grafana dashboards' );

	await page.goto( '/documentation/' );
	await expect( page.locator( '.docsway-home__title' ) ).toHaveText( title );
	await expect( page.locator( '.docsway-home .docsway-chip' ) ).toHaveCount( 6 );

	await page.goto( '/wp-admin/admin.php?page=docsway-settings#search-home' );
	await page.fill( '#dsw-home-title', original );
	await page.fill( 'input[name="quick_searches[5][label]"]', '' );
	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
} );

test( 'the wizard can create the docs home page', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=docsway-settings' );
	const original = await page.inputValue( '#dsw-home' );

	await page.goto( '/wp-admin/admin.php?page=docsway-setup&step=features' );
	await page.locator( 'input[name=home_mode][value=create]' ).check();
	await page.click( 'button:has-text("Save and continue")' );
	await expect( page.locator( 'h1' ) ).toContainText( 'Your docs are set up' );

	await page.goto( '/wp-admin/admin.php?page=docsway-settings' );
	const created = await page.inputValue( '#dsw-home' );
	expect( created ).not.toBe( original );
	await expect( page.locator( '#dsw-home option:checked' ) ).toHaveText( 'Documentation' );

	await page.goto( '/?page_id=' + created );
	await expect( page.locator( '.docsway-layout--home .docsway-home' ) ).toBeVisible();
	await expect( page.locator( '.docsway-layout__title' ) ).toHaveCount( 0 );

	await page.goto( '/wp-admin/admin.php?page=docsway-settings#navigation' ); // Put the seeded docs home back.
	await page.selectOption( '#dsw-home', original );
	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( '#dsw-home' ) ).toHaveValue( original );
} );

test( 'search insights lists searches with no results', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=docsway-insights' );
	await expect( page.locator( '.dsw-log' ).first() ).toContainText( 'heic' );
} );

test( 'help page has every topic and the contextual help tab', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=docsway-help' );
	await expect( page.locator( '.dsw-topic' ) ).toHaveCount( 15 );
	await page.goto( '/wp-admin/admin.php?page=docsway-structure' );
	await page.click( '#contextual-help-link' );
	await expect( page.locator( '#tab-link-docsway-order' ) ).toBeVisible();
} );

test( 'setup wizard runs end to end without breaking the site', async ( { page } ) => {
	const name = unique();
	await page.goto( '/wp-admin/admin.php?page=docsway-setup' );
	await expect( page.locator( 'h1' ) ).toContainText( 'set up your documentation' );
	await page.click( 'text=Start' );

	await page.click( 'button:has-text("Save and continue")' ); // Content: keep current choice.

	await page.fill( '#dsw-outline', name + '\n  ' + name + ' child' );
	await expect( page.locator( '#dsw-outline-preview li' ) ).toHaveCount( 2 );
	await page.click( 'button:has-text("Save and continue")' );
	await expect( page.locator( '.notice-success' ) ).toContainText( '2 sections created' );

	await page.click( 'text=Skip this step' ); // Pages.
	await expect( page.locator( 'h1' ) ).toContainText( 'Choose how your docs look' );
	await expect( page.locator( 'input[name=layout][value=docsway]' ) ).toBeChecked();
	await page.click( 'button:has-text("Save and continue")' ); // Look.
	await expect( page.locator( 'input[name=results_mode][value=home]' ) ).toBeChecked();
	await page.click( 'button:has-text("Save and continue")' ); // Extras.
	await expect( page.locator( 'h1' ) ).toContainText( 'Your docs are set up' );

	await page.goto( '/search-results/?dq=album' );
	await expect( page.locator( '.docsway-result' ).first() ).toBeVisible();
} );
