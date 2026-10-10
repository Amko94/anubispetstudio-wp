<?php
/** Lightweight search metadata; WordPress continues to manage sitemaps and canonicals. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function anubis_seo_enabled() {
	return ! defined( 'WPSEO_VERSION' ) && ! defined( 'RANK_MATH_VERSION' ) && ! defined( 'AIOSEO_VERSION' ) && ! defined( 'SEOPRESS_VERSION' );
}

function anubis_seo_location() {
	$location = explode( ',', anubis_contact( 'city' ), 2 );
	$city = trim( $location[0] );
	$postal = '';
	if ( preg_match( '/^(\d{5})\s+(.+)$/u', $city, $match ) ) {
		$postal = $match[1];
		$city = $match[2];
	}
	return array( 'city' => $city, 'postal' => $postal );
}

function anubis_seo_metadata() {
	$city = anubis_seo_location()['city'];
	$brand = 'Anubis Pet Studio';
	if ( is_front_page() ) {
		return array( 'title' => 'Hundefriseur ' . $city . ' | ' . $brand, 'description' => 'Haarschnitte und Fellpflege für deinen Hund in ' . $city . '. Entdecke Leistungen und Preise bei Anubis Pet Studio und buche deinen Termin online.' );
	}
	$pages = array(
		'leistungen-preise' => array( 'title' => 'Hundepflege: Leistungen & Preise in ' . $city, 'description' => 'Preise für Hundehaarschnitte, Krallenpflege und Einzelbehandlungen bei Anubis Pet Studio in ' . $city . '. Ohne Baden und Föhnen.' ),
		'termin-buchen' => array( 'title' => 'Hundepflege-Termin in ' . $city . ' buchen', 'description' => 'Wähle Rasse, Hundegröße und Behandlungen und buche deinen Hundepflege-Termin bei Anubis Pet Studio in ' . $city . ' online.' ),
		'ueber-mich' => array( 'title' => 'Über mich', 'description' => 'Lerne den Menschen hinter Anubis Pet Studio in ' . $city . ' kennen. Persönliche Hundepflege mit Geduld und Rücksicht auf deinen Hund.' ),
		'galerie' => array( 'title' => 'Hundepflege: Vorher & Nachher', 'description' => 'Entdecke Vorher-Nachher-Bilder der Hundepflege bei Anubis Pet Studio in ' . $city . ' und erhalte Einblicke in unsere Arbeit.' ),
		'impressum' => array( 'title' => 'Impressum', 'description' => 'Anbieterangaben und Kontaktinformationen von Anubis Pet Studio in ' . $city . '.' ),
		'datenschutz' => array( 'title' => 'Datenschutz', 'description' => 'Informationen zum Datenschutz und zum Umgang mit personenbezogenen Daten bei Anubis Pet Studio.' ),
	);
	foreach ( $pages as $slug => $metadata ) {
		if ( is_page( $slug ) ) {
			$metadata['title'] .= ' | ' . $brand;
			return $metadata;
		}
	}
	$post = get_queried_object();
	$description = '';
	if ( $post instanceof WP_Post && ! post_password_required( $post ) ) {
		$description = wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_excerpt ?: $post->post_content ) ), 25, ' …' );
	}
	return array( 'title' => '', 'description' => $description );
}

add_filter( 'pre_get_document_title', function ( $title ) {
	if ( ! anubis_seo_enabled() || ( ! is_front_page() && ! is_page() ) ) { return $title; }
	$metadata = anubis_seo_metadata();
	return $metadata['title'] ?: $title;
} );

// Selection URLs contain customer-supplied dog information, not separate search pages.
add_filter( 'wp_robots', function ( $robots ) {
	if ( is_page( 'termin-buchen' ) ) {
		foreach ( array( 'dog_breed', 'dog_size', 'dog_service', 'dog_services', 'Hunderasse', 'Hundegröße' ) as $key ) {
			if ( isset( $_GET[ $key ] ) ) {
				unset( $robots['index'] );
				$robots['noindex'] = true;
				break;
			}
		}
	}
	return $robots;
} );

add_action( 'wp_head', function () {
	if ( ! anubis_seo_enabled() || is_feed() || ( ! is_front_page() && ! is_page() ) || post_password_required() ) { return; }
	$metadata = anubis_seo_metadata();
	$url = is_front_page() ? home_url( '/' ) : get_permalink( get_queried_object_id() );
	$hero = anubis_hero_content();
	$image = esc_url_raw( $hero['image'] );
	if ( $metadata['description'] ) {
		echo '<meta name="description" content="' . esc_attr( $metadata['description'] ) . '">' . "\n";
	}
	// Core already emits the canonical for singular pages, including a static front page.
	if ( is_front_page() && ! is_singular() ) {
		echo '<link rel="canonical" href="' . esc_url( $url ) . '">' . "\n";
	}
	$tags = array( 'og:type' => 'website', 'og:site_name' => 'Anubis Pet Studio', 'og:title' => $metadata['title'] ?: wp_get_document_title(), 'og:description' => $metadata['description'], 'og:url' => $url, 'og:locale' => get_locale(), 'og:image' => $image, 'og:image:alt' => $hero['image_alt'] );
	foreach ( $tags as $property => $content ) {
		if ( $content ) { echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '">' . "\n"; }
	}
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	if ( ! is_front_page() ) { return; }
	$location = anubis_seo_location();
	$address = array( '@type' => 'PostalAddress', 'streetAddress' => anubis_contact( 'street' ), 'addressLocality' => $location['city'] );
	if ( $location['postal'] ) { $address['postalCode'] = $location['postal']; }
	if ( preg_match( '/,\s*Deutschland\s*$/u', anubis_contact( 'city' ) ) ) { $address['addressCountry'] = 'DE'; }
	$business = array(
		'@context' => 'https://schema.org', '@type' => 'LocalBusiness', '@id' => home_url( '/#studio' ),
		'name' => 'Anubis Pet Studio', 'url' => home_url( '/' ), 'description' => $metadata['description'],
		'image' => $image, 'telephone' => substr( anubis_contact_phone_url(), 4 ), 'email' => anubis_contact( 'email' ), 'address' => $address,
	);
	// Online booking hours do not establish the studio's public opening hours.
	echo '<script type="application/ld+json">' . wp_json_encode( $business, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}, 5 );
