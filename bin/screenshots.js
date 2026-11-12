// Takes the product screenshots used for listings and the website.
//
// Run:  npm run screenshots            (all of them)
//       npm run screenshots -- 03 07   (only shots whose name starts with 03 or 07)
//
// Uses the test site (http://localhost:8889, Twenty Sixteen) with fresh seed content,
// changes settings for each shot, and reseeds at the end so the tests still pass.
// Images are saved at 2x to marketing/screenshots/.
const { chromium } = require( '@playwright/test' );
const { execSync } = require( 'child_process' );
const fs = require( 'fs' );
const path = require( 'path' );

const BASE = process.env.WAYPOST_URL || 'http://localhost:8889';
const OUT = path.join( __dirname, '..', 'marketing', 'screenshots' );
const ADMIN = path.join( __dirname, '..', 'tests', '.auth', 'admin.json' );
const only = process.argv.slice( 2 );

function wp( cmd ) {
	execSync( `npx wp-env run tests-cli wp ${ cmd }`, { stdio: 'pipe', cwd: path.join( __dirname, '..' ) } );
}
function settings( values ) {
	wp( 'eval-file bin/screenshot-settings.php ' + Buffer.from( JSON.stringify( values ) ).toString( 'base64' ) );
}

// The look most shots share: the Waypost layout with its own docs bar, in the Modern style.
const LOOK = { blogname: 'Magpie', layout: 'waypost', chrome: 'waypost', style: 'modern', accent: '#4f46e5', width: 'contained', sidebar: 'left', toc_position: 'side', show_updated: 1, show_reading: 1, heading_links: 1, feedback: 1 };

async function scrollTo( page, selector, offset = 90 ) {
	await page.locator( selector ).first().evaluate( ( el, off ) => window.scrollTo( 0, el.getBoundingClientRect().top + window.scrollY - off ), offset );
	await page.waitForTimeout( 300 );
}

const shots = [
	{
		name: '01-docs-page',
		caption: 'Your pages become a documentation site: sidebar menu, table of contents, breadcrumbs, reading time and previous and next links.',
		async run( page ) {
			await page.goto( '/installing-with-docker/' );
		},
	},
	{
		name: '02-search-home',
		caption: 'A search home for your docs with quick searches and topic cards.',
		async run( page ) {
			await page.goto( '/documentation/' );
		},
	},
	{
		name: '03-best-answer',
		caption: 'Smart search answers questions: the best section on top with a short summary and other pages that cover it. Topic filters above the results are part of Waypost Pro.',
		async run( page ) {
			await page.goto( '/documentation/?dq=' + encodeURIComponent( 'How do I share an album?' ) );
			await scrollTo( page, '.waypost-home__results', 70 );
		},
	},
	{
		name: '04-instant-search',
		caption: 'Instant results as you type, from anywhere with Ctrl+K or Cmd+K. It ignores question words and fixes typing mistakes.',
		async run( page ) {
			await page.goto( '/installing-with-docker/' );
			await page.keyboard.press( 'Control+k' );
			await page.locator( '.waypost-modal .waypost-search__input' ).fill( 'reset admin password' );
			await page.locator( '.waypost-modal .waypost-hit--answer' ).waitFor();
			await page.waitForTimeout( 400 );
		},
	},
	{
		name: '05-highlights',
		caption: 'Readers land on the exact section, with their search words highlighted.',
		async run( page ) {
			await page.goto( '/documentation/?dq=' + encodeURIComponent( 'stop sharing album' ) );
			await page.locator( '.waypost-answer__more' ).click();
			await page.locator( '.waypost-found-bar' ).waitFor();
			await page.waitForTimeout( 900 );
		},
	},
	{
		name: '06-documents',
		caption: 'Waypost Pro: search inside PDF, Word, PowerPoint and Excel files. Documents sit in your sections with a file type label.',
		async run( page ) {
			await page.goto( '/documentation/?dq=pebblecast' );
			const toggle = page.locator( '.waypost-menu__item--top', { hasText: 'Downloads' } ).locator( '.waypost-menu__toggle' ).first();
			await toggle.click();
			await scrollTo( page, '.waypost-home__results', 70 );
		},
	},
	{
		name: '07-dark-style',
		caption: 'Seven ready-made styles, including Dark and Auto light and dark with a reader switch.',
		settings: { style: 'dark', accent: '#60a5fa' },
		async run( page ) {
			await page.goto( '/system-requirements/' );
		},
	},
	{
		name: '08-classic-style',
		caption: 'Classic, Minimalist, Light or your own theme’s look, with any accent colour.',
		settings: { style: 'classic', accent: '#9a3412' },
		async run( page ) {
			await page.goto( '/albums-overview/' );
		},
	},
	{
		name: '09-mobile',
		caption: 'Works on phones: the menu folds away and search stays one tap away.',
		viewport: { width: 390, height: 844 },
		scale: 3,
		async run( page ) {
			await page.goto( '/installing-with-docker/' );
		},
	},
	{
		name: '10-setup-wizard',
		caption: 'A five-minute setup wizard picks your pages, sections, layout and style.',
		admin: true,
		async run( page ) {
			await page.goto( '/wp-admin/admin.php?page=waypost-setup&step=look' );
		},
	},
	{
		name: '11-menu-order',
		caption: 'Drag and drop to order the menu. Sections can nest four levels deep.',
		admin: true,
		async run( page ) {
			await page.goto( '/wp-admin/admin.php?page=waypost-structure' );
		},
	},
	{
		name: '12-appearance',
		caption: 'Pick a ready-made style and accent colour in Settings. Layout, sidebar side and width are one click each.',
		admin: true,
		async run( page ) {
			await page.goto( '/wp-admin/admin.php?page=waypost-settings#appearance' );
			await scrollTo( page, '#appearance .wpst-styles', 60 );
		},
	},
	{
		name: '13-search-insights',
		caption: 'Search insights show what readers look for and which searches found nothing.',
		admin: true,
		async run( page ) {
			await page.goto( '/wp-admin/admin.php?page=waypost-insights' );
		},
	},
	{
		name: '14-media-sections',
		caption: 'Waypost Pro: add a PDF or Word file to the docs by ticking its sections in the Media Library.',
		admin: true,
		async run( page ) {
			await page.goto( '/wp-admin/admin.php?page=waypost-structure&tab=assign&show=documents' );
			const id = await page.locator( '#waypost-assign tbody tr', { hasText: 'quick start card' } ).locator( 'input[type=checkbox]' ).getAttribute( 'value' );
			await page.goto( `/wp-admin/upload.php?item=${ id }` );
			await page.getByText( 'Doc sections', { exact: true } ).first().waitFor();
			await page.waitForTimeout( 500 );
		},
		// Only the details panel: the file preview beside it is a large empty icon.
		clip: '.media-modal .attachment-info',
	},
];

( async () => {
	fs.mkdirSync( OUT, { recursive: true } );
	console.log( 'Reseeding the test site…' );
	wp( 'eval-file bin/seed.php' );
	const browser = await chromium.launch();
	const captions = {};
	try {
		// Admin shots first, so Search insights does not list the searches the other shots make.
		for ( const shot of [ ...shots.filter( ( s ) => s.admin ), ...shots.filter( ( s ) => ! s.admin ) ] ) {
			if ( only.length && ! only.some( ( p ) => shot.name.startsWith( p ) ) ) {
				continue;
			}
			settings( { ...LOOK, ...( shot.settings || {} ) } );
			const context = await browser.newContext( {
				baseURL: BASE,
				viewport: shot.viewport || { width: 1440, height: 900 },
				deviceScaleFactor: shot.scale || 2,
				isMobile: !! shot.viewport,
				hasTouch: !! shot.viewport,
				storageState: shot.admin ? ADMIN : undefined,
				colorScheme: 'light',
				reducedMotion: 'reduce',
			} );
			const page = await context.newPage();
			await shot.run( page );
			await page.evaluate( () => document.fonts && document.fonts.ready );
			await page.waitForTimeout( 300 );
			const file = path.join( OUT, shot.name + '.png' );
			if ( shot.clip ) {
				await page.locator( shot.clip ).first().screenshot( { path: file } );
			} else {
				await page.screenshot( { path: file } );
			}
			captions[ shot.name ] = `${ shot.name }.png: ${ shot.caption }`;
			console.log( '✓ ' + shot.name );
			await context.close();
		}
	} finally {
		await browser.close();
		console.log( 'Restoring the seeded settings…' );
		settings( { blogname: 'Waypost' } );
		wp( 'eval-file bin/seed.php' );
	}
	if ( ! only.length ) {
		fs.writeFileSync( path.join( OUT, 'captions.txt' ), Object.keys( captions ).sort().map( ( k ) => captions[ k ] ).join( '\n' ) + '\n' );
	}
	console.log( `Saved to ${ OUT }` );
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
