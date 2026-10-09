<?php
// Isolierter Test: kein WordPress-Bootstrap und keine echten Buchungen.
define( 'ABSPATH', __DIR__ );
define( 'DAY_IN_SECONDS', 86400 );
function add_action( ...$args ) {}
function get_option( $key, $default = false ) { return $default; }
function update_option( ...$args ) {}
function delete_option( ...$args ) {}
function wp_clear_scheduled_hook( ...$args ) {}
function ssa() { return $GLOBALS['fixture_plugin']; }
class RetentionModel {
	private $name;
	function __construct( $name ) { $this->name = $name; }
	function get_table_name() { return 'wp_ssa_' . $this->name; }
}
class RetentionDB {
	public $last_error = '';
	public $queries = array();
	public $rows = array();
	public $fail = false;
	function prepare( $sql, ...$args ) {
		if ( isset( $args[0] ) && is_array( $args[0] ) ) { $args = $args[0]; }
		foreach ( $args as $arg ) { $sql = preg_replace( '/%[sd]/', is_int( $arg ) ? (string) $arg : "'" . $arg . "'", $sql, 1 ); }
		return $sql;
	}
	function get_var( $sql ) { $this->queries[] = $sql; return '1'; }
	function get_col( $sql ) {
		$this->queries[] = $sql;
		preg_match( "/end_date <= '([^']+)'/", $sql, $match );
		if ( ! $match || strpos( $sql, 'FOR UPDATE' ) === false ) { throw new Exception( 'Missing cutoff or row lock' ); }
		return array_keys( array_filter( $this->rows, function ( $date ) use ( $match ) { return $date > '1000-01-01 00:00:00' && $date <= $match[1]; } ) );
	}
	function query( $sql ) {
		$this->queries[] = $sql;
		if ( $this->fail && strpos( $sql, 'DELETE FROM `wp_ssa_revision`' ) === 0 ) { return false; }
		return 1;
	}
}
require __DIR__ . '/../inc/booking-retention.php';
function check( $condition, $message ) { if ( ! $condition ) { throw new Exception( $message ); } }
check( anubis_booking_retention_cutoff( strtotime( '2026-10-09 12:00:00 UTC' ) ) === '2026-09-09 12:00:00', '30-day boundary' );
$GLOBALS['fixture_plugin'] = new stdClass();
foreach ( array( 'appointment', 'appointment_meta', 'async_action', 'revision', 'revision_meta', 'staff_appointment', 'resource_appointment' ) as $name ) {
	$GLOBALS['fixture_plugin']->{ $name . '_model' } = new RetentionModel( $name );
}
$wpdb = new RetentionDB();
$wpdb->rows = array( 11 => gmdate( 'Y-m-d H:i:s', time() - 31 * DAY_IN_SECONDS ), 12 => gmdate( 'Y-m-d H:i:s', time() - 29 * DAY_IN_SECONDS ), 13 => gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS ), 14 => '0000-00-00 00:00:00' );
anubis_run_booking_retention();
$deletes = array_filter( $wpdb->queries, function ( $q ) { return strpos( $q, 'DELETE' ) === 0; } );
check( count( $deletes ) === 7, 'All dependent tables included' );
foreach ( $deletes as $sql ) { check( strpos( $sql, 'IN (11)' ) !== false, 'Recent, future or invalid appointment selected' ); }
check( in_array( 'COMMIT', $wpdb->queries, true ), 'Successful transaction committed' );
$wpdb = new RetentionDB();
$wpdb->rows = array( 11 => gmdate( 'Y-m-d H:i:s', time() - 31 * DAY_IN_SECONDS ) );
$wpdb->fail = true;
anubis_run_booking_retention();
check( in_array( 'ROLLBACK', $wpdb->queries, true ), 'Failure rolls back' );
check( ! in_array( 'COMMIT', $wpdb->queries, true ), 'Failure not committed' );
check( strpos( end( $wpdb->queries ), 'RELEASE_LOCK' ) !== false, 'Lock released on failure' );
echo "PASS: cutoff, date selection, dependency cleanup, rollback and lock release\n";
