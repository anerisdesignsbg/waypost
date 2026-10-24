<?php
/**
 * Removes Waypost data on uninstall, only if the site owner asked for it in Settings.
 * Pages are never deleted.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// The plain-text copy of each page used by search is rebuilt on demand, so it always goes.
delete_metadata( 'post', 0, '_waypost_text', '', true );
delete_option( 'waypost_text_ready' );

$waypost_settings = get_option( 'waypost_settings', array() );

if ( empty( $waypost_settings['delete_on_uninstall'] ) ) {
	return;
}

// Remove the built-in sections taxonomy terms.
register_taxonomy( 'waypost_section', array(), array( 'hierarchical' => true ) );
$waypost_terms = get_terms( array( 'taxonomy' => 'waypost_section', 'hide_empty' => false, 'fields' => 'ids' ) );
if ( ! is_wp_error( $waypost_terms ) ) {
	foreach ( $waypost_terms as $waypost_term ) {
		wp_delete_term( $waypost_term, 'waypost_section' );
	}
}

// Section links may also exist on a third-party taxonomy.
delete_metadata( 'term', 0, 'waypost_linked_post', '', true );

foreach ( array( 'waypost_settings', 'waypost_order', 'waypost_search_log' ) as $waypost_option ) {
	delete_option( $waypost_option );
}
delete_transient( 'waypost_tree' );
