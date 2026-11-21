<?php
// Dev check: renders Docsway's markup and reports any tag or attribute wp_kses() would remove.
$page = get_page_by_path( 'shared-albums' );
$GLOBALS['post'] = $page;
setup_postdata( $page );
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
$parts = array(
	'menu'        => Docsway_Render::menu(),
	'search_box'  => Docsway_Render::search_box() . Docsway_Render::search_box( array( 'context' => 'home' ) ),
	'scheme'      => Docsway_Render::scheme_toggle(),
	'toc'         => Docsway_Render::toc( array( 'post_id' => $page->ID ) ),
	'crumbs'      => Docsway_Render::breadcrumbs( array( 'post_id' => $page->ID ) ),
	'prev_next'   => Docsway_Render::prev_next( array( 'post_id' => $page->ID ) ),
	'meta'        => Docsway_Render::page_meta( $page->ID ),
	'feedback'    => Docsway_Render::feedback( $page->ID ),
	'home'        => Docsway_Render::search_home(),
	'results'     => Docsway_Render::results( 'album', 1, home_url( '/documentation/' ) ),
	'topbar'      => Docsway_Render::topbar( 'doc' ),
	'footer'      => Docsway_Render::docs_footer(),
	'quick'       => Docsway_Render::quick_searches( '', home_url( '/documentation/' ) ),
);
foreach ( array_keys( Docsway_Settings::styles() ) as $st ) {
	$parts[ 'style_preview_' . $st ] = Docsway_Render::style_preview( $st );
}
if ( class_exists( 'Docsway_Admin' ) ) {
	$parts['copy_field'] = Docsway_Admin::copy_field( '[docsway_menu]' );
	$parts['help_link']  = Docsway_Admin::help_link( 'ordering' );
	$parts['options']    = Docsway_Admin::section_options( 0, 'Top level' );
}
$bad = 0;
foreach ( $parts as $name => $html ) {
	$a = $sig( $html );
	$b = $sig( wp_kses( $html, Docsway_Render::allowed_html() ) );
	foreach ( $a as $k => $n ) {
		if ( ( isset( $b[ $k ] ) ? $b[ $k ] : 0 ) < $n ) {
			echo "$name: loses $k\n";
			$bad++;
		}
	}
}
echo $bad ? "$bad problems\n" : "Nothing removed from " . count( $parts ) . " parts\n";
