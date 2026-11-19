<?php
/**
 * Licensing and updates through Freemius (SDK bundled in freemius/).
 *
 * The product ID and public key come from the Freemius dashboard (Docsway Pro,
 * Settings, Keys); both are public by design. Never put the secret key here.
 * Defining either constant as '' (as .wp-env.json does for the local sites)
 * switches licensing off, so Pro runs without a licence.
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'DOCSWAY_PRO_FS_ID' ) ) {
	define( 'DOCSWAY_PRO_FS_ID', '44694' );
}
if ( ! defined( 'DOCSWAY_PRO_FS_PUBLIC_KEY' ) ) {
	define( 'DOCSWAY_PRO_FS_PUBLIC_KEY', 'pk_362ee06213c60c9b26fc1d136f782' );
}

/** Is Freemius set up for this copy? */
function docsway_pro_licensing_on() {
	return '' !== DOCSWAY_PRO_FS_ID && '' !== DOCSWAY_PRO_FS_PUBLIC_KEY;
}

/** The Freemius SDK object for Docsway Pro. */
function docsway_pro_fs() {
	global $docsway_pro_fs;
	if ( ! isset( $docsway_pro_fs ) ) {
		require_once DOCSWAY_PRO_DIR . 'freemius/start.php';
		$docsway_pro_fs = fs_dynamic_init(
			array(
				'id'               => DOCSWAY_PRO_FS_ID,
				'slug'             => 'aneris-docsway-pro',
				'type'             => 'plugin',
				'public_key'       => DOCSWAY_PRO_FS_PUBLIC_KEY,
				'is_premium'       => true,
				'is_premium_only'  => true,
				'has_paid_plans'   => true,
				'has_addons'       => false,
				'is_org_compliant' => false, // Sold on our own site, never on WordPress.org.
				// Freemius uses Pro's own page (licence activation, then Account) under the Docsway menu,
				// so the free plugin's screens stay reachable before a licence is entered.
				'menu'             => array(
					'slug'    => 'docsway-pro',
					'parent'  => array( 'slug' => 'docsway' ),
					'support' => false,
				),
			)
		);
	}
	return $docsway_pro_fs;
}

/** Pro features run with an active licence or trial, or always while licensing is not set up. */
function docsway_pro_can_run() {
	return ! docsway_pro_licensing_on() || docsway_pro_fs()->can_use_premium_code();
}

/** The Docsway, Docsway Pro page: what Pro adds, with links to the licence and account. */
function docsway_pro_menu() {
	add_submenu_page( 'docsway', __( 'Docsway Pro', 'aneris-docsway-pro' ), __( 'Docsway Pro', 'aneris-docsway-pro' ), 'manage_options', 'docsway-pro', 'docsway_pro_page' );
}

function docsway_pro_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Docsway Pro', 'aneris-docsway-pro' ); ?></h1>
		<p><?php esc_html_e( 'Your licence is active. Docsway Pro adds:', 'aneris-docsway-pro' ); ?></p>
		<ul class="ul-disc">
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=docsway-settings#documents' ) ); ?>"><?php esc_html_e( 'Search inside PDF, Word and other documents', 'aneris-docsway-pro' ); ?></a></li>
			<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=docsway-settings#search' ) ); ?>"><?php esc_html_e( 'Synonyms and topic filters', 'aneris-docsway-pro' ); ?></a></li>
		</ul>
		<p><a class="button" href="<?php echo esc_url( docsway_pro_fs()->get_account_url() ); ?>"><?php esc_html_e( 'Licence and account', 'aneris-docsway-pro' ); ?></a></p>
	</div>
	<?php
}

/** Removes Docsway Pro data when the plugin is deleted. Documents' plain-text copy goes with the free plugin. */
function docsway_pro_uninstall() {
	delete_metadata( 'post', 0, '_docsway_doc_status', '', true );
	wp_clear_scheduled_hook( 'docsway_read_documents' );
	delete_transient( 'docsway_tree' );
}

if ( docsway_pro_licensing_on() ) {
	// Freemius asks to start the SDK as soon as the plugin loads, and does not allow uninstall.php.
	docsway_pro_fs();
	docsway_pro_fs()->add_action( 'after_uninstall', 'docsway_pro_uninstall' );
	add_action( 'admin_menu', 'docsway_pro_menu', 20 );
	do_action( 'docsway_pro_fs_loaded' );
} else {
	register_uninstall_hook( DOCSWAY_PRO_FILE, 'docsway_pro_uninstall' );
}
