<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Procesarea trimiterii formularului prin admin-ajax (nonce, sanitizare,
 * honeypot, rate-limit, validare telefon, reCAPTCHA opțional, consimțământ
 * GDPR), salvare în tabelă + notificare email + auto-reply către client.
 */
class FHM_Ajax {

	public static function init() {
		add_action( 'wp_ajax_fhm_submit', array( __CLASS__, 'submit' ) );
		add_action( 'wp_ajax_nopriv_fhm_submit', array( __CLASS__, 'submit' ) );

		// Nonce „cache-safe": pe pagini cache-uite, JS cere un nonce proaspăt.
		add_action( 'wp_ajax_fhm_nonce', array( __CLASS__, 'nonce' ) );
		add_action( 'wp_ajax_nopriv_fhm_nonce', array( __CLASS__, 'nonce' ) );
	}

	public static function nonce() {
		wp_send_json_success( array( 'nonce' => wp_create_nonce( 'fhm_submit' ) ) );
	}

	private static function ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? wp_unslash( $_SERVER['REMOTE_ADDR'] ) : '';
		return substr( sanitize_text_field( $ip ), 0, 45 );
	}

	/**
	 * Validare telefon RO (mobil 07xxxxxxxx sau fix 02x/03x), tolerantă la
	 * spații/cratime și la prefixul internațional +40 / 0040.
	 */
	private static function valid_phone( $phone ) {
		$digits = preg_replace( '/[^0-9+]/', '', $phone );
		$digits = preg_replace( '/^(\+40|0040)/', '0', $digits );
		return (bool) preg_match( '/^0(7\d{8}|[23]\d{8})$/', $digits );
	}

	/** Secretul reCAPTCHA din setări sau, în lipsă, din constantă. */
	private static function recaptcha_secret() {
		$secret = FHM_Settings::get( 'recaptcha_secret' );
		if ( '' === $secret && defined( 'FHM_RECAPTCHA_SECRET' ) && FHM_RECAPTCHA_SECRET ) {
			$secret = FHM_RECAPTCHA_SECRET;
		}
		return $secret;
	}

	/**
	 * reCAPTCHA v3: trece dacă nu e configurat sau dacă verificarea reușește.
	 * Dacă serviciul Google pică, nu blocăm lead-ul.
	 */
	private static function recaptcha_ok() {
		$secret  = self::recaptcha_secret();
		$enabled = FHM_Settings::get( 'recaptcha_enabled' ) || ( defined( 'FHM_RECAPTCHA_SECRET' ) && FHM_RECAPTCHA_SECRET );
		if ( ! $enabled || '' === $secret ) {
			return true;
		}
		$token = isset( $_POST['recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_token'] ) ) : '';
		if ( '' === $token ) {
			return false;
		}
		$resp = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
			'timeout' => 10,
			'body'    => array(
				'secret'   => $secret,
				'response' => $token,
				'remoteip' => self::ip(),
			),
		) );
		if ( is_wp_error( $resp ) ) {
			return true;
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $data['success'] ) ) {
			return false;
		}
		if ( isset( $data['score'] ) && (float) $data['score'] < 0.5 ) {
			return false;
		}
		return true;
	}

	public static function submit() {
		check_ajax_referer( 'fhm_submit', 'nonce' );

		// Honeypot anti-bot: dacă e completat câmpul ascuns, ieșim discret.
		if ( ! empty( $_POST['website'] ) ) {
			wp_send_json_success( array( 'message' => __( 'Mulțumim!', 'fhm' ) ) );
		}

		// Rate-limit simplu pe IP (configurabil din setări).
		$rl_max    = max( 1, (int) FHM_Settings::get( 'ratelimit_max' ) );
		$rl_window = max( 1, (int) FHM_Settings::get( 'ratelimit_window' ) );
		$key       = 'fhm_rl_' . md5( self::ip() );
		$cnt       = (int) get_transient( $key );
		if ( $cnt >= $rl_max ) {
			wp_send_json_error( array( 'message' => __( 'Prea multe cereri. Încearcă peste câteva minute.', 'fhm' ) ) );
		}

		if ( ! self::recaptcha_ok() ) {
			wp_send_json_error( array( 'message' => __( 'Verificarea anti-spam a eșuat. Te rugăm reîncearcă.', 'fhm' ) ) );
		}

		$nume       = isset( $_POST['nume'] ) ? sanitize_text_field( wp_unslash( $_POST['nume'] ) ) : '';
		$telefon    = isset( $_POST['telefon'] ) ? sanitize_text_field( wp_unslash( $_POST['telefon'] ) ) : '';
		$email      = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$judet      = isset( $_POST['judet'] ) ? sanitize_text_field( wp_unslash( $_POST['judet'] ) ) : '';
		$slug       = isset( $_POST['judet_slug'] ) ? sanitize_title( wp_unslash( $_POST['judet_slug'] ) ) : '';
		$localitate = isset( $_POST['localitate'] ) ? sanitize_text_field( wp_unslash( $_POST['localitate'] ) ) : '';
		$detalii    = isset( $_POST['detalii'] ) ? sanitize_textarea_field( wp_unslash( $_POST['detalii'] ) ) : '';
		$consent    = ! empty( $_POST['consent'] );

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
			wp_send_json_error( array( 'message' => __( 'Completează numele, telefonul și județul.', 'fhm' ), 'field' => '' === $nume ? 'nume' : 'telefon' ) );
		}
		if ( ! self::valid_phone( $telefon ) ) {
			wp_send_json_error( array( 'message' => __( 'Numărul de telefon nu pare valid (ex: 07xx xxx xxx).', 'fhm' ), 'field' => 'telefon' ) );
		}
		if ( FHM_Settings::get( 'product_required' ) && '' === $produs ) {
			wp_send_json_error( array( 'message' => __( 'Te rugăm alege un produs.', 'fhm' ), 'field' => 'produs' ) );
		}
		if ( ! $consent ) {
			wp_send_json_error( array( 'message' => __( 'Trebuie să accepți prelucrarea datelor.', 'fhm' ), 'field' => 'consent' ) );
		}
		if ( '' !== $email && ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Adresa de email nu este validă.', 'fhm' ), 'field' => 'email' ) );
		}

		$ok = FHM_DB::insert( array(
			'judet'      => $judet,
			'judet_slug' => $slug,
			'nume'       => $nume,
			'telefon'    => $telefon,
			'email'      => $email,
			'produs'     => $produs,
			'localitate' => $localitate,
			'detalii'    => $detalii,
			'ip'         => self::ip(),
			'status'     => FHM_Settings::get( 'default_status' ),
		) );

		if ( false === $ok ) {
			wp_send_json_error( array( 'message' => __( 'Eroare la salvare. Te rugăm reîncearcă.', 'fhm' ) ) );
		}

		set_transient( $key, $cnt + 1, $rl_window * MINUTE_IN_SECONDS );

		// Destinatari notificare: din setări (listă) sau emailul de admin.
		$recipients = array();
		foreach ( explode( ',', (string) FHM_Settings::get( 'notify_emails' ) ) as $e ) {
			$e = sanitize_email( trim( $e ) );
			if ( $e && is_email( $e ) ) { $recipients[] = $e; }
		}
		if ( empty( $recipients ) ) {
			$recipients = array( get_option( 'admin_email' ) );
		}
		$to = apply_filters( 'fhm_notify_email', $recipients );

		// Subiect cu variabile {judet} / {nume}.
		$subject = (string) FHM_Settings::get( 'notify_subject' );
		$subject = str_replace( array( '{judet}', '{nume}' ), array( $judet, $nume ), $subject );
		if ( '' === trim( $subject ) ) {
			$subject = sprintf( __( 'Cerere montaj fose septice — %s', 'fhm' ), $judet );
		}

		$body  = __( 'Cerere nouă de montaj fose septice:', 'fhm' ) . "\n\n";
		$body .= 'Județ:      ' . $judet . "\n";
		$body .= 'Localitate: ' . $localitate . "\n";
		$body .= 'Nume:       ' . $nume . "\n";
		$body .= 'Telefon:    ' . $telefon . "\n";
		$body .= 'Email:      ' . $email . "\n";
		$body .= 'Produs:     ' . $produs . "\n";
		$body .= 'Detalii:    ' . $detalii . "\n";
		$body .= 'Data:       ' . current_time( 'mysql' ) . "\n";

		// Headers: From (din setări) + Reply-To = emailul clientului.
		$headers    = array();
		$from_email = sanitize_email( (string) FHM_Settings::get( 'from_email' ) );
		$from_name  = (string) FHM_Settings::get( 'from_name' );
		if ( $from_email && is_email( $from_email ) ) {
			$headers[] = 'From: ' . ( $from_name ? $from_name . ' ' : '' ) . '<' . $from_email . '>';
		}
		if ( '' !== $email && is_email( $email ) ) {
			$headers[] = 'Reply-To: ' . $nume . ' <' . $email . '>';
		}
		wp_mail( $to, $subject, $body, $headers );

		// Auto-reply către client (dacă a lăsat email și e activat).
		$autoreply = FHM_Settings::get( 'autoreply_enabled' ) && apply_filters( 'fhm_autoreply_enabled', true );
		if ( '' !== $email && is_email( $email ) && $autoreply ) {
			$ar_subject = apply_filters( 'fhm_autoreply_subject', (string) FHM_Settings::get( 'autoreply_subject' ) );
			$ar_body    = str_replace( array( '{nume}', '{judet}' ), array( $nume, $judet ), (string) FHM_Settings::get( 'autoreply_body' ) );
			$ar_body    = apply_filters( 'fhm_autoreply_body', $ar_body, $nume, $judet, $produs );
			$ar_headers = array();
			if ( $from_email && is_email( $from_email ) ) {
				$ar_headers[] = 'From: ' . ( $from_name ? $from_name . ' ' : '' ) . '<' . $from_email . '>';
			}
			wp_mail( $email, $ar_subject, $ar_body, $ar_headers );
		}

		$success = (string) FHM_Settings::get( 'form_success' );
		wp_send_json_success( array( 'message' => '' !== trim( $success ) ? $success : __( 'Mulțumim! Te contactăm în cel mai scurt timp.', 'fhm' ) ) );
	}
}
