// Admin screens, as an administrator.
const { test, expect } = require( '@playwright/test' );

const unique = () => 'Test ' + Date.now().toString( 36 );

test( 'dashboard checklist is complete on the seeded site', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=waypost' );
	await expect( page.locator( '.wpst-step.is-done' ) ).toHaveCount( 5 );
	await expect( page.locator( '.wpst-stats' ) ).toContainText( 'Doc pages' );
} );

test( 'menu order screen lists sections and greys out empty ones', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=waypost-structure' );
	await expect( page.locator( '.wpst-node--term', { hasText: 'Installation' } ).first() ).toBeVisible();
	await expect( page.locator( '.wpst-node.is-empty', { hasText: 'Coming soon' } ) ).toHaveCount( 1 );
	await page.click( '#waypost-save-order' );
	await expect( page.locator( '#waypost-order-status' ) ).toHaveText( 'Saved' );
} );

test( 'assign pages: create a section, add a page, then remove it', async ( { page } ) => {
	const name = unique();
	await page.goto( '/wp-admin/admin.php?page=waypost-structure&tab=assign' );
	await page.fill( '#waypost-new-name', name );
	await page.click( '#waypost-add-section' );
	await expect( page.locator( '#waypost-section-status' ) ).toHaveText( 'Saved' );

	await page.fill( '#waypost-assign-filter', 'pricing' );
	const row = page.locator( '#waypost-assign tbody tr:visible' ).first();
	await row.locator( 'input[type=checkbox]' ).check();
	await page.selectOption( '#waypost-assign-term', { label: name } );
	await page.click( '[data-assign=add]' );
	await expect( row.locator( '.wpst-assign__terms' ) ).toHaveText( name );

	await row.locator( 'input[type=checkbox]' ).check();
	await page.click( '[data-assign=remove]' );
	await expect( row.locator( '.wpst-assign__terms' ) ).toHaveText( '–' );
} );

test( 'section links can be saved', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=waypost-structure&tab=links' );
	const row = page.locator( 'tr', { hasText: 'Introduction' } ).first();
	await row.locator( '.wpst-link-save' ).click();
	await expect( row.locator( '.wpst-status' ) ).toHaveText( 'Saved' );
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
			await page.goto( '/wp-admin/admin.php?page=waypost-setup&step=' + step );
			expect( await spills( '.wpst-wizard__card' ), `wizard ${ step } at ${ width }px` ).toEqual( [] );
		}
		for ( const section of [ 'content', 'navigation', 'search', 'search-home', 'appearance', 'advanced' ] ) {
			await page.goto( '/wp-admin/admin.php?page=waypost-settings#' + section );
			expect( await spills( '.wpst-help__body > section.wpst-card' ), `settings ${ section } at ${ width }px` ).toEqual( [] );
		}
	}
} );

test( 'settings show one section at a time and return to it after saving', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=waypost-settings' );
	const visible = () => page.locator( '.wpst-settings .wpst-help__body > section.wpst-card:visible' );
	await expect( visible() ).toHaveCount( 1 );
	await expect( visible() ).toHaveAttribute( 'id', 'content' );

	await page.locator( '.wpst-sections-nav a[href="#search-home"]' ).click();
	await expect( visible() ).toHaveCount( 1 );
	await expect( visible() ).toHaveAttribute( 'id', 'search-home' );
	await expect( page.locator( '.wpst-sections-nav a.is-active' ) ).toContainText( 'Search home' );
	await expect( page ).toHaveURL( /#search-home$/ );

	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
	await expect( page ).toHaveURL( /#search-home$/ ); // WordPress removes saved=1 from the address.
	await expect( visible() ).toHaveAttribute( 'id', 'search-home' );

	await page.goto( '/wp-admin/admin.php?page=waypost-settings#appearance' ); // A link straight to a section.
	await expect( visible() ).toHaveAttribute( 'id', 'appearance' );
} );

test( 'settings save and persist', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=waypost-settings#search' );
	await page.fill( '#wpst-ph', 'Search Magpie docs…' );
	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
	await expect( page.locator( '#wpst-ph' ) ).toHaveValue( 'Search Magpie docs…' );
	await page.fill( '#wpst-ph', '' );
	await page.click( 'button:has-text("Save settings")' );
} );

test( 'style picker previews, saves and styles the front end', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=waypost-settings#appearance' );
	await expect( page.locator( '.wpst-styles .wpst-style' ) ).toHaveCount( 7 );

	await page.locator( 'label.wpst-style:has(input[name=style][value=dark])' ).click();
	await expect( page.locator( '.wpst-look' ) ).toHaveClass( /waypost-style-dark/ );
	await expect( page.locator( '#wpst-accent' ) ).toHaveValue( '#60a5fa' );
	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
	await expect( page.locator( 'input[name=style][value=dark]' ) ).toBeChecked();

	await page.goto( '/installing-on-ubuntu/' );
	await expect( page.locator( 'body' ) ).toHaveClass( /waypost-style-dark/ );
	await expect( page.locator( '.waypost-menu' ).first() ).toHaveCSS( 'background-color', 'rgb(20, 25, 34)' );
	const onAccent = await page.evaluate( () => getComputedStyle( document.documentElement ).getPropertyValue( '--waypost-on-accent' ).trim() );
	expect( onAccent ).toBe( '#111111' ); // Dark text on the light accent.

	await page.goto( '/wp-admin/admin.php?page=waypost-settings#appearance' );
	await page.locator( 'label.wpst-style:has(input[name=style][value=theme])' ).click();
	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( 'input[name=style][value=theme]' ) ).toBeChecked();
	await expect( page.locator( '#wpst-accent' ) ).toHaveValue( '#2563eb' );
} );

test( 'search home settings save and show on the docs home', async ( { page } ) => {
	const title = unique();
	await page.goto( '/wp-admin/admin.php?page=waypost-settings#search-home' );
	const original = await page.inputValue( '#wpst-home-title' );
	await page.fill( '#wpst-home-title', title );
	await page.fill( 'input[name="quick_searches[5][label]"]', 'Grafana dashboards' );
	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
	await expect( page.locator( 'input[name="quick_searches[5][label]"]' ) ).toHaveValue( 'Grafana dashboards' );

	await page.goto( '/documentation/' );
	await expect( page.locator( '.waypost-home__title' ) ).toHaveText( title );
	await expect( page.locator( '.waypost-home .waypost-chip' ) ).toHaveCount( 6 );

	await page.goto( '/wp-admin/admin.php?page=waypost-settings#search-home' );
	await page.fill( '#wpst-home-title', original );
	await page.fill( 'input[name="quick_searches[5][label]"]', '' );
	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
} );

test( 'the wizard can create the docs home page', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=waypost-settings' );
	const original = await page.inputValue( '#wpst-home' );

	await page.goto( '/wp-admin/admin.php?page=waypost-setup&step=features' );
	await page.locator( 'input[name=home_mode][value=create]' ).check();
	await page.click( 'button:has-text("Save and continue")' );
	await expect( page.locator( 'h1' ) ).toContainText( 'Your docs are set up' );

	await page.goto( '/wp-admin/admin.php?page=waypost-settings' );
	const created = await page.inputValue( '#wpst-home' );
	expect( created ).not.toBe( original );
	await expect( page.locator( '#wpst-home option:checked' ) ).toHaveText( 'Documentation' );

	await page.goto( '/?page_id=' + created );
	await expect( page.locator( '.waypost-layout--home .waypost-home' ) ).toBeVisible();
	await expect( page.locator( '.waypost-layout__title' ) ).toHaveCount( 0 );

	await page.goto( '/wp-admin/admin.php?page=waypost-settings#navigation' ); // Put the seeded docs home back.
	await page.selectOption( '#wpst-home', original );
	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( '#wpst-home' ) ).toHaveValue( original );
} );

test( 'search insights lists searches with no results', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=waypost-insights' );
	await expect( page.locator( '.wpst-log' ).first() ).toContainText( 'heic' );
} );

test( 'help page has every topic and the contextual help tab', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=waypost-help' );
	await expect( page.locator( '.wpst-topic' ) ).toHaveCount( 14 );
	await page.goto( '/wp-admin/admin.php?page=waypost-structure' );
	await page.click( '#contextual-help-link' );
	await expect( page.locator( '#tab-link-waypost-order' ) ).toBeVisible();
} );

test( 'setup wizard runs end to end without breaking the site', async ( { page } ) => {
	const name = unique();
	await page.goto( '/wp-admin/admin.php?page=waypost-setup' );
	await expect( page.locator( 'h1' ) ).toContainText( 'set up your documentation' );
	await page.click( 'text=Start' );

	await page.click( 'button:has-text("Save and continue")' ); // Content: keep current choice.

	await page.fill( '#wpst-outline', name + '\n  ' + name + ' child' );
	await expect( page.locator( '#wpst-outline-preview li' ) ).toHaveCount( 2 );
	await page.click( 'button:has-text("Save and continue")' );
	await expect( page.locator( '.notice-success' ) ).toContainText( '2 sections created' );

	await page.click( 'text=Skip this step' ); // Pages.
	await expect( page.locator( 'h1' ) ).toContainText( 'Choose how your docs look' );
	await expect( page.locator( 'input[name=layout][value=waypost]' ) ).toBeChecked();
	await page.click( 'button:has-text("Save and continue")' ); // Look.
	await expect( page.locator( 'input[name=results_mode][value=home]' ) ).toBeChecked();
	await page.click( 'button:has-text("Save and continue")' ); // Extras.
	await expect( page.locator( 'h1' ) ).toContainText( 'Your docs are set up' );

	await page.goto( '/search-results/?dq=album' );
	await expect( page.locator( '.waypost-result' ).first() ).toBeVisible();
} );
