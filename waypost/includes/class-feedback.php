<?php
/**
 * "Was this page helpful?" votes. Counts are kept per page in post meta.
 * No IP address or user detail is stored: a salted hash of the address and
 * page is kept for a day only to stop the same reader voting again.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Feedback {

	const YES = '_waypost_helpful_yes';
	const NO  = '_waypost_helpful_no';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
	}

	public static function routes() {
		register_rest_route(
			'waypost/v1',
			'/feedback',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'rest_vote' ),
				'permission_callback' => '__return_true', // Readers are usually logged out.
				'args'                => array(
					'post' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
					'vote' => array( 'type' => 'string', 'required' => true, 'enum' => array( 'yes', 'no' ) ),
				),
			)
		);
	}

	public static function rest_vote( WP_REST_Request $req ) {
		$post_id = (int) $req['post'];
		if ( ! Waypost_Settings::get( 'feedback' ) || 'publish' !== get_post_status( $post_id ) || ! Waypost_Tree::is_doc( $post_id ) ) {
			return new WP_Error( 'waypost_feedback', __( 'Feedback is not open for this page.', 'waypost' ), array( 'status' => 400 ) );
		}
		$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$lock = 'waypost_fb_' . md5( wp_salt( 'nonce' ) . '|' . $ip . '|' . $post_id );
		if ( get_transient( $lock ) ) {
			return new WP_Error( 'waypost_feedback_repeat', __( 'Thanks, your answer is already counted.', 'waypost' ), array( 'status' => 429 ) );
		}
		set_transient( $lock, 1, DAY_IN_SECONDS );

		$key = 'yes' === $req['vote'] ? self::YES : self::NO;
		update_post_meta( $post_id, $key, (int) get_post_meta( $post_id, $key, true ) + 1 );
		return rest_ensure_response( array( 'ok' => true ) );
	}

	/** Pages with votes, least helpful first: array of array( id, title, yes, no ). */
	public static function rows( $limit = 50 ) {
		global $wpdb;
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- small aggregate read for an admin screen.
			$wpdb->prepare( "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ( %s, %s )", self::YES, self::NO )
		);
		$rows = array();
		foreach ( $ids as $id ) {
			$rows[] = array(
				'id'    => (int) $id,
				'title' => get_the_title( $id ),
				'yes'   => (int) get_post_meta( $id, self::YES, true ),
				'no'    => (int) get_post_meta( $id, self::NO, true ),
			);
		}
		usort(
			$rows,
			function ( $a, $b ) {
				return $b['no'] === $a['no'] ? $a['yes'] - $b['yes'] : $b['no'] - $a['no'];
			}
		);
		return array_slice( $rows, 0, $limit );
	}

	/** Deletes every count and the one-day repeat-vote locks, so readers can answer again. */
	public static function reset() {
		global $wpdb;
		delete_metadata( 'post', 0, self::YES, '', true );
		delete_metadata( 'post', 0, self::NO, '', true );
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_waypost\\_fb\\_%' OR option_name LIKE '\\_transient\\_timeout\\_waypost\\_fb\\_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
