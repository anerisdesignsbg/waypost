<?php
/**
 * Licensing and updates through Freemius (SDK bundled in freemius/).
 *
 * The product ID and public key come from the Freemius dashboard (Waypost Pro,
 * Settings, Keys); both are public by design. Never put the secret key here.
 * Defining either constant as '' (as .wp-env.json does for the local sites)
 * switches licensing off, so Pro runs without a licence.
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WAYPOST_PRO_FS_ID' ) ) {
	define( 'WAYPOST_PRO_FS_ID', '44694' );
}
if ( ! defined( 'WAYPOST_PRO_FS_PUBLIC_KEY' ) ) {
	define( 'WAYPOST_PRO_FS_PUBLIC_KEY', 'pk_362ee06213c60c9b26fc1d136f782' );
}

/** Is Freemius set up for this copy? */
function waypost_pro_licensing_on() {
	return '' !== WAYPOST_PRO_FS_ID && '' !== WAYPOST_PRO_FS_PUBLIC_KEY;
}

/** The Freemius SDK object for Waypost Pro. */
function waypost_pro_fs() {
	global $waypost_pro_fs;
	if ( ! isset( $waypost_pro_fs ) ) {
		require_once WAYPOST_PRO_DIR . 'freemius/start.php';
		$waypost_pro_fs = fs_dynamic_init(
			array(
				'id'               => WAYPOST_PRO_FS_ID,
				'slug'             => 'waypost-pro',
				'type'             => 'plugin',
				'public_key'       => WAYPOST_PRO_FS_PUBLIC_KEY,
				'is_premium'       => true,
				'is_premium_only'  => true,
				'has_paid_plans'   => true,
				'has_addons'       => false,
				'is_org_compliant' => false, // Sold on our own site, never on WordPress.org.
				// Freemius uses Pro's own page (licence activation, then Account) under the Waypost menu,
				// so the free plugin's screens stay reachable before a licence is entered.
				'menu'             => array(
					'slug'    => 'waypost-pro',
					'parent'  => array( 'slug' => 'waypost' ),
					'support' => false,
				),
			)
		);
	}
	return $waypost_pro_fs;
}

/** Pro features run with an active licence or trial, or always while licensing is not set up. */
function waypost_pro_can_run() {
	return ! waypost_pro_licensing_on() || waypost_pro_fs()->can_use_premium_code();
}

/** The Waypost, Waypost Pro page: what Pro adds, with links to the licence and account. */
function waypost_pro_menu() {
	add_submenu_page( 'waypost', __( 'Waypost Pro', 'waypost-pro' ), __( 'Waypost Pro', 'waypost-pro' ), 'manage_options', 'waypost-pro', 'waypost_pro_page' );
}

function waypost_pro_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Waypost Pro', 'waypost-pro' ); ?></h1>
		<p><?php esc_html_e( 'Your licence is active. Waypost Pro adds:', 'waypost-pro' ); ?></p>
		<ul class="ul-disc">
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=waypost-settings#documents' ) ); ?>"><?php esc_html_e( 'Search inside PDF, Word and other documents', 'waypost-pro' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=waypost-settings#search' ) ); ?>"><?php esc_html_e( 'Synonyms and topic filters', 'waypost-pro' ); ?></a></li>
		</ul>
		<p><a class="button" href="<?php echo esc_url( waypost_pro_fs()->get_account_url() ); ?>"><?php esc_html_e( 'Licence and account', 'waypost-pro' ); ?></a></p>
	</div>
	<?php
}

/** Removes Waypost Pro data when the plugin is deleted. Documents' plain-text copy goes with the free plugin. */
function waypost_pro_uninstall() {
	delete_metadata( 'post', 0, '_waypost_doc_status', '', true );
	wp_clear_scheduled_hook( 'waypost_read_documents' );
	delete_transient( 'waypost_tree' );
}

if ( waypost_pro_licensing_on() ) {
	// Freemius asks to start the SDK as soon as the plugin loads, and does not allow uninstall.php.
	waypost_pro_fs();
	waypost_pro_fs()->add_action( 'after_uninstall', 'waypost_pro_uninstall' );
	add_action( 'admin_menu', 'waypost_pro_menu', 20 );
	do_action( 'waypost_pro_fs_loaded' );
} else {
	register_uninstall_hook( WAYPOST_PRO_FILE, 'waypost_pro_uninstall' );
}
