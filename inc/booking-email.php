<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

function anubis_booking_email_template() {
	return file_get_contents( get_template_directory() . '/content/booking-confirmation.html' );
}

add_filter( 'ssa/templates/get_template_vars', function ( $vars, $template ) {
	if ( 'notification' !== $template || empty( $vars['Appointment'] ) ) { return $vars; }
	$appointment = $vars['Appointment'];
	$type = $appointment['AppointmentType'] ?? array();
	$details = array( 'price' => 'Preis nach Vereinbarung', 'date' => '', 'time' => '', 'duration' => $type['duration'] ?? '', 'logo_url' => get_template_directory_uri() . '/assets/images/logo-email.png' );
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
