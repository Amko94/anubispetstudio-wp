<?php
/** Kontaktdaten für Startseite und persönliche Terminvereinbarung. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function anubis_contact_defaults() {
	return array(
		'phone' => '0157-73622141',
		'email' => 'info@anubispetstudio.de',
		'street' => 'Schumannstraße 8',
		'city' => '90429 Nürnberg, Deutschland',
	);
}

function anubis_contact( $key ) {
	$defaults = anubis_contact_defaults();
	if ( ! isset( $defaults[ $key ] ) ) { return ''; }
	$value = get_theme_mod( 'anubis_contact_' . $key, $defaults[ $key ] );
	$value = 'email' === $key ? sanitize_email( $value ) : sanitize_text_field( $value );
	return '' !== trim( $value ) ? $value : $defaults[ $key ];
}

function anubis_contact_phone_url() {
	$phone = preg_replace( '/[^0-9+]/', '', anubis_contact( 'phone' ) );
	if ( 0 === strpos( $phone, '00' ) ) { $phone = '+' . substr( $phone, 2 ); }
	elseif ( 0 === strpos( $phone, '0' ) ) { $phone = '+49' . substr( $phone, 1 ); }
	return 'tel:' . $phone;
}

function anubis_contact_address() {
	return anubis_contact( 'street' ) . ', ' . anubis_contact( 'city' );
}

function anubis_contact_maps_url() {
	return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( anubis_contact_address() );
}

function anubis_customize_contact( $wp_customize ) {
	$wp_customize->add_section( 'anubis_contact', array(
		'title' => 'Kontaktdaten', 'priority' => 32,
		'description' => 'Kontaktdaten auf der Startseite und bei der persönlichen Terminvereinbarung. Der Kartenlink wird aus der Adresse erzeugt. Leere Felder verwenden die bisherigen Daten. Inhalte im Seiteneditor und E-Mail-Vorlagen werden separat bearbeitet.',
	) );
	$labels = array( 'phone' => 'Telefonnummer (ohne Landesvorwahl gilt +49)', 'email' => 'E-Mail-Adresse', 'street' => 'Straße und Hausnummer', 'city' => 'Postleitzahl, Ort und Land' );
	foreach ( anubis_contact_defaults() as $key => $default ) {
		$id = 'anubis_contact_' . $key;
		$wp_customize->add_setting( $id, array(
			'default' => $default, 'capability' => 'edit_theme_options',
			'sanitize_callback' => 'email' === $key ? 'sanitize_email' : 'sanitize_text_field',
		) );
		$wp_customize->add_control( $id, array(
			'label' => $labels[ $key ], 'section' => 'anubis_contact',
			'type' => 'email' === $key ? 'email' : 'text',
		) );
	}
}
add_action( 'customize_register', 'anubis_customize_contact' );
