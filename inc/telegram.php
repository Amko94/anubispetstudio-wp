<?php
/** Telegram booking alerts. Secrets are only accessible to WordPress administrators. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function anubis_telegram_ids( $value ) {
	return array_values( array_unique( array_filter( preg_split( '/[\s,;]+/', trim( $value ) ), function ( $id ) {
		return (bool) preg_match( '/^-?[1-9][0-9]*$/', $id );
	} ) ) );
}

function anubis_telegram_request( $method, $body = array() ) {
	$token = get_option( 'anubis_telegram_token', '' );
	if ( ! $token ) { return new WP_Error( 'telegram_config', 'Bitte zuerst den Bot-Token speichern.' ); }
	$response = wp_remote_post( 'https://api.telegram.org/bot' . $token . '/' . $method, array( 'timeout' => 10, 'redirection' => 0, 'body' => $body ) );
	// Do not expose transport errors containing the secret URL.
	if ( is_wp_error( $response ) ) { return new WP_Error( 'telegram_connection', 'Telegram konnte nicht erreicht werden. Bitte erneut versuchen.' ); }
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $data['ok'] ) ) {
		$code = (int) wp_remote_retrieve_response_code( $response );
		$message = 401 === $code ? 'Der Bot-Token ist ungültig.' : ( 409 === $code ? 'Dieser Bot wird bereits von einer anderen Anbindung verwendet. Bitte einen eigenen Bot verwenden.' : 'Telegram hat die Anfrage abgelehnt. Bitte Token und Chat-ID prüfen und den Bot mit Start öffnen.' );
		return new WP_Error( 'telegram_api', $message );
	}
	return $data['result'];
}

add_action( 'admin_menu', function () {
	add_options_page( 'Telegram-Terminmeldungen', 'Telegram-Terminmeldungen', 'manage_options', 'anubis-telegram', 'anubis_telegram_admin' );
} );

function anubis_telegram_admin() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$notice = ''; $error = false; $chats = array();
	if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		check_admin_referer( 'anubis_telegram_settings' );
		$action = sanitize_key( wp_unslash( $_POST['telegram_action'] ?? '' ) );
		if ( 'save' === $action ) {
			$token = trim( wp_unslash( $_POST['bot_token'] ?? '' ) );
			if ( $token && ! preg_match( '/^[0-9]+:[A-Za-z0-9_-]+$/', $token ) ) {
				$notice = 'Bitte einen gültigen Bot-Token eingeben.'; $error = true;
			} else {
				if ( $token ) { update_option( 'anubis_telegram_token', $token, false ); }
				$ids = anubis_telegram_ids( wp_unslash( $_POST['chat_ids'] ?? '' ) );
				update_option( 'anubis_telegram_chats', $ids, false );
				update_option( 'anubis_telegram_enabled', ! empty( $_POST['telegram_enabled'] ) && get_option( 'anubis_telegram_token' ) && $ids ? 1 : 0, false );
				$notice = 'Einstellungen gespeichert.';
			}
		} elseif ( 'find' === $action ) {
			$result = anubis_telegram_request( 'getUpdates', array( 'timeout' => 0, 'limit' => 100 ) );
			if ( is_wp_error( $result ) ) { $notice = $result->get_error_message(); $error = true; }
			else {
				foreach ( $result as $update ) {
					$chat = $update['message']['chat'] ?? array();
					if ( ! empty( $chat['id'] ) && 'private' === ( $chat['type'] ?? '' ) ) {
						$chats[ (string) $chat['id'] ] = trim( ( $chat['first_name'] ?? '' ) . ' ' . ( $chat['last_name'] ?? '' ) );
					}
				}
				if ( ! $chats ) { $notice = 'Noch kein Chat gefunden. Sende deinem Bot jetzt eine Nachricht, z. B. Test, und klicke erneut auf Chats finden.'; }
			}
		} elseif ( 'test' === $action ) {
			$ids = get_option( 'anubis_telegram_chats', array() );
			if ( ! $ids ) { $notice = 'Bitte zuerst deine Chat-ID speichern.'; $error = true; }
			else {
				$sent = 0;
				foreach ( $ids as $id ) {
					$result = anubis_telegram_request( 'sendMessage', array( 'chat_id' => $id, 'text' => 'Anubis Pet Studio: Die Telegram-Verbindung funktioniert! Dies ist eine Testnachricht, keine Terminbuchung.' ) );
					if ( is_wp_error( $result ) ) { $notice = $result->get_error_message(); $error = true; } else { $sent++; }
				}
				$notice = $sent . ' Testnachricht(en) gesendet.' . ( $error ? ' ' . $notice : '' );
			}
		}
	}
	?>
	<div class="wrap"><h1>Telegram-Terminmeldungen</h1>
	<?php if ( $notice ) : ?><div class="notice <?php echo $error ? 'notice-error' : 'notice-success'; ?>"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
	<p>1. Bot-Token von BotFather speichern. 2. Deinem Bot in Telegram „Test“ senden. 3. Chats finden und deine Chat-ID unten eintragen. 4. Speichern und eine Testnachricht senden.</p>
	<form method="post">
	<?php wp_nonce_field( 'anubis_telegram_settings' ); ?>
	<table class="form-table"><tr><th><label for="bot-token">Bot-Token</label></th><td><input type="password" id="bot-token" name="bot_token" class="regular-text" autocomplete="new-password" value=""><p class="description"><?php echo get_option( 'anubis_telegram_token' ) ? 'Token gespeichert. Leer lassen, um ihn beizubehalten.' : 'Den geheimen Token hier einfügen.'; ?></p></td></tr>
	<tr><th><label for="chat-ids">Chat-IDs</label></th><td><textarea id="chat-ids" name="chat_ids" class="regular-text" rows="3"><?php echo esc_textarea( implode( "\n", get_option( 'anubis_telegram_chats', array() ) ) ); ?></textarea><p class="description">Eine Chat-ID pro Zeile. Nur ausgewählte Empfänger erhalten Buchungsmeldungen.</p></td></tr>
	<tr><th>Neue Buchungen</th><td><label><input type="checkbox" name="telegram_enabled" value="1" <?php checked( get_option( 'anubis_telegram_enabled' ), 1 ); ?>> Telegram-Benachrichtigungen aktivieren</label><p class="description">Die Nachricht enthält Termin und Behandlung sowie einen Link zum Adminbereich. Kundendaten bleiben im Adminbereich. Kunden erhalten weiterhin ihre E-Mail-Bestätigung.</p></td></tr></table>
	<button class="button button-primary" name="telegram_action" value="save">Speichern</button>
	<button class="button" name="telegram_action" value="find">Chats finden</button>
	<button class="button" name="telegram_action" value="test">Testnachricht senden</button>
	<p>„Chats finden“ und „Testnachricht senden“ verwenden die bereits gespeicherten Einstellungen.</p>
	</form>
	<?php if ( $chats ) : ?><h2>Gefundene Chats</h2><ul><?php foreach ( $chats as $id => $name ) : ?><li><?php echo esc_html( $name . ': ' . $id ); ?></li><?php endforeach; ?></ul><p>Kopiere deine Chat-ID in das Feld oben und speichere sie.</p><?php endif; ?>
	<?php $failure = get_option( 'anubis_telegram_failure', '' ); if ( $failure ) : ?><div class="notice notice-error"><p>Letzter Versandfehler: <?php echo esc_html( $failure ); ?></p></div><?php endif; ?>
	</div>
	<?php
}

function anubis_telegram_queue_booking( $id ) {
	if ( ! get_option( 'anubis_telegram_enabled' ) ) { return; }
	if ( ! wp_next_scheduled( 'anubis_telegram_booking', array( (int) $id, 0 ) ) ) {
		wp_schedule_single_event( time(), 'anubis_telegram_booking', array( (int) $id, 0 ) );
	}
}
add_action( 'ssa/appointment/booked', 'anubis_telegram_queue_booking', 10, 1 );

add_action( 'anubis_telegram_booking', function ( $id, $attempt ) {
	if ( ! get_option( 'anubis_telegram_enabled' ) || ! function_exists( 'ssa' ) ) { return; }
	$appointment = ssa()->appointment_model->get( $id );
	if ( ! $appointment || in_array( $appointment['status'] ?? '', array( 'canceled', 'abandoned' ), true ) ) { return; }
	$type = ssa()->appointment_type_model->get( $appointment['appointment_type_id'] );
	$date = ssa()->utils->get_datetime_as_local_datetime( $appointment['start_date'], $appointment['appointment_type_id'] )->format( 'd.m.Y H:i' );
	$text = 'Neue Terminbuchung bei Anubis Pet Studio' . "\n" . 'Termin: ' . $date . "\n" . 'Behandlung: ' . wp_strip_all_tags( $type['title'] ?? '' ) . "\n" . 'Details: ' . admin_url( 'admin.php?page=simply-schedule-appointments' );
	$failed = false;
	foreach ( get_option( 'anubis_telegram_chats', array() ) as $chat ) {
		$key = 'anubis_telegram_sent_' . (int) $id . '_' . md5( $chat );
		if ( get_option( $key ) ) { continue; }
		$result = anubis_telegram_request( 'sendMessage', array( 'chat_id' => $chat, 'text' => $text ) );
		if ( is_wp_error( $result ) ) {
			$failed = true;
			update_option( 'anubis_telegram_failure', $result->get_error_message(), false );
		} else { update_option( $key, 1, false ); }
	}
	if ( $failed && $attempt < 2 ) { wp_schedule_single_event( time() + 60 * ( $attempt + 1 ), 'anubis_telegram_booking', array( $id, $attempt + 1 ) ); }
	if ( ! $failed ) { delete_option( 'anubis_telegram_failure' ); }
}, 10, 2 );
