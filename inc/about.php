<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

// Create once, preserving subsequent changes in the page editor.
add_action( 'init', function () {
	if ( get_option( 'anubis_about_page_initialized' ) ) { return; }
	$page = get_page_by_path( 'ueber-mich' );
	$id = $page ? $page->ID : wp_insert_post( wp_slash( array(
		'post_type' => 'page', 'post_status' => 'publish',
		'post_title' => 'Über mich', 'post_name' => 'ueber-mich',
		'post_content' => file_get_contents( get_template_directory() . '/content/ueber-mich.html' ),
	) ), true );
	if ( ! is_wp_error( $id ) && $id ) {
		update_option( 'anubis_about_page_id', (int) $id );
		update_option( 'anubis_about_page_initialized', 1 );
	}
}, 36 );

function anubis_about_url() {
	$id = (int) get_option( 'anubis_about_page_id' );
	return $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) : '';
}

add_action( 'customize_register', function ( $wp_customize ) {
	$wp_customize->add_section( 'anubis_about', array(
		'title' => 'Über mich', 'priority' => 33,
	) );
	$wp_customize->add_setting( 'anubis_about_text', array(
		'default' => '', 'sanitize_callback' => 'sanitize_textarea_field',
	) );
	$wp_customize->add_control( 'anubis_about_text', array(
		'label' => 'Persönlicher Text', 'section' => 'anubis_about', 'type' => 'textarea',
		'description' => 'Hier kannst du dich vorstellen. Eine Leerzeile beginnt einen neuen Absatz. Leer lassen, um den vorhandenen Seitentext zu verwenden.',
	) );
	$wp_customize->add_setting( 'anubis_about_image', array(
		'default' => 0, 'sanitize_callback' => 'absint',
	) );
	$wp_customize->add_control( new WP_Customize_Media_Control( $wp_customize, 'anubis_about_image', array(
		'label' => 'Persönliches Foto', 'section' => 'anubis_about', 'mime_type' => 'image',
		'description' => 'Foto aus der Mediathek auswählen oder hochladen. Ohne Foto bleibt der Bildplatzhalter sichtbar.',
	) ) );
} );

// Keep the existing page text as the fallback, including edits in the page editor.
add_filter( 'the_content', function ( $content ) {
	if ( ! is_page( (int) get_option( 'anubis_about_page_id' ) ) || ! in_the_loop() || ! is_main_query() ) { return $content; }
	$image_id = absint( get_theme_mod( 'anubis_about_image', 0 ) );
	$image = $image_id ? wp_get_attachment_image( $image_id, 'large', false, array( 'class' => 'about-photo' ) ) : '';
	if ( $image ) {
		$content = preg_replace_callback( '~<figure\b[^>]*class="about-photo-placeholder"[^>]*>.*?</figure>~s', function () use ( $image ) {
			return '<figure class="about-photo-frame">' . $image . '</figure>';
		}, $content, 1 );
	}
	$text = get_theme_mod( 'anubis_about_text', '' );
	if ( ! is_string( $text ) || '' === trim( $text ) ) { return $content; }
	return preg_replace_callback( '~(<div\b[^>]*class="about-copy"[^>]*>\s*<p\b[^>]*>.*?</p>\s*<h2\b[^>]*>.*?</h2>).*?(</div>)~s', function ( $match ) use ( $text ) {
		return $match[1] . "\n" . wpautop( esc_html( $text ) ) . $match[2];
	}, $content, 1 );
}, 20 );

add_filter( 'wp_nav_menu_items', function ( $items, $args ) {
	if ( 'primary' !== $args->theme_location ) { return $items; }
	$url = anubis_about_url();
	if ( ! $url || false !== strpos( $items, esc_url( $url ) ) ) { return $items; }
	$link = '<li class="menu-item"><a href="' . esc_url( $url ) . '"' . ( is_page( (int) get_option( 'anubis_about_page_id' ) ) ? ' aria-current="page"' : '' ) . '>Über mich</a></li>';
	return preg_replace_callback( '~</li>~', function ( $match ) use ( $link ) { return $match[0] . $link; }, $items, 1 );
}, 20, 2 );
