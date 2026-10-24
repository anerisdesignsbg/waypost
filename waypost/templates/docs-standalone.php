<?php
/**
 * Page template for the docs layout with the standalone Waypost docs bar and
 * footer instead of the theme's header and footer (classic and block themes).
 * Everything renders before wp_head() so blocks in the content can queue styles.
 */

defined( 'ABSPATH' ) || exit;

$waypost_main   = Waypost_Layout::render();
$waypost_top    = Waypost_Render::topbar( Waypost_Layout::page_kind() );
$waypost_footer = Waypost_Render::docs_footer();
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
echo $waypost_top; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
echo $waypost_main; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
echo $waypost_footer; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
wp_footer();
?>
</body>
</html>
