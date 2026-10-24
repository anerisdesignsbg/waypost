<?php
/**
 * Finds headings in content and gives them stable anchor IDs.
 * The same slug rules are used for the table of contents, the IDs added to
 * the page and the search deep links, so all three always agree.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Headings {

	const PATTERN = '#<h([2-4])(\s[^>]*)?>(.*?)</h\1>#is';

	/**
	 * @return array[] each [ level, text, id ]
	 */
	public static function extract( $html, array $levels = array( 2, 3, 4 ) ) {
		$out  = array();
		$used = array();
		if ( ! $html || ! preg_match_all( self::PATTERN, $html, $m, PREG_SET_ORDER ) ) {
			return $out;
		}
		foreach ( $m as $h ) {
			$level = (int) $h[1];
			$text  = self::clean_text( $h[3] );
			if ( '' === $text ) {
				continue;
			}
			$id = self::existing_id( $h[2] );
			if ( ! $id ) {
				$id = self::unique_slug( $text, $used );
			} else {
				$used[ $id ] = true;
			}
			if ( in_array( $level, $levels, true ) ) {
				$out[] = array( $level, $text, $id );
			}
		}
		return $out;
	}

	/** Adds id attributes to headings that do not have one. */
	public static function add_ids( $html ) {
		$used = array();
		return preg_replace_callback(
			self::PATTERN,
			function ( $h ) use ( &$used ) {
				$text = self::clean_text( $h[3] );
				if ( '' === $text ) {
					return $h[0];
				}
				$id = self::existing_id( $h[2] );
				if ( $id ) {
					$used[ $id ] = true;
					return $h[0];
				}
				$id = self::unique_slug( $text, $used );
				return '<h' . $h[1] . ' id="' . esc_attr( $id ) . '"' . $h[2] . '>' . $h[3] . '</h' . $h[1] . '>';
			},
			$html
		);
	}

	public static function clean_text( $html ) {
		$text = wp_strip_all_tags( self::strip_shortcode_tags( $html ) );
		$text = html_entity_decode( $text, ENT_QUOTES, get_bloginfo( 'charset' ) );
		return trim( preg_replace( '/\s+/u', ' ', $text ) );
	}

	/** Removes any [shortcode] tags, registered or not (page builders store layouts this way). */
	public static function strip_shortcode_tags( $text ) {
		return preg_replace( '/\[\/?[a-zA-Z][a-zA-Z0-9_\-]*(?:\s[^\]]*)?\]/', ' ', (string) $text );
	}

	private static function existing_id( $attrs ) {
		if ( $attrs && preg_match( '/\sid=(["\'])(.*?)\1/i', $attrs, $m ) ) {
			return $m[2];
		}
		return '';
	}

	private static function unique_slug( $text, array &$used ) {
		$base = sanitize_title( $text );
		if ( '' === $base ) {
			$base = 'section';
		}
		$id = $base;
		$n  = 2;
		while ( isset( $used[ $id ] ) ) {
			$id = $base . '-' . $n++;
		}
		$used[ $id ] = true;
		return $id;
	}
}
