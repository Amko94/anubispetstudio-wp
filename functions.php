<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function anubis_theme_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'custom-logo' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );

	register_nav_menus( array(
		'primary' => __( 'Hauptmenü', 'anubis-theme' ),
	) );
}
add_action( 'after_setup_theme', 'anubis_theme_setup' );

function anubis_theme_scripts() {
	wp_enqueue_style( 'anubis-theme-style', get_stylesheet_uri(), array(), filemtime( get_stylesheet_directory() . '/style.css' ) );
	wp_enqueue_script( 'anubis-theme-nav', get_template_directory_uri() . '/assets/js/main.js', array(), filemtime( get_template_directory() . '/assets/js/main.js' ), true );
	if ( is_page( (int) get_option( 'anubis_booking_page_id' ) ) ) {
		wp_enqueue_script( 'anubis-booking', get_template_directory_uri() . '/assets/js/booking.js', array(), filemtime( get_template_directory() . '/assets/js/booking.js' ), true );
	}
}
add_action( 'wp_enqueue_scripts', 'anubis_theme_scripts' );

function anubis_hero_defaults() {
	return array(
        'title'       => 'Willkommen, Vierbeiner.',
        'description' => 'Hundefriseur in Nürnberg: Schnitt und Fellpflege mit Welpen-Eingewöhnung und viel Geduld.',
		'image'       => get_template_directory_uri() . '/assets/images/tisch_bild.jpeg',
		'image_alt'   => 'Grooming-Tisch im Anubis Pet Studio',
	);
}

function anubis_sanitize_hero_title( $value ) {
	return wp_kses( $value, array( 'em' => array(), 'strong' => array(), 'br' => array() ) );
}

function anubis_hero_content() {
	$content = anubis_hero_defaults();
	foreach ( $content as $key => $fallback ) {
		$value = get_theme_mod( 'anubis_hero_' . $key, $fallback );
		$content[ $key ] = is_string( $value ) && trim( $value ) !== '' ? $value : $fallback;
	}
	return $content;
}

function anubis_customize_hero( $wp_customize ) {
	$wp_customize->add_section( 'anubis_hero', array(
		'title'       => __( 'Startseite: Text & Bild', 'anubis-theme' ),
		'description' => __( 'Inhalte des geteilten Einstiegs. Leere Felder verwenden die ursprünglichen Inhalte.', 'anubis-theme' ),
		'priority'    => 30,
	) );

	$defaults = anubis_hero_defaults();
	$fields = array(
		'title'       => array( 'Überschrift', 'textarea', 'anubis_sanitize_hero_title' ),
		'description' => array( 'Beschreibung', 'textarea', 'sanitize_textarea_field' ),
		'image_alt'   => array( 'Bildbeschreibung (Alternativtext)', 'text', 'sanitize_text_field' ),
	);
	foreach ( $fields as $key => $field ) {
		$id = 'anubis_hero_' . $key;
		$wp_customize->add_setting( $id, array(
			'default'           => $defaults[ $key ],
			'sanitize_callback' => $field[2],
			'capability'        => 'edit_theme_options',
		) );
		$wp_customize->add_control( $id, array(
			'label'       => $field[0],
			'section'     => 'anubis_hero',
			'type'        => $field[1],
			'description' => 'title' === $key ? 'Mit <em>Text</em> kannst du ein Wort farbig hervorheben.' : '',
		) );
	}
	$wp_customize->add_setting( 'anubis_hero_image', array(
		'default'           => $defaults['image'],
		'sanitize_callback' => 'esc_url_raw',
		'capability'        => 'edit_theme_options',
	) );
	$wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'anubis_hero_image', array(
		'label'   => __( 'Bild für den Einstieg', 'anubis-theme' ),
		'section' => 'anubis_hero',
	) ) );
}
add_action( 'customize_register', 'anubis_customize_hero' );

function anubis_theme_fallback_menu() {
	echo '<ul id="m">';
	echo anubis_home_menu_item();
	echo '<li><a href="' . esc_url( anubis_prices_url() ) . '">Leistungen &amp; Preise</a></li>';
	echo '<li><a href="' . esc_url( home_url( '/#kontakt' ) ) . '">Kontakt</a></li>';
	echo '<li><a class="btn pri" href="' . esc_url( anubis_booking_url() ) . '">Termin buchen</a></li>';
	echo '</ul>';
}

function anubis_home_menu_item() {
	$current = is_front_page() ? ' aria-current="page"' : '';
	return '<li class="menu-item"><a href="' . esc_url( home_url( '/' ) ) . '"' . $current . '>Startseite</a></li>';
}

function anubis_add_home_menu_item( $items, $args ) {
	if ( 'primary' !== $args->theme_location ) { return $items; }
	$home_url = preg_quote( esc_url( home_url( '/' ) ), '~' );
	if ( preg_match( '~<a\b[^>]*\bhref=([\x22\x27])' . $home_url . '\\1~i', $items ) ) {
		return $items;
	}
	return anubis_home_menu_item() . $items;
}
add_filter( 'wp_nav_menu_items', 'anubis_add_home_menu_item', 10, 2 );

/** Einmalige Anlage; spätere Änderungen im Seiteneditor bleiben erhalten. */
function anubis_create_prices_page() {
	if ( get_option( 'anubis_prices_page_initialized' ) ) {
		return;
	}
	$page = get_page_by_path( 'leistungen-preise' );
	if ( $page ) {
		update_option( 'anubis_prices_page_id', $page->ID );
		update_option( 'anubis_prices_page_initialized', 1 );
		return;
	}
	$content_file = get_template_directory() . '/content/leistungen-preise.html';
	if ( ! is_readable( $content_file ) ) {
		return;
	}
	$page_id = wp_insert_post( array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Leistungen & Preise',
		'post_name'    => 'leistungen-preise',
		'post_content' => wp_slash( anubis_parameterize_prices( file_get_contents( $content_file ) ) ),
		'post_excerpt' => 'Haarschnitte ab [anubis_price key="haircut_min"], Einzelbehandlungen und Welpen-Eingewöhnung – ohne Baden und Föhnen.',
	), true );
	if ( ! is_wp_error( $page_id ) && $page_id ) {
		update_option( 'anubis_prices_page_id', $page_id );
		update_option( 'anubis_prices_page_initialized', 1 );
	}
}
add_action( 'init', 'anubis_create_prices_page' );

/** Entfernt einmalig die doppelten Texte aus der bereits angelegten Seite. */
function anubis_remove_repeated_prices_copy() {
	if ( (int) get_option( 'anubis_prices_copy_cleaned' ) >= 3 ) {
		return;
	}
	$page = get_post( (int) get_option( 'anubis_prices_page_id' ) );
	if ( ! $page || 'page' !== $page->post_type ) {
		return;
	}
	$content = str_replace(
		'<div class="price-signoff"><p class="eyebrow">ANUBIS PET STUDIO</p><p class="price-motto">Where dogs feel at home</p></div>',
		'',
		$page->post_content
	);
	$content = str_replace(
		'<div><dt>Außergewöhnlich hoher Pflegeaufwand<span>Der zusätzliche Aufwand und die Kosten werden vor Behandlungsbeginn besprochen.</span></dt><dd>individueller Preis</dd></div>',
		'',
		$content
	);
	$excerpt = str_replace( 'Professionelle Fellpflege mit Liebe, Geduld und Sorgfalt. ', '', $page->post_excerpt );
	$content = str_replace( array(
		'<p class="eyebrow">ANUBIS PET STUDIO</p>',
		'<p class="price-motto">Where dogs feel at home</p>',
	), '', $content );
	$result = wp_update_post( wp_slash( array(
		'ID'           => $page->ID,
		'post_content' => $content,
		'post_excerpt' => $excerpt,
	) ), true );
	if ( ! is_wp_error( $result ) && $result ) {
		update_option( 'anubis_prices_copy_cleaned', 3 );
	}
}
add_action( 'init', 'anubis_remove_repeated_prices_copy', 20 );

function anubis_prices_url() {
	$page_id = (int) get_option( 'anubis_prices_page_id' );
	return $page_id && 'publish' === get_post_status( $page_id ) ? get_permalink( $page_id ) : home_url( '/leistungen-preise/' );
}

/** Gemeinsame Preisvariablen für Preisliste, Kurztext und Startseite. */
function anubis_price_definitions() {
	return array(
		'small'     => array( 'Kleine Hunde', 50, 'haircuts', '', '' ),
		'medium'    => array( 'Mittelgroße Hunde', 60, 'haircuts', '', '' ),
		'large'     => array( 'Große Hunde', 70, 'haircuts', '', '' ),
		'nails'     => array( 'Krallen schneiden', 10, 'individual', '', '' ),
		'paws'      => array( 'Pfotenhaare schneiden', 10, 'individual', '', '' ),
		'eyes'      => array( 'Augenbereich freischneiden', 10, 'individual', '', '' ),
		'hygiene'   => array( 'Hygieneschnitt', 15, 'individual', '', '' ),
		'head'      => array( 'Gesicht und Kopf schneiden', 20, 'individual', '', '' ),
		'combo'     => array( 'Pfoten- und Augenpflege kombiniert', 15, 'individual', '', '' ),
		'brushing'  => array( 'Bürsten und Auskämmen', 15, 'individual', '', ' pro 15 Minuten' ),
		'extra'     => array( 'Zusätzliche Fellpflege', 15, 'individual', '', ' je weitere 15 Minuten' ),
		'puppy'     => array( 'Welpen-Eingewöhnung', 20, 'individual', '', '' ),
		'stripping' => array( 'Handtrimmen', 'Preis nach Vereinbarung', 'individual', '', '' ),
		'special'   => array( 'Spezielle Fellpflege', 'Preis nach Vereinbarung', 'individual', '', '' ),
		'mat_clean' => array( 'Gepflegtes Fell', 0, 'supplements', '', ' Aufpreis' ),
		'mat_light' => array( 'Leichte bis mittlere Verfilzungen', 20, 'supplements', 'ab ', ' Aufpreis' ),
		'mat_heavy' => array( 'Stärkere Verfilzungen', 40, 'supplements', 'ab ', ' Aufpreis' ),
	);
}

function anubis_sanitize_price_amount( $value ) {
	$value = str_replace( ',', '.', trim( (string) $value ) );
	return is_numeric( $value ) && is_finite( (float) $value ) && (float) $value >= 0 ? round( (float) $value, 2 ) : null;
}

function anubis_price_value( $key ) {
	$definitions = anubis_price_definitions();
	if ( ! isset( $definitions[ $key ] ) ) {
		return null;
	}
	$default = $definitions[ $key ][1];
	$value = get_theme_mod( 'anubis_price_' . $key, $default );
	if ( is_numeric( $default ) ) {
		$value = anubis_sanitize_price_amount( $value );
		return null === $value ? $default : $value;
	}
	return is_string( $value ) && trim( $value ) !== '' ? sanitize_text_field( $value ) : $default;
}

function anubis_price( $key ) {
	if ( 'haircut_min' === $key || 'individual_min' === $key ) {
		$keys = 'haircut_min' === $key ? array( 'small', 'medium', 'large' ) : array( 'nails', 'paws', 'eyes', 'hygiene', 'head', 'combo', 'brushing', 'extra', 'puppy' );
		$value = min( array_map( 'anubis_price_value', $keys ) );
		$prefix = $suffix = '';
	} else {
		$definitions = anubis_price_definitions();
		if ( ! isset( $definitions[ $key ] ) ) { return ''; }
		$value = anubis_price_value( $key );
		$prefix = $definitions[ $key ][3];
		$suffix = $definitions[ $key ][4];
	}
	if ( ! is_numeric( $value ) ) { return $value; }
	$decimals = (float) $value === (float) round( $value ) ? 0 : 2;
	return $prefix . number_format( $value, $decimals, ',', '.' ) . ' €' . $suffix;
}

function anubis_price_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'key' => '' ), $atts, 'anubis_price' );
	return esc_html( anubis_price( $atts['key'] ) );
}
add_shortcode( 'anubis_price', 'anubis_price_shortcode' );

function anubis_customize_prices( $wp_customize ) {
	$wp_customize->add_panel( 'anubis_prices', array(
		'title' => 'Leistungen & Preise', 'priority' => 31,
		'description' => 'Preise gelten gemeinsam für Startseite und Preisliste. Leere Felder verwenden die bisherigen Preise.',
	) );
	foreach ( array( 'haircuts' => 'Komplett-Haarschnitt', 'individual' => 'Einzelbehandlungen', 'supplements' => 'Verfilzungszuschläge' ) as $group => $label ) {
		$wp_customize->add_section( 'anubis_prices_' . $group, array( 'title' => $label, 'panel' => 'anubis_prices' ) );
	}
	foreach ( anubis_price_definitions() as $key => $definition ) {
		$id = 'anubis_price_' . $key;
		$numeric = is_numeric( $definition[1] );
		$wp_customize->add_setting( $id, array(
			'default' => $definition[1], 'capability' => 'edit_theme_options',
			'sanitize_callback' => $numeric ? 'anubis_sanitize_price_amount' : 'sanitize_text_field',
		) );
		$wp_customize->add_control( $id, array(
			'label' => $definition[0] . ( $numeric ? ' (€)' : '' ),
			'section' => 'anubis_prices_' . $definition[2],
			'type' => $numeric ? 'number' : 'text',
			'input_attrs' => $numeric ? array( 'min' => 0, 'step' => '0.01' ) : array(),
			'description' => trim( $definition[3] . $definition[4] ),
		) );
	}
}
add_action( 'customize_register', 'anubis_customize_prices' );

function anubis_parameterize_prices( $content ) {
	foreach ( anubis_price_definitions() as $key => $definition ) {
		$pattern = '~(<div>\s*<dt>' . preg_quote( $definition[0], '~' ) . '(?:<span>.*?</span>)?</dt>\s*<dd>).*?(</dd>\s*</div>)~s';
		$content = preg_replace_callback( $pattern, function ( $match ) use ( $key ) {
			return $match[1] . '[anubis_price key="' . $key . '"]' . $match[2];
		}, $content );
	}
	return $content;
}

function anubis_haircut_cards_shortcode() {
	$definitions = anubis_price_definitions();
	$html = '<dl class="haircut-cards">';
	foreach ( array( 'small', 'medium', 'large' ) as $size ) {
		$image = get_template_directory_uri() . '/assets/images/dogs/' . $size . '.png';
		$html .= '<div class="haircut-card"><dt><img src="' . esc_url( $image ) . '" alt="" width="1254" height="1254" loading="lazy" decoding="async"><span>' . esc_html( $definitions[ $size ][0] ) . '</span></dt><dd>' . esc_html( anubis_price( $size ) ) . '</dd></div>';
	}
	return $html . '</dl>';
}
add_shortcode( 'anubis_haircut_cards', 'anubis_haircut_cards_shortcode' );

function anubis_add_haircut_cards() {
	if ( get_option( 'anubis_haircut_cards_ready' ) ) { return; }
	$page = get_post( (int) get_option( 'anubis_prices_page_id' ) );
	if ( ! $page || 'page' !== $page->post_type ) { return; }
	$content = preg_replace(
		'~<dl class="price-list">\s*<div><dt>Kleine Hunde</dt><dd>.*?</dd></div>\s*<div><dt>Mittelgroße Hunde</dt><dd>.*?</dd></div>\s*<div><dt>Große Hunde</dt><dd>.*?</dd></div>\s*</dl>~s',
		'[anubis_haircut_cards]',
		$page->post_content
	);
	$result = wp_update_post( wp_slash( array( 'ID' => $page->ID, 'post_content' => $content ) ), true );
	if ( ! is_wp_error( $result ) && $result ) { update_option( 'anubis_haircut_cards_ready', 1 ); }
}
add_action( 'init', 'anubis_add_haircut_cards', 31 );

function anubis_remove_price_heading_numbers() {
	if ( get_option( 'anubis_price_heading_numbers_removed' ) ) { return; }
	$page = get_post( (int) get_option( 'anubis_prices_page_id' ) );
	if ( ! $page || 'page' !== $page->post_type ) { return; }
	$content = str_replace(
		array( '<h2>1. Komplett-Haarschnitt</h2>', '<h2>2. Einzelbehandlungen</h2>', '<h2>3. Zuschläge bei Verfilzungen</h2>', '<h2>4. Wichtige Informationen</h2>' ),
		array( '<h2>Komplett-Haarschnitt</h2>', '<h2>Einzelbehandlungen</h2>', '<h2>Zuschläge bei Verfilzungen</h2>', '<h2>Wichtige Informationen</h2>' ),
		$page->post_content
	);
	$result = wp_update_post( wp_slash( array( 'ID' => $page->ID, 'post_content' => $content ) ), true );
	if ( ! is_wp_error( $result ) && $result ) { update_option( 'anubis_price_heading_numbers_removed', 1 ); }
}
add_action( 'init', 'anubis_remove_price_heading_numbers', 32 );

function anubis_create_booking_page() {
	if ( get_option( 'anubis_booking_page_initialized' ) ) { return; }
	$page = get_page_by_path( 'termin-buchen' );
	if ( $page ) {
		update_option( 'anubis_booking_page_id', $page->ID );
		update_option( 'anubis_booking_page_initialized', 1 );
		return;
	}
	$file = get_template_directory() . '/content/termin-buchen.html';
	if ( ! is_readable( $file ) ) { return; }
	$id = wp_insert_post( array(
		'post_type' => 'page', 'post_status' => 'publish',
		'post_title' => 'Termin buchen', 'post_name' => 'termin-buchen',
		'post_content' => wp_slash( file_get_contents( $file ) ),
	), true );
	if ( ! is_wp_error( $id ) && $id ) {
		update_option( 'anubis_booking_page_id', $id );
		update_option( 'anubis_booking_page_initialized', 1 );
	}
}
add_action( 'init', 'anubis_create_booking_page', 33 );

function anubis_booking_url() {
	$id = (int) get_option( 'anubis_booking_page_id' );
	return $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) : home_url( '/termin-buchen/' );
}

function anubis_booking_shortcode() {
	return anubis_booking_selection();
}
add_shortcode( 'anubis_booking', 'anubis_booking_shortcode' );

function anubis_prices_link_shortcode() {
	return '<a href="' . esc_url( anubis_prices_url() ) . '">Leistungen &amp; Preise ansehen →</a>';
}
add_shortcode( 'anubis_prices_link', 'anubis_prices_link_shortcode' );

function anubis_booking_menu_link( $items, $args ) {
	if ( 'primary' !== $args->theme_location ) { return $items; }
	foreach ( $items as $item ) {
		if ( 'termin buchen' === strtolower( trim( wp_strip_all_tags( $item->title ) ) ) ) {
			$item->url = anubis_booking_url();
		}
	}
	return $items;
}
add_filter( 'wp_nav_menu_objects', 'anubis_booking_menu_link', 10, 2 );

/** Bestehende Seitentexte erhalten; nur die Preisfelder durch Variablen ersetzen. */
function anubis_migrate_price_variables() {
	if ( get_option( 'anubis_price_variables_ready' ) ) { return; }
	$page = get_post( (int) get_option( 'anubis_prices_page_id' ) );
	if ( ! $page || 'page' !== $page->post_type ) { return; }
	$result = wp_update_post( wp_slash( array(
		'ID' => $page->ID,
		'post_content' => anubis_parameterize_prices( $page->post_content ),
		'post_excerpt' => str_replace( 'Haarschnitte ab 50 €', 'Haarschnitte ab [anubis_price key="haircut_min"]', $page->post_excerpt ),
	) ), true );
	if ( ! is_wp_error( $result ) && $result ) { update_option( 'anubis_price_variables_ready', 1 ); }
}
add_action( 'init', 'anubis_migrate_price_variables', 30 );

function anubis_social_networks() {
	return array( 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'youtube' => 'YouTube' );
}

function anubis_social_defaults() {
	return array(
		'instagram' => 'https://www.instagram.com/anubispetstudio/',
		'tiktok'    => 'https://www.tiktok.com/@anubispetstudio8',
		'youtube'   => 'https://www.youtube.com/@anubispetstudio',
	);
}

function anubis_customize_social_links( $wp_customize ) {
	$defaults = anubis_social_defaults();
	$wp_customize->add_section( 'anubis_social_links', array(
		'title' => 'Social-Media-Links', 'priority' => 32,
		'description' => 'Profil-URLs für die Social-Media-Leiste am rechten Bildschirmrand. Nur ausgefüllte Links werden angezeigt.',
	) );
	foreach ( anubis_social_networks() as $key => $label ) {
		$id = 'anubis_social_' . $key;
		$wp_customize->add_setting( $id, array(
			'default' => $defaults[ $key ], 'sanitize_callback' => 'esc_url_raw', 'capability' => 'edit_theme_options',
		) );
		$wp_customize->add_control( $id, array(
			'label' => $label . ' – Profil-URL', 'section' => 'anubis_social_links', 'type' => 'url',
		) );
	}
}
add_action( 'customize_register', 'anubis_customize_social_links' );

function anubis_social_icon( $network ) {
	$icons = array(
		'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="17.5" cy="6.5" r="1.2" fill="currentColor"/>',
		'tiktok' => '<path fill="currentColor" d="M16.6 2h-3.4v13.2a3 3 0 1 1-2.6-3V8.8a6.4 6.4 0 1 0 6 6.4V8.5a9 9 0 0 0 5.4 1.8V6.9A5.5 5.5 0 0 1 16.6 2Z"/>',
		'youtube' => '<path fill="currentColor" d="M21.6 6.2a2.8 2.8 0 0 0-2-2C17.8 3.7 12 3.7 12 3.7s-5.8 0-7.6.5a2.8 2.8 0 0 0-2 2C2 8 2 12 2 12s0 4 .4 5.8a2.8 2.8 0 0 0 2 2c1.8.5 7.6.5 7.6.5s5.8 0 7.6-.5a2.8 2.8 0 0 0 2-2C22 16 22 12 22 12s0-4-.4-5.8Z"/><path fill="var(--social-icon-cutout,#fff)" d="m10 8.5 6 3.5-6 3.5Z"/>',
	);
	return isset( $icons[ $network ] ) ? '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">' . $icons[ $network ] . '</svg>' : '';
}

function anubis_render_social_links() {
	$links = array();
	$defaults = anubis_social_defaults();
	foreach ( anubis_social_networks() as $key => $label ) {
		$url = esc_url( get_theme_mod( 'anubis_social_' . $key, $defaults[ $key ] ), array( 'http', 'https' ) );
		if ( $url ) {
			$links[] = '<a class="social-link social-' . esc_attr( $key ) . '" href="' . $url . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $label . ' – Profil öffnen (neuer Tab)' ) . '" title="' . esc_attr( $label ) . '">' . anubis_social_icon( $key ) . '</a>';
		}
	}
	if ( $links ) {
		echo '<nav class="social-links" aria-label="Social Media">' . implode( '', $links ) . '</nav>';
	}
}

require_once get_template_directory() . '/inc/booking.php';
require_once get_template_directory() . '/inc/booking-retention.php';
require_once get_template_directory() . '/inc/booking-email.php';
require_once get_template_directory() . '/inc/impressum.php';
require_once get_template_directory() . '/inc/privacy.php';
require_once get_template_directory() . '/inc/telegram.php';
