<?php
/**
 * Applies settings for one screenshot (used by bin/screenshots.js).
 *
 * Run: wp eval-file bin/screenshot-settings.php <base64 JSON>
 * Keys are Docsway settings, plus "blogname" for the site title.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run this with WP-CLI.\n" );
}
$docsway_shot = isset( $args[0] ) ? json_decode( base64_decode( $args[0] ), true ) : array(); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
if ( ! is_array( $docsway_shot ) ) {
	WP_CLI::error( 'Could not read the settings.' );
}
if ( isset( $docsway_shot['blogname'] ) ) {
	update_option( 'blogname', $docsway_shot['blogname'] );
	unset( $docsway_shot['blogname'] );
}
if ( $docsway_shot ) {
	Docsway_Settings::update( $docsway_shot );
}
WP_CLI::success( 'Settings applied.' );
