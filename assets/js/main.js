document.addEventListener( 'DOMContentLoaded', function () {
	var hero = document.querySelector( '.hero' );
	if ( hero ) {
		[ 'content', 'media' ].forEach( function ( side ) {
			var panel = hero.querySelector( '.hero-' + side );
			if ( ! panel ) { return; }
			panel.addEventListener( 'pointerenter', function ( event ) {
				if ( event.pointerType === 'mouse' ) {
					hero.dataset.expanded = side;
				}
			} );
			panel.addEventListener( 'click', function ( event ) {
				if ( event.target.closest( 'a, button, input, textarea, select' ) ) { return; }
				if ( window.matchMedia( '(hover: none)' ).matches || event.pointerType === 'touch' ) {
					if ( hero.dataset.expanded === side ) {
						delete hero.dataset.expanded;
					} else {
						hero.dataset.expanded = side;
					}
				}
			} );
		} );
		hero.addEventListener( 'pointerleave', function ( event ) {
			if ( event.pointerType === 'mouse' ) { delete hero.dataset.expanded; }
		} );
	}
	var menu = document.getElementById( 'm' );
	var toggle = document.querySelector( '.bg' );

	if ( ! menu || ! toggle ) {
		return;
	}

	toggle.addEventListener( 'click', function () {
		var isOpen = menu.classList.toggle( 'o' );
		toggle.setAttribute( 'aria-expanded', isOpen ? 'true' : 'false' );
	} );

	menu.addEventListener( 'click', function () {
		menu.classList.remove( 'o' );
		toggle.setAttribute( 'aria-expanded', 'false' );
	} );
} );
