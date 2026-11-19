<?php
/**
 * Page template for the Docsway docs layout (classic themes). The theme keeps
 * its header and footer; Docsway_Render::layout() draws everything between.
 */

defined( 'ABSPATH' ) || exit;

get_header();
echo Docsway_Layout::render(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
get_footer();
