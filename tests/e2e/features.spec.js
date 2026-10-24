// Docs bar, Auto light and dark, page extras and "Was this helpful?". Starts from the seeded settings.
const { test, expect } = require( '@playwright/test' );

const SEEDED = { chrome: 'theme', style: 'theme', show_updated: true, show_reading: false, heading_links: true, feedback: true };

async function settings( page, values ) {
	await page.goto( '/wp-admin/admin.php?page=waypost-settings' );
	for ( const [ name, value ] of Object.entries( values ) ) {
		const box = page.locator( `input[type=checkbox][name=${ name }]` );
		if ( 'boolean' === typeof value ) {
			await box.setChecked( value );
		} else {
			await page.locator( `input[name=${ name }][value=${ value }]` ).check( { force: true } );
		}
	}
	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
}

test.describe( 'Phase 3 features', () => {
	test.afterEach( async ( { page } ) => {
		await settings( page, SEEDED );
	} );

	test( 'the docs bar replaces the theme header and footer', async ( { page } ) => {
		await settings( page, { chrome: 'waypost' } );
		await page.goto( '/installing-on-ubuntu/' );
		await expect( page.locator( 'body' ) ).toHaveClass( /waypost-standalone/ );
		await expect( page.locator( '#masthead' ) ).toHaveCount( 0 ); // Twenty Sixteen's header.
		await expect( page.locator( '.waypost-topbar' ) ).toBeVisible();
		await expect( page.locator( '.waypost-topbar__label' ) ).toHaveText( 'Docs' );
		await expect( page.locator( '.waypost-topbar form[data-waypost-search]' ) ).toBeVisible();
		await expect( page.locator( '.waypost-layout__sidebar form[data-waypost-search]' ) ).toHaveCount( 0 ); // Search lives in the bar.
		await expect( page.locator( '.waypost-topbar__back' ) ).toHaveAttribute( 'href', /localhost:\d+\/$/ );
		await expect( page.locator( '.waypost-docs-footer' ) ).toBeVisible();
		await expect( page.locator( 'meta[name=viewport]' ) ).toHaveCount( 1 );

		await page.mouse.wheel( 0, 2000 );
		await expect.poll( async () => ( await page.locator( '.waypost-topbar' ).boundingBox() ).y ).toBeLessThan( 40 ); // Stays at the top.

		await page.goto( '/documentation/' );
		await expect( page.locator( '.waypost-topbar form[data-waypost-search]' ) ).toHaveCount( 0 ); // The home has the big box.
		await expect( page.locator( '.waypost-home' ) ).toBeVisible();
	} );

	test( 'Auto light and dark follows the device and the reader’s switch', async ( { page } ) => {
		await settings( page, { style: 'auto' } );
		const bg = () => page.locator( '.waypost-layout' ).evaluate( ( el ) => getComputedStyle( el ).backgroundColor );

		await page.emulateMedia( { colorScheme: 'light' } );
		await page.goto( '/installing-on-ubuntu/' );
		await page.evaluate( () => localStorage.removeItem( 'waypost-scheme' ) );
		await page.reload();
		expect( await bg() ).toBe( 'rgb(248, 250, 252)' );

		await page.emulateMedia( { colorScheme: 'dark' } );
		expect( await bg() ).toBe( 'rgb(13, 17, 23)' );

		const toggle = page.locator( '[data-waypost-scheme]' ).first();
		await expect( toggle ).toHaveAttribute( 'aria-pressed', 'true' );
		await toggle.click();
		expect( await bg() ).toBe( 'rgb(248, 250, 252)' ); // The reader chose light on a dark device.
		await page.reload();
		expect( await bg() ).toBe( 'rgb(248, 250, 252)' ); // Remembered.
		await page.evaluate( () => localStorage.removeItem( 'waypost-scheme' ) );
	} );

	test( 'page extras: updated date, reading time and heading links', async ( { page, context } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		await expect( page.locator( '.waypost-meta__updated time' ) ).toBeVisible();
		await expect( page.locator( '.waypost-meta__reading' ) ).toHaveCount( 0 );

		await settings( page, { show_reading: true } );
		await page.goto( '/installing-on-ubuntu/' );
		await expect( page.locator( '.waypost-meta__reading' ) ).toContainText( 'min read' );

		const heading = page.locator( '.waypost-layout__content h2[id]' ).first();
		const id = await heading.getAttribute( 'id' );
		const anchor = heading.locator( '.waypost-anchor' );
		await expect( anchor ).toHaveAttribute( 'href', '#' + id );
		await context.grantPermissions( [ 'clipboard-read', 'clipboard-write' ] );
		await heading.hover();
		await anchor.click();
		await expect( anchor ).toHaveAttribute( 'data-copied', 'Link copied' );
		expect( await page.evaluate( () => navigator.clipboard.readText() ) ).toMatch( new RegExp( '#' + id + '$' ) );

		await settings( page, { heading_links: false, show_updated: false, show_reading: false } );
		await page.goto( '/installing-on-ubuntu/' );
		await expect( page.locator( '.waypost-anchor' ) ).toHaveCount( 0 );
		await expect( page.locator( '.waypost-meta' ) ).toHaveCount( 0 );
	} );

	test( 'Was this page helpful? counts once and shows in Search insights', async ( { page, request } ) => {
		await page.goto( '/installing-on-debian/' );
		const box = page.locator( '[data-waypost-feedback]' );
		await expect( box ).toBeVisible();
		await box.locator( 'button[data-vote=no]' ).click();
		await expect( box.locator( '.waypost-feedback__thanks' ) ).toBeVisible();
		await page.reload();
		await expect( box.locator( '.waypost-feedback__thanks' ) ).toBeVisible(); // This reader already answered.

		const post = Number( await box.getAttribute( 'data-waypost-feedback' ) );
		const again = await request.post( '/wp-json/waypost/v1/feedback', { data: { post, vote: 'yes' } } );
		expect( again.status() ).toBe( 429 );

		await page.goto( '/wp-admin/admin.php?page=waypost-insights' );
		const row = page.locator( '#page-feedback tr', { hasText: 'Installing on Debian' } );
		await expect( row.locator( 'td' ).nth( 2 ) ).toHaveText( '1' );
		await expect( row.locator( 'td' ).nth( 3 ) ).toHaveText( '0%' );

		await page.locator( '#page-feedback input[name=confirm]' ).check();
		await page.locator( '#page-feedback button:has-text("Reset feedback")' ).click();
		await expect( page.locator( '#page-feedback .wpst-empty' ) ).toBeVisible();
	} );

	test( 'feedback can be switched off', async ( { page } ) => {
		await settings( page, { feedback: false } );
		await page.goto( '/installing-on-ubuntu/' );
		await expect( page.locator( '[data-waypost-feedback]' ) ).toHaveCount( 0 );
	} );
} );
