document.addEventListener( 'DOMContentLoaded', function () {
	var form = document.querySelector( '[data-dog-booking]' );
	if ( ! form ) { return; }
	var config = JSON.parse( form.dataset.dogBooking );
	var breedInput = form.querySelector( '#dog-breed' );
	var sizeInput = form.querySelector( '#dog-size' );
	var serviceInput = null;
	var serviceInputs = Array.from( form.querySelectorAll( '.booking-services input' ) );
	var sizeChoice = form.querySelector( '.dog-size-choice' );
	var result = form.querySelector( '.dog-size-result' );
	var careNote = form.querySelector( '.dog-care-note' );
	var preview = form.querySelector( '.dog-price-preview' );
	var durationPreview = form.querySelector( '.dog-duration-preview' );
	var next = document.querySelector( '.dog-booking-next' );
	var lastBreed = breedInput.value.trim().toLocaleLowerCase( 'de' );
	var dropdowns = [];
	function normalize( value ) { return value.toLocaleLowerCase( 'de' ).normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' ); }
	function createDropdown( control, isBreed ) {
		var wrapper = document.createElement( 'div' );
		wrapper.className = 'booking-dropdown';
		control.parentNode.insertBefore( wrapper, control );
		wrapper.appendChild( control );
		var trigger = control;
		if ( ! isBreed ) {
			control.classList.add( 'booking-native-select' );
			control.tabIndex = -1;
			control.setAttribute( 'aria-hidden', 'true' );
			trigger = document.createElement( 'button' );
			trigger.type = 'button';
			trigger.id = control.id + '-trigger';
			trigger.className = 'booking-select-trigger';
			wrapper.appendChild( trigger );
			var label = form.querySelector( 'label[for="' + control.id + '"]' );
			label.htmlFor = trigger.id;
		} else {
			control.removeAttribute( 'list' );
			control.classList.add( 'booking-search-input' );
			trigger.setAttribute( 'aria-autocomplete', 'list' );
		}
		trigger.setAttribute( 'role', 'combobox' );
		trigger.setAttribute( 'aria-haspopup', 'listbox' );
		trigger.setAttribute( 'aria-expanded', 'false' );
		trigger.setAttribute( 'aria-required', String( control.required ) );
		var list = document.createElement( 'div' );
		list.className = 'booking-options';
		list.id = control.id + '-options';
		list.setAttribute( 'role', 'listbox' );
		list.setAttribute( 'aria-label', isBreed ? 'Hunderassen' : label.textContent );
		list.hidden = true;
		wrapper.appendChild( list );
		trigger.setAttribute( 'aria-controls', list.id );
		var options = [], active = -1;
		function close() { list.hidden = true; trigger.setAttribute( 'aria-expanded', 'false' ); trigger.removeAttribute( 'aria-activedescendant' ); active = -1; }
		function activate( index ) {
			if ( ! options.length ) { return; }
			active = Math.max( 0, Math.min( options.length - 1, index ) );
			options.forEach( function ( option, i ) { option.classList.toggle( 'is-active', i === active ); } );
			trigger.setAttribute( 'aria-activedescendant', options[ active ].id );
			options[ active ].scrollIntoView( { block: 'nearest' } );
		}
		function choose( value ) {
			control.value = value;
			control.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			control.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			close(); trigger.focus();
			// The focus event opens breed suggestions; selection should leave them closed.
			close();
		}
		function render() {
			list.replaceChildren(); options = []; active = -1;
			trigger.removeAttribute( 'aria-activedescendant' );
			var entries = isBreed ? config.breeds.filter( function ( entry ) { return normalize( entry.name ).includes( normalize( control.value.trim() ) ); } ) : Array.from( control.options ).filter( function ( option ) { return option.value && ! option.disabled; } );
			entries.forEach( function ( entry, i ) {
				var option = document.createElement( 'div' );
				option.className = 'booking-option'; option.id = list.id + '-' + i;
				option.setAttribute( 'role', 'option' );
				var value = isBreed ? entry.name : entry.value;
				option.setAttribute( 'aria-selected', String( control.value === value ) );
				var name = document.createElement( 'span' ); name.className = 'booking-option-name';
				name.textContent = isBreed ? entry.name : entry.textContent;
				option.appendChild( name );
				var detail = document.createElement( 'span' ); detail.className = 'booking-option-detail';
				if ( isBreed ) {
					detail.textContent = ( entry.size ? config.sizes[ entry.size ] : 'Größe auswählen' ) + ( entry.haircut ? '' : ' · Teilpflege' );
				} else if ( control === serviceInput ) {
					var key = value === 'haircut' ? sizeInput.value : value;
					detail.textContent = ( config.prices[ key ] || '' ) + ' · ' + config.durations[ value === 'haircut' ? sizeInput.value : 'individual' ] + ' Min.';
				}
				if ( detail.textContent ) { option.appendChild( detail ); }
				option.addEventListener( 'pointerdown', function ( event ) { event.preventDefault(); } );
				option.addEventListener( 'click', function () { choose( value ); } );
				list.appendChild( option ); options.push( option );
			} );
			if ( ! entries.length ) {
				var empty = document.createElement( 'div' ); empty.className = 'booking-options-empty';
				empty.textContent = 'Keine passende Rasse gefunden. Du kannst deinen Eintrag verwenden und die Größe auswählen.';
				list.appendChild( empty );
			}
		}
		function open() {
			if ( trigger.disabled ) { return; }
			dropdowns.forEach( function ( dropdown ) { dropdown.close(); } );
			render(); list.hidden = false; trigger.setAttribute( 'aria-expanded', 'true' );
		}
		function refresh() {
			if ( ! isBreed ) {
				trigger.disabled = control.disabled;
				trigger.textContent = control.options[ control.selectedIndex ] ? control.options[ control.selectedIndex ].textContent : 'Bitte auswählen';
				trigger.classList.toggle( 'is-placeholder', ! control.value );
				if ( control.disabled ) { close(); }
			}
		}
		if ( isBreed ) { trigger.addEventListener( 'focus', open ); trigger.addEventListener( 'input', open ); }
		trigger.addEventListener( 'click', function () { if ( isBreed || list.hidden ) { open(); } else { close(); } } );
		trigger.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'ArrowDown' || event.key === 'ArrowUp' ) {
				event.preventDefault(); if ( list.hidden ) { open(); }
				activate( active < 0 ? ( event.key === 'ArrowDown' ? 0 : options.length - 1 ) : active + ( event.key === 'ArrowDown' ? 1 : -1 ) );
			} else if ( event.key === 'Enter' && ! list.hidden && active >= 0 ) {
				event.preventDefault(); options[ active ].click();
			} else if ( event.key === 'Escape' ) { event.preventDefault(); close();
			} else if ( event.key === 'Tab' ) { close(); }
		} );
		wrapper.addEventListener( 'focusout', function ( event ) { if ( ! wrapper.contains( event.relatedTarget ) ) { close(); } } );
		document.addEventListener( 'pointerdown', function ( event ) { if ( ! wrapper.contains( event.target ) ) { close(); } } );
		control.addEventListener( 'invalid', function ( event ) { if ( ! isBreed ) { event.preventDefault(); trigger.focus(); open(); } } );
		var dropdown = { close: close, refresh: refresh }; dropdowns.push( dropdown ); refresh();
	}
	createDropdown( breedInput, true );
	createDropdown( sizeInput, false );

	function update() {
		var name = breedInput.value.trim().toLocaleLowerCase( 'de' );
		var breed = config.breeds.find( function ( entry ) { return entry.name.toLocaleLowerCase( 'de' ) === name; } );
		if ( breed && breed.size ) {
			sizeInput.value = breed.size;
		} else if ( name !== lastBreed ) {
			sizeInput.value = '';
		}
		lastBreed = name;
		sizeChoice.hidden = !! ( breed && breed.size );
		result.hidden = ! sizeInput.value;
		if ( sizeInput.value ) {
			result.querySelector( 'img' ).src = config.images[ sizeInput.value ];
			result.querySelector( 'span' ).textContent = 'Größenklasse: ' + config.sizes[ sizeInput.value ];
		}
		var haircutAllowed = ! breed || breed.haircut;
		careNote.hidden = haircutAllowed;
		serviceInputs.forEach( function ( input ) { if ( input.value === 'haircut' && ! haircutAllowed ) { input.checked = false; } } );
		var chosen = serviceInputs.filter( function ( input ) { return input.checked; } ).map( function ( input ) { return input.value; } );
		var total = 0, duration = 0, negotiated = false;
		var euro = new Intl.NumberFormat( 'de-DE', { style: 'currency', currency: 'EUR' } );
		serviceInputs.forEach( function ( input ) {
			var key = input.value;
			var priceKey = key === 'haircut' ? sizeInput.value : key;
			var minutes = config.durations[ key === 'haircut' ? sizeInput.value : 'individual' ];
			var value = config.values[ priceKey ];
			var numeric = typeof value === 'number';
			var cost = numeric ? value * ( [ 'brushing', 'extra' ].includes( key ) ? Math.ceil( minutes / 15 ) : 1 ) : 0;
			var included = chosen.some( function ( selected ) {
				return selected !== key && ( ( config.exclusions[ selected ] || [] ).includes( key ) || ( config.exclusions[ key ] || [] ).includes( selected ) );
			} );
			input.disabled = ! name || ! sizeInput.value || ( key === 'haircut' && ! haircutAllowed ) || included;
			input.closest( 'label' ).classList.toggle( 'is-disabled', input.disabled );
			input.closest( 'label' ).classList.toggle( 'is-selected', input.checked );
			input.closest( 'label' ).querySelector( '.service-detail' ).textContent = included ? 'Bereits enthalten / nicht zusätzlich kombinierbar' : ( sizeInput.value ? ( numeric ? euro.format( cost ) : value ) + ' · ' + minutes + ' Min.' : 'Bitte zuerst Rasse und Größe wählen' );
			if ( input.checked && ! input.disabled ) { total += cost; duration += minutes; negotiated = negotiated || ! numeric; }
		} );
		preview.textContent = chosen.length && sizeInput.value ? 'Gesamtpreis: ' + euro.format( total ) + ( negotiated ? ' + Preis nach Vereinbarung' : '' ) : 'Wähle deine Leistungen für den Gesamtpreis.';
		durationPreview.textContent = chosen.length && sizeInput.value ? 'Gesamtdauer: ' + duration + ' Minuten' : '';
		serviceInputs.forEach( function ( input ) { input.setCustomValidity( '' ); } );
		var validationInput = serviceInputs.find( function ( input ) { return ! input.disabled; } );
		if ( validationInput ) { validationInput.setCustomValidity( chosen.length ? '' : 'Bitte wähle mindestens eine Leistung.' ); }
		dropdowns.forEach( function ( dropdown ) { dropdown.refresh(); } );
	}
	[ breedInput, sizeInput ].concat( serviceInputs ).forEach( function ( input ) {
		input.addEventListener( 'input', function () { if ( next ) { next.hidden = true; } update(); } );
		input.addEventListener( 'change', function () { if ( next ) { next.hidden = true; } update(); } );
	} );
	update();
	if ( next ) {
		var edit = next.querySelector( '.dog-booking-edit' );
		form.hidden = true;
		next.classList.add( 'is-current-step' );
		edit.hidden = false;
		edit.addEventListener( 'click', function () {
			next.hidden = true;
			form.hidden = false;
			breedInput.focus( { preventScroll: true } );
			dropdowns.forEach( function ( dropdown ) { dropdown.close(); } );
			form.scrollIntoView( { block: 'start' } );
		} );
		next.focus( { preventScroll: true } );
		next.scrollIntoView( { block: 'start' } );
	}
} );
