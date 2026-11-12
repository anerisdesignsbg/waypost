<?php
/**
 * Plugin Name:       Waypost Demo Content
 * Description:       Builds the Waypost tutorials as a docs site: sections, pages, screenshots and settings. Run it from Tools, Waypost demo, then you can delete this plugin.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Requires Plugins:  waypost
 * Author:            Aneris Designs
 * Author URI:        https://anerisdesigns.co
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       waypost-demo-content
 */

defined( 'ABSPATH' ) || exit;

define( 'WAYPOST_DEMO_DIR', plugin_dir_path( __FILE__ ) );

require_once WAYPOST_DEMO_DIR . 'includes/class-markdown.php';
require_once WAYPOST_DEMO_DIR . 'includes/class-builder.php';

add_action( 'admin_menu', 'waypost_demo_menu' );
add_action( 'admin_post_waypost_demo_build', 'waypost_demo_handle' );

function waypost_demo_menu() {
	add_management_page( __( 'Waypost demo', 'waypost-demo-content' ), __( 'Waypost demo', 'waypost-demo-content' ), 'manage_options', 'waypost-demo', 'waypost_demo_page' );
}

function waypost_demo_page() {
	$result = get_transient( 'waypost_demo_result' );
	delete_transient( 'waypost_demo_result' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Waypost demo content', 'waypost-demo-content' ); ?></h1>
		<?php if ( is_array( $result ) && isset( $result['error'] ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $result['error'] ); ?></p></div>
		<?php elseif ( is_array( $result ) ) : ?>
			<div class="notice notice-success"><p>
				<?php
				echo esc_html( sprintf( /* translators: 1: sections, 2: pages, 3: images */ __( 'Done: %1$d sections, %2$d pages and %3$d images.', 'waypost-demo-content' ), $result['sections'], $result['pages'], $result['images'] ) );
				?>
				<a href="<?php echo esc_url( $result['home'] ); ?>"><?php esc_html_e( 'View the docs', 'waypost-demo-content' ); ?></a>
			</p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'This builds the Waypost tutorials as a documentation site on this WordPress: doc sections, one page per tutorial with its screenshots, a docs home page set as the front page, and the Waypost settings (Modern style with the docs bar).', 'waypost-demo-content' ); ?></p>
		<p><?php esc_html_e( 'It also sets the site title to "Waypost" and, the first time, moves WordPress\'s sample post and page to the bin. Running it again replaces the tutorial pages with fresh copies; nothing else is touched.', 'waypost-demo-content' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="waypost_demo_build">
			<?php wp_nonce_field( 'waypost_demo_build' ); ?>
			<p><button type="submit" class="button button-primary button-hero"><?php esc_html_e( 'Build the docs site', 'waypost-demo-content' ); ?></button></p>
		</form>
	</div>
	<?php
}

function waypost_demo_handle() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'waypost-demo-content' ) );
	}
	check_admin_referer( 'waypost_demo_build' );
	$result = Waypost_Demo_Builder::build();
	set_transient( 'waypost_demo_result', is_wp_error( $result ) ? array( 'error' => $result->get_error_message() ) : $result, 300 );
	wp_safe_redirect( admin_url( 'tools.php?page=waypost-demo' ) );
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command(
		'waypost-demo build',
		function () {
			$result = Waypost_Demo_Builder::build();
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}
			WP_CLI::success( sprintf( '%d sections, %d pages, %d images. %s', $result['sections'], $result['pages'], $result['images'], $result['home'] ) );
		}
	);
}
