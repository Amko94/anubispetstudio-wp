<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Einmalige Anlage; vorhandene Seiten und spätere Änderungen bleiben erhalten. */
function anubis_create_impressum_page() {
	if ( get_option( 'anubis_impressum_page_initialized' ) ) {
		return;
	}
	$page = get_page_by_path( 'impressum' );
	if ( $page ) {
		update_option( 'anubis_impressum_page_id', $page->ID );
		update_option( 'anubis_impressum_page_initialized', 1 );
		return;
	}
	$file = get_template_directory() . '/content/impressum.html';
	if ( ! is_readable( $file ) ) {
		return;
	}
	$page_id = wp_insert_post( array(
		'post_type' => 'page',
		'post_status' => 'publish',
		'post_title' => 'Impressum',
		'post_name' => 'impressum',
		'post_content' => wp_slash( file_get_contents( $file ) ),
	), true );
	if ( ! is_wp_error( $page_id ) && $page_id ) {
		update_option( 'anubis_impressum_page_id', $page_id );
		update_option( 'anubis_impressum_page_initialized', 1 );
	}
}
add_action( 'init', 'anubis_create_impressum_page' );

/** Apply the requested template once; later edits in WordPress remain intact. */
function anubis_update_impressum_template() {
	if ( get_option( 'anubis_impressum_dynamic_template_ready' ) ) { return; }
	$page = get_post( (int) get_option( 'anubis_impressum_page_id' ) );
	if ( ! $page ) { $page = get_page_by_path( 'impressum' ); }
	$file = get_template_directory() . '/content/impressum.html';
	if ( ! $page || 'page' !== $page->post_type || ! is_readable( $file ) ) { return; }
	$content = file_get_contents( $file );
	if ( false === $content ) { return; }
	$result = wp_update_post( wp_slash( array( 'ID' => $page->ID, 'post_content' => $content ) ), true );
	if ( ! is_wp_error( $result ) && $result ) { update_option( 'anubis_impressum_dynamic_template_ready', 1 ); }
}
add_action( 'init', 'anubis_update_impressum_template', 20 );

function anubis_impressum_url() {
	$page_id = (int) get_option( 'anubis_impressum_page_id' );
	return $page_id && 'publish' === get_post_status( $page_id ) ? get_permalink( $page_id ) : home_url( '/impressum/' );
}

function anubis_impressum_field( $key ) {
	$contact_keys = array( 'street', 'city', 'phone', 'email' );
	$known = array_merge( $contact_keys, array( 'owner', 'salon', 'responsible', 'responsible_address' ) );
	if ( ! in_array( $key, $known, true ) ) { return ''; }
	$value = get_theme_mod( 'anubis_impressum_' . $key, '' );
	$value = is_string( $value ) ? ( 'email' === $key ? sanitize_email( $value ) : sanitize_text_field( $value ) ) : '';
	if ( '' !== trim( $value ) ) { return $value; }
	if ( in_array( $key, $contact_keys, true ) ) { return anubis_contact( $key ); }
	if ( 'owner' === $key ) { return 'Oghuzan Cetiner'; }
	if ( 'salon' === $key ) { return 'Anubis Pet Studio'; }
	if ( 'responsible' === $key ) { return anubis_impressum_field( 'owner' ); }
	return anubis_impressum_field( 'street' ) . ', ' . anubis_impressum_field( 'city' );
}

add_shortcode( 'anubis_impressum_field', function ( $atts ) {
	$atts = shortcode_atts( array( 'key' => '' ), $atts, 'anubis_impressum_field' );
	return esc_html( anubis_impressum_field( $atts['key'] ) );
} );

/** Overrides in the Customizer; page text also remains editable in WordPress. */
function anubis_customize_impressum( $wp_customize ) {
	$wp_customize->add_section( 'anubis_impressum', array(
		'title' => 'Impressum',
		'priority' => 33,
		'description' => 'Leere Felder verwenden die vorhandenen Daten: Oghuzan Cetiner, Anubis Pet Studio und die aktuellen Homepage-Kontaktdaten. Seitenüberschriften und Text bleiben im Seiteneditor bearbeitbar.',
	) );
	$labels = array(
		'owner' => 'Vorname und Nachname des Inhabers', 'salon' => 'Name des Salons',
		'street' => 'Straße und Hausnummer', 'city' => 'Postleitzahl, Ort und Land',
		'phone' => 'Telefonnummer', 'email' => 'E-Mail-Adresse',
		'responsible' => 'Inhaltlich verantwortlich (leer = Inhaber)',
		'responsible_address' => 'Anschrift des Verantwortlichen (leer = Impressumsanschrift)',
	);
	foreach ( $labels as $key => $label ) {
		$id = 'anubis_impressum_' . $key;
		$wp_customize->add_setting( $id, array( 'default' => '', 'capability' => 'edit_theme_options', 'sanitize_callback' => 'email' === $key ? 'sanitize_email' : 'sanitize_text_field' ) );
		$wp_customize->add_control( $id, array( 'label' => $label, 'section' => 'anubis_impressum', 'type' => 'email' === $key ? 'email' : 'text', 'description' => 'Aktueller Wert: ' . anubis_impressum_field( $key ) ) );
	}
	if ( ! class_exists( 'Anubis_Impressum_Edit_Control' ) ) {
		class Anubis_Impressum_Edit_Control extends WP_Customize_Control {
			public function render_content() {
				$page_id = (int) get_option( 'anubis_impressum_page_id' );
				$url = $page_id ? get_edit_post_link( $page_id, 'raw' ) : admin_url( 'edit.php?post_type=page' );
				if ( ! $url ) {
					return;
				}
				echo '<p>Änderungen im Customizer zuerst veröffentlichen. Im Seiteneditor kannst du Überschriften und Text bearbeiten. Die Shortcodes zeigen die Angaben aus diesen Feldern an.</p>';
				echo '<p><a class="button button-primary" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">Impressum bearbeiten ↗</a></p>';
				echo '<p>Öffnet den Seiteneditor in einem neuen Tab.</p>';
			}
		}
	}
	$wp_customize->add_control( new Anubis_Impressum_Edit_Control( $wp_customize, 'anubis_impressum_edit', array(
		'section' => 'anubis_impressum',
		'settings' => array(),
	) ) );
}
add_action( 'customize_register', 'anubis_customize_impressum' );
