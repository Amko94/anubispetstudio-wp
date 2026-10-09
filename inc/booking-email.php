<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function anubis_booking_email_template() {
	return file_get_contents( get_template_directory() . '/content/booking-confirmation.html' );
}

// Synchronize only customer cancellation emails, without changing delivery settings.
add_action( 'init', function () {
	if ( ! function_exists( 'ssa' ) ) { return; }
	$message = file_get_contents( get_template_directory() . '/content/booking-cancellation.html' );
	$subject = 'Stornierung bestätigt – Anubis Pet Studio';
	$hash = sha1( $subject . $message );
	if ( get_option( 'anubis_cancellation_email_hash' ) === $hash ) { return; }
	$settings = ssa()->notifications_settings->get();
	$found = false;
	foreach ( $settings['notifications'] as &$notification ) {
		if ( 'email' !== $notification['type'] || 'appointment_canceled' !== $notification['trigger'] || ! in_array( '{{customer_email}}', $notification['sent_to'], true ) ) { continue; }
		$notification['subject'] = $subject;
		$notification['message'] = $message;
		$found = true;
	}
	unset( $notification );
	if ( $found ) {
		ssa()->notifications_settings->update( $settings );
		update_option( 'anubis_cancellation_email_hash', $hash, false );
	}
}, 46 );

add_filter( 'ssa/templates/get_template_vars', function ( $vars, $template ) {
	if ( 'notification' !== $template || empty( $vars['Appointment'] ) ) { return $vars; }
	$appointment = $vars['Appointment'];
	$type = $appointment['AppointmentType'] ?? array();
	$details = array( 'price' => 'Preis nach Vereinbarung', 'date' => '', 'time' => '', 'duration' => $type['duration'] ?? '', 'logo_url' => get_template_directory_uri() . '/assets/images/logo-email.png' );
	$booking_page_id = (int) get_option( 'anubis_booking_page_id' );
	$details['booking_url'] = $booking_page_id ? get_permalink( $booking_page_id ) : home_url( '/termin-buchen/' );
	$description = wp_strip_all_tags( $type['description'] ?? '' );
	if ( preg_match( '/Gesamtpreis:\s*(.*?)(?:\.\s+Ohne|$)/u', $description, $match ) ) { $details['price'] = trim( $match[1] ); }
	if ( ! empty( $appointment['start_date'] ) ) {
		$date = ssa()->utils->get_datetime_as_local_datetime( $appointment['start_date'], $appointment['appointment_type_id'] );
		$details['date'] = $date->format( 'd.m.Y' );
		$details['time'] = $date->format( 'H:i' );
		if ( ! empty( $appointment['end_date'] ) ) {
			$end = ssa()->utils->get_datetime_as_local_datetime( $appointment['end_date'], $appointment['appointment_type_id'] );
			$details['time'] .= ' – ' . $end->format( 'H:i' );
			$details['duration'] = (int) round( ( $end->getTimestamp() - $date->getTimestamp() ) / 60 );
		}
	}
	$vars['Anubis'] = $details;
	return $vars;
}, 10, 2 );

// Embed the logo in the email so it also works when the site runs on localhost.
add_filter( 'ssa/email/args', function ( $args ) {
	$url = get_template_directory_uri() . '/assets/images/logo-email.png';
	$args['message'] = str_replace( $url, 'cid:anubis-booking-logo', $args['message'] );
	return $args;
} );

add_action( 'phpmailer_init', function ( $mailer ) {
	if ( false !== strpos( $mailer->Body, 'cid:anubis-booking-logo' ) ) {
		$mailer->addEmbeddedImage( get_template_directory() . '/assets/images/logo-email.png', 'anubis-booking-logo', 'anubis-logo.png', 'base64', 'image/png' );
	}
} );
