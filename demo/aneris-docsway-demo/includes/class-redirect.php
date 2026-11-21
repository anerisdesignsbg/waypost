<?php
/**
 * Keeps visitors on the docs: any front-end address that is not a doc page, the
 * docs home or the results page (404s, blog and category archives, author pages,
 * attachment pages, feeds, WordPress search) is sent to one address, the docs home
 * unless another is set under Tools, Docsway demo.
 */

defined( 'ABSPATH' ) || exit;

class Docsway_Demo_Redirect {

	const OPTION = 'docsway_demo_redirect';

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'maybe_redirect' ), 1 );
		add_action( 'admin_post_docsway_demo_redirect', array( __CLASS__, 'save' ) );
	}

	/** Where other addresses go: the saved address, or the docs home. */
	public static function target() {
		$url = (string) get_option( self::OPTION, '' );
		return '' !== $url ? $url : home_url( '/' );
	}

	public static function maybe_redirect() {
		if ( is_robots() || is_favicon() || is_preview() || is_customize_preview() || get_query_var( 'sitemap' ) || ! class_exists( 'Docsway_Layout' ) ) {
			return;
		}
		if ( '' !== Docsway_Layout::page_kind() ) {
			return; // A doc page, the docs home or the results page.
		}
		$target = self::target();
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$here   = ( is_ssl() ? 'https://' : 'http://' ) . $host . $uri;
		if ( untrailingslashit( $target ) === untrailingslashit( $here ) ) {
			return; // Never send a page to itself (when the address set is not a doc page).
		}
		// Temporary, so browsers do not remember it if the address changes later.
		wp_redirect( esc_url_raw( $target ), 302, 'Aneris Docsway demo' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the address may be the main site's product page.
		exit;
	}

	public static function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'aneris-docsway-demo' ) );
		}
		check_admin_referer( 'docsway_demo_redirect' );
		$url = isset( $_POST['redirect'] ) ? esc_url_raw( trim( wp_unslash( $_POST['redirect'] ) ), array( 'http', 'https' ) ) : '';
		update_option( self::OPTION, $url, false );
		wp_safe_redirect( add_query_arg( 'redirect-saved', 1, admin_url( 'tools.php?page=docsway-demo' ) ) );
		exit;
	}

	/** The setting on the Tools, Docsway demo screen. */
	public static function form() {
		?>
		<h2><?php esc_html_e( 'Other addresses', 'aneris-docsway-demo' ); ?></h2>
		<?php if ( isset( $_GET['redirect-saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification -- display only. ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Saved.', 'aneris-docsway-demo' ); ?></p></div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Visitors who open any address that is not a doc page (an old link, a mistyped address, a blog or author page) are sent here. Leave it empty for the docs home, or enter the product page on your main site.', 'aneris-docsway-demo' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="docsway_demo_redirect">
			<?php wp_nonce_field( 'docsway_demo_redirect' ); ?>
			<p>
				<label for="docsway-demo-redirect"><?php esc_html_e( 'Send other addresses to', 'aneris-docsway-demo' ); ?></label><br>
				<input type="url" class="regular-text" id="docsway-demo-redirect" name="redirect" value="<?php echo esc_attr( (string) get_option( self::OPTION, '' ) ); ?>" placeholder="<?php echo esc_attr( home_url( '/' ) ); ?>">
				<button type="submit" class="button"><?php esc_html_e( 'Save', 'aneris-docsway-demo' ); ?></button>
			</p>
		</form>
		<?php
	}
}
