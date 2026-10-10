( function () {
	'use strict';
	function updatePlaceholders() {
		document.querySelectorAll( '.customer-information-container' ).forEach( function ( form ) {
			if ( ! form.previousElementSibling || ! form.previousElementSibling.classList.contains( 'anubis-form-intro' ) ) {
				var intro = document.createElement( 'div' );
				intro.className = 'anubis-form-intro';
				var title = document.createElement( 'h2' );
				title.textContent = 'Du und dein Vierbeiner';
				var hint = document.createElement( 'p' );
				hint.textContent = 'Name und E-Mail brauchen wir für deine Buchung. Die weiteren Angaben sind freiwillig.';
				intro.appendChild( title );
				intro.appendChild( hint );
				form.before( intro );
			}
			form.querySelectorAll( '.form-field' ).forEach( function ( wrapper ) {
				var input = wrapper.querySelector( 'input[type="tel"]' ) || wrapper.querySelector( 'input[name], textarea[name]' );
				if ( ! input ) { return; }
				var name = input.name.toLowerCase();
				if ( wrapper.dataset.anubisField !== name ) { wrapper.dataset.anubisField = name; }
				var autocomplete = { name: 'name', email: 'email', phone: 'tel', telephone: 'tel' }[ name ];
				if ( autocomplete && input.autocomplete !== autocomplete ) { input.autocomplete = autocomplete; }
				if ( name === 'email' && input.inputMode !== 'email' ) { input.inputMode = 'email'; }
			} );
		} );
		document.querySelectorAll( 'textarea[name="notes"]' ).forEach( function ( field ) {
			field.placeholder = 'Zum Beispiel: empfindliche Stellen, Ängste oder besondere Wünsche.';
		} );
	}
	updatePlaceholders();
	new MutationObserver( updatePlaceholders ).observe( document.body, { childList: true, subtree: true } );
} )();
