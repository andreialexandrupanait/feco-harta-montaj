<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Procesarea trimiterii formularului prin admin-ajax (nonce, sanitizare,
 * honeypot, rate-limit, validare telefon, reCAPTCHA opțional prin constante,
 * consimțământ GDPR), salvare în tabelă + notificare email (HTML, cu telefon
 * și email apelabile) + auto-reply către client.
 */
class FHM_Ajax {

	const RL_MAX    = 6;
	const RL_WINDOW = 10; // minute

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

	/**
	 * reCAPTCHA v3 (opțional, prin constante FHM_RECAPTCHA_SECRET).
	 * Trece dacă nu e configurat sau dacă verificarea reușește.
	 */
	private static function recaptcha_ok() {
		if ( ! ( defined( 'FHM_RECAPTCHA_SECRET' ) && FHM_RECAPTCHA_SECRET ) ) {
			return true;
		}
		$token = isset( $_POST['recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['recaptcha_token'] ) ) : '';
		if ( '' === $token ) {
			return false;
		}
		$resp = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', array(
			'timeout' => 10,
			'body'    => array(
				'secret'   => FHM_RECAPTCHA_SECRET,
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

	/** Antetul From din setări (sau gol). */
	private static function from_header() {
		$from_email = sanitize_email( (string) FHM_Settings::get( 'from_email' ) );
		$from_name  = (string) FHM_Settings::get( 'from_name' );
		if ( $from_email && is_email( $from_email ) ) {
			return 'From: ' . ( $from_name ? $from_name . ' ' : '' ) . '<' . $from_email . '>';
		}
		return '';
	}

	/**
	 * Trimite (sau retrimite) notificarea HTML către admin pentru un lead.
	 * Telefonul și emailul sunt apelabile (tel: / mailto:). Reutilizat de
	 * formular și de butonul „Retrimite notificare" din dashboard.
	 *
	 * @param array $lead judet, localitate, nume, telefon, email, produs, detalii, created_at.
	 * @return bool
	 */
	public static function notify_admin( array $lead ) {
		$judet      = isset( $lead['judet'] ) ? $lead['judet'] : '';
		$localitate = isset( $lead['localitate'] ) ? $lead['localitate'] : '';
		$nume       = isset( $lead['nume'] ) ? $lead['nume'] : '';
		$telefon    = isset( $lead['telefon'] ) ? $lead['telefon'] : '';
		$email      = isset( $lead['email'] ) ? $lead['email'] : '';
		$produs     = isset( $lead['produs'] ) ? $lead['produs'] : '';
		$detalii    = isset( $lead['detalii'] ) ? $lead['detalii'] : '';
		$data       = isset( $lead['created_at'] ) && $lead['created_at'] ? $lead['created_at'] : current_time( 'mysql' );

		// Destinatari: din setări (listă) sau emailul de admin.
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

		// Telefon / email apelabile.
		$tel_digits = preg_replace( '/[^0-9+]/', '', $telefon );
		$tel_html   = $telefon ? '<a href="tel:' . esc_attr( $tel_digits ) . '">' . esc_html( $telefon ) . '</a>' : '—';
		$email_html = ( $email && is_email( $email ) ) ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : esc_html( $email );

		$rows  = array(
			__( 'Județ', 'fhm' )      => esc_html( $judet ),
			__( 'Localitate', 'fhm' ) => esc_html( $localitate ),
			__( 'Nume', 'fhm' )       => esc_html( $nume ),
			__( 'Telefon', 'fhm' )    => $tel_html,
			__( 'Email', 'fhm' )      => $email_html,
			__( 'Produs', 'fhm' )     => esc_html( $produs ),
			__( 'Detalii', 'fhm' )    => nl2br( esc_html( $detalii ) ),
			__( 'Data', 'fhm' )       => esc_html( $data ),
		);

		$body  = '<div style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;font-size:14px;color:#1d2530">';
		$body .= '<p style="font-weight:700">' . esc_html__( 'Cerere nouă de montaj fose septice:', 'fhm' ) . '</p>';
		$body .= '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse">';
		foreach ( $rows as $label => $value ) {
			$body .= '<tr><td style="color:#6b7785;vertical-align:top">' . esc_html( $label ) . '</td><td style="font-weight:600">' . $value . '</td></tr>';
		}
		$body .= '</table></div>';

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$from    = self::from_header();
		if ( $from ) {
			$headers[] = $from;
		}
		if ( '' !== $email && is_email( $email ) ) {
			$headers[] = 'Reply-To: ' . $nume . ' <' . $email . '>';
		}

		return wp_mail( $to, $subject, $body, $headers );
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
		if ( $cnt >= self::RL_MAX ) {
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

		$lead = array(
			'judet'      => $judet,
			'judet_slug' => $slug,
			'nume'       => $nume,
			'telefon'    => $telefon,
			'email'      => $email,
			'produs'     => $produs,
			'localitate' => $localitate,
			'detalii'    => $detalii,
			'ip'         => self::ip(),
		);

		$ok = FHM_DB::insert( $lead );
		if ( false === $ok ) {
			wp_send_json_error( array( 'message' => __( 'Eroare la salvare. Te rugăm reîncearcă.', 'fhm' ) ) );
		}

		set_transient( $key, $cnt + 1, self::RL_WINDOW * MINUTE_IN_SECONDS );

		// Notificare către admin (HTML, telefon/email apelabile).
		self::notify_admin( $lead );

		// Auto-reply către client (dacă a lăsat email și e activat).
		$autoreply = FHM_Settings::get( 'autoreply_enabled' ) && apply_filters( 'fhm_autoreply_enabled', true );
		if ( '' !== $email && is_email( $email ) && $autoreply ) {
			$ar_subject = apply_filters( 'fhm_autoreply_subject', (string) FHM_Settings::get( 'autoreply_subject' ) );
			$ar_body    = str_replace( array( '{nume}', '{judet}' ), array( $nume, $judet ), (string) FHM_Settings::get( 'autoreply_body' ) );
			$ar_body    = apply_filters( 'fhm_autoreply_body', $ar_body, $nume, $judet, $produs );
			$ar_headers = array();
			$from       = self::from_header();
			if ( $from ) {
				$ar_headers[] = $from;
			}
			wp_mail( $email, $ar_subject, $ar_body, $ar_headers );
		}

		wp_send_json_success( array( 'message' => __( 'Mulțumim! Te contactăm în cel mai scurt timp.', 'fhm' ) ) );
	}
}
