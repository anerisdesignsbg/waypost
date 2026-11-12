// Builds the WordPress.org listing images in marketing/wordpress-org/assets/:
// banners and icons from marketing/brand/, plus the screenshots renamed to screenshot-N.png.
// That folder is copied as-is into the SVN /assets folder.
//
// Run: npm run wporg-assets   (after npm run screenshots when the screenshots change)
const { chromium } = require( '@playwright/test' );
const fs = require( 'fs' );
const path = require( 'path' );

const ROOT = path.join( __dirname, '..', 'marketing' );
const BRAND = path.join( ROOT, 'brand' );
const SHOTS = path.join( ROOT, 'screenshots' );
const OUT = path.join( ROOT, 'wordpress-org', 'assets' );

( async () => {
	fs.mkdirSync( OUT, { recursive: true } );
	const browser = await chromium.launch();
	const render = async ( file, width, height, scale, out, transparent ) => {
		const page = await browser.newPage( { viewport: { width, height }, deviceScaleFactor: scale } );
		await page.goto( 'file://' + path.join( BRAND, file ) );
		await page.waitForLoadState( 'networkidle' );
		await page.screenshot( { path: path.join( OUT, out ), omitBackground: !! transparent } );
		await page.close();
		console.log( '✓ ' + out );
	};
	await render( 'banner.html', 772, 250, 1, 'banner-772x250.png' );
	await render( 'banner.html', 772, 250, 2, 'banner-1544x500.png' );
	await render( 'icon.html', 128, 128, 1, 'icon-128x128.png', true );
	await render( 'icon.html', 128, 128, 2, 'icon-256x256.png', true );
	await browser.close();

	fs.copyFileSync( path.join( BRAND, 'icon.svg' ), path.join( OUT, 'icon.svg' ) );
	console.log( '✓ icon.svg' );

	// Screenshots in name order become screenshot-1.png, screenshot-2.png… matching readme.txt.
	fs.readdirSync( SHOTS ).filter( ( f ) => /^\d+-.+\.png$/.test( f ) ).sort().forEach( ( f, i ) => {
		fs.copyFileSync( path.join( SHOTS, f ), path.join( OUT, `screenshot-${ i + 1 }.png` ) );
		console.log( `✓ screenshot-${ i + 1 }.png  (${ f })` );
	} );
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
