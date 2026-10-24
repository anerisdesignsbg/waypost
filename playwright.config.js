// Browser tests for Waypost. Run `npm run start` and `npm run seed` first.
const { defineConfig, devices } = require( '@playwright/test' );

const baseURL = process.env.WAYPOST_URL || 'http://localhost:8888';

module.exports = defineConfig( {
	testDir: './tests/e2e',
	timeout: 30000,
	retries: 0,
	workers: 1, // Tests share one WordPress site and change its settings.
	reporter: [ [ 'list' ], [ 'html', { open: 'never' } ] ],
	use: {
		baseURL,
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
		...( process.env.CHROMIUM_PATH ? { launchOptions: { executablePath: process.env.CHROMIUM_PATH } } : {} ),
	},
	projects: [
		{ name: 'setup', testMatch: /auth\.setup\.js/ },
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ], storageState: 'tests/.auth/admin.json' },
			dependencies: [ 'setup' ],
		},
	],
} );
