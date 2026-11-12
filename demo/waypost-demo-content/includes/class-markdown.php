<?php
/**
 * Turns the tutorial markdown into page HTML. Covers what the tutorials use:
 * headings, paragraphs, lists (with indented code inside items), tables,
 * fenced code, images, links, bold and inline code.
 */

defined( 'ABSPATH' ) || exit;

class Waypost_Demo_Markdown {

	/** @var callable ( $src, $alt ) => image HTML */
	private $image;
	/** @var callable ( $url ) => link address */
	private $link;

	public function __construct( callable $image, callable $link ) {
		$this->image = $image;
		$this->link  = $link;
	}

	/** @return array { title: the first # heading, html: everything else } */
	public function convert( $md ) {
		$lines = preg_split( '/\r?\n/', (string) $md );
		$title = '';
		$html  = $this->blocks( $lines, $title );
		return array( 'title' => $title, 'html' => $html );
	}

	private function blocks( array $lines, &$title ) {
		$html = '';
		$n    = count( $lines );
		$i    = 0;
		while ( $i < $n ) {
			$line = $lines[ $i ];
			if ( '' === trim( $line ) ) {
				$i++;
				continue;
			}
			if ( preg_match( '/^```/', $line ) ) {
				$code = array();
				for ( $i++; $i < $n && ! preg_match( '/^```/', $lines[ $i ] ); $i++ ) {
					$code[] = $lines[ $i ];
				}
				$i++;
				$html .= '<pre><code>' . esc_html( implode( "\n", $code ) ) . "</code></pre>\n";
				continue;
			}
			if ( preg_match( '/^(#{1,4})\s+(.*)$/', $line, $m ) ) {
				$level = strlen( $m[1] );
				if ( 1 === $level && '' === $title ) {
					$title = trim( $m[2] );
				} else {
					$level = max( 2, $level );
					$html .= "<h{$level}>" . $this->inline( $m[2] ) . "</h{$level}>\n";
				}
				$i++;
				continue;
			}
			if ( preg_match( '/^!\[([^\]]*)\]\(([^)]+)\)\s*$/', $line, $m ) ) {
				$html .= call_user_func( $this->image, $m[2], $m[1] ) . "\n";
				$i++;
				continue;
			}
			if ( 0 === strpos( ltrim( $line ), '|' ) ) {
				$rows = array();
				for ( ; $i < $n && 0 === strpos( ltrim( $lines[ $i ] ), '|' ); $i++ ) {
					$rows[] = $lines[ $i ];
				}
				$html .= $this->table( $rows );
				continue;
			}
			if ( preg_match( '/^([-*]|\d+\.)\s+/', $line ) ) {
				$html .= $this->listing( $lines, $i );
				continue;
			}
			$para = array();
			for ( ; $i < $n && '' !== trim( $lines[ $i ] ) && ! preg_match( '/^(#{1,4}\s|```|\||!\[|[-*]\s|\d+\.\s)/', $lines[ $i ] ); $i++ ) {
				$para[] = trim( $lines[ $i ] );
			}
			$html .= '<p>' . $this->inline( implode( ' ', $para ) ) . "</p>\n";
		}
		return $html;
	}

	/** A list starting at $i; items may hold indented lines, blank lines and fenced code. */
	private function listing( array $lines, &$i ) {
		$n       = count( $lines );
		$ordered = (bool) preg_match( '/^\d+\./', $lines[ $i ] );
		$marker  = $ordered ? '/^\d+\.\s+(.*)$/' : '/^[-*]\s+(.*)$/';
		$items   = array();
		while ( $i < $n ) {
			$line = $lines[ $i ];
			if ( preg_match( $marker, $line, $m ) ) {
				$items[] = array( $m[1] );
				$i++;
				continue;
			}
			if ( '' === trim( $line ) ) {
				$j = $i + 1;
				while ( $j < $n && '' === trim( $lines[ $j ] ) ) {
					$j++;
				}
				if ( $j < $n && ( preg_match( '/^\s{2,}\S/', $lines[ $j ] ) || preg_match( $marker, $lines[ $j ] ) ) ) {
					$items[ count( $items ) - 1 ][] = '';
					$i = $j;
					continue;
				}
				break;
			}
			if ( preg_match( '/^\s{2,}/', $line ) ) {
				$items[ count( $items ) - 1 ][] = preg_replace( '/^\s{2,3}/', '', $line );
				$i++;
				continue;
			}
			break;
		}
		$tag  = $ordered ? 'ol' : 'ul';
		$html = "<{$tag}>\n";
		foreach ( $items as $item ) {
			if ( 1 === count( $item ) ) {
				$html .= '<li>' . $this->inline( $item[0] ) . "</li>\n";
			} else {
				$none  = '';
				$inner = $this->blocks( $item, $none );
				// A first line on its own reads better without a paragraph around it.
				$inner = preg_replace( '#^<p>(.*?)</p>\n#s', '$1', $inner, 1 );
				$html .= '<li>' . $inner . "</li>\n";
			}
		}
		return $html . "</{$tag}>\n";
	}

	private function table( array $rows ) {
		$cells = function ( $row ) {
			$row = trim( trim( $row ), '|' );
			return array_map( 'trim', explode( '|', $row ) );
		};
		$html = "<table>\n<thead><tr>";
		foreach ( $cells( array_shift( $rows ) ) as $c ) {
			$html .= '<th>' . $this->inline( $c ) . '</th>';
		}
		$html .= "</tr></thead>\n<tbody>\n";
		foreach ( $rows as $row ) {
			if ( preg_match( '/^\|?\s*:?-{2,}/', trim( $row ) ) ) {
				continue; // The |---|---| line.
			}
			$html .= '<tr>';
			foreach ( $cells( $row ) as $c ) {
				$html .= '<td>' . $this->inline( $c ) . '</td>';
			}
			$html .= "</tr>\n";
		}
		return $html . "</tbody>\n</table>\n";
	}

	/** Bold, inline code and links; everything else is escaped. */
	private function inline( $text ) {
		$tokens = array();
		$keep   = function ( $html ) use ( &$tokens ) {
			$key            = "\x01" . count( $tokens ) . "\x02";
			$tokens[ $key ] = $html;
			return $key;
		};
		$text = preg_replace_callback( '/`([^`]+)`/', function ( $m ) use ( $keep ) {
			return $keep( '<code>' . esc_html( $m[1] ) . '</code>' );
		}, (string) $text );
		$text = preg_replace_callback( '/!\[([^\]]*)\]\(([^)]+)\)/', function ( $m ) use ( $keep ) {
			return $keep( call_user_func( $this->image, $m[2], $m[1] ) );
		}, $text );
		$text = preg_replace_callback( '/\[([^\]]+)\]\(([^)]+)\)/', function ( $m ) use ( $keep ) {
			$url = call_user_func( $this->link, $m[2] );
			return $keep( '<a href="' . esc_url( $url ) . '">' . $this->inline( $m[1] ) . '</a>' );
		}, $text );
		$text = esc_html( $text );
		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text );
		return strtr( $text, $tokens );
	}
}
