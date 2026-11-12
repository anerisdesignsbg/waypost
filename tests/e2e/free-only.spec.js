// The free plugin on its own: Waypost Pro is switched off for these tests and on again afterwards.
const { test, expect } = require( '@playwright/test' );

async function setPro( page, on ) {
	await page.goto( '/wp-admin/plugins.php' );
	const row = page.locator( 'tr[data-slug="waypost-pro"]' );
	const link = row.locator( on ? '.activate a' : '.deactivate a' );
	if ( await link.count() ) {
		await link.click();
		await expect( page.locator( 'tr[data-slug="waypost-pro"]' ).locator( on ? '.deactivate a' : '.activate a' ) ).toHaveCount( 1 );
	}
}

test.describe( 'Free plugin without Pro', () => {
	test.beforeAll( async ( { browser } ) => {
		const page = await browser.newPage( { storageState: 'tests/.auth/admin.json' } );
		await setPro( page, false );
		await page.close();
	} );
	test.afterAll( async ( { browser } ) => {
		const page = await browser.newPage( { storageState: 'tests/.auth/admin.json' } );
		await setPro( page, true );
		await page.close();
	} );

	test( 'Settings, Assign and Help show no Pro features', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=waypost-settings' );
		await expect( page.locator( '.wpst-sections-nav a[href="#search"]' ) ).toBeVisible();
		await expect( page.locator( '.wpst-sections-nav a[href="#documents"]' ) ).toHaveCount( 0 );
		await expect( page.locator( '#wpst-synonyms' ) ).toHaveCount( 0 );
		await expect( page.locator( 'input[name=topic_filters]' ) ).toHaveCount( 0 );
		await expect( page.locator( 'input[name=best_answer]' ) ).toHaveCount( 1 ); // Free.

		await page.goto( '/wp-admin/admin.php?page=waypost-structure&tab=assign' );
		await expect( page.locator( '.wpst-filter a', { hasText: 'Documents' } ) ).toHaveCount( 0 );

		await page.goto( '/wp-admin/admin.php?page=waypost-help' );
		await expect( page.locator( '.wpst-topic' ) ).toHaveCount( 13 );
		await expect( page.locator( '#documents' ) ).toHaveCount( 0 );
	} );

	test( 'search and the menu work without documents or topic filters', async ( { browser } ) => {
		const page = await browser.newPage( { storageState: { cookies: [], origins: [] } } );

		await page.goto( '/documentation/?dq=pebblecast' ); // Only inside a PDF.
		await expect( page.locator( '.waypost-results__count' ) ).toContainText( '0 results' );

		await page.goto( '/documentation/?dq=' + encodeURIComponent( 'How do I share an album?' ) );
		await expect( page.locator( '.waypost-answer' ) ).toBeVisible(); // The best answer is free.
		await expect( page.locator( '.waypost-topics' ) ).toHaveCount( 0 );
		await expect( page.locator( '.waypost-result' ).first() ).toBeVisible();

		await page.goto( '/installing-on-ubuntu/' );
		await expect( page.locator( '.waypost-menu' ) ).toBeVisible();
		await expect( page.locator( '.waypost-menu__item--file' ) ).toHaveCount( 0 );
		await page.close();
	} );
} );
