<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function anubis_create_privacy_page() {
	if ( get_option( 'anubis_privacy_page_initialized' ) ) { return; }
	$page = get_page_by_path( 'datenschutz' );
	if ( ! $page ) {
		$file = get_template_directory() . '/content/datenschutz.html';
		if ( ! is_readable( $file ) ) { return; }
		$id = wp_insert_post( array(
			'post_type' => 'page', 'post_status' => 'publish',
			'post_title' => 'Datenschutzerklärung', 'post_name' => 'datenschutz',
			'post_content' => wp_slash( file_get_contents( $file ) ),
		), true );
		if ( is_wp_error( $id ) || ! $id ) { return; }
	} else { $id = $page->ID; }
	update_option( 'anubis_privacy_page_id', $id );
	update_option( 'anubis_privacy_page_initialized', 1 );
	if ( ! get_option( 'wp_page_for_privacy_policy' ) ) { update_option( 'wp_page_for_privacy_policy', $id ); }
}
add_action( 'init', 'anubis_create_privacy_page' );

/** Vom Betreiber vervollständigte Fassung einmalig übernehmen. */
function anubis_privacy_apply_short_copy() {
	if ( get_option( 'anubis_privacy_final_copy_applied' ) ) { return; }
	$page = get_post( (int) get_option( 'anubis_privacy_page_id' ) );
	$file = get_template_directory() . '/content/datenschutz.html';
	if ( ! $page || 'page' !== $page->post_type || ! is_readable( $file ) ) { return; }
	$result = wp_update_post( wp_slash( array( 'ID' => $page->ID, 'post_content' => file_get_contents( $file ) ) ), true );
	if ( ! is_wp_error( $result ) && $result ) { update_option( 'anubis_privacy_final_copy_applied', 1 ); }
}
add_action( 'init', 'anubis_privacy_apply_short_copy', 30 );

/** Entfernt einmalig die Telegram-Texte auch aus der bereits gespeicherten Seite. */
function anubis_remove_privacy_telegram_copy() {
	if ( get_option( 'anubis_privacy_telegram_removed' ) ) { return; }
	$page = get_post( (int) get_option( 'anubis_privacy_page_id' ) );
	if ( ! $page || 'page' !== $page->post_type ) { return; }
	$content = preg_replace( '~<h2>6\. Interne Terminmeldungen über Telegram</h2>.*?(?=<h2>7\.)~s', '', $page->post_content );
	$content = str_replace( array(
		' Bei aktivierter Telegram-Anbindung Empfänger, Speicherfristen und gegebenenfalls die Voraussetzungen für Drittlandübermittlungen klären.',
		'E-Mails, Nachrichten in Telegram oder separat',
	), array( '', 'E-Mails oder separat' ), $content );
	if ( $content !== $page->post_content ) {
		$content = preg_replace_callback( '~<h2>(7|8|9|10|11)\.~', function ( $match ) {
			return '<h2>' . ( (int) $match[1] - 1 ) . '.';
		}, $content );
		$result = wp_update_post( wp_slash( array( 'ID' => $page->ID, 'post_content' => $content ) ), true );
		if ( is_wp_error( $result ) || ! $result ) { return; }
	}
	update_option( 'anubis_privacy_telegram_removed', 1 );
}
add_action( 'init', 'anubis_remove_privacy_telegram_copy', 20 );

function anubis_privacy_url() {
	$id = (int) get_option( 'anubis_privacy_page_id' );
	return $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) : home_url( '/datenschutz/' );
}

function anubis_customize_privacy( $wp_customize ) {
	$wp_customize->add_section( 'anubis_privacy', array( 'title' => 'Datenschutz', 'priority' => 34 ) );
	if ( ! class_exists( 'Anubis_Privacy_Edit_Control' ) ) {
		class Anubis_Privacy_Edit_Control extends WP_Customize_Control {
			public function render_content() {
				$url = get_edit_post_link( (int) get_option( 'anubis_privacy_page_id' ), 'raw' );
				if ( ! $url ) { return; }
				echo '<p>Angaben und markierte offene Punkte im Seiteneditor bearbeiten. Änderungen im Customizer zuerst veröffentlichen.</p><p><a class="button button-primary" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">Datenschutz bearbeiten ↗</a></p><p>Öffnet den Seiteneditor in einem neuen Tab.</p>';
			}
		}
	}
	$wp_customize->add_control( new Anubis_Privacy_Edit_Control( $wp_customize, 'anubis_privacy_edit', array( 'section' => 'anubis_privacy', 'settings' => array() ) ) );
}
add_action( 'customize_register', 'anubis_customize_privacy' );
