<?php
/**
 * Waypost dummy content.
 *
 * Builds a realistic documentation site for a fictional network monitoring
 * product ("Acme Monitor"): 170+ pages in sections up to four levels deep,
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
		'meta_key'       => WAYPOST_SEED_META, // phpcs:ignore WordPress.DB.SlowDBQuery
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
delete_transient( 'waypost_tree' );
Waypost_Settings::update( array( 'taxonomy_mode' => 'own', 'post_types' => array( 'page' ) ) );

// ── 2. Text helpers ─────────────────────────────────────────────────────────

$words = explode( ' ', 'traffic threshold collector interface router switch packet flow sampling attack mitigation blackhole announcement prefix community neighbour session latency bandwidth detection baseline alert notification hostgroup subnet bitrate packets bytes counter daemon service configuration option value default restart reload log entry dashboard metric export retention storage cluster node replica failover upstream peer transit scrubbing filter rule action policy schedule report timezone certificate token endpoint request response header payload limit window burst average peak' );

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
	"sudo apt-get update\nsudo apt-get install acme-monitor",
	"acme-cli set main traffic_threshold 1000\nacme-cli commit",
	"curl -u admin:secret https://localhost:10007/hosts | jq .",
	"docker run -d --name acme -p 2055:2055/udp acme/monitor:latest",
	"neighbor 192.0.2.1 {\n  local-as 65001;\n  peer-as 65000;\n}",
	"systemctl restart acme-monitor\njournalctl -u acme-monitor -f",
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
foreach ( array( 'Welcome to Acme Monitor', 'How Acme Monitor works', 'Key concepts' ) as $t ) {
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
foreach ( array( 'Upgrading Acme Monitor', 'Uninstalling Acme Monitor', 'System requirements' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_install );
}

$s_config = $section( 'Configuration' );
foreach ( array( 'Main configuration file', 'Traffic thresholds', 'Hostgroups', 'Licence management', 'Time zones and clocks', 'Data retention' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_config );
}
$s_notify = $section( 'Notifications', $s_config );
foreach ( array( 'Email notifications', 'Slack notifications', 'Telegram notifications', 'Microsoft Teams notifications', 'Webhook notifications', 'PagerDuty notifications' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_notify );
}
$ids['Internal: support playbook'] = $page( 'Internal: support playbook', $body( 'x' ), $s_config, 'private' ); // Must not appear.

$s_capture = $section( 'Capture engines' );
foreach ( array( 'Netflow v5 and v9', 'IPFIX', 'sFlow v5', 'Port mirror (SPAN)', 'AF_XDP', 'PCAP replay', 'Mirror on VMware' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_capture );
}

$s_mitig = $section( 'Mitigation' );
$s_bgp   = $section( 'BGP', $s_mitig );
foreach ( array( 'BGP overview', 'BGP blackhole', 'BGP Flow Spec', 'BGP communities', 'GoBGP integration', 'ExaBGP integration' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_bgp );
}
$s_filter = $section( 'Filtering', $s_mitig );
foreach ( array( 'ACL filtering', 'Scrubbing centre integration', 'Rate limiting at the edge' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_filter );
}

$s_integr = $section( 'Integrations' );
foreach ( array( 'Grafana', 'Prometheus', 'InfluxDB', 'ClickHouse', 'Zabbix', 'Splunk', 'Elasticsearch', 'Graylog', 'Datadog', 'New Relic', 'OpenSearch', 'Loki' ) as $t ) {
	$ids[ "{$t} integration" ] = $page( "{$t} integration", $body( $t ), $s_integr );
}

$s_api = $section( 'API reference' );
$ids['API authentication'] = $page( 'API authentication', $body( 'auth' ), $s_api );
foreach ( array( 'hosts', 'hostgroups', 'attacks', 'blackholes', 'flowspec', 'statistics', 'interfaces', 'users', 'licence', 'settings', 'notifications', 'reports', 'audit log', 'sessions', 'peers' ) as $res ) {
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
$verbs    = array( 'Detect', 'Block', 'Monitor', 'Report on', 'Tune alerts for', 'Automate' );
$objects  = array( 'UDP floods', 'SYN floods', 'DNS amplification', 'customer subnets', 'a new data centre', 'peering links' );
foreach ( $verbs as $v ) {
	foreach ( array_slice( $objects, 0, 4 ) as $o ) {
		$t        = "How to " . strtolower( $v ) . " {$o}";
		$ids[ $t ] = $page( $t, $body( $t ), $s_guides );
	}
}

$s_trouble = $section( 'Troubleshooting' );
foreach ( array( 'Collector receives no traffic', 'High CPU usage', 'Attack was not detected', 'BGP session is down', 'Log files explained', 'Debug mode', 'Email alerts not arriving' ) as $t ) {
	$ids[ $t ] = $page( $t, $body( $t ), $s_trouble );
}

$s_faq = $section( 'FAQ' );
$ids['Frequently asked questions'] = $page( 'Frequently asked questions', $body( 'faq' ), $s_faq );
$ids['Licensing FAQ']             = $page( 'Licensing FAQ', $body( 'lic' ), $s_faq );

$s_vi = $section( 'Hướng dẫn tiếng Việt' );
foreach ( array( 'Cài đặt trên Ubuntu', 'Cấu hình cảnh báo lưu lượng', 'Câu hỏi thường gặp' ) as $t ) {
	$ids[ $t ] = $page( $t, '<p>Hướng dẫn này giải thích cách cài đặt và cấu hình. Lưu lượng mạng được theo dõi liên tục.</p><h2>Yêu cầu hệ thống</h2><p>Máy chủ cần 4 GB RAM.</p><h2>Các bước cài đặt</h2><p>Làm theo từng bước.</p><h2>Kiểm tra kết quả</h2><p>Mở bảng điều khiển.</p>', $s_vi );
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

// Very long title (tests menu wrapping and pager).
$ids['long'] = $page( 'Configuring redundant collectors across multiple data centres with automatic failover and shared state', $body( 'long' ), $s_config );

// One page in two sections (should appear in both; previous/next uses the first place).
$ids['Installing with Docker (shared)'] = $ids['Installing with Docker'];
wp_set_object_terms( $ids['Installing with Docker'], array( $s_containers, $s_guides ), $tax );

// Empty section: hidden on the site, greyed in admin.
$section( 'Coming soon' );

// Section whose only page is a draft: also hidden.
$s_beta = $section( 'Beta features' );
$page( 'Beta: anomaly detection', $body( 'beta' ), $s_beta, 'draft' );

// Pages not in the docs, sharing keywords, so search scoping can be checked.
foreach ( array( 'About us', 'Pricing', 'Contact', 'Careers', 'Privacy policy' ) as $t ) {
	$page( $t, '<p>Acme Monitor protects networks from traffic attacks with fast BGP mitigation and threshold alerts.</p>' );
}
for ( $i = 1; $i <= 10; $i++ ) {
	$page( "Blog: network security update {$i}", $para( 3 ) . '<p>Traffic thresholds and BGP blackhole tips.</p>', array(), 'publish', 'post' );
}

// ── 4. Section links (clickable headings) ───────────────────────────────────

update_term_meta( $s_intro, Waypost_Tree::LINK_META, $ids['Welcome to Acme Monitor'] );
update_term_meta( $s_bgp, Waypost_Tree::LINK_META, $ids['BGP overview'] );

// ── 5. Custom top-level order ───────────────────────────────────────────────

$order = array();
foreach ( array( $s_intro, $s_install, $s_config, $s_capture, $s_mitig, $s_integr, $s_guides, $s_api, $s_trouble, $s_faq, $s_vi ) as $pos => $tid ) {
	$order[ 'term_' . $tid ] = $pos;
}
update_option( 'waypost_order', $order, false );

// ── 6. Special pages and settings ───────────────────────────────────────────

$home = $page(
	'Documentation',
	"<!-- wp:paragraph --><p>Everything you need to install, configure and run Acme Monitor.</p><!-- /wp:paragraph -->\n<!-- wp:waypost/search /-->\n<!-- wp:waypost/menu {\"title\":\"All documentation\"} /-->"
);
$results = $page( 'Search results', "<!-- wp:shortcode -->\n[waypost_search_results]\n<!-- /wp:shortcode -->" );
$page(
	'Blocks demo',
	"<!-- wp:heading --><h2>Docs Search block</h2><!-- /wp:heading -->\n<!-- wp:waypost/search {\"placeholder\":\"Try ‘bgp’ or ‘docker’\"} /-->\n<!-- wp:heading --><h2>Docs Menu block</h2><!-- /wp:heading -->\n<!-- wp:waypost/menu /-->"
);

Waypost_Settings::update(
	array(
		'setup_done'    => 1,
		'results_page'  => $results,
		'home_page'     => $home,
		'toc_auto'      => 1,
		'prevnext_auto' => 1,
		'crumbs_auto'   => 1,
	)
);

// ── 7. Search insights sample data ──────────────────────────────────────────

update_option(
	'waypost_search_log',
	array(
		'bgp'                 => array( 'count' => 42, 'hits' => 9, 'first' => time() - 9 * DAY_IN_SECONDS, 'last' => time() - 3600 ),
		'docker'              => array( 'count' => 31, 'hits' => 4, 'first' => time() - 8 * DAY_IN_SECONDS, 'last' => time() - 7200 ),
		'threshold'           => array( 'count' => 18, 'hits' => 12, 'first' => time() - 7 * DAY_IN_SECONDS, 'last' => time() - 86400 ),
		'ipv6'                => array( 'count' => 14, 'hits' => 0, 'first' => time() - 6 * DAY_IN_SECONDS, 'last' => time() - 5400 ),
		'terraform'           => array( 'count' => 9, 'hits' => 0, 'first' => time() - 5 * DAY_IN_SECONDS, 'last' => time() - 2 * DAY_IN_SECONDS ),
		'reset admin password' => array( 'count' => 7, 'hits' => 0, 'first' => time() - 4 * DAY_IN_SECONDS, 'last' => time() - 3 * DAY_IN_SECONDS ),
		'grafana'             => array( 'count' => 6, 'hits' => 1, 'first' => time() - 3 * DAY_IN_SECONDS, 'last' => time() - 900 ),
	),
	false
);

// ── 8. Theme, sidebar and an editor account ─────────────────────────────────

if ( $want_divi ) {
	if ( wp_get_theme( 'Divi' )->exists() ) {
		switch_theme( 'Divi' );
		$divi = $page(
			'Divi layout test',
			'[et_pb_section fb_built="1"][et_pb_row][et_pb_column type="4_4"][et_pb_text]<p>This page is built with Divi modules.</p><h2>Divi heading one</h2><p>Text inside a Divi text module about traffic thresholds.</p><h2>Divi heading two</h2><p>More text.</p>[/et_pb_text][/et_pb_column][/et_pb_row][/et_pb_section]',
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
