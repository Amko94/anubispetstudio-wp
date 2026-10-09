( function () {
	'use strict';
	function updatePlaceholders() {
		document.querySelectorAll( 'textarea[name="notes"]' ).forEach( function ( field ) {
			field.placeholder = 'Gibt es etwas, das wir beachten sollten? Zum Beispiel: Dein Hund schnappt bei Berührungen, ist am linken Ohr empfindlich oder hat Angst vor der Schermaschine.';
		} );
	}
	updatePlaceholders();
	new MutationObserver( updatePlaceholders ).observe( document.body, { childList: true, subtree: true } );
} )();
