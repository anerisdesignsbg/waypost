<?php
/**
 * Applies settings for one screenshot (used by bin/screenshots.js).
 *
 * Run: wp eval-file bin/screenshot-settings.php <base64 JSON>
 * Keys are Waypost settings, plus "blogname" for the site title.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run this with WP-CLI.\n" );
}
$waypost_shot = isset( $args[0] ) ? json_decode( base64_decode( $args[0] ), true ) : array(); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
if ( ! is_array( $waypost_shot ) ) {
	WP_CLI::error( 'Could not read the settings.' );
}
if ( isset( $waypost_shot['blogname'] ) ) {
	update_option( 'blogname', $waypost_shot['blogname'] );
	unset( $waypost_shot['blogname'] );
}
if ( $waypost_shot ) {
	Waypost_Settings::update( $waypost_shot );
}
WP_CLI::success( 'Settings applied.' );
