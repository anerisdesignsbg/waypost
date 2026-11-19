// Documents: PDF, Word and text files from the Media Library placed in doc sections (seeded under Downloads).
const { test, expect } = require( '@playwright/test' );

const visitor = { storageState: { cookies: [], origins: [] } };

async function setDocuments( page, on ) {
	await page.goto( '/wp-admin/admin.php?page=docsway-settings' );
	await page.locator( '.dsw-sections-nav a[href="#documents"]' ).click();
	await page.locator( 'input[type=checkbox][name=documents]' ).setChecked( on );
	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
}

test.describe( 'Documents for readers', () => {
	test.use( visitor );

	test( 'instant search finds words inside a PDF', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		await page.locator( '.docsway-search__input' ).first().fill( 'pebblecast' );
		const hit = page.locator( '.docsway-search__panel .docsway-hit', { hasText: 'Magpie quick start card' } ).first();
		await expect( hit ).toBeVisible();
		await expect( hit ).toHaveAttribute( 'href', /magpie-quick-start-card(-\d+)?\.pdf$/ );
		await expect( hit.locator( '.docsway-file' ) ).toHaveText( 'PDF' );
		await expect( hit.locator( '.docsway-hit__excerpt mark' ).first() ).toHaveText( /pebblecast/i );
	} );

	test( 'full results show Word and text documents with their section', async ( { page } ) => {
		await page.goto( '/documentation/?dq=kestrel' );
		const result = page.locator( '.docsway-result', { hasText: 'Hardware compatibility list' } );
		await expect( result ).toBeVisible();
		await expect( result.locator( '.docsway-file' ) ).toHaveText( 'DOCX' );
		await expect( result.locator( '.docsway-result__section' ) ).toHaveText( /Downloads|Installation/ );
		await expect( result.locator( '.docsway-result__title a' ) ).toHaveAttribute( 'href', /\.docx$/ );

		await page.goto( '/documentation/?dq=' + encodeURIComponent( 'Zürich' ) ); // Accented text from Word.
		await expect( page.locator( '.docsway-result', { hasText: 'Hardware compatibility list' } ) ).toBeVisible();

		await page.goto( '/documentation/?dq=' + encodeURIComponent( 'keyboard shortcuts' ) );
		await expect( page.locator( '.docsway-result', { hasText: 'Keyboard shortcuts' } ).locator( '.docsway-file' ) ).toHaveText( 'TXT' );
	} );

	test( 'a scanned PDF is found by its description', async ( { page } ) => {
		await page.goto( '/documentation/?dq=warranty' );
		await expect( page.locator( '.docsway-result', { hasText: 'Scanned warranty card' } ) ).toBeVisible();
	} );

	test( 'the menu lists documents but reading order skips them', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		const files = page.locator( '.docsway-menu__item--file' );
		await expect( files ).toHaveCount( 5 ); // Four in Downloads, the Word file also in Installation.
		const pdf = page.locator( '.docsway-menu__item--file a', { hasText: 'Magpie quick start card' } ).first();
		await expect( pdf ).toHaveAttribute( 'href', /\.pdf$/ );
		await expect( pdf.locator( '.docsway-file' ) ).toHaveText( 'PDF' );

		// The last page of Installation leads on to Configuration, not to the Word file in Installation.
		await page.goto( '/system-requirements/' );
		await expect( page.locator( '.docsway-pager' ) ).not.toContainText( 'Hardware compatibility list' );
	} );
} );

test.describe( 'Documents for site owners', () => {
	test( 'Settings shows reading status and can read everything again', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=docsway-settings' );
		await page.locator( '.dsw-sections-nav a[href="#documents"]' ).click();
		const status = page.locator( '.dsw-doc-status' );
		await expect( status ).toContainText( '3 of 4 documents are searchable.' );
		await expect( status.locator( '.dsw-doc-problems' ) ).toContainText( 'Scanned warranty card' );
		await page.click( 'button:has-text("Read all documents again")' );
		await expect( page.locator( '.notice-success' ) ).toContainText( '4 documents read.' );
		await expect( page.locator( '.dsw-doc-status' ) ).toContainText( '3 of 4 documents are searchable.' );
	} );

	test( 'the Assign screen lists documents and the media screen sets sections', async ( { page } ) => {
		await page.goto( '/wp-admin/admin.php?page=docsway-structure&tab=assign&show=documents' );
		const row = page.locator( '#docsway-assign tbody tr', { hasText: 'Keyboard shortcuts' } );
		await expect( row ).toContainText( 'TXT' );
		await expect( row ).toContainText( 'Searchable' );
		await expect( page.locator( '#docsway-assign tbody tr' ) ).toHaveCount( 4 );
		const id = await row.locator( 'input[type=checkbox]' ).getAttribute( 'value' );

		await page.goto( `/wp-admin/upload.php?item=${ id }` );
		const box = page.getByLabel( 'Configuration', { exact: true } );
		await expect( box ).toBeVisible();
		let saved = page.waitForResponse( ( r ) => r.url().includes( 'admin-ajax.php' ) && ( r.request().postData() || '' ).includes( 'save-attachment-compat' ) );
		await box.check();
		await saved;

		await page.goto( '/main-configuration-file/' );
		await expect( page.locator( '.docsway-menu__item--file a', { hasText: 'Keyboard shortcuts' } ) ).toHaveCount( 2 );

		await page.goto( `/wp-admin/upload.php?item=${ id }` );
		saved = page.waitForResponse( ( r ) => r.url().includes( 'admin-ajax.php' ) && ( r.request().postData() || '' ).includes( 'save-attachment-compat' ) );
		await page.getByLabel( 'Configuration', { exact: true } ).uncheck();
		await saved;
		await page.goto( '/main-configuration-file/' );
		await expect( page.locator( '.docsway-menu__item--file a', { hasText: 'Keyboard shortcuts' } ) ).toHaveCount( 1 );
	} );

	test( 'turning documents off removes them from search and the menu', async ( { page } ) => {
		await setDocuments( page, false );
		try {
			await page.goto( '/documentation/?dq=pebblecast' );
			await expect( page.locator( '.docsway-results__count' ) ).toContainText( '0 results' );
			await expect( page.locator( '.docsway-menu__item--file' ) ).toHaveCount( 0 );
		} finally {
			await setDocuments( page, true );
		}
		await page.goto( '/documentation/?dq=pebblecast' );
		await expect( page.locator( '.docsway-results__count' ) ).toContainText( '1 result' );
	} );
} );
