/* Aneris Docsway product site: mobile menu and the yearly / lifetime price switch. */
( function () {
	'use strict';

	var toggle = document.querySelector( '.ads-nav-toggle' );
	var nav = document.getElementById( 'ads-nav' );
	if ( toggle && nav ) {
		var setOpen = function ( open ) {
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			nav.classList.toggle( 'is-open', open );
		};
		toggle.addEventListener( 'click', function () {
			setOpen( toggle.getAttribute( 'aria-expanded' ) !== 'true' );
		} );
		nav.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( 'a' ) ) {
				setOpen( false );
			}
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				setOpen( false );
			}
		} );
	}

	var buttons = document.querySelectorAll( '.ads-cycle__btn' );
	var setCycle = function ( cycle ) {
		buttons.forEach( function ( b ) {
			var on = b.getAttribute( 'data-cycle' ) === cycle;
			b.classList.toggle( 'is-on', on );
			b.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		} );
		document.querySelectorAll( '.ads-prices [data-' + cycle + ']' ).forEach( function ( el ) {
			var value = el.getAttribute( 'data-' + cycle );
			if ( 'A' === el.tagName ) {
				el.setAttribute( 'href', value );
			} else if ( el.classList.contains( 'ads-price__amount' ) ) {
				el.firstElementChild.textContent = value;
			} else {
				el.textContent = value;
			}
		} );
	};
	buttons.forEach( function ( b ) {
		b.addEventListener( 'click', function () {
			setCycle( b.getAttribute( 'data-cycle' ) );
		} );
	} );
}() );
