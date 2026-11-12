// Copies the tutorials and screenshots into the Waypost Demo Content plugin
// (demo/waypost-demo-content/content/), with screenshots resized for the web.
// Run by the demo:* npm scripts; the copied files are not committed.
const fs = require( 'fs' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

const root = path.join( __dirname, '..' );
const out = path.join( root, 'demo', 'waypost-demo-content', 'content' );
const tutorials = path.join( root, 'marketing', 'tutorials' );
const shots = path.join( root, 'marketing', 'screenshots' );

fs.rmSync( out, { recursive: true, force: true } );
fs.mkdirSync( path.join( out, 'tutorials' ), { recursive: true } );
fs.mkdirSync( path.join( out, 'images' ), { recursive: true } );

for ( const f of fs.readdirSync( tutorials ).filter( ( f ) => /^\d+-.+\.md$/.test( f ) ) ) {
	fs.copyFileSync( path.join( tutorials, f ), path.join( out, 'tutorials', f ) );
}
for ( const f of fs.readdirSync( shots ).filter( ( f ) => /^\d+-.+\.png$/.test( f ) ) ) {
	const dest = path.join( out, 'images', f );
	try {
		// macOS: shrink to 1600 pixels at most, plenty for a docs page.
		execFileSync( 'sips', [ '-Z', '1600', path.join( shots, f ), '--out', dest ], { stdio: 'ignore' } );
	} catch ( e ) {
		fs.copyFileSync( path.join( shots, f ), dest );
	}
}
fs.copyFileSync( path.join( root, 'marketing', 'wordpress-org', 'assets', 'icon-256x256.png' ), path.join( out, 'images', 'waypost-icon.png' ) );
console.log( 'Demo content ready in demo/waypost-demo-content/content/' );
