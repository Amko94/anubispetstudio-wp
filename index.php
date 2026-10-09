<?php
/**
 * Hauptvorlage: Startseite für Anubis Pet Studio
 */
get_header();
$hero = anubis_hero_content();
?>

<section class="hero"><div class="w">
	<div class="hero-content">
		<h1><?php echo anubis_sanitize_hero_title( $hero['title'] ); ?></h1>
		<p><?php echo nl2br( esc_html( $hero['description'] ) ); ?></p>
	</div>
	<div class="hero-media">
		<img src="<?php echo esc_url( $hero['image'] ); ?>" alt="<?php echo esc_attr( $hero['image_alt'] ); ?>">
	</div>
</div></section>

<section id="leistungen"><div class="w">
	<h2>Leistungen &amp; Preise</h2>
	<p class="services-intro"><?php
	$prices_excerpt = get_post_field( 'post_excerpt', (int) get_option( 'anubis_prices_page_id' ) );
	echo wp_kses_post( do_shortcode( $prices_excerpt ?: 'Haarschnitte ab [anubis_price key="haircut_min"], Einzelbehandlungen und Welpen-Eingewöhnung – ohne Baden und Föhnen.' ) );
	?></p>
	<div class="g">
		<div class="card"><h3>Komplett-Haarschnitt</h3><p>Fell, Gesicht, Pfoten und Hygieneschnitt.</p><p class="service-price">ab <?php echo esc_html( anubis_price( 'haircut_min' ) ); ?></p></div>
		<div class="card"><h3>Einzelbehandlungen</h3><p>Gezielte Pflege für Krallen, Pfoten und Augen.</p><p class="service-price">ab <?php echo esc_html( anubis_price( 'individual_min' ) ); ?></p></div>
		<div class="card"><h3>Welpen-Eingewöhnung</h3><p>Ca. <?php echo esc_html( anubis_booking_duration( 'individual' ) ); ?> Minuten behutsames Kennenlernen.</p><p class="service-price"><?php echo esc_html( anubis_price( 'puppy' ) ); ?></p></div>
	</div>
	<p class="services-link"><a class="btn" href="<?php echo esc_url( anubis_prices_url() ); ?>">Alle Leistungen &amp; Preise ansehen →</a></p>
</div></section>

<section class="k" id="kontakt"><div class="w">
	<div>
		<h2>Kontakt</h2>
		<address class="contact-details">
			<p><a class="contact-link" href="tel:+4915773622141">
				<span class="contact-icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M22 16.9v3a2 2 0 0 1-2.2 2A19.8 19.8 0 0 1 3.1 5.2 2 2 0 0 1 5.1 3h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L9 10.9a16 16 0 0 0 6.1 6.1l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2"/></svg></span>
				<span>0157-73622141</span>
			</a></p>
			<p><a class="contact-link" href="mailto:info@anubispetstudio.de">
				<span class="contact-icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/></svg></span>
				<span>info@anubispetstudio.de</span>
			</a></p>
			<p><a class="contact-link contact-location" href="https://www.google.com/maps/search/?api=1&amp;query=Schumannstra%C3%9Fe%208%2C%2090429%20N%C3%BCrnberg%2C%20Deutschland" target="_blank" rel="noopener noreferrer">
				<span class="contact-icon"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
				<span>Schumannstraße 8<br>90429 Nürnberg, Deutschland</span>
			</a></p>
		</address>
		<p style="margin-top:14px"><?php echo esc_html( anubis_booking_hours_label() ); ?> · Samstag nur nach telefonischer Vereinbarung</p>
	</div>
</div></section>

<?php
get_footer();
