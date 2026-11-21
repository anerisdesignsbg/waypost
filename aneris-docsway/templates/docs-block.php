<?php
/**
 * Page template for the Docsway docs layout in block themes. Like WordPress's
 * own template-canvas.php, everything is rendered before wp_head() so blocks in
 * the header, footer and content can queue their styles in time.
 */

defined( 'ABSPATH' ) || exit;

$docsway_main = Docsway_Layout::render();
list( $docsway_header, $docsway_footer ) = Docsway_Layout::block_chrome();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="wp-site-blocks">
<?php
// The theme's header and footer parts and the layout (which holds the page content)
// are escaped with the post content list plus Docsway's own markup.
echo wp_kses( $docsway_header, Docsway_Render::content_allowed_html() );
echo wp_kses( $docsway_main, Docsway_Render::content_allowed_html() );
echo wp_kses( $docsway_footer, Docsway_Render::content_allowed_html() );
?>
</div>

<?php wp_footer(); ?>
</body>
</html>
