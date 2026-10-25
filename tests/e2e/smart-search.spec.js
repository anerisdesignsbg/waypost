// Smart search: best answer, word forms, typing mistakes, topics, synonyms and highlights on arrival.
const { test, expect } = require( '@playwright/test' );

async function setSynonyms( page, text ) {
	await page.goto( '/wp-admin/admin.php?page=waypost-settings' );
	await page.locator( '.wpst-sections-nav a[href="#search"]' ).click();
	await page.locator( '#wpst-synonyms' ).fill( text );
	await page.click( 'button:has-text("Save settings")' );
	await expect( page.locator( '.notice-success' ) ).toBeVisible();
}

test.describe( 'Smart search for readers', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test( 'a question gets a best answer that links to the right section', async ( { page } ) => {
		await page.goto( '/documentation/?dq=' + encodeURIComponent( 'How do I share an album?' ) );
		const answer = page.locator( '.waypost-answer' );
		await expect( answer ).toBeVisible();
		await expect( answer.locator( '.waypost-answer__title' ) ).toHaveText( 'Share an album' );
		await expect( answer.locator( '.waypost-answer__path' ) ).toContainText( 'Shared albums' );
		await expect( answer.locator( '.waypost-answer__title a' ) ).toHaveAttribute( 'href', /\/shared-albums\/#share-an-album$/ );
		await expect( answer.locator( '.waypost-answer__text' ) ).toContainText( 'press Share' );
		await expect( answer.locator( '.waypost-answer__related li' ).first() ).toBeVisible();
		await expect( page.locator( '.waypost-results__heading' ) ).toHaveText( 'All results' );
		// Each result lists its matching sections with a line of text.
		const first = page.locator( '.waypost-result' ).first();
		await expect( first.locator( '.waypost-result__headings li' ).first().locator( '.waypost-result__snippet' ) ).toBeVisible();
	} );

	test( 'other forms of a word are found and highlighted whole', async ( { page } ) => {
		await page.goto( '/documentation/?dq=installing+docker' );
		const result = page.locator( '.waypost-result', { hasText: 'Installing with Docker' } ).first();
		await expect( result ).toBeVisible();
		await expect( result.locator( '.waypost-result__title mark' ).first() ).toHaveText( 'Installing' );
		await page.goto( '/documentation/?dq=installation' );
		await expect( page.locator( '.waypost-result', { hasText: 'Installing with Docker' } ).first() ).toBeVisible();
	} );

	test( 'a typing mistake is corrected and explained', async ( { page } ) => {
		await page.goto( '/documentation/?dq=dokcer' );
		await expect( page.locator( '.waypost-results__notice' ) ).toContainText( 'docker' );
		await expect( page.locator( '.waypost-result', { hasText: 'Installing with Docker' } ).first() ).toBeVisible();
	} );

	test( 'when no page has every word, pages with some of them are shown', async ( { page } ) => {
		await page.goto( '/documentation/?dq=docker+zzqxv' );
		await expect( page.locator( '.waypost-results__notice' ) ).toContainText( 'No page has all of your words' );
		await expect( page.locator( '.waypost-answer' ) ).toHaveCount( 0 );
		await expect( page.locator( '.waypost-result' ).first() ).toBeVisible();
	} );

	test( 'topics narrow the results in place', async ( { page } ) => {
		await page.goto( '/documentation/?dq=docker' );
		const chip = page.locator( '.waypost-topic', { hasText: 'Installation' } );
		await expect( chip ).toBeVisible();
		const count = await chip.locator( '.waypost-topic__count' ).innerText();
		await chip.click();
		await expect( page ).toHaveURL( /topic=\d+/ );
		await expect( page.locator( '.waypost-topic.is-current' ) ).toContainText( 'Installation' );
		await expect( page.locator( '.waypost-results__count' ) ).toContainText( count + ' result' );
		const sections = await page.locator( '.waypost-result__section' ).allInnerTexts();
		expect( sections.length ).toBeGreaterThan( 0 );
		for ( const s of sections ) {
			expect( s.toLowerCase() ).toMatch( /^installation/ );
		}
		await page.locator( '.waypost-topic', { hasText: 'All topics' } ).click();
		await expect( page ).not.toHaveURL( /topic=/ );
	} );

	test( 'instant results open with the best answer', async ( { page } ) => {
		await page.goto( '/installing-on-ubuntu/' );
		await page.locator( '.waypost-search__input' ).first().fill( 'reset admin password' );
		const answer = page.locator( '.waypost-search__panel .waypost-hit--answer' ).first();
		await expect( answer ).toContainText( 'Reset the admin password' );
		await expect( answer ).toHaveAttribute( 'href', /\/user-accounts\/#reset-the-admin-password$/ );
	} );

	test( 'the page a reader opens highlights what they searched for', async ( { page } ) => {
		await page.goto( '/documentation/?dq=' + encodeURIComponent( 'restore backup' ) );
		await page.locator( '.waypost-answer__more' ).click();
		await expect( page ).toHaveURL( /\/backups-and-retention\/#restore-a-backup$/ );
		await expect( page.locator( 'mark.waypost-found' ).first() ).toBeVisible();
		const bar = page.locator( '.waypost-found-bar' );
		await expect( bar ).toContainText( 'restore backup' );
		await bar.locator( 'button' ).click();
		await expect( page.locator( 'mark.waypost-found' ) ).toHaveCount( 0 );
		await expect( bar ).toHaveCount( 0 );

		// Coming back later without a search: no highlights.
		await page.goto( '/backups-and-retention/' );
		await expect( page.locator( 'mark.waypost-found' ) ).toHaveCount( 0 );
	} );
} );

test.describe( 'Smart search settings', () => {
	test( 'synonyms find pages that use a different word', async ( { page, browser } ) => {
		await setSynonyms( page, 'snapshot, backup' );
		try {
			const visitor = await browser.newPage( { storageState: { cookies: [], origins: [] } } );
			await visitor.goto( '/documentation/?dq=snapshot' );
			await expect( visitor.locator( '.waypost-result', { hasText: 'Backups and retention' } ).first() ).toBeVisible();
			await visitor.close();
		} finally {
			await setSynonyms( page, '' );
		}
	} );
} );
