<?php
/** Rasse → Größe → Leistung → SSA-Terminauswahl. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Start fetching the iframe's app assets before its WordPress document arrives.
add_action( 'wp_head', function () {
	if ( ! is_page( (int) get_option( 'anubis_booking_page_id' ) ) || ! function_exists( 'ssa' ) ) { return; }
	$plugin = ssa();
	$assets = array(
		'booking-app-new/dist/static/js/manifest.js' => 'script',
		'booking-app-new/dist/static/js/chunk-vendors.js' => 'script',
		'booking-app-new/dist/static/js/app.js' => 'script',
		'booking-app-new/dist/static/css/app.css' => 'style',
	);
	foreach ( $assets as $path => $type ) {
		$url = $plugin->url( $path . '?ver=' . $plugin::VERSION );
		echo '<link rel="preload" href="' . esc_url( $url ) . '" as="' . esc_attr( $type ) . '">' . "\n";
	}
}, 2 );

function anubis_dog_breeds() {
	static $breeds;
	if ( null === $breeds ) {
		$breeds = json_decode( file_get_contents( get_template_directory() . '/assets/dog-breeds.json' ), true );
		$breeds = is_array( $breeds ) ? $breeds : array();
	}
	return $breeds;
}

function anubis_dog_sizes() {
	return array( 'small' => 'Klein', 'medium' => 'Mittelgroß', 'large' => 'Groß' );
}

function anubis_booking_services() {
	$services = array( 'haircut' => 'Komplett-Haarschnitt' );
	foreach ( anubis_price_definitions() as $key => $definition ) {
		if ( 'individual' === $definition[2] ) { $services[ $key ] = $definition[0]; }
	}
	return $services;
}

function anubis_booking_query( $key ) {
	return isset( $_GET[ $key ] ) && is_string( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
}

function anubis_booking_choice( $name, $size, $service ) {
	$name = trim( $name );
	$breed = null;
	foreach ( anubis_dog_breeds() as $entry ) {
		if ( 0 === strcasecmp( $entry['name'], $name ) ) { $breed = $entry; break; }
	}
	if ( $breed ) {
		$name = $breed['name'];
		if ( $breed['size'] ) { $size = $breed['size']; }
	}
	$valid_size = isset( anubis_dog_sizes()[ $size ] );
	$selected = array_values( array_unique( array_filter( (array) $service, 'is_string' ) ) );
	$known = anubis_booking_services();
	$haircut_allowed = ! $breed || $breed['haircut'];
	$valid = '' !== $name && $valid_size && count( $selected ) > 0;
	$total = 0; $duration = 0; $negotiated = false;
	foreach ( $selected as $key ) {
		if ( ! isset( $known[ $key ] ) ) { $valid = false; continue; }
		if ( 'haircut' === $key && ! $haircut_allowed ) { $valid = false; }
		foreach ( anubis_service_exclusions()[ $key ] ?? array() as $excluded ) {
			if ( in_array( $excluded, $selected, true ) ) { $valid = false; }
		}
		$minutes = anubis_booking_duration( 'haircut' === $key ? $size : 'individual' );
		$value = anubis_price_value( 'haircut' === $key ? $size : $key );
		$duration += $minutes;
		if ( is_numeric( $value ) ) { $total += (float) $value * ( in_array( $key, array( 'brushing', 'extra' ), true ) ? ceil( $minutes / 15 ) : 1 ); }
		else { $negotiated = true; }
	}
	$price = number_format( $total, 2, ',', '.' ) . ' €' . ( $negotiated ? ' + Preis nach Vereinbarung' : '' );
	return array(
		'name' => $name, 'size' => $valid_size ? $size : '', 'service' => $selected,
		'breed' => $breed, 'haircut_allowed' => $haircut_allowed, 'valid' => $valid,
		'price' => $valid ? $price : '', 'duration' => $duration,
		'mapping' => count( $selected ) === 1 ? ( 'haircut' === $selected[0] ? 'haircut_' . $size : $selected[0] ) : '',
	);
}

function anubis_service_exclusions() {
	return array(
		'haircut' => array( 'paws', 'eyes', 'hygiene', 'head', 'combo' ),
		'combo' => array( 'paws', 'eyes' ),
	);
}

function anubis_combination_type( $choice ) {
	if ( ! $choice['valid'] || ! function_exists( 'ssa' ) ) { return 0; }
	if ( $choice['mapping'] ) { return absint( get_theme_mod( 'anubis_booking_type_' . $choice['mapping'], 0 ) ); }
	$published = anubis_public_booking_types();
	foreach ( $choice['service'] as $key ) {
		$mapping = 'haircut' === $key ? 'haircut_' . $choice['size'] : $key;
		if ( ! isset( $published[ absint( get_theme_mod( 'anubis_booking_type_' . $mapping, 0 ) ) ] ) ) { return 0; }
	}
	$keys = $choice['service']; sort( $keys );
	$slug = 'anubis-combination-' . md5( $choice['size'] . implode( '|', $keys ) );
	$model = ssa()->appointment_type_model;
	$existing = $model->query( array( 'slug' => $slug ) );
	$labels = array_intersect_key( anubis_booking_services(), array_flip( $keys ) );
	$data = array(
		'duration' => $choice['duration'], 'availability' => anubis_booking_availability(),
		'description' => implode( ' + ', $labels ) . ' · Gesamtpreis: ' . $choice['price'],
	);
	if ( ! empty( $existing[0]['id'] ) ) {
		if ( 'publish' !== $existing[0]['status'] ) { return 0; }
		$id = (int) $existing[0]['id'];
		$result = $model->update( $id, $data );
		if ( false === $result || ( is_array( $result ) && ! empty( $result['error'] ) ) ) { return 0; }
	} else {
		$id = $model->insert( array_merge( $data, array(
			'title' => implode( ' + ', $labels ), 'slug' => $slug, 'status' => 'publish',
			'capacity' => 1, 'capacity_type' => 'individual', 'has_max_capacity' => 1,
			'buffer_before' => 0, 'buffer_after' => 0, 'min_booking_notice' => 0,
			'availability_type' => 'available_blocks', 'availability_increment' => 10,
			'timezone_style' => 'locked', 'booking_layout' => 'week',
			'customer_information' => anubis_booking_customer_fields(),
			'notifications' => array( 'fields' => array( array( 'field' => 'admin', 'send' => true ), array( 'field' => 'customer', 'send' => true ) ), 'notifications_opt_in' => array( 'enabled' => false ) ),
			'location' => 'Schumannstraße 8, 90429 Nürnberg, Deutschland',
		) ) );
	}
	if ( ! is_numeric( $id ) || $id <= 0 ) { return 0; }
	$managed = get_option( 'anubis_ssa_combinations', array() );
	$managed[ (int) $id ] = array( 'size' => $choice['size'], 'services' => $keys );
	update_option( 'anubis_ssa_combinations', $managed, false );
	return (int) $id;
}

function anubis_public_booking_types() {
	if ( ! function_exists( 'ssa' ) ) { return array(); }
	$types = ssa()->appointment_type_model->get_all_appointment_types();
	$result = array();
	foreach ( $types as $type ) {
		if ( isset( $type['status'], $type['id'], $type['title'] ) && 'publish' === $type['status'] ) {
			$result[ (int) $type['id'] ] = $type['title'];
		}
	}
	return $result;
}

function anubis_booking_selection() {
	$choice = anubis_booking_choice( anubis_booking_query( 'dog_breed' ), anubis_booking_query( 'dog_size' ), isset( $_GET['dog_services'] ) && is_array( $_GET['dog_services'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_GET['dog_services'] ) ) : anubis_booking_query( 'dog_service' ) );
	$sizes = anubis_dog_sizes();
	$services = anubis_booking_services();
	$prices = array();
	foreach ( anubis_price_definitions() as $key => $definition ) { $prices[ $key ] = anubis_price( $key ); }
	$durations = array();
	foreach ( anubis_booking_duration_defaults() as $key => $default ) { $durations[ $key ] = anubis_booking_duration( $key ); }
	$values = array(); $images = array();
	foreach ( anubis_price_definitions() as $key => $definition ) { $values[ $key ] = anubis_price_value( $key ); }
	foreach ( $sizes as $key => $label ) { $images[ $key ] = get_template_directory_uri() . '/assets/images/dogs/' . $key . '.png'; }
	$config = array( 'values' => $values, 'images' => $images, 'exclusions' => anubis_service_exclusions(), 'breeds' => anubis_dog_breeds(), 'prices' => $prices, 'sizes' => $sizes, 'durations' => $durations );
	ob_start();
	?>
	<form class="dog-booking-form" action="<?php echo esc_url( anubis_booking_url() ); ?>" method="get" data-dog-booking="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
		<fieldset><legend>Welche Hunderasse hat dein Hund?</legend>
			<label for="dog-breed">Hunderasse suchen</label>
			<input id="dog-breed" name="dog_breed" list="dog-breed-list" value="<?php echo esc_attr( $choice['name'] ); ?>" placeholder="z. B. Malteser, Labradoodle oder Mischling" required maxlength="120" autocomplete="off" aria-describedby="dog-breed-help">
			<datalist id="dog-breed-list"><?php foreach ( anubis_dog_breeds() as $breed ) : ?><option value="<?php echo esc_attr( $breed['name'] ); ?>"></option><?php endforeach; ?></datalist>
			<p id="dog-breed-help" class="booking-help">Wähle auch die passende Variante, z. B. „Labradoodle (mittel)“. Ist deine Rasse nicht dabei, kannst du sie selbst eingeben.</p>
			<div class="dog-size-choice"<?php echo $choice['breed'] && $choice['breed']['size'] ? ' hidden' : ''; ?>>
				<label for="dog-size">Größe deines Hundes</label>
				<select id="dog-size" name="dog_size" required><option value="">Bitte auswählen</option><?php foreach ( $sizes as $key => $label ) : ?><option value="<?php echo esc_attr( $key ); ?>" <?php selected( $choice['size'], $key ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select>
				<p class="booking-help">Bei Mischlingen und Rassen ohne eindeutige Größenangabe benötigen wir deine Einschätzung.</p>
			</div>
			<div class="dog-size-result" aria-live="polite"<?php echo $choice['size'] ? '' : ' hidden'; ?>><img src="<?php echo esc_url( $images[ $choice['size'] ] ?? $images['small'] ); ?>" alt="" width="150" height="150"><span><?php echo $choice['size'] ? 'Größenklasse: ' . esc_html( $sizes[ $choice['size'] ] ) : ''; ?></span></div>
		</fieldset>
		<fieldset><legend>Welche Leistungen möchtest du buchen?</legend>
			<p class="booking-help">Wähle eine oder mehrere Behandlungen. Bereits enthaltene Leistungen werden nicht doppelt berechnet.</p>
			<div class="booking-services"><?php foreach ( $services as $key => $label ) : ?>
				<div class="booking-service-choice">
				<label class="booking-service"><input type="checkbox" name="dog_services[]" value="<?php echo esc_attr( $key ); ?>"<?php echo 'haircut' === $key ? ' aria-describedby="dog-care-note"' : ''; ?> <?php checked( in_array( $key, $choice['service'], true ) ); ?>><span><strong><?php echo esc_html( $label ); ?></strong><small class="service-detail"></small></span></label>
				<?php if ( 'haircut' === $key ) : ?><p id="dog-care-note" class="dog-care-note booking-help" aria-live="polite"<?php echo $choice['haircut_allowed'] ? ' hidden' : ''; ?>>Für diese Rasse sieht unsere Liste Teilpflege vor. Bitte wähle eine Einzelleistung.</p><?php endif; ?>
				</div>
			<?php endforeach; ?></div>
			<p class="dog-price-preview" aria-live="polite"><?php echo $choice['valid'] ? 'Gesamtpreis: ' . esc_html( $choice['price'] ) : ''; ?></p>
			<p class="dog-duration-preview booking-help" aria-live="polite"><?php echo $choice['valid'] ? 'Gesamtdauer: ' . esc_html( $choice['duration'] ) . ' Minuten' : ''; ?></p>
			<p class="booking-help">Der Preis gilt für sauberes, trockenes und gepflegtes Fell. Zuschläge besprechen wir vor der Behandlung.</p>
		</fieldset>
		<button class="btn pri" type="submit">Auswahl bestätigen →</button>
	</form>
	<?php
	if ( $choice['valid'] ) {
		echo '<div class="dog-booking-next" id="termine" tabindex="-1"><h2>Deine Auswahl</h2><p>' . esc_html( $choice['name'] . ' · ' . $sizes[ $choice['size'] ] . ' · ' . implode( ' + ', array_intersect_key( $services, array_flip( $choice['service'] ) ) ) ) . '</p><button class="btn dog-booking-edit" type="button" hidden>← Auswahl ändern</button>';
		$type_id = anubis_combination_type( $choice );
		$types = anubis_public_booking_types();
		if ( $type_id && isset( $types[ $type_id ] ) && shortcode_exists( 'ssa_booking' ) ) {
			// SSA übernimmt Query-Parameter in gleichnamige Kundenfelder.
			$_GET['Hunderasse'] = $choice['name'];
			$_GET['Hundegröße'] = $sizes[ $choice['size'] ];
			echo '<h3>Wähle deinen Termin</h3>';
			echo '<p class="booking-help">' . esc_html( anubis_booking_hours_label() ) . '. Samstag nur nach telefonischer Vereinbarung.</p>';
			echo '<div class="booking-calendar-frame" aria-busy="true"><div class="booking-calendar-loading" role="status"><span class="booking-calendar-spinner" aria-hidden="true"></span>Kalender wird geladen …</div>';
			echo do_shortcode( '[ssa_booking type="' . $type_id . '" accent_color="6e5239" background="fdfdfd" padding="0" date_view="month" time_view="rows" ssa_locale="de_DE"]' );
			echo '</div>';
		} else {
			echo '<p>Für diese Auswahl vereinbaren wir den Termin persönlich. Ruf uns unter <a href="' . esc_url( anubis_contact_phone_url() ) . '">' . esc_html( anubis_contact( 'phone' ) ) . '</a> an oder schreib an <a href="' . esc_url( 'mailto:' . anubis_contact( 'email' ) ) . '">' . esc_html( anubis_contact( 'email' ) ) . '</a>.</p>';
		}
		echo '</div>';
	}
	return ob_get_clean();
}

// Supply booking translations when the optional WordPress language pack is missing.
add_filter( 'gettext_simply-schedule-appointments', function ( $translated, $text, $domain ) {
	if ( is_admin() || 'de_DE' !== get_locale() || $translated !== $text ) { return $translated; }
	static $strings = array(
		'Select a date' => 'Wähle ein Datum', 'Select a time' => 'Wähle eine Uhrzeit',
		'Select a date and time' => 'Wähle Datum und Uhrzeit', 'Select a time on' => 'Wähle eine Uhrzeit am',
		'Go back' => 'Zurück', 'Back' => 'Zurück', 'Go forward' => 'Weiter',
		'Loading' => 'Wird geladen', 'Loading Available Appointments' => 'Freie Termine werden geladen',
		'Getting appointment information' => 'Termininformationen werden geladen',
		'Enter your contact information' => 'Deine Kontaktdaten', 'Confirm Selection' => 'Auswahl bestätigen',
		'Appointment date selected' => 'Datum ausgewählt', 'Appointment booked' => 'Termin gebucht',
		'Select an appointment type' => 'Wähle eine Behandlung', 'View your appointment' => 'Dein Termin',
		'Loading appointment' => 'Termin wird geladen', 'Book this appointment' => 'Termin verbindlich buchen',
		'Booking your appointment' => 'Dein Termin wird gebucht', 'Saving' => 'Wird gespeichert',
		'Saving your appointment information' => 'Deine Termindaten werden gespeichert',
		'You are booking: ' => 'Deine Buchung: ', 'Booking:' => 'Buchung:', 'When: ' => 'Wann: ',
		'Thank you! Your appointment is booked: ' => 'Vielen Dank! Dein Termin ist gebucht: ',
		'Thank you! Your appointment is booked' => 'Vielen Dank! Dein Termin ist gebucht',
		'Available: ' => 'Verfügbar: ', 'Next available appointment' => 'Nächster freier Termin',
		'Confirm Appointment Time' => 'Terminzeit bestätigen', 'Or, pick another time' => 'Oder wähle eine andere Uhrzeit',
		'Save to calendar' => 'Im Kalender speichern', 'Google calendar' => 'Google-Kalender', 'Other' => 'Andere',
		'A calendar invitation has been sent to your email address' => 'Eine Kalendereinladung wurde an deine E-Mail-Adresse gesendet',
		'minute' => 'Minute', 'minutes' => 'Minuten', 'hour' => 'Stunde', 'hours' => 'Stunden',
		'day' => 'Tag', 'days' => 'Tage', 'week' => 'Woche', 'weeks' => 'Wochen',
		'Night' => 'Nachts', 'Morning' => 'Vormittags', 'Afternoon' => 'Nachmittags', 'Evening' => 'Abends',
		'Email' => 'E-Mail', 'Phone' => 'Telefon', 'Notes' => 'Was sollten wir über deinen Hund wissen? (optional)', 'Address' => 'Adresse',
		'Enter a phone number' => 'Telefonnummer eingeben', 'Search country...' => 'Land suchen …',
		'City' => 'Ort', 'State' => 'Bundesland', 'Zip' => 'Postleitzahl',
		' is required.' => ' ist erforderlich.', 'A valid email address is required' => 'Bitte gib eine gültige E-Mail-Adresse ein',
		'Please enter a valid phone number' => 'Bitte gib eine gültige Telefonnummer ein',
		'Uh oh.' => 'Das hat leider nicht geklappt.', 'We ran into a problem: ' => 'Es gab ein Problem: ',
		'No available appointments' => 'Keine freien Termine',
		'Your timezone:' => 'Deine Zeitzone:', 'Select a timezone' => 'Wähle eine Zeitzone',
		'Change the timezone.' => 'Zeitzone ändern', 'Save timezone' => 'Zeitzone speichern',
		'Modify a booked appointment' => 'Gebuchten Termin ändern', 'Make a change' => 'Termin ändern',
		'Reschedule' => 'Termin verschieben', 'Cancel Appointment' => 'Termin absagen',
		'Are you sure you want to cancel?' => 'Möchtest du den Termin wirklich absagen?',
		'Keep Appointment' => 'Termin behalten', 'Canceled:' => 'Abgesagt:',
		'Canceling appointment' => 'Termin wird abgesagt', 'This appointment has been canceled' => 'Dieser Termin wurde abgesagt',
		'Edit Information' => 'Angaben ändern', 'Change selected time' => 'Uhrzeit ändern',
		'Schedule a new appointment' => 'Neuen Termin buchen', 'Update this appointment' => 'Termin aktualisieren',
		'What would you like to do next?' => 'Was möchtest du als Nächstes tun?',
	);
	return $strings[ $text ] ?? $translated;
}, 10, 3 );

add_action( 'ssa_booking_head', function () {
	$path = '/assets/booking-calendar.css';
	echo '<link rel="stylesheet" href="' . esc_url( get_template_directory_uri() . $path . '?ver=' . filemtime( get_template_directory() . $path ) ) . '">';
} );

add_action( 'ssa_booking_footer', function () {
	$path = '/assets/js/booking-fields.js';
	echo '<script src="' . esc_url( get_template_directory_uri() . $path . '?ver=' . filemtime( get_template_directory() . $path ) ) . '"></script>';
} );

add_action( 'init', function () {
	if ( ! function_exists( 'ssa' ) || get_option( 'anubis_booking_german_phone_ready' ) ) { return; }
	$settings = ssa()->settings->get();
	$global = $settings['global'];
	$global['country_code'] = 'DE';
	ssa()->settings->update_section( 'global', $global );
	$updated = ssa()->settings->get();
	if ( 'DE' === ( $updated['global']['country_code'] ?? '' ) ) { update_option( 'anubis_booking_german_phone_ready', 1 ); }
}, 43 );

add_filter( 'ssa/moment_format', function ( $format, $locale ) {
	if ( 'de_DE' !== $locale ) { return $format; }
	$formats = array( 'MMMM D, YYYY' => 'D. MMMM YYYY', 'h:mm a' => 'HH:mm', 'MMMM D, YYYY h:mm a' => 'D. MMMM YYYY HH:mm' );
	return $formats[ $format ] ?? $format;
}, 10, 2 );

function anubis_customize_booking_types( $wp_customize ) {
	$wp_customize->add_section( 'anubis_booking_types', array(
		'title' => 'Buchung: Leistungen zuordnen', 'priority' => 33,
		'description' => 'Leistungen mit veröffentlichten SSA-Terminarten verbinden. Dort auch Kundenfelder „Hunderasse“ und „Hundegröße“ anlegen, damit die Vorauswahl im Termin gespeichert wird. Ohne Zuordnung wird persönliche Terminvereinbarung angezeigt.',
	) );
	$choices = array( 0 => 'Persönliche Terminvereinbarung' ) + anubis_public_booking_types();
	$services = array();
	foreach ( anubis_dog_sizes() as $size => $label ) { $services[ 'haircut_' . $size ] = 'Komplett-Haarschnitt – ' . $label; }
	$services += array_diff_key( anubis_booking_services(), array( 'haircut' => true ) );
	foreach ( $services as $key => $label ) {
		$id = 'anubis_booking_type_' . $key;
		$wp_customize->add_setting( $id, array( 'default' => 0, 'sanitize_callback' => 'absint', 'capability' => 'edit_theme_options' ) );
		$wp_customize->add_control( $id, array( 'label' => $label, 'section' => 'anubis_booking_types', 'type' => 'select', 'choices' => $choices ) );
	}
}
add_action( 'customize_register', 'anubis_customize_booking_types' );

function anubis_booking_duration_defaults() {
	return array( 'small' => 40, 'medium' => 50, 'large' => 60, 'individual' => 30 );
}

function anubis_sanitize_booking_duration( $value ) {
	return filter_var( $value, FILTER_VALIDATE_INT, array( 'options' => array( 'min_range' => 5, 'max_range' => 480 ) ) ) !== false ? (int) $value : null;
}

function anubis_booking_duration( $key ) {
	$defaults = anubis_booking_duration_defaults();
	if ( ! isset( $defaults[ $key ] ) ) { return 30; }
	$value = anubis_sanitize_booking_duration( get_theme_mod( 'anubis_duration_' . $key, $defaults[ $key ] ) );
	return null === $value ? $defaults[ $key ] : $value;
}

function anubis_sanitize_booking_time( $value ) {
	return is_string( $value ) && preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ? $value : null;
}

function anubis_booking_hours() {
	$start = anubis_sanitize_booking_time( get_theme_mod( 'anubis_booking_start', '09:00' ) );
	$end = anubis_sanitize_booking_time( get_theme_mod( 'anubis_booking_end', '19:00' ) );
	if ( ! $start || ! $end || $start >= $end ) { return array( '09:00', '19:00' ); }
	return array( $start, $end );
}

function anubis_booking_hours_label() {
	$hours = anubis_booking_hours();
	return 'Mo – Fr ' . $hours[0] . ' – ' . $hours[1] . ' Uhr';
}

function anubis_booking_availability() {
	$hours = anubis_booking_hours();
	$availability = array();
	foreach ( array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday' ) as $day ) {
		$availability[ $day ] = in_array( $day, array( 'Saturday', 'Sunday' ), true ) ? array() : array( array( 'time_start' => $hours[0] . ':00', 'time_end' => $hours[1] . ':00' ) );
	}
	return $availability;
}

function anubis_customize_booking_schedule( $wp_customize ) {
	$wp_customize->add_section( 'anubis_booking_schedule', array(
		'title' => 'Buchung: Dauer & Zeiten', 'priority' => 32,
		'description' => 'Dauer in Minuten. Die Werte werden mit den von diesem Theme angelegten SSA-Terminarten synchronisiert. Buchbar Mo–Fr; Samstag nur telefonisch.',
	) );
	$labels = array( 'small' => 'Komplett-Haarschnitt: kleine Hunde', 'medium' => 'Komplett-Haarschnitt: mittelgroße Hunde', 'large' => 'Komplett-Haarschnitt: große Hunde', 'individual' => 'Alle Einzelbehandlungen' );
	foreach ( anubis_booking_duration_defaults() as $key => $default ) {
		$id = 'anubis_duration_' . $key;
		$wp_customize->add_setting( $id, array( 'default' => $default, 'sanitize_callback' => 'anubis_sanitize_booking_duration', 'capability' => 'edit_theme_options' ) );
		$wp_customize->add_control( $id, array( 'label' => $labels[ $key ] . ' (Minuten)', 'section' => 'anubis_booking_schedule', 'type' => 'number', 'input_attrs' => array( 'min' => 5, 'max' => 480, 'step' => 1 ) ) );
	}
	foreach ( array( 'start' => array( 'Beginn Mo–Fr', '09:00' ), 'end' => array( 'Ende Mo–Fr', '19:00' ) ) as $key => $field ) {
		$id = 'anubis_booking_' . $key;
		$wp_customize->add_setting( $id, array( 'default' => $field[1], 'sanitize_callback' => 'anubis_sanitize_booking_time', 'capability' => 'edit_theme_options' ) );
		$wp_customize->add_control( $id, array( 'label' => $field[0], 'section' => 'anubis_booking_schedule', 'type' => 'time' ) );
	}
}
add_action( 'customize_register', 'anubis_customize_booking_schedule' );

function anubis_duration_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'key' => 'individual' ), $atts, 'anubis_duration' );
	return esc_html( anubis_booking_duration( $atts['key'] ) );
}
add_shortcode( 'anubis_duration', 'anubis_duration_shortcode' );

function anubis_managed_booking_services() {
	$result = array();
	foreach ( anubis_dog_sizes() as $size => $label ) {
		$result[ 'haircut_' . $size ] = array( 'title' => 'Komplett-Haarschnitt – ' . $label, 'duration' => $size, 'price' => $size );
	}
	foreach ( array_diff_key( anubis_booking_services(), array( 'haircut' => true ) ) as $key => $label ) {
		$result[ $key ] = array( 'title' => $label, 'duration' => 'individual', 'price' => $key );
	}
	return $result;
}

function anubis_booking_type_details( $definition ) {
	return array(
		'duration' => anubis_booking_duration( $definition['duration'] ),
		'availability' => anubis_booking_availability(),
		'description' => 'Gesamtpreis: ' . anubis_price( $definition['price'] ) . '. Ohne Baden und Föhnen. Zuschläge nach Fellzustand werden vor der Behandlung besprochen.',
	);
}

function anubis_booking_customer_fields( $custom = false ) {
	$definitions = array( 'Name' => array( 'single-text', 'face' ), 'Email' => array( 'single-text', 'email' ), 'Phone' => array( 'phone', 'phone' ), 'Notes' => array( 'multi-text', 'description' ) );
	if ( $custom ) { $definitions += array( 'Hunderasse' => array( 'single-text', 'pets' ), 'Hundegröße' => array( 'single-text', 'pets' ) ); }
	$fields = array();
	foreach ( $definitions as $field => $properties ) {
		$fields[] = array( 'field' => $field, 'type' => $properties[0], 'icon' => $properties[1], 'display' => true, 'required' => in_array( $field, array( 'Name', 'Email' ), true ), 'values' => array() );
	}
	return $fields;
}

/** Keep customer booking confirmations; team notifications will use Telegram. */
add_filter( 'ssa/email/args', function ( $args ) {
	$headers = is_array( $args['headers'] ) ? $args['headers'] : preg_split( '/\r?\n/', $args['headers'] );
	foreach ( $headers as $header ) {
		if ( 0 === stripos( trim( $header ), 'From:' ) ) { return $args; }
	}
	$email = ! empty( $args['from_email'] ) && is_email( $args['from_email'] ) ? $args['from_email'] : 'info@anubispetstudio.de';
	$headers[] = 'From: Anubis Pet Studio <' . $email . '>';
	$args['headers'] = $headers;
	return $args;
} );

add_action( 'init', function () {
	if ( ! function_exists( 'ssa' ) || get_option( 'anubis_booking_logo_only_email_ready' ) ) { return; }
	$settings = ssa()->settings->get();
	$global = $settings['global'];
	$global['admin_email'] = 'info@anubispetstudio.de';
	ssa()->settings->update_section( 'global', $global );
	$notifications = ssa()->notifications_settings->get();
	$notifications['enabled'] = true;
	$found = array();
	foreach ( $notifications['notifications'] as &$notification ) {
		if ( 'email' !== $notification['type'] || 'appointment_booked' !== $notification['trigger'] ) { continue; }
		$admin = in_array( '{{admin_email}}', $notification['sent_to'], true );
		$customer = in_array( '{{customer_email}}', $notification['sent_to'], true );
		if ( ! $admin && ! $customer ) { continue; }
		$notification['active'] = $customer;
		if ( $admin && $customer ) {
			$notification['sent_to'] = array_values( array_diff( $notification['sent_to'], array( '{{admin_email}}' ) ) );
		}
		$notification['when'] = 'after';
		$notification['duration'] = 0;
		$found[ $admin ? 'admin' : 'customer' ] = true;
		$notification['subject'] = $admin ? 'Neue Terminbuchung: {{ Appointment.customer_information.Name }}' : 'Terminbestätigung – Anubis Pet Studio';
		$notification['message'] = $admin
			? '<p>Eine neue Terminbuchung ist eingegangen.</p><p><strong>Behandlung:</strong> {{ Appointment.AppointmentType.title }}<br><strong>Termin:</strong> {{ Appointment.business_start_date }}</p><p><strong>Kundendaten und Hinweise:</strong><br>{{ Appointment.customer_information_summary }}</p>'
			: anubis_booking_email_template();
	}
	unset( $notification );
	ssa()->notifications_settings->update( $notifications );
	if ( ! empty( $found['admin'] ) && ! empty( $found['customer'] ) ) { update_option( 'anubis_booking_logo_only_email_ready', 1 ); }
}, 45 );

/** Erstellt ausschließlich eigene Terminarten; bestehende Plugin-Terminarten bleiben erhalten. */
function anubis_initialize_booking_types() {
	if ( ! function_exists( 'ssa' ) || get_option( 'anubis_ssa_types_initialized' ) ) { return; }
	if ( ! add_option( 'anubis_ssa_setup_lock', time(), '', false ) ) { return; }
	$model = ssa()->appointment_type_model;
	$managed = get_option( 'anubis_ssa_managed_types', array() );
	$settings = ssa()->settings->get();
	$global = $settings['global'];
	$global['timezone_string'] = 'Europe/Berlin';
	$global['admin_email'] = 'info@anubispetstudio.de';
	ssa()->settings->update_section( 'global', $global );
	$complete = true;
	foreach ( anubis_managed_booking_services() as $key => $definition ) {
		if ( isset( $managed[ $key ] ) ) { continue; }
		$slug = 'anubis-' . str_replace( '_', '-', $key );
		$existing = $model->query( array( 'slug' => $slug ) );
		$id = ! empty( $existing[0]['id'] ) ? (int) $existing[0]['id'] : 0;
		if ( ! $id ) {
			$id = $model->insert( array_merge( anubis_booking_type_details( $definition ), array(
				'title' => $definition['title'], 'slug' => $slug, 'status' => 'publish',
				'capacity' => 1, 'capacity_type' => 'individual', 'has_max_capacity' => 1,
				'buffer_before' => 0, 'buffer_after' => 0, 'min_booking_notice' => 0,
				'availability_type' => 'available_blocks', 'availability_increment' => 10,
				'timezone_style' => 'locked', 'booking_layout' => 'week',
				'customer_information' => anubis_booking_customer_fields(),
				'custom_customer_information' => anubis_booking_customer_fields( true ),
				'notifications' => array( 'fields' => array( array( 'field' => 'admin', 'send' => true ), array( 'field' => 'customer', 'send' => true ) ), 'notifications_opt_in' => array( 'enabled' => false ) ),
				'location' => 'Schumannstraße 8, 90429 Nürnberg, Deutschland',
			) ) );
		}
		if ( is_numeric( $id ) && $id > 0 ) {
			$managed[ $key ] = (int) $id;
			set_theme_mod( 'anubis_booking_type_' . $key, (int) $id );
			update_option( 'anubis_ssa_managed_types', $managed );
		} else { $complete = false; }
	}
	if ( $complete ) { update_option( 'anubis_ssa_types_initialized', 1 ); }
	delete_option( 'anubis_ssa_setup_lock' );
}
add_action( 'init', 'anubis_initialize_booking_types', 40 );

function anubis_initialize_booking_customer_fields() {
	if ( ! function_exists( 'ssa' ) || ! get_option( 'anubis_ssa_types_initialized' ) || get_option( 'anubis_ssa_customer_fields_ready' ) ) { return; }
	$success = true;
	foreach ( get_option( 'anubis_ssa_managed_types', array() ) as $id ) {
		$result = ssa()->appointment_type_model->update( $id, array( 'customer_information' => anubis_booking_customer_fields(), 'custom_customer_information' => anubis_booking_customer_fields( true ) ) );
		if ( false === $result || ( is_array( $result ) && ! empty( $result['error'] ) ) ) { $success = false; }
	}
	if ( $success ) { update_option( 'anubis_ssa_customer_fields_ready', 1 ); }
}
add_action( 'init', 'anubis_initialize_booking_customer_fields', 41 );

/** Aktualisiert nur bei geänderten Variablen, nicht bei jedem Seitenaufruf. */
function anubis_sync_booking_types() {
	if ( ! function_exists( 'ssa' ) || ! get_option( 'anubis_ssa_types_initialized' ) || is_customize_preview() ) { return; }
	$payload = array();
	foreach ( anubis_managed_booking_services() as $key => $definition ) { $payload[ $key ] = anubis_booking_type_details( $definition ); }
	foreach ( get_option( 'anubis_ssa_combinations', array() ) as $id => $combination ) {
		$choice = anubis_booking_choice( 'Mischling', $combination['size'], $combination['services'] );
		$payload[ 'combination_' . $id ] = array( 'duration' => $choice['duration'], 'availability' => anubis_booking_availability(), 'description' => 'Gesamtpreis: ' . $choice['price'] );
	}
	$hash = md5( wp_json_encode( $payload ) );
	if ( get_option( 'anubis_ssa_schedule_hash' ) === $hash ) { return; }
	$model = ssa()->appointment_type_model;
	$success = true;
	$managed = get_option( 'anubis_ssa_managed_types', array() );
	foreach ( get_option( 'anubis_ssa_combinations', array() ) as $id => $combination ) { $managed[ 'combination_' . $id ] = $id; }
	foreach ( $managed as $key => $id ) {
		if ( ! isset( $payload[ $key ] ) ) { continue; }
		$record = $model->get( $id );
		if ( ! $record || 'delete' === $record['status'] ) { continue; }
		$result = $model->update( $id, $payload[ $key ] );
		if ( false === $result || ( is_array( $result ) && ! empty( $result['error'] ) ) ) { $success = false; }
	}
	if ( $success ) { update_option( 'anubis_ssa_schedule_hash', $hash ); }
}
add_action( 'init', 'anubis_sync_booking_types', 41 );
add_action( 'customize_save_after', 'anubis_sync_booking_types' );

function anubis_update_booking_copy() {
	if ( get_option( 'anubis_booking_copy_updated' ) ) { return; }
	$updates = array(
		'anubis_booking_page_id' => array(
			'Wähle die passende Behandlung und einen freien Termin für deinen Hund.' => 'Wähle zuerst die Hunderasse, danach die passende Behandlung und deinen Termin.',
			'<h3>Fragen vor der Buchung?</h3>' => '<p>Online-Termine: [anubis_booking_hours]. Für Samstag ruf uns bitte an.</p><h3>Fragen vor der Buchung?</h3>',
		),
		'anubis_prices_page_id' => array( 'Ca. 20 Minuten behutsames Kennenlernen' => 'Ca. [anubis_duration key="individual"] Minuten behutsames Kennenlernen' ),
	);
	$success = true;
	foreach ( $updates as $option => $replacements ) {
		$page = get_post( (int) get_option( $option ) );
		if ( ! $page ) { $success = false; continue; }
		$result = wp_update_post( wp_slash( array( 'ID' => $page->ID, 'post_content' => str_replace( array_keys( $replacements ), array_values( $replacements ), $page->post_content ) ) ), true );
		if ( is_wp_error( $result ) || ! $result ) { $success = false; }
	}
	if ( $success ) { update_option( 'anubis_booking_copy_updated', 1 ); }
}
add_action( 'init', 'anubis_update_booking_copy', 42 );
add_action( 'init', function () {
	if ( get_option( 'anubis_booking_intro_removed' ) ) { return; }
	$page = get_post( (int) get_option( 'anubis_booking_page_id' ) );
	if ( ! $page ) { return; }
	$content = preg_replace( '/<p\b[^>]*>\s*(?:Wähle zuerst die Hunderasse, danach die passende Behandlung und deinen Termin\.|Wähle die passende Behandlung und einen freien Termin für deinen Hund\.)\s*<\/p>\s*/u', '', $page->post_content );
	if ( $content !== $page->post_content ) {
		$result = wp_update_post( array( 'ID' => $page->ID, 'post_content' => $content ), true );
		if ( is_wp_error( $result ) ) { return; }
	}
	update_option( 'anubis_booking_intro_removed', 1 );
}, 44 );
add_shortcode( 'anubis_booking_hours', function () { return esc_html( anubis_booking_hours_label() ); } );
