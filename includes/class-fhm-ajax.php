<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Procesarea trimiterii formularului prin admin-ajax (nonce, sanitizare,
 * honeypot, rate-limit, consimțământ GDPR), salvare în tabelă + notificare email.
 */
class FHM_Ajax {

	public static function init() {
		add_action( 'wp_ajax_fhm_submit', array( __CLASS__, 'submit' ) );
		add_action( 'wp_ajax_nopriv_fhm_submit', array( __CLASS__, 'submit' ) );
	}

	private static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		return substr( sanitize_text_field( $ip ), 0, 45 );
	}

	public static function submit() {
		check_ajax_referer( 'fhm_submit', 'nonce' );

		// Honeypot anti-bot: dacă e completat câmpul ascuns, ieșim discret.
		if ( ! empty( $_POST['website'] ) ) {
			wp_send_json_success( array( 'message' => __( 'Mulțumim!', 'fhm' ) ) );
		}

		// Rate-limit simplu pe IP.
		$key = 'fhm_rl_' . md5( self::ip() );
		$cnt = (int) get_transient( $key );
		if ( $cnt >= 6 ) {
			wp_send_json_error( array( 'message' => __( 'Prea multe cereri. Încearcă peste câteva minute.', 'fhm' ) ) );
		}

		$nume    = isset( $_POST['nume'] ) ? sanitize_text_field( wp_unslash( $_POST['nume'] ) ) : '';
		$telefon = isset( $_POST['telefon'] ) ? sanitize_text_field( wp_unslash( $_POST['telefon'] ) ) : '';
		$email   = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$judet   = isset( $_POST['judet'] ) ? sanitize_text_field( wp_unslash( $_POST['judet'] ) ) : '';
		$slug    = isset( $_POST['judet_slug'] ) ? sanitize_title( wp_unslash( $_POST['judet_slug'] ) ) : '';
		$detalii = isset( $_POST['detalii'] ) ? sanitize_textarea_field( wp_unslash( $_POST['detalii'] ) ) : '';
		$consent = ! empty( $_POST['consent'] );

		// Produs (opțional): rezolvăm ID-ul WooCommerce în numele canonic,
		// ca să nu stocăm text arbitrar din client.
		$produs_id = isset( $_POST['produs_id'] ) ? (int) $_POST['produs_id'] : 0;
		$produs    = '';
		if ( $produs_id && function_exists( 'wc_get_product' ) ) {
			$wc_product = wc_get_product( $produs_id );
			if ( $wc_product ) {
				$produs = $wc_product->get_name();
			}
		}

		if ( '' === $nume || '' === $telefon || '' === $judet ) {
			wp_send_json_error( array( 'message' => __( 'Completează numele, telefonul și județul.', 'fhm' ) ) );
		}
		if ( ! $consent ) {
			wp_send_json_error( array( 'message' => __( 'Trebuie să accepți prelucrarea datelor.', 'fhm' ) ) );
		}
		if ( '' !== $email && ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Adresa de email nu este validă.', 'fhm' ) ) );
		}

		$ok = FHM_DB::insert( array(
			'judet'      => $judet,
			'judet_slug' => $slug,
			'nume'       => $nume,
			'telefon'    => $telefon,
			'email'      => $email,
			'produs'     => $produs,
			'detalii'    => $detalii,
			'ip'         => self::ip(),
		) );

		if ( false === $ok ) {
			wp_send_json_error( array( 'message' => __( 'Eroare la salvare. Te rugăm reîncearcă.', 'fhm' ) ) );
		}

		set_transient( $key, $cnt + 1, 10 * MINUTE_IN_SECONDS );

		// Email notificare. Schimbi destinatarul fie din Setări > General (admin email),
		// fie cu filtrul: add_filter('fhm_notify_email', fn() => 'comenzi@feco.ro');
		$to      = apply_filters( 'fhm_notify_email', get_option( 'admin_email' ) );
		$subject = sprintf( __( 'Cerere montaj fose septice — %s', 'fhm' ), $judet );
		$body    = __( 'Cerere nouă de montaj fose septice:', 'fhm' ) . "\n\n";
		$body   .= 'Județ:    ' . $judet . "\n";
		$body   .= 'Nume:     ' . $nume . "\n";
		$body   .= 'Telefon:  ' . $telefon . "\n";
		$body   .= 'Email:    ' . $email . "\n";
		$body   .= 'Produs:   ' . $produs . "\n";
		$body   .= 'Detalii:  ' . $detalii . "\n";
		$body   .= 'Data:     ' . current_time( 'mysql' ) . "\n";
		wp_mail( $to, $subject, $body );

		wp_send_json_success( array( 'message' => __( 'Mulțumim! Te contactăm în cel mai scurt timp.', 'fhm' ) ) );
	}
}
