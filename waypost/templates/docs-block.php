<?php
/**
 * Page template for the Waypost docs layout in block themes. Like WordPress's
 * own template-canvas.php, everything is rendered before wp_head() so blocks in
 * the header, footer and content can queue their styles in time.
 */

defined( 'ABSPATH' ) || exit;

$waypost_main = Waypost_Layout::render();
list( $waypost_header, $waypost_footer ) = Waypost_Layout::block_chrome();
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
echo $waypost_header; // phpcs:ignore WordPress.Security.EscapeOutput -- rendered blocks.
echo $waypost_main; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
echo $waypost_footer; // phpcs:ignore WordPress.Security.EscapeOutput -- rendered blocks.
?>
</div>

<?php wp_footer(); ?>
</body>
</html>
