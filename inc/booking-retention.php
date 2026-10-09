<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** SSA speichert Terminzeiten in UTC. Keine Kundendaten in Löschprotokollen. */
function anubis_booking_retention_cutoff( $now = null ) {
	return gmdate( 'Y-m-d H:i:s', ( null === $now ? time() : $now ) - 30 * DAY_IN_SECONDS );
}

function anubis_schedule_booking_retention() {
	if ( function_exists( 'ssa' ) && ! wp_next_scheduled( 'anubis_booking_retention' ) ) {
		wp_schedule_event( time() + 60, 'hourly', 'anubis_booking_retention' );
	}
}
add_action( 'init', 'anubis_schedule_booking_retention' );
add_action( 'switch_theme', function () { wp_clear_scheduled_hook( 'anubis_booking_retention' ); } );

/** Kleine, atomare Chargen nach dem Vorbild der SSA-Purge-Abhängigkeiten. */
function anubis_run_booking_retention() {
	if ( ! function_exists( 'ssa' ) ) { return; }
	global $wpdb;
	$plugin = ssa();
	$tables = array();
	foreach ( array( 'appointment', 'appointment_meta', 'async_action', 'revision', 'revision_meta', 'staff_appointment', 'resource_appointment' ) as $name ) {
		$model = $plugin->{ $name . '_model' };
		$table = $model->get_table_name();
		if ( is_string( $table ) && preg_match( '/^[a-zA-Z0-9_]+$/D', $table ) ) {
			$tables[ $name ] = '`' . $table . '`';
		}
	}
	if ( empty( $tables['appointment'] ) || empty( $tables['appointment_meta'] ) ) { return; }
	// MySQL advisory lock also protects against concurrent WP-Cron requests.
	$lock = 'anubis_retention_' . md5( $tables['appointment'] );
	if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) { return; }
	try {
		$cutoff = anubis_booking_retention_cutoff();
		// Up to 500 appointments per run; any remaining backlog continues next hour.
		for ( $batch = 0; $batch < 5; $batch++ ) {
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) { break; }
			$ids = $wpdb->get_col( $wpdb->prepare(
				"SELECT id FROM {$tables['appointment']} WHERE end_date > '1000-01-01 00:00:00' AND end_date <= %s ORDER BY end_date, id LIMIT 100 FOR UPDATE",
				$cutoff
			) );
			if ( $wpdb->last_error ) { $wpdb->query( 'ROLLBACK' ); break; }
			if ( ! $ids ) { $wpdb->query( 'COMMIT' ); break; }
			$ids = array_map( 'intval', $ids );
			$marks = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
			$queries = array();
			if ( isset( $tables['revision'], $tables['revision_meta'] ) ) {
				$queries[] = "DELETE m FROM {$tables['revision_meta']} m INNER JOIN {$tables['revision']} r ON m.revision_id = r.id WHERE r.appointment_id IN ($marks)";
			}
			foreach ( array( 'appointment_meta', 'revision', 'staff_appointment', 'resource_appointment' ) as $name ) {
				if ( isset( $tables[ $name ] ) ) { $queries[] = "DELETE FROM {$tables[$name]} WHERE appointment_id IN ($marks)"; }
			}
			if ( isset( $tables['async_action'] ) ) {
				$queries[] = "DELETE FROM {$tables['async_action']} WHERE object_type = 'appointment' AND object_id IN ($marks)";
			}
			$queries[] = "DELETE FROM {$tables['appointment']} WHERE id IN ($marks)";
			foreach ( $queries as $query ) {
				if ( false === $wpdb->query( $wpdb->prepare( $query, $ids ) ) ) {
					$wpdb->query( 'ROLLBACK' );
					update_option( 'anubis_retention_error', 'Buchungsdaten konnten nicht vollständig gelöscht werden. Datenbank prüfen.', false );
					return;
				}
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) { $wpdb->query( 'ROLLBACK' ); break; }
			foreach ( $ids as $id ) {
				foreach ( get_option( 'anubis_telegram_chats', array() ) as $chat ) { delete_option( 'anubis_telegram_sent_' . $id . '_' . md5( $chat ) ); }
				for ( $attempt = 0; $attempt <= 2; $attempt++ ) { wp_clear_scheduled_hook( 'anubis_telegram_booking', array( $id, $attempt ) ); }
			}
			delete_option( 'anubis_retention_error' );
			update_option( 'anubis_retention_last_run', time(), false );
			if ( count( $ids ) < 100 ) { break; }
		}
	} finally {
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
	}
}
add_action( 'anubis_booking_retention', 'anubis_run_booking_retention' );

add_action( 'admin_notices', function () {
	if ( current_user_can( 'manage_options' ) && get_option( 'anubis_retention_error' ) ) {
		echo '<div class="notice notice-error"><p>Automatische Buchungslöschung: ' . esc_html( get_option( 'anubis_retention_error' ) ) . '</p></div>';
	}
} );
