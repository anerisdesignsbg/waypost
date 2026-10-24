// Logs in once and saves the session for the other tests.
const { test: setup } = require( '@playwright/test' );

setup( 'log in as admin', async ( { page } ) => {
	await page.goto( '/wp-login.php' );
	await page.fill( '#user_login', process.env.WP_USER || 'admin' );
	await page.fill( '#user_pass', process.env.WP_PASS || 'password' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
	await page.context().storageState( { path: 'tests/.auth/admin.json' } );
} );
