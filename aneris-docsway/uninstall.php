<?php
/**
 * Removes Docsway data on uninstall, only if the site owner asked for it in Settings.
 * Pages are never deleted.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// The plain-text copy of each page (and document, with Docsway Pro) used by search is rebuilt on demand, so it always goes.
delete_metadata( 'post', 0, '_docsway_text', '', true );
delete_option( 'docsway_text_ready' );
delete_metadata( 'post', 0, '_docsway_passages', '', true );
delete_transient( 'docsway_vocab' );

$docsway_settings = get_option( 'docsway_settings', array() );

if ( empty( $docsway_settings['delete_on_uninstall'] ) ) {
	return;
}

// Remove the built-in sections taxonomy terms.
register_taxonomy( 'docsway_section', array(), array( 'hierarchical' => true ) );
$docsway_terms = get_terms( array( 'taxonomy' => 'docsway_section', 'hide_empty' => false, 'fields' => 'ids' ) );
if ( ! is_wp_error( $docsway_terms ) ) {
	foreach ( $docsway_terms as $docsway_term ) {
		wp_delete_term( $docsway_term, 'docsway_section' );
	}
}

// Section links may also exist on a third-party taxonomy.
delete_metadata( 'term', 0, 'docsway_linked_post', '', true );

// "Was this helpful?" counts.
delete_metadata( 'post', 0, '_docsway_helpful_yes', '', true );
delete_metadata( 'post', 0, '_docsway_helpful_no', '', true );

foreach ( array( 'docsway_settings', 'docsway_order', 'docsway_search_log' ) as $docsway_option ) {
	delete_option( $docsway_option );
}
delete_transient( 'docsway_tree' );
