// Builds website images for the Waypost product page in marketing/website/:
// mockups (screenshots in browser and phone frames on brand backgrounds),
// a social sharing image, and plain screenshots resized for the web.
//
// Run: npm run website-assets   (after npm run screenshots when the screenshots change)
const { chromium } = require( '@playwright/test' );
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );

const ROOT = path.join( __dirname, '..', 'marketing' );
const SHOTS = path.join( ROOT, 'screenshots' );
const OUT = path.join( ROOT, 'website' );
const shot = ( name ) => 'file://' + path.join( SHOTS, name + '.png' );
const icon = 'file://' + path.join( ROOT, 'brand', 'icon.svg' );

const CSS = `
* { box-sizing: border-box; margin: 0; }
html, body { width: 100%; height: 100%; overflow: hidden; }
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif; display: flex; align-items: center; justify-content: center; position: relative; }
.bg-indigo { background: radial-gradient(circle at 80% 15%, rgba(129,140,248,.45), transparent 45%), linear-gradient(135deg, #1e1b4b 0%, #312e81 45%, #4f46e5 100%); }
.bg-soft { background: radial-gradient(circle at 15% 20%, #e0e7ff, transparent 50%), radial-gradient(circle at 85% 85%, #ede9fe, transparent 45%), #f8fafc; }
.grid { position: absolute; inset: 0; background-image: linear-gradient(rgba(255,255,255,.05) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px); background-size: 32px 32px; }
.browser { position: relative; border-radius: 14px; overflow: hidden; background: #fff; box-shadow: 0 30px 80px rgba(15, 12, 60, .35), 0 0 0 1px rgba(15, 12, 60, .08); }
.bar { height: 40px; display: flex; align-items: center; gap: 8px; padding: 0 16px; background: #f1f5f9; border-bottom: 1px solid #e2e8f0; }
.bar i { width: 12px; height: 12px; border-radius: 50%; background: #cbd5e1; }
.bar i:nth-child(1) { background: #f87171; } .bar i:nth-child(2) { background: #fbbf24; } .bar i:nth-child(3) { background: #34d399; }
.url { margin-left: 18px; flex: 1; max-width: 520px; height: 24px; border-radius: 6px; background: #fff; border: 1px solid #e2e8f0; font-size: 12px; color: #64748b; display: flex; align-items: center; padding: 0 10px; }
.browser img { display: block; width: 100%; }
.phone { position: absolute; border-radius: 44px; background: #0f172a; padding: 14px; box-shadow: 0 30px 70px rgba(15, 12, 60, .45); }
.phone .screen { border-radius: 32px; overflow: hidden; position: relative; background: #fff; padding-top: 34px; }
.phone .screen img { display: block; width: 100%; }
.phone .notch { position: absolute; top: 10px; left: 50%; transform: translateX(-50%); width: 96px; height: 26px; border-radius: 14px; background: #0f172a; z-index: 2; }
.badge { position: absolute; padding: 8px 16px; border-radius: 999px; font-weight: 700; font-size: 18px; letter-spacing: .02em; color: #fff; background: #4f46e5; box-shadow: 0 10px 30px rgba(79, 70, 229, .4); }
.label { position: absolute; font-weight: 700; font-size: 20px; color: #1e1b4b; background: #fff; padding: 8px 14px; border-radius: 10px; box-shadow: 0 8px 24px rgba(15,12,60,.18); }
`;

function browser( src, url, width, extra = '' ) {
	return `<div class="browser" style="width:${ width }px;${ extra }"><div class="bar"><i></i><i></i><i></i><div class="url">${ url }</div></div><img src="${ src }" alt=""></div>`;
}
function phone( src, width, style ) {
	return `<div class="phone" style="width:${ width }px;${ style }"><div class="screen"><div class="notch"></div><img src="${ src }" alt=""></div></div>`;
}

// name, size, background, body
const mockups = [
	{
		name: 'hero',
		w: 1920, h: 1200, bg: 'bg-indigo',
		body: `<div class="grid"></div>
			${ browser( shot( '03-best-answer' ), 'yoursite.com/docs/?dq=How+do+I+share+an+album', 1440, 'transform:translateX(-90px);' ) }
			${ phone( shot( '09-mobile' ), 300, 'right:130px;bottom:90px;' ) }`,
	},
	{
		name: 'smart-search',
		w: 1600, h: 1100, bg: 'bg-soft',
		body: browser( shot( '04-instant-search' ), 'yoursite.com/docs/', 1360 ),
	},
	{
		name: 'best-answer',
		w: 1600, h: 1100, bg: 'bg-soft',
		body: browser( shot( '03-best-answer' ), 'yoursite.com/docs/?dq=How+do+I+share+an+album', 1360 ),
	},
	{
		name: 'docs-site',
		w: 1600, h: 1100, bg: 'bg-soft',
		body: browser( shot( '01-docs-page' ), 'yoursite.com/shared-albums/', 1360 ),
	},
	{
		name: 'highlights',
		w: 1600, h: 1100, bg: 'bg-soft',
		body: browser( shot( '05-highlights' ), 'yoursite.com/shared-albums/#stop-sharing', 1360 ),
	},
	{
		name: 'styles',
		w: 1920, h: 1200, bg: 'bg-indigo',
		body: `<div class="grid"></div>
			${ browser( shot( '08-classic-style' ), 'Classic', 1000, 'position:absolute;left:110px;top:200px;transform:rotate(-4deg);' ) }
			${ browser( shot( '07-dark-style' ), 'Dark', 1000, 'position:absolute;right:110px;top:150px;transform:rotate(4deg);' ) }
			${ browser( shot( '01-docs-page' ), 'Modern', 1100, 'position:absolute;left:410px;top:330px;z-index:2;' ) }`,
	},
	{
		name: 'documents-pro',
		w: 1600, h: 1100, bg: 'bg-soft',
		body: browser( shot( '06-documents' ), 'yoursite.com/docs/?dq=pebblecast', 1360 ) + '<div class="badge" style="top:60px;right:90px;">Waypost Pro</div>',
	},
	{
		name: 'setup-wizard',
		w: 1600, h: 1100, bg: 'bg-soft',
		body: browser( shot( '10-setup-wizard' ), 'yoursite.com/wp-admin/admin.php?page=waypost-setup', 1360 ),
	},
	{
		name: 'search-insights',
		w: 1600, h: 1100, bg: 'bg-soft',
		body: browser( shot( '13-search-insights' ), 'yoursite.com/wp-admin/admin.php?page=waypost-insights', 1360 ),
	},
	{
		name: 'mobile',
		w: 1200, h: 1200, bg: 'bg-indigo',
		body: `<div class="grid"></div>${ phone( shot( '09-mobile' ), 380, 'position:relative;' ) }`,
	},
	{
		name: 'social-share',
		w: 1200, h: 630, bg: 'bg-indigo',
		body: `<div class="grid"></div>
			<div style="position:absolute;left:64px;top:150px;width:490px;color:#fff;">
				<div style="display:flex;align-items:center;gap:14px;"><img src="${ icon }" style="width:58px;height:58px;border-radius:14px;"><span style="font-size:46px;font-weight:800;letter-spacing:-1px;">Waypost</span></div>
				<p style="margin-top:22px;font-size:30px;line-height:1.25;font-weight:600;">Your WordPress pages, now a docs site that answers questions.</p>
				<p style="margin-top:18px;font-size:18px;color:#c7d2fe;">Sidebar menu, smart search and contents. No migration.</p>
			</div>
			${ browser( shot( '03-best-answer' ), 'yoursite.com/docs/', 760, 'position:absolute;left:580px;top:90px;' ) }`,
	},
];

( async () => {
	fs.mkdirSync( path.join( OUT, 'screenshots' ), { recursive: true } );
	const tmp = fs.mkdtempSync( path.join( os.tmpdir(), 'waypost-web-' ) );
	const browserApp = await chromium.launch();
	try {
		for ( const m of mockups ) {
			const file = path.join( tmp, m.name + '.html' );
			fs.writeFileSync( file, `<!doctype html><html><head><meta charset="utf-8"><style>${ CSS }</style></head><body class="${ m.bg }">${ m.body }</body></html>` );
			const page = await browserApp.newPage( { viewport: { width: m.w, height: m.h }, deviceScaleFactor: 1 } );
			await page.goto( 'file://' + file );
			await page.waitForLoadState( 'networkidle' );
			await page.screenshot( { path: path.join( OUT, m.name + '.jpg' ), type: 'jpeg', quality: 88 } );
			await page.close();
			console.log( '✓ ' + m.name + '.jpg' );
		}
		// Plain screenshots, 1600 pixels wide, for galleries and lightboxes.
		for ( const f of fs.readdirSync( SHOTS ).filter( ( f ) => /^\d+-.+\.png$/.test( f ) ) ) {
			const page = await browserApp.newPage( { viewport: { width: 1600, height: 1000 } } );
			const file = path.join( tmp, 'plain.html' );
			fs.writeFileSync( file, `<!doctype html><html><head><style>html,body{margin:0}img{display:block;width:1600px}</style></head><body><img src="${ 'file://' + path.join( SHOTS, f ) }"></body></html>` );
			await page.goto( 'file://' + file );
			await page.waitForLoadState( 'networkidle' );
			const out = path.join( OUT, 'screenshots', f.replace( /\.png$/, '.jpg' ) );
			await page.locator( 'img' ).screenshot( { path: out, type: 'jpeg', quality: 86 } );
			await page.close();
		}
		console.log( '✓ screenshots/ (14 web-sized copies)' );
	} finally {
		await browserApp.close();
		fs.rmSync( tmp, { recursive: true, force: true } );
	}
} )().catch( ( e ) => {
	console.error( e );
	process.exit( 1 );
} );
