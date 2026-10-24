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

test( 'settings save and persist', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=waypost-settings' );
	await page.fill( '#wpst-ph', 'Search Acme docs…' );
	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
	await expect( page.locator( '#wpst-ph' ) ).toHaveValue( 'Search Acme docs…' );
	await page.fill( '#wpst-ph', '' );
	await page.click( 'button:has-text("Save settings")' );
} );

test( 'search insights lists searches with no results', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=waypost-insights' );
	await expect( page.locator( '.wpst-log' ).first() ).toContainText( 'ipv6' );
} );

test( 'help page has every topic and the contextual help tab', async ( { page } ) => {
	await page.goto( '/wp-admin/admin.php?page=waypost-help' );
	await expect( page.locator( '.wpst-topic' ) ).toHaveCount( 12 );
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
	await expect( page.locator( 'input[name=results_mode][value=existing]' ) ).toBeChecked();
	await page.click( 'button:has-text("Save and continue")' ); // Extras.
	await expect( page.locator( 'h1' ) ).toContainText( 'Your docs are set up' );

	await page.goto( '/search-results/?dq=bgp' );
	await expect( page.locator( '.waypost-result' ).first() ).toBeVisible();
} );
