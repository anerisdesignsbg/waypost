// The Waypost docs layout in block themes. Runs last (files run A to Z): it switches the
// site's theme and puts Twenty Sixteen back afterwards.
const { test, expect } = require( '@playwright/test' );

async function activate( page, name ) {
	await page.goto( '/wp-admin/themes.php' );
	const link = page.locator( `a.activate[aria-label="Activate ${ name }"]` );
	if ( await link.count() ) {
		await link.first().click();
		await expect( page.locator( '.theme.active' ) ).toContainText( name );
	}
}

test.describe.serial( 'Block themes', () => {
	test.afterAll( async ( { browser } ) => {
		const context = await browser.newContext( { storageState: 'tests/.auth/admin.json' } );
		await activate( await context.newPage(), 'Twenty Sixteen' );
		await context.close();
	} );

	for ( const theme of [ 'Twenty Twenty-Five', 'Twenty Twenty-Four', 'Twenty Twenty-Three' ] ) {
		test( `${ theme }: docs layout between the theme's header and footer`, async ( { page } ) => {
			await activate( page, theme );

			await page.goto( '/installing-on-ubuntu/' );
			await expect( page.locator( '.waypost-layout--doc' ) ).toHaveCount( 1 );
			await expect( page.locator( 'header.wp-block-template-part' ) ).toHaveCount( 1 );
			await expect( page.locator( 'footer.wp-block-template-part' ) ).toHaveCount( 1 );
			await expect( page.locator( 'main' ) ).toHaveCount( 1 );
			await expect( page.locator( 'meta[name=viewport]' ) ).toHaveCount( 1 );
			await expect( page.locator( '.waypost-layout__sidebar .waypost-menu' ) ).toBeVisible();
			await expect( page.locator( '.waypost-layout__title' ) ).toHaveText( 'Installing on Ubuntu' );
			const scroll = await page.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
			expect( scroll ).toBe( 0 );

			await page.goto( '/documentation/?dq=album' );
			await expect( page.locator( '.waypost-layout--home .waypost-home__list .waypost-result' ) ).toHaveCount( 10 );

			await page.goto( '/about-us/' );
			await expect( page.locator( '.waypost-layout' ) ).toHaveCount( 0 );
			await expect( page.locator( 'header.wp-block-template-part' ) ).toHaveCount( 1 );
		} );
	}
} );
