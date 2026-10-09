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

function anubis_impressum_url() {
	$page_id = (int) get_option( 'anubis_impressum_page_id' );
	return $page_id && 'publish' === get_post_status( $page_id ) ? get_permalink( $page_id ) : home_url( '/impressum/' );
}

/** Sichtbarer Einstieg im Customizer; Inhalte bleiben im WordPress-Seiteneditor. */
function anubis_customize_impressum( $wp_customize ) {
	$wp_customize->add_section( 'anubis_impressum', array(
		'title' => 'Impressum',
		'priority' => 33,
		'description' => 'Betreiber, Anschrift und alle weiteren Impressumsangaben werden direkt auf der WordPress-Seite bearbeitet.',
	) );
	if ( ! class_exists( 'Anubis_Impressum_Edit_Control' ) ) {
		class Anubis_Impressum_Edit_Control extends WP_Customize_Control {
			public function render_content() {
				$page_id = (int) get_option( 'anubis_impressum_page_id' );
				$url = $page_id ? get_edit_post_link( $page_id, 'raw' ) : admin_url( 'edit.php?post_type=page' );
				if ( ! $url ) {
					return;
				}
				echo '<p>Änderungen im Customizer zuerst veröffentlichen. Im Seiteneditor die Beispieldaten ersetzen und anschließend auf „Aktualisieren“ klicken.</p>';
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
