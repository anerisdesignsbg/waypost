<?php
/**
 * The thinking behind docs search: turns what a reader typed into words worth
 * looking for (with other forms of each word and the site's synonyms), splits
 * pages into their heading sections, scores how well each page and section
 * answers the search, and suggests a spelling when nothing is found.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Match {

	/** Heading sections of each page: list of [ heading, anchor, text ]. The first has no heading. */
	const PASSAGES_META = '_waypost_passages';
	/** Words from titles, headings and section names, for spelling suggestions. */
	const VOCAB_CACHE = 'waypost_vocab';

	/** Words that carry no meaning in a question, such as "how do I". */
	private static function stop_words() {
		$list = 'a an and are as at be but by can do does for from how i if in into is it its me my of on or our so that the their them then there these this to us was we what when where which who why will with you your';
		return array_flip( explode( ' ', $list ) );
	}

	// ── Query ───────────────────────────────────────────────────────────────

	/** Lower-case words of a search, without punctuation or question words (unless that leaves nothing). */
	public static function words( $q ) {
		$q     = mb_strtolower( (string) $q );
		$q     = preg_replace( '/[^\p{L}\p{N}\s\-_.\/#+]/u', ' ', $q );
		$words = array_values( array_filter( preg_split( '/\s+/u', trim( (string) $q ) ), function ( $w ) { return mb_strlen( trim( $w, '-_./' ) ) > 1; } ) );
		$words = array_map( function ( $w ) { return trim( $w, '-_./' ); }, $words );
		$stop  = self::stop_words();
		$kept  = array_values( array_filter( $words, function ( $w ) use ( $stop ) { return ! isset( $stop[ $w ] ); } ) );
		return array_values( array_unique( $kept ? $kept : $words ) );
	}

	/**
	 * One group per search word: the word, its root form and its synonyms.
	 * A page matches a group when it contains any of them.
	 *
	 * @return array[] list of lists of strings, longest first in each.
	 */
	public static function groups( $q ) {
		$syn    = self::synonyms();
		$groups = array();
		foreach ( self::words( $q ) as $w ) {
			$forms = array( $w, self::stem( $w ) );
			foreach ( $syn as $set ) {
				if ( in_array( $w, $set, true ) || in_array( self::stem( $w ), array_map( array( __CLASS__, 'stem' ), $set ), true ) ) {
					foreach ( $set as $s ) {
						$forms[] = $s;
						$forms[] = self::stem( $s );
					}
				}
			}
			$forms = array_values( array_unique( array_filter( $forms, function ( $f ) { return mb_strlen( $f ) > 1; } ) ) );
			// Drop forms that contain a shorter form: "install" already finds "installing".
			$lean = array();
			foreach ( $forms as $f ) {
				$covered = false;
				foreach ( $forms as $g ) {
					if ( $g !== $f && mb_strlen( $g ) < mb_strlen( $f ) && false !== mb_strpos( $f, $g ) ) {
						$covered = true;
						break;
					}
				}
				if ( ! $covered ) {
					$lean[] = $f;
				}
			}
			usort( $lean, function ( $a, $b ) { return mb_strlen( $b ) - mb_strlen( $a ); } );
			$groups[] = $lean;
		}
		return $groups;
	}

	/** Every form worth highlighting in results, longest first. */
	public static function marks( array $groups ) {
		$all = array();
		foreach ( $groups as $g ) {
			foreach ( $g as $f ) {
				$all[] = $f;
			}
		}
		$all = array_values( array_unique( $all ) );
		usort( $all, function ( $a, $b ) { return mb_strlen( $b ) - mb_strlen( $a ); } );
		return $all;
	}

	/**
	 * Root of an English word, so "installing", "installed" and "installation"
	 * all find "install". Light on purpose: it only has to be good enough for
	 * a "contains" match. Words in other alphabets are left alone.
	 */
	public static function stem( $w ) {
		if ( mb_strlen( $w ) < 5 || preg_match( '/[^a-z]/', $w ) ) {
			return $w;
		}
		$rules = array(
			'ations' => '',
			'ation'  => '',
			'ings'   => '',
			'ing'    => '',
			'ies'    => 'y',
			'ied'    => 'y',
			'ed'     => '',
			'es'     => '',
			'e'      => '',
			's'      => '',
			'y'      => '', // "expiry" finds "expired"; only when 5 letters are left, see below.
		);
		foreach ( $rules as $end => $put ) {
			$len = strlen( $end );
			if ( strlen( $w ) - $len >= 4 && substr( $w, -$len ) === $end ) {
				if ( ( 's' === $end && 'ss' === substr( $w, -2 ) ) || ( 'y' === $end && strlen( $w ) < 6 ) ) {
					continue;
				}
				$w = substr( $w, 0, -$len ) . $put;
				break;
			}
		}
		// "running" leaves "runn": drop the doubled letter.
		if ( preg_match( '/([bdfgmnprt])\1$/', $w ) ) {
			$w = substr( $w, 0, -1 );
		}
		return $w;
	}

	/** Synonym sets from Settings, Search: one line each, words separated by commas. */
	public static function synonyms() {
		static $sets = null;
		if ( null === $sets ) {
			$sets = array();
			foreach ( preg_split( '/\r?\n/', (string) Waypost_Settings::get( 'synonyms' ) ) as $line ) {
				$set = array_values( array_filter( array_map( function ( $s ) { return mb_strtolower( trim( $s ) ); }, explode( ',', $line ) ) ) );
				if ( count( $set ) > 1 ) {
					$sets[] = $set;
				}
			}
		}
		return $sets;
	}

	// ── Sections of a page ──────────────────────────────────────────────────

	/**
	 * Splits page HTML at its h2 to h4 headings. Anchors come from
	 * Waypost_Headings, so deep links match the IDs on the page.
	 *
	 * @return array[] list of [ heading, anchor, text ]
	 */
	public static function passages( $html ) {
		$html = (string) $html;
		$out  = array();
		$ids  = Waypost_Headings::extract( $html, array( 2, 3, 4 ) );
		if ( ! $ids || ! preg_match_all( Waypost_Headings::PATTERN, $html, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
			$text = Waypost_Search::html_text( $html );
			return '' === $text ? array() : array( array( '', '', $text ) );
		}
		$pos = 0;
		$i   = 0;
		$cur = array( '', '' );
		foreach ( $m as $h ) {
			if ( '' === Waypost_Headings::clean_text( $h[3][0] ) ) {
				continue; // extract() skips empty headings too, so the two lists stay in step.
			}
			$text = Waypost_Search::html_text( substr( $html, $pos, $h[0][1] - $pos ) );
			if ( '' !== $text || '' !== $cur[0] ) {
				$out[] = array( $cur[0], $cur[1], $text );
			}
			$cur = isset( $ids[ $i ] ) ? array( $ids[ $i ][1], $ids[ $i ][2] ) : array( Waypost_Headings::clean_text( $h[3][0] ), '' );
			$pos = $h[0][1] + strlen( $h[0][0] );
			$i++;
		}
		$out[] = array( $cur[0], $cur[1], Waypost_Search::html_text( substr( $html, $pos ) ) );
		return $out;
	}

	/** Sections stored for a post, or one section made of its whole text (documents, older pages). */
	public static function stored_passages( $post_id, $text ) {
		$p = get_post_meta( $post_id, self::PASSAGES_META, true );
		if ( is_array( $p ) && $p ) {
			return $p;
		}
		return '' === (string) $text ? array() : array( array( '', '', (string) $text ) );
	}

	// ── Scoring ─────────────────────────────────────────────────────────────

	/**
	 * How well a post answers the search.
	 *
	 * @return array { score, matched (groups found anywhere), best (index of the best section), sections (index => score, matched groups) }
	 */
	public static function score( $title, array $passages, array $groups, $phrase ) {
		$title_l = mb_strtolower( $title );
		$n       = count( $groups );
		$found   = array_fill( 0, $n, false );
		$score   = 0.0;

		// Title: the strongest sign a page is about the search.
		$in_title = 0;
		foreach ( $groups as $gi => $g ) {
			if ( self::contains( $title_l, $g ) ) {
				$in_title++;
				$found[ $gi ] = true;
			}
		}
		$score += 8 * $in_title;
		if ( $n && $in_title === $n ) {
			$score += 10;
		}
		if ( $phrase && false !== mb_strpos( $title_l, $phrase ) ) {
			$score += 12;
		}

		$sections = array();
		$best     = -1;
		$best_s   = 0.0;
		$extra    = 0.0;
		foreach ( $passages as $pi => $p ) {
			$head = mb_strtolower( (string) $p[0] );
			$body = mb_strtolower( (string) $p[2] );
			$s    = 0.0;
			$have = 0;
			foreach ( $groups as $gi => $g ) {
				$hit = false;
				if ( '' !== $head && self::contains( $head, $g ) ) {
					$s  += 5;
					$hit = true;
				}
				$count = self::count( $body, $g );
				if ( $count ) {
					$s  += min( 3, $count ) + ( $count > 3 ? 0.5 : 0 );
					$hit = true;
				}
				if ( $hit ) {
					$have++;
					$found[ $gi ] = true;
				}
			}
			if ( $n > 1 && $have === $n ) {
				$s += 6; // Every word in one place: this section is about the search.
			}
			if ( $phrase && ( false !== mb_strpos( $body, $phrase ) || false !== mb_strpos( $head, $phrase ) ) ) {
				$s += 6;
			}
			if ( $s > 0 && 0 === $pi && '' === $head && $n && $in_title === $n ) {
				$s += 2.5; // The page is about the search, so its opening usually sums it up best.
			}
			if ( $s > 0 ) {
				$sections[ $pi ] = array( $s, $have );
				$extra          += $s;
			}
			if ( $s > $best_s ) {
				$best_s = $s;
				$best   = $pi;
			}
		}
		$score += $best_s + min( 6, 0.3 * ( $extra - $best_s ) );
		if ( $best < 0 && $in_title && $passages ) {
			$best = 0; // Found by its title alone: the page's opening is the answer.
		}

		return array(
			'score'    => $score,
			'matched'  => count( array_filter( $found ) ),
			'best'     => $best,
			'sections' => $sections,
		);
	}

	private static function contains( $text, array $forms ) {
		foreach ( $forms as $f ) {
			if ( false !== mb_strpos( $text, $f ) ) {
				return true;
			}
		}
		return false;
	}

	private static function count( $text, array $forms ) {
		$c = 0;
		foreach ( $forms as $f ) {
			$c = max( $c, substr_count( $text, $f ) );
		}
		return $c;
	}

	// ── Spelling ────────────────────────────────────────────────────────────

	/** A corrected search when some words look misspelt, or ''. */
	public static function suggest( $q ) {
		$vocab = self::vocabulary();
		if ( ! $vocab ) {
			return '';
		}
		$changed = false;
		$out     = array();
		foreach ( self::words( $q ) as $w ) {
			$len = mb_strlen( $w );
			if ( isset( $vocab[ $w ] ) || $len < 4 || preg_match( '/\d/', $w ) ) {
				$out[] = $w;
				continue;
			}
			$max  = $len >= 6 ? 2 : 1;
			$pick = '';
			$dist = $max + 1;
			$freq = 0;
			// Two letters swapped ("dokcer") is the most common slip: treat it as one change.
			for ( $i = 0; $i < strlen( $w ) - 1; $i++ ) {
				$swap = substr( $w, 0, $i ) . $w[ $i + 1 ] . $w[ $i ] . substr( $w, $i + 2 );
				if ( isset( $vocab[ $swap ] ) && ( 1 < $dist || $vocab[ $swap ] > $freq ) ) {
					$pick = $swap;
					$dist = 1;
					$freq = $vocab[ $swap ];
				}
			}
			foreach ( $vocab as $v => $f ) {
				if ( abs( strlen( $v ) - strlen( $w ) ) > $max ) {
					continue;
				}
				$d = levenshtein( $w, (string) $v );
				if ( $d < $dist || ( $d === $dist && $f > $freq ) ) {
					$pick = (string) $v;
					$dist = $d;
					$freq = $f;
				}
			}
			if ( '' !== $pick && $dist <= $max ) {
				$out[]   = $pick;
				$changed = true;
			} else {
				$out[] = $w;
			}
		}
		return $changed ? implode( ' ', $out ) : '';
	}

	/** [ word => how often ] from doc titles, headings and section names. Cached with the menu. */
	public static function vocabulary() {
		$cached = get_transient( self::VOCAB_CACHE );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$text = array();
		$ids  = wp_list_pluck( Waypost_Tree::flat(), 'post_id' );
		foreach ( Waypost_Tree::flat() as $f ) {
			$text[] = $f['title'];
			foreach ( $f['trail'] as $t ) {
				$text[] = $t['title'];
			}
		}
		if ( $ids ) {
			update_meta_cache( 'post', $ids );
			foreach ( $ids as $id ) {
				$p = get_post_meta( $id, self::PASSAGES_META, true );
				foreach ( is_array( $p ) ? $p : array() as $s ) {
					$text[] = $s[0];
				}
			}
		}
		$vocab = array();
		foreach ( preg_split( '/[^\p{L}\p{N}]+/u', mb_strtolower( implode( ' ', $text ) ), -1, PREG_SPLIT_NO_EMPTY ) as $w ) {
			if ( mb_strlen( $w ) >= 3 && ! preg_match( '/\d/', $w ) ) {
				$vocab[ $w ] = isset( $vocab[ $w ] ) ? $vocab[ $w ] + 1 : 1;
			}
		}
		arsort( $vocab );
		$vocab = array_slice( $vocab, 0, 3000, true );
		set_transient( self::VOCAB_CACHE, $vocab, 12 * HOUR_IN_SECONDS );
		return $vocab;
	}

	// ── Snippets ────────────────────────────────────────────────────────────

	/**
	 * A snippet of about $len characters around the first match, cut at a
	 * sentence or word boundary so it reads like a short summary.
	 */
	public static function snippet( $text, array $marks, $len = 170 ) {
		$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
		if ( '' === $text ) {
			return '';
		}
		if ( mb_strlen( $text ) <= $len ) {
			return $text;
		}
		$lower = mb_strtolower( $text );
		$pos   = false;
		foreach ( $marks as $m ) {
			$p = mb_strpos( $lower, $m );
			if ( false !== $p && ( false === $pos || $p < $pos ) ) {
				$pos = $p;
			}
		}
		$start = 0;
		if ( false !== $pos && $pos > $len * 0.45 ) {
			// Start at the sentence holding the match when it is close enough, else a little before it.
			$before = mb_substr( $text, 0, $pos );
			$stop   = max( mb_strrpos( $before, '. ' ), mb_strrpos( $before, '? ' ), mb_strrpos( $before, '! ' ) );
			$start  = ( false !== $stop && $pos - $stop < $len * 0.6 ) ? $stop + 2 : max( 0, $pos - (int) ( $len * 0.3 ) );
			if ( $start > 0 && $start !== $stop + 2 ) {
				$space = mb_strpos( $text, ' ', $start );
				$start = false === $space ? $start : $space + 1;
			}
		}
		$cut = mb_substr( $text, $start, $len );
		if ( $start + $len < mb_strlen( $text ) ) {
			$end = max( mb_strrpos( $cut, '. ' ), mb_strrpos( $cut, '? ' ), mb_strrpos( $cut, '! ' ) );
			if ( false !== $end && $end > $len * 0.6 ) {
				$cut = mb_substr( $cut, 0, $end + 1 );
			} else {
				$space = mb_strrpos( $cut, ' ' );
				$cut   = rtrim( false === $space ? $cut : mb_substr( $cut, 0, $space ), ' ,;:' ) . '…';
			}
		}
		return ( $start > 0 ? '…' : '' ) . $cut;
	}
}
