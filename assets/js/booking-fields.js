( function () {
	'use strict';
	function updatePlaceholders() {
		// Keep a single booking summary once the customer form is shown.
		try {
			var frame = window.frameElement;
			var step = frame && frame.closest( '.dog-booking-next' );
			if ( step ) { step.classList.toggle( 'is-customer-step', !! document.querySelector( '.customer-information-container' ) ); }
		} catch ( error ) { /* Parent access is unavailable outside the local booking embed. */ }
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
