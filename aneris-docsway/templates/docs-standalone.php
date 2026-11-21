<?php
/**
 * Page template for the docs layout with the standalone Docsway docs bar and
 * footer instead of the theme's header and footer (classic and block themes).
 * Everything renders before wp_head() so blocks in the content can queue styles.
 */

defined( 'ABSPATH' ) || exit;

$docsway_main   = Docsway_Layout::render();
$docsway_top    = Docsway_Render::topbar( Docsway_Layout::page_kind() );
$docsway_footer = Docsway_Render::docs_footer();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<?php if ( ! wp_is_block_theme() ) : // Block themes add their own. ?>
		<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php endif; ?>
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
echo wp_kses( $docsway_top, Docsway_Render::allowed_html() );
echo wp_kses( $docsway_main, Docsway_Render::content_allowed_html() ); // Holds the page content.
echo wp_kses( $docsway_footer, Docsway_Render::allowed_html() );
wp_footer();
?>
</body>
</html>
