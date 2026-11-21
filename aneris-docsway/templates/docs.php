<?php
/**
 * Page template for the Docsway docs layout (classic themes). The theme keeps
 * its header and footer; Docsway_Render::layout() draws everything between.
 */

defined( 'ABSPATH' ) || exit;

get_header();
// The layout holds the page content, so it is escaped with the post content list plus Docsway's own markup.
echo wp_kses( Docsway_Layout::render(), Docsway_Render::content_allowed_html() );
get_footer();
