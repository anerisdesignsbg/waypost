<?php
/**
 * Waypost dummy content.
 *
 * Builds a realistic documentation site for a fictional self-hosted photo
 * library ("Magpie"): 170+ pages in sections up to four levels deep,
 * plus deliberate edge cases. Safe to run repeatedly: everything it created
 * last time is removed first.
 *
 * Run:  npm run seed          (classic theme with sidebar)
 *       npm run seed:divi     (also switches to Divi and adds a Divi layout page)
 *
 * Inside wp-env this runs as: wp eval-file bin/seed.php [divi]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run this with WP-CLI: wp eval-file bin/seed.php\n" );
}
if ( ! class_exists( 'Waypost_Settings' ) ) {
	WP_CLI::error( 'Activate the Waypost plugin first.' );
}

$want_divi = isset( $args ) && in_array( 'divi', (array) $args, true );
mt_srand( 42 ); // Same content every run.

const WAYPOST_SEED_META = '_waypost_seed';
$tax = WAYPOST_OWN_TAXONOMY;

// ── 1. Clean up the previous run ────────────────────────────────────────────

WP_CLI::log( 'Removing previous seed content…' );
$old = get_posts(
	array(
		'post_type'      => 'any',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			'relation' => 'OR',
			array( 'key' => WAYPOST_SEED_META ),
			array( 'key' => '_waypost_created' ), // Docs home pages made by the setup wizard.
		),
	)
);
foreach ( $old as $id ) {
	wp_delete_post( $id, true );
}
foreach ( get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids' ) ) as $tid ) {
	wp_delete_term( $tid, $tax );
}
delete_option( 'waypost_order' );
delete_option( 'waypost_search_log' );
Waypost_Feedback::reset();
delete_transient( 'waypost_tree' );
Waypost_Settings::update( array( 'taxonomy_mode' => 'own', 'post_types' => array( 'page' ) ) );

// ── 2. Text helpers ─────────────────────────────────────────────────────────

$words = explode( ' ', 'photo video album library upload import export share link guest family member person face place map tag caption date timeline memory favourite archive backup restore storage disk folder thumbnail preview original resolution format camera phone device sync duplicate search filter collection slideshow print edit crop rotate metadata location event trip holiday account user invite permission quota space configuration option value default restart log entry dashboard schedule report timezone certificate token endpoint request response limit' );

$sentence = function ( $min = 8, $max = 18 ) use ( $words ) {
	$n = mt_rand( $min, $max );
	$s = array();
	for ( $i = 0; $i < $n; $i++ ) {
		$s[] = $words[ mt_rand( 0, count( $words ) - 1 ) ];
	}
	return ucfirst( implode( ' ', $s ) ) . '.';
};
$para = function ( $sentences = 3 ) use ( $sentence ) {
	$out = array();
	for ( $i = 0; $i < $sentences; $i++ ) {
		$out[] = $sentence();
	}
	return '<p>' . implode( ' ', $out ) . '</p>';
};
$code_samples = array(
	"sudo apt-get update\nsudo apt-get install magpie",
	"magpie-cli config set uploads.max_size_mb 500\nmagpie-cli restart",
	"curl -u admin:secret https://localhost:8080/api/albums | jq .",
	"docker run -d --name magpie -p 8080:8080 -v /srv/photos:/photos magpie/server:latest",
	"storage:\n  originals: /srv/photos\n  previews: /var/cache/magpie\n  keep_deleted_days: 30",
	"systemctl restart magpie\njournalctl -u magpie -f",
);
$section_titles = array( 'Overview', 'Before you start', 'Requirements', 'Step by step', 'Configuration options', 'Verifying it works', 'Common problems', 'Performance tuning', 'Security notes', 'Examples', 'Next steps' );

/** Typical doc page: intro, 3–7 H2 sections, some H3s, code and lists. */
$body = function ( $title, $variant = 'normal' ) use ( $para, $sentence, $code_samples, $section_titles ) {
	$html = $para( 2 );
	if ( 'short' === $variant ) {
		return $html; // No headings at all: the contents box must not appear.
	}
	$picks = $section_titles;
	shuffle( $picks );
	$picks = array_slice( $picks, 0, mt_rand( 3, 7 ) );
	foreach ( $picks as $i => $h ) {
		$html .= "\n<h2>{$h}</h2>\n" . $para( mt_rand( 1, 3 ) );
		if ( 0 === $i % 2 ) {
			$html .= "\n<h3>{$h}: details</h3>\n" . $para( 2 );
		}
		if ( 1 === mt_rand( 1, 3 ) ) {
			$html .= "\n<pre><code>" . esc_html( $code_samples[ mt_rand( 0, count( $code_samples ) - 1 ) ] ) . "</code></pre>";
		}
		if ( 1 === mt_rand( 1, 4 ) ) {
			$html .= "\n<ul><li>" . $sentence( 4, 8 ) . '</li><li>' . $sentence( 4, 8 ) . '</li><li>' . $sentence( 4, 8 ) . '</li></ul>';
		}
	}
	return $html;
};

$created = 0;
$page = function ( $title, $content, $terms = array(), $status = 'publish', $type = 'page' ) use ( $tax, &$created ) {
	$id = wp_insert_post(
		array(
			'post_type'    => $type,
			'post_status'  => $status,
			'post_title'   => $title,
			'post_content' => $content,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( "Could not create {$title}: " . $id->get_error_message() );
		return 0;
	}
	update_post_meta( $id, WAYPOST_SEED_META, 1 );
	if ( $terms ) {
		wp_set_object_terms( $id, array_map( 'intval', (array) $terms ), $tax );
	}
	$created++;
	return $id;
};
$section = function ( $name, $parent = 0 ) use ( $tax ) {
	$r = wp_insert_term( $name, $tax, array( 'parent' => $parent ) );
	return is_wp_error( $r ) ? 0 : (int) $r['term_id'];
};

// ── 3. Sections and pages ───────────────────────────────────────────────────

WP_CLI::log( 'Creating sections and pages…' );
$ids = array(); // title => post ID, for links and tests.

$s_intro = $section( 'Introduction' );
foreach ( array( 'Welcome to Magpie', 'How Magpie works', 'Key concepts' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_intro );
}
$ids['Release notes'] = $page( 'Release notes', $body( 'Release notes', 'short' ), $s_intro );

$s_install = $section( 'Installation' );
$s_linux   = $section( 'Linux', $s_install );
foreach ( array( 'Installing on Ubuntu', 'Installing on Debian', 'Installing on CentOS Stream', 'Installing on Rocky Linux', 'Installing on AlmaLinux' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_linux );
}
$s_containers = $section( 'Containers', $s_install );
foreach ( array( 'Installing with Docker', 'Docker Compose example', 'Kubernetes with Helm', 'Podman' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_containers );
}
$s_cloud = $section( 'Cloud', $s_install );
foreach ( array( 'AWS deployment', 'Azure deployment', 'Google Cloud deployment', 'DigitalOcean deployment', 'Hetzner deployment' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_cloud );
}
$s_market = $section( 'Marketplace images', $s_cloud ); // Fourth level.
foreach ( array( 'AWS Marketplace AMI', 'Azure Marketplace image', 'Google Cloud Marketplace image' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_market );
}
foreach ( array( 'Upgrading Magpie', 'Uninstalling Magpie', 'System requirements' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_install );
}

$s_config = $section( 'Configuration' );
foreach ( array( 'Main configuration file', 'Storage locations', 'User accounts', 'Licence management', 'Time zones and clocks', 'Backups and retention' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_config );
}
$s_notify = $section( 'Notifications', $s_config );
foreach ( array( 'Email notifications', 'Slack notifications', 'Telegram notifications', 'Microsoft Teams notifications', 'Webhook notifications', 'PagerDuty notifications' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_notify );
}
$ids['Internal: support playbook'] = $page( 'Internal: support playbook', $body( 'x' ), $s_config, 'private' ); // Must not appear.

$s_import = $section( 'Importing photos' );
foreach ( array( 'Import from a phone', 'Import from Google Photos', 'Import from iCloud', 'Import from a folder', 'Import from Dropbox', 'Import from an SD card', 'Watch folders' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_import );
}

$s_sharing = $section( 'Sharing' );
$s_albums  = $section( 'Albums', $s_sharing );
foreach ( array( 'Albums overview', 'Shared albums', 'Public links', 'Album permissions', 'Collaborative albums', 'Slideshows' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_albums );
}
$s_family = $section( 'Family accounts', $s_sharing );
foreach ( array( 'Family groups', 'Partner sharing', 'Child accounts' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_family );
}

$s_integr = $section( 'Integrations' );
foreach ( array( 'Lightroom', 'Amazon S3', 'Backblaze B2', 'Google Drive', 'OneDrive', 'Nextcloud', 'Home Assistant', 'Zapier', 'IFTTT', 'Plex', 'Jellyfin', 'Digital photo frames' ) as $t ) {
	$ids[ "{$t} integration" ] = $page( "{$t} integration", $body( $t ), $s_integr );
}

$s_api = $section( 'API reference' );
$ids['API authentication'] = $page( 'API authentication', $body( 'auth' ), $s_api );
foreach ( array( 'photos', 'albums', 'people', 'places', 'tags', 'uploads', 'shares', 'users', 'licence', 'settings', 'notifications', 'jobs', 'audit log', 'sessions', 'devices' ) as $res ) {
	foreach ( array( 'List', 'Get', 'Update' ) as $verb ) {
		if ( 'Update' === $verb && mt_rand( 0, 1 ) ) {
			continue;
		}
		$t        = "{$verb} {$res}";
		$ids[ $t ] = $page( $t, $body( $t ), $s_api );
	}
}
foreach ( array( 'Rate limits', 'Errors and status codes', 'Pagination' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_api );
}

$s_guides = $section( 'Guides' );
$verbs    = array( 'Organise', 'Back up', 'Share', 'Find', 'Tidy up', 'Automate' );
$objects  = array( 'holiday photos', 'family videos', 'duplicate shots', 'old scans', 'a new phone', 'RAW files' );
foreach ( $verbs as $v ) {
	foreach ( array_slice( $objects, 0, 4 ) as $o ) {
		$t        = "How to " . strtolower( $v ) . " {$o}";
		$ids[ $t ] = $page( $t, $body( $t ), $s_guides );
	}
}

$s_trouble = $section( 'Troubleshooting' );
foreach ( array( 'Uploads are stuck', 'High CPU usage', 'Faces are not recognised', 'Mobile app cannot connect', 'Log files explained', 'Debug mode', 'Email alerts not arriving' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_trouble );
}

$s_faq = $section( 'FAQ' );
$ids['Frequently asked questions'] = $page( 'Frequently asked questions', $body( 'faq' ), $s_faq );
$ids['Licensing FAQ']             = $page( 'Licensing FAQ', $body( 'lic' ), $s_faq );

$s_vi = $section( 'Hướng dẫn tiếng Việt' );
foreach ( array( 'Cài đặt trên Ubuntu', 'Chia sẻ album ảnh', 'Câu hỏi thường gặp' ) as $t ) {
	$ids[ $t ] = $page( $t, '<p>Hướng dẫn này giải thích cách cài đặt và cấu hình. Ảnh và video được sao lưu tự động.</p><h2>Yêu cầu hệ thống</h2><p>Máy chủ cần 4 GB RAM.</p><h2>Các bước cài đặt</h2><p>Làm theo từng bước.</p><h2>Kiểm tra kết quả</h2><p>Mở bảng điều khiển.</p>', $s_vi );
}

// Edge cases ----------------------------------------------------------------

// Long page: Glossary with A–Z headings and terms (tests contents length and scroll highlight).
$glossary = $para( 1 );
foreach ( range( 'A', 'Z' ) as $l ) {
	$glossary .= "\n<h2>{$l}</h2>";
	for ( $i = 1; $i <= 2; $i++ ) {
		$glossary .= "\n<h3>{$l} term {$i}</h3>\n" . $para( 1 );
	}
}
$ids['Glossary'] = $page( 'Glossary', $glossary, $s_faq );

// Duplicate headings, a heading with markup and an ampersand, an existing id, and H4 only.
$ids['Edge case: tricky headings'] = $page(
	'Edge case: tricky headings',
	$para( 1 ) .
	"\n<h2>Overview</h2>" . $para( 1 ) .
	"\n<h2>Overview</h2>" . $para( 1 ) .
	"\n<h2>Using <code>--config</code> &amp; flags</h2>" . $para( 1 ) .
	"\n<h2 id=\"custom-anchor\">Heading with its own id</h2>" . $para( 1 ) .
	"\n<h3>Firewall rules</h3>" . $para( 1 ) .
	"\n<h4>Only an H4</h4>" . $para( 1 ) .
	"\n<h2>Ünïcödé &amp; émojis 🚀</h2>" . $para( 1 ),
	$s_guides
);

// Saved the way Divi 5 saves pages: text inside block attributes as JSON, with <, >, &
// and quotes as \u codes. Search must read the text, not the layout data. wp_slash()
// because wp_insert_post() strips backslashes, which would turn \u003c into u003c.
$ids['Edge case: Divi 5 content'] = $page(
	'Edge case: Divi 5 content',
	wp_slash( '<!-- wp:divi/placeholder --><!-- wp:divi/section {"builderVersion":"5.10.1"} --><!-- wp:divi/text '
		. serialize_block_attributes( array( 'content' => array( 'innerContent' => array( 'desktop' => array( 'value' => '<p>Lumière keeps every café photo safe.</p><h2>Ảnh động và Lumière</h2><p>Animated photos play when you hover over them.</p><h2>Exporting</h2><p>Export albums as a zip file.</p><h2>Printing</h2><p>Order prints of any album.</p>' ) ) ), 'builderVersion' => '5.10.1' ) )
		. ' /--><!-- /wp:divi/section --><!-- /wp:divi/placeholder -->' ),
	$s_guides
);

// Very long title (tests menu wrapping and pager).
$ids['long'] = $page( 'Keeping originals and previews on separate disks across several servers with automatic failover and a shared cache', $body( 'long' ), $s_config );

// One page in two sections (should appear in both; previous/next uses the first place).
$ids['Installing with Docker (shared)'] = $ids['Installing with Docker'];
wp_set_object_terms( $ids['Installing with Docker'], array( $s_containers, $s_guides ), $tax );

// Empty section: hidden on the site, greyed in admin.
$section( 'Coming soon' );

// Section whose only page is a draft: also hidden.
$s_beta = $section( 'Beta features' );
$page( 'Beta: smart search', $body( 'beta' ), $s_beta, 'draft' );

// Pages not in the docs, sharing keywords, so search scoping can be checked.
foreach ( array( 'About us', 'Pricing', 'Contact', 'Careers', 'Privacy policy' ) as $t ) {
	$page( $t, '<p>Magpie keeps every photo and video safe at home, with shared albums, automatic backup and Docker installs.</p>' );
}
for ( $i = 1; $i <= 10; $i++ ) {
	$page( "Blog: photo tips {$i}", $para( 3 ) . '<p>Shared album and backup tips, plus running Magpie in Docker.</p>', array(), 'publish', 'post' );
}

// Real wording on the pages people search for most, so search answers read well in demos.
$real = array(
	'Installing with Docker' => '<p>Docker is the quickest way to run Magpie. One container holds the web app, the photo processor and the database, and your photos stay in a folder you choose on the host.</p>
<h2>Before you start</h2>
<p>You need Docker Engine 24 or newer and at least 4 GB of free memory. Check your version with <code>docker --version</code>.</p>
<h2>Start the container</h2>
<p>Run this command, replacing /srv/photos with the folder that holds your photos:</p>
<pre><code>docker run -d --name magpie -p 8420:8420 -v /srv/photos:/photos magpie/server:latest</code></pre>
<p>Open http://your-server:8420 in a browser and create the first administrator account. The first scan of a large library can take a few hours.</p>
<h2>Keep Magpie up to date</h2>
<p>Pull the new image and start the container again. Your library, albums and settings are kept because they live in the mounted folder.</p>
<pre><code>docker pull magpie/server:latest
docker restart magpie</code></pre>
<h2>Common problems</h2>
<p>If the page does not load, make sure port 8420 is free and allowed through the firewall. If photos are missing, check that the folder is mounted at /photos inside the container.</p>',
	'Shared albums' => '<p>A shared album lets family and friends see, comment on and add photos to an album you choose, using their own Magpie account.</p>
<h2>Share an album</h2>
<p>Open the album, press Share and type the names or email addresses of the people to invite. Each person gets an email with a link. They can see the album as soon as they sign in.</p>
<h2>Choose what people can do</h2>
<p>Viewers can see photos and leave comments. Contributors can also add their own photos to the album. You can change someone from viewer to contributor at any time under Share, People.</p>
<h2>Stop sharing</h2>
<p>Under Share, People, press Remove next to a name. The album disappears from their library straight away. Photos they added stay in the album.</p>
<h2>Sharing with people who have no account</h2>
<p>To share with someone who does not use Magpie, create a public link instead. Public links work in any browser and can have an expiry date and a password.</p>',
	'Public links' => '<p>A public link shares an album or a single photo with anyone who has the link, without a Magpie account.</p>
<h2>Create a public link</h2>
<p>Open the album or photo, press Share and choose Create public link. Copy the link and send it however you like.</p>
<h2>Protect a link</h2>
<p>Add a password, an expiry date, or both. People who open an expired link see a message instead of your photos. You can turn off downloads so photos can only be viewed.</p>
<h2>Turn a link off</h2>
<p>Under Share, Public links, press Delete. Anyone who opens the old link sees that it no longer exists.</p>',
	'Backups and retention' => '<p>Magpie can back up your whole library every night: originals, albums, faces, comments and settings. Restoring from a backup puts everything back exactly as it was.</p>
<h2>Turn on nightly backups</h2>
<p>Go to Settings, Storage, Backups and choose a backup folder on another disk or a network share. Backups run at 2am by default; change the time to suit you.</p>
<h2>How long backups are kept</h2>
<p>Magpie keeps the last 7 nightly backups and one backup from each of the last 4 weeks. Older ones are deleted automatically to save space.</p>
<h2>Restore a backup</h2>
<p>Stop Magpie, then run the restore command with the date of the backup you want. Restoring replaces the current library, so take a fresh backup first if you are unsure.</p>
<pre><code>magpie-cli backup restore 2026-10-01</code></pre>
<h2>Keep a copy away from home</h2>
<p>A backup on the same server will not survive a fire or theft. Copy the backup folder to cloud storage or a drive you keep somewhere else.</p>',
	'System requirements' => '<p>Magpie runs on most home servers and network storage devices. These are the minimum and recommended specifications for a library of up to 100,000 photos.</p>
<h2>Hardware</h2>
<p>Minimum: 2 CPU cores and 4 GB of memory. Recommended: 4 cores and 8 GB, which makes face detection and video previews much faster.</p>
<h2>Storage</h2>
<p>Allow the size of your photos plus about 15 per cent for previews and thumbnails. SSD storage for the database makes browsing noticeably quicker.</p>
<h2>Operating systems</h2>
<p>Any 64-bit Linux that runs Docker, plus Ubuntu 22.04 or newer and Debian 12 for native installs. Windows and macOS can run Magpie through Docker Desktop.</p>
<h2>Browsers and apps</h2>
<p>The web app works in current versions of Chrome, Firefox, Safari and Edge. The phone apps need iOS 16 or Android 11 or newer.</p>',
	'User accounts' => '<p>Each person in your household can have their own Magpie account with a private library, their own albums and their own upload settings.</p>
<h2>Add a person</h2>
<p>Go to Settings, People and press Add person. Enter their name and email address. They receive an invitation to choose a password.</p>
<h2>Reset a password</h2>
<p>If someone forgets their password, they can press Forgot password on the sign-in page. As an administrator you can also go to Settings, People, choose the person and press Reset password.</p>
<h2>Reset the admin password</h2>
<p>If you are locked out of the only administrator account, run this on the server and follow the prompts:</p>
<pre><code>magpie-cli users reset-password admin</code></pre>
<h2>Storage quotas</h2>
<p>Set a quota for each person under Settings, People, Quota so one large library cannot fill the disk.</p>',
);
foreach ( $real as $t => $html ) {
	if ( ! empty( $ids[ $t ] ) ) {
		wp_update_post( array( 'ID' => $ids[ $t ], 'post_content' => $html ) );
	}
}

// Documents from the Media Library, read for search (bin/seed-documents).
Waypost_Settings::update( array( 'documents' => 1, 'documents_menu' => 1 ) );
Waypost_Documents::register();
$s_files = $section( 'Downloads' );
$doc     = function ( $file, $title, $terms, $description = '' ) use ( $tax, &$created ) {
	$path = dirname( __FILE__ ) . '/seed-documents/' . $file;
	$up   = wp_upload_bits( $file, null, file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( ! empty( $up['error'] ) ) {
		WP_CLI::warning( "Could not upload {$file}: " . $up['error'] );
		return 0;
	}
	$type = wp_check_filetype( $up['file'] );
	$id   = wp_insert_attachment(
		array(
			'post_title'     => $title,
			'post_content'   => $description,
			'post_mime_type' => $type['type'],
			'post_status'    => 'inherit',
		),
		$up['file']
	);
	update_post_meta( $id, WAYPOST_SEED_META, 1 );
	wp_set_object_terms( $id, array_map( 'intval', (array) $terms ), $tax ); // Reads the text straight away.
	$created++;
	return $id;
};
$ids['Quick start card (PDF)']   = $doc( 'magpie-quick-start-card.pdf', 'Magpie quick start card', $s_files );
$ids['Keyboard shortcuts (TXT)'] = $doc( 'magpie-keyboard-shortcuts.txt', 'Keyboard shortcuts', $s_files );
$ids['Warranty card (scan)']     = $doc( 'scanned-warranty-card.pdf', 'Scanned warranty card', $s_files, 'Paper warranty card for the Magpie Home Box, scanned.' );
$ids['Hardware list (DOCX)']     = $doc( 'magpie-hardware-compatibility.docx', 'Hardware compatibility list', array( $s_install, $s_files ) );

// ── 4. Section links (clickable headings) ───────────────────────────────────

update_term_meta( $s_intro, Waypost_Tree::LINK_META, $ids['Welcome to Magpie'] );
update_term_meta( $s_albums, Waypost_Tree::LINK_META, $ids['Albums overview'] );

// ── 5. Custom top-level order ───────────────────────────────────────────────

$order = array();
foreach ( array( $s_intro, $s_install, $s_config, $s_import, $s_sharing, $s_integr, $s_guides, $s_api, $s_trouble, $s_faq, $s_files, $s_vi ) as $pos => $tid ) {
	$order[ 'term_' . $tid ] = $pos;
}
update_option( 'waypost_order', $order, false );

// ── 6. Special pages and settings ───────────────────────────────────────────

$home = $page(
	'Documentation',
	'<!-- wp:waypost/search-home /-->' // The docs layout shows the menu in its sidebar.
);
$results = $page( 'Search results', "<!-- wp:shortcode -->\n[waypost_search_results]\n<!-- /wp:shortcode -->" );
$page(
	'Blocks demo',
	"<!-- wp:heading --><h2>Docs Search block</h2><!-- /wp:heading -->\n<!-- wp:waypost/search {\"placeholder\":\"Try ‘album’ or ‘docker’\"} /-->\n<!-- wp:heading --><h2>Docs Menu block</h2><!-- /wp:heading -->\n<!-- wp:waypost/menu /-->"
);

Waypost_Settings::update(
	array(
		'setup_done'    => 1,
		'results_page'  => $results,
		'home_page'     => $home,
		'toc_auto'      => 1,
		'prevnext_auto' => 1,
		'crumbs_auto'   => 1,
		'style'         => 'theme',
		'accent'        => '#2563eb',
		'results_target' => 'home',
		'layout'        => 'waypost',
		'sidebar'       => 'left',
		'width'         => 'contained',
		'max_width'     => 1280,
		'toc_position'  => 'side',
		'home_title'    => 'Search the Magpie docs',
		'home_intro'    => 'Everything you need to install, configure and run Magpie.',
		'topics_title'  => 'Popular topics',
		'quick_searches' => array(
			array( 'label' => 'How do I install Magpie?', 'query' => 'install' ),
			array( 'label' => 'How do I share an album?', 'query' => 'shared albums' ),
			array( 'label' => 'Import from my phone', 'query' => 'import phone' ),
			array( 'label' => 'Back up my library', 'query' => 'backup' ),
			array( 'label' => 'Docker', 'query' => 'docker' ),
		),
		'topics'        => array(
			array( 'icon' => 'rocket', 'title' => 'Get started', 'text' => 'What Magpie is and how it works', 'page' => $ids['Welcome to Magpie'] ),
			array( 'icon' => 'download', 'title' => 'Install guide', 'text' => 'Requirements and step by step setup', 'page' => $ids['System requirements'] ),
			array( 'icon' => 'users', 'title' => 'Sharing', 'text' => 'Albums, links and family accounts', 'page' => $ids['Albums overview'] ),
			array( 'icon' => 'plug', 'title' => 'Integrations', 'text' => 'Lightroom, cloud storage and more', 'page' => $ids['Lightroom integration'] ),
			array( 'icon' => 'code', 'title' => 'API reference', 'text' => 'Automate Magpie with the REST API', 'page' => $ids['API authentication'] ),
			array( 'icon' => 'help', 'title' => 'Troubleshooting', 'text' => 'Fixes for common problems', 'page' => $ids['Uploads are stuck'] ),
		),
	)
);

// ── 7. Search insights sample data ──────────────────────────────────────────

update_option(
	'waypost_search_log',
	array(
		'album'                => array( 'count' => 42, 'hits' => 9, 'first' => time() - 9 * DAY_IN_SECONDS, 'last' => time() - 3600 ),
		'docker'               => array( 'count' => 31, 'hits' => 4, 'first' => time() - 8 * DAY_IN_SECONDS, 'last' => time() - 7200 ),
		'backup'               => array( 'count' => 18, 'hits' => 12, 'first' => time() - 7 * DAY_IN_SECONDS, 'last' => time() - 86400 ),
		'heic'                 => array( 'count' => 14, 'hits' => 0, 'first' => time() - 6 * DAY_IN_SECONDS, 'last' => time() - 5400 ),
		'live photos'          => array( 'count' => 9, 'hits' => 0, 'first' => time() - 5 * DAY_IN_SECONDS, 'last' => time() - 2 * DAY_IN_SECONDS ),
		'reset admin password' => array( 'count' => 7, 'hits' => 0, 'first' => time() - 4 * DAY_IN_SECONDS, 'last' => time() - 3 * DAY_IN_SECONDS ),
		'lightroom'            => array( 'count' => 6, 'hits' => 1, 'first' => time() - 3 * DAY_IN_SECONDS, 'last' => time() - 900 ),
	),
	false
);

// ── 8. Theme, sidebar and an editor account ─────────────────────────────────

if ( $want_divi ) {
	if ( wp_get_theme( 'Divi' )->exists() ) {
		switch_theme( 'Divi' );
		$divi = $page(
			'Divi layout test',
			'[et_pb_section fb_built="1"][et_pb_row][et_pb_column type="4_4"][et_pb_text]<p>This page is built with Divi modules.</p><h2>Divi heading one</h2><p>Text inside a Divi text module about shared albums.</p><h2>Divi heading two</h2><p>More text.</p>[/et_pb_text][/et_pb_column][/et_pb_row][/et_pb_section]',
			$s_guides
		);
		update_post_meta( $divi, '_et_pb_use_builder', 'on' );
		WP_CLI::log( 'Divi activated. Build a Theme Builder template with the Waypost modules to finish (see README).' );
	} else {
		WP_CLI::warning( 'Divi is not installed. Unzip it into themes/Divi and add .wp-env.override.json (see README).' );
	}
} elseif ( wp_get_theme( 'twentysixteen' )->exists() ) {
	switch_theme( 'twentysixteen' );
	$sidebars = get_option( 'sidebars_widgets', array() );
	$text     = get_option( 'widget_text', array() );
	$text     = is_array( $text ) ? $text : array();
	$text[90] = array( 'title' => '', 'text' => '[waypost_search button="no"][waypost_menu]', 'filter' => false, 'visual' => false );
	$text['_multiwidget'] = 1;
	update_option( 'widget_text', $text );
	$sidebars['sidebar-1'] = array( 'text-90' );
	update_option( 'sidebars_widgets', $sidebars );
}

if ( ! get_user_by( 'login', 'editor' ) ) {
	wp_insert_user( array( 'user_login' => 'editor', 'user_pass' => 'password', 'role' => 'editor', 'user_email' => 'editor@example.com' ) );
}

update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules();
Waypost_Tree::flush();

$docs = count( Waypost_Tree::flat() );
WP_CLI::success( "Created {$created} items. {$docs} pages are in the docs menu. Visit /documentation/ to start." );
