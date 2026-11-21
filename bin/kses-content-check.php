<?php
// Dev check: reports anything wp_kses() with the content list would remove from doc page content.
$sig = function ( $html ) {
	preg_match_all( '/<([a-zA-Z][a-zA-Z0-9-]*)([^>]*)>/', $html, $m, PREG_SET_ORDER );
	$out = array();
	foreach ( $m as $t ) {
		preg_match_all( '/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?=\s*=|\s|$|\/)/', preg_replace( '/"[^"]*"|\'[^\']*\'/', '', $t[2] ), $a );
		$out[] = strtolower( $t[1] );
		foreach ( $a[1] as $n ) {
			$out[] = strtolower( $t[1] ) . '@' . strtolower( $n );
		}
	}
	return array_count_values( $out );
};
$lost = array();
$ids  = get_posts( array( 'post_type' => 'page', 'posts_per_page' => -1, 'fields' => 'ids', 'has_password' => false ) );
foreach ( $ids as $id ) {
	$GLOBALS['post'] = get_post( $id );
	setup_postdata( $GLOBALS['post'] );
	$html = apply_filters( 'the_content', get_the_content() );
	$a    = $sig( $html );
	$b    = $sig( wp_kses( $html, Docsway_Render::content_allowed_html() ) );
	foreach ( $a as $k => $n ) {
		if ( ( isset( $b[ $k ] ) ? $b[ $k ] : 0 ) < $n ) {
			$lost[ $k ][] = get_post_field( 'post_name', $id );
		}
	}
}
if ( wp_is_block_theme() ) {
	$GLOBALS['post'] = get_page_by_path( 'shared-albums' );
	list( $h, $f ) = Docsway_Layout::block_chrome();
	foreach ( array( 'header' => $h, 'footer' => $f ) as $n => $html ) {
		$a = $sig( $html );
		$b = $sig( wp_kses( $html, Docsway_Render::content_allowed_html() ) );
		foreach ( $a as $k => $c ) {
			if ( ( isset( $b[ $k ] ) ? $b[ $k ] : 0 ) < $c ) {
				$lost[ $k ][] = 'theme ' . $n;
			}
		}
	}
}
foreach ( $lost as $k => $where ) {
	echo $k . ': ' . implode( ', ', array_slice( array_unique( $where ), 0, 4 ) ) . "\n";
}
echo count( $ids ) . ' pages checked on ' . get_stylesheet() . ', ' . count( $lost ) . " kinds of loss\n";
