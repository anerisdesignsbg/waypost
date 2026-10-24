<?php
/**
 * Page template for the Waypost docs layout (classic themes). The theme keeps
 * its header and footer; Waypost_Render::layout() draws everything between.
 */

defined( 'ABSPATH' ) || exit;

get_header();
echo Waypost_Layout::render(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
get_footer();
