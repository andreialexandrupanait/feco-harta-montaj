<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Pagina de Setări (Settings API) — o singură opțiune `fhm_settings` (array).
 * Restul codului citește prin FHM_Settings::get(), cu fallback pe valorile
 * implicite, deci fără setări salvate comportamentul rămâne identic.
 */
class FHM_Settings {

	const OPTION = 'fhm_settings';
	const GROUP  = 'fhm_settings_group';

	private static $cache = null;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function defaults() {
		return array(
			'notify_emails'       => '',
			'from_name'           => '',
			'from_email'          => '',
			'notify_subject'      => 'Cerere montaj fose septice — {judet}',
			'autoreply_enabled'   => 1,
			'autoreply_subject'   => 'Am primit solicitarea ta — FECO',
			'autoreply_body'      => "Bună, {nume}!\n\nAm primit solicitarea ta de montaj fose septice și te vom contacta în cel mai scurt timp cu o ofertă pentru zona ta.\n\nMulțumim,\nEchipa FECO",
			'form_title'          => 'Montaj fose septice',
			'form_subtitle'       => 'Completează datele — te contactăm cu ofertă pentru zona ta.',
			'form_button'         => 'Trimite solicitarea',
			'form_success'        => 'Mulțumim! Te contactăm în cel mai scurt timp.',
			'consent_text'        => 'Sunt de acord cu prelucrarea datelor în scopul contactării.',
			'privacy_url'         => '',
			'product_required'    => 0,
			'product_category'    => '',
			'recaptcha_enabled'   => 0,
			'recaptcha_site'      => '',
			'recaptcha_secret'    => '',
			'ratelimit_max'       => 6,
			'ratelimit_window'    => 10,
			'retention_months'    => 0,
			'default_status'      => 'nou',
			'map_color_available' => '#cfe0f3',
			'map_color_selected'  => '#3e72bb',
			'map_color_hover'     => '#a8c6e8',
		);
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved        = get_option( self::OPTION, array() );
			self::$cache  = wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
		}
		return self::$cache;
	}

	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : '';
	}

	public static function register() {
		register_setting( self::GROUP, self::OPTION, array( 'sanitize_callback' => array( __CLASS__, 'sanitize' ) ) );
	}

	public static function sanitize( $input ) {
		$d   = self::defaults();
		$in  = is_array( $input ) ? $input : array();
		$out = array();

		// Emailuri (listă separată prin virgulă).
		$emails = array();
		foreach ( explode( ',', isset( $in['notify_emails'] ) ? $in['notify_emails'] : '' ) as $e ) {
			$e = sanitize_email( trim( $e ) );
			if ( $e && is_email( $e ) ) { $emails[] = $e; }
		}
		$out['notify_emails'] = implode( ', ', $emails );

		$out['from_name']    = sanitize_text_field( isset( $in['from_name'] ) ? $in['from_name'] : '' );
		$fe                  = sanitize_email( isset( $in['from_email'] ) ? $in['from_email'] : '' );
		$out['from_email']   = is_email( $fe ) ? $fe : '';
		$out['notify_subject'] = sanitize_text_field( isset( $in['notify_subject'] ) ? $in['notify_subject'] : $d['notify_subject'] );

		$out['autoreply_enabled'] = empty( $in['autoreply_enabled'] ) ? 0 : 1;
		$out['autoreply_subject'] = sanitize_text_field( isset( $in['autoreply_subject'] ) ? $in['autoreply_subject'] : $d['autoreply_subject'] );
		$out['autoreply_body']    = sanitize_textarea_field( isset( $in['autoreply_body'] ) ? $in['autoreply_body'] : $d['autoreply_body'] );

		$out['form_title']    = sanitize_text_field( isset( $in['form_title'] ) ? $in['form_title'] : $d['form_title'] );
		$out['form_subtitle'] = sanitize_text_field( isset( $in['form_subtitle'] ) ? $in['form_subtitle'] : $d['form_subtitle'] );
		$out['form_button']   = sanitize_text_field( isset( $in['form_button'] ) ? $in['form_button'] : $d['form_button'] );
		$out['form_success']  = sanitize_text_field( isset( $in['form_success'] ) ? $in['form_success'] : $d['form_success'] );
		$out['consent_text']  = sanitize_text_field( isset( $in['consent_text'] ) ? $in['consent_text'] : $d['consent_text'] );
		$out['privacy_url']   = esc_url_raw( isset( $in['privacy_url'] ) ? $in['privacy_url'] : '' );

		$out['product_required'] = empty( $in['product_required'] ) ? 0 : 1;
		$out['product_category'] = sanitize_title( isset( $in['product_category'] ) ? $in['product_category'] : '' );

		$out['recaptcha_enabled'] = empty( $in['recaptcha_enabled'] ) ? 0 : 1;
		$out['recaptcha_site']    = sanitize_text_field( isset( $in['recaptcha_site'] ) ? $in['recaptcha_site'] : '' );
		$out['recaptcha_secret']  = sanitize_text_field( isset( $in['recaptcha_secret'] ) ? $in['recaptcha_secret'] : '' );

		$out['ratelimit_max']    = max( 1, absint( isset( $in['ratelimit_max'] ) ? $in['ratelimit_max'] : $d['ratelimit_max'] ) );
		$out['ratelimit_window'] = max( 1, absint( isset( $in['ratelimit_window'] ) ? $in['ratelimit_window'] : $d['ratelimit_window'] ) );

		$out['retention_months'] = absint( isset( $in['retention_months'] ) ? $in['retention_months'] : 0 );
		$status                  = sanitize_key( isset( $in['default_status'] ) ? $in['default_status'] : 'nou' );
		$out['default_status']   = array_key_exists( $status, FHM_DB::statuses() ) ? $status : 'nou';

		foreach ( array( 'map_color_available', 'map_color_selected', 'map_color_hover' ) as $c ) {
			$hex        = sanitize_hex_color( isset( $in[ $c ] ) ? $in[ $c ] : '' );
			$out[ $c ]  = $hex ? $hex : $d[ $c ];
		}

		// Reprogramează cron-ul de retenție în funcție de noua valoare.
		FHM_Plugin::schedule_cleanup( $out['retention_months'] );

		// Invalidează cache-ul listei de produse (categoria s-ar putea schimba).
		FHM_Shortcode::clear_products_cache();

		self::$cache = null; // invalidează cache-ul intern
		return $out;
	}

	public static function menu() {
		add_submenu_page(
			'fhm-leads',
			__( 'Setări', 'fhm' ),
			__( 'Setări', 'fhm' ),
			'manage_options',
			'fhm-settings',
			array( __CLASS__, 'page' )
		);
	}

	private static function text( $key, $label, $desc = '', $placeholder = '' ) {
		printf(
			'<tr><th scope="row"><label for="fhm-%1$s">%2$s</label></th><td><input type="text" class="regular-text" id="fhm-%1$s" name="%3$s[%1$s]" value="%4$s" placeholder="%5$s">%6$s</td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( self::OPTION ),
			esc_attr( self::get( $key ) ),
			esc_attr( $placeholder ),
			$desc ? '<p class="description">' . esc_html( $desc ) . '</p>' : ''
		);
	}

	private static function textarea( $key, $label, $desc = '' ) {
		printf(
			'<tr><th scope="row"><label for="fhm-%1$s">%2$s</label></th><td><textarea class="large-text" rows="5" id="fhm-%1$s" name="%3$s[%1$s]">%4$s</textarea>%5$s</td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( self::OPTION ),
			esc_textarea( self::get( $key ) ),
			$desc ? '<p class="description">' . esc_html( $desc ) . '</p>' : ''
		);
	}

	private static function checkbox( $key, $label, $desc = '' ) {
		printf(
			'<tr><th scope="row">%2$s</th><td><label><input type="checkbox" id="fhm-%1$s" name="%3$s[%1$s]" value="1"%4$s> %5$s</label></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( self::OPTION ),
			checked( (bool) self::get( $key ), true, false ),
			$desc ? esc_html( $desc ) : ''
		);
	}

	private static function number( $key, $label, $desc = '' ) {
		printf(
			'<tr><th scope="row"><label for="fhm-%1$s">%2$s</label></th><td><input type="number" min="0" class="small-text" id="fhm-%1$s" name="%3$s[%1$s]" value="%4$s">%5$s</td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( self::OPTION ),
			esc_attr( self::get( $key ) ),
			$desc ? ' <span class="description">' . esc_html( $desc ) . '</span>' : ''
		);
	}

	private static function color( $key, $label ) {
		printf(
			'<tr><th scope="row"><label for="fhm-%1$s">%2$s</label></th><td><input type="color" id="fhm-%1$s" name="%3$s[%1$s]" value="%4$s"> <code>%4$s</code></td></tr>',
			esc_attr( $key ),
			esc_html( $label ),
			esc_attr( self::OPTION ),
			esc_attr( self::get( $key ) )
		);
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		echo '<div class="wrap"><h1>' . esc_html__( 'Setări — Hartă Montaj', 'fhm' ) . '</h1>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::GROUP );

		echo '<h2>' . esc_html__( 'Notificări & emailuri', 'fhm' ) . '</h2><table class="form-table">';
		self::text( 'notify_emails', __( 'Adrese email notificări', 'fhm' ), __( 'Una sau mai multe, separate prin virgulă. Gol = emailul de admin.', 'fhm' ), 'comenzi@feco.ro, vanzari@feco.ro' );
		self::text( 'from_name', __( 'Nume expeditor (From)', 'fhm' ) );
		self::text( 'from_email', __( 'Email expeditor (From)', 'fhm' ) );
		self::text( 'notify_subject', __( 'Subiect notificare', 'fhm' ), __( 'Variabile disponibile: {judet}, {nume}', 'fhm' ) );
		self::checkbox( 'autoreply_enabled', __( 'Auto-reply', 'fhm' ), __( 'Trimite confirmare automată către client (dacă a lăsat email).', 'fhm' ) );
		self::text( 'autoreply_subject', __( 'Subiect auto-reply', 'fhm' ) );
		self::textarea( 'autoreply_body', __( 'Mesaj auto-reply', 'fhm' ), __( 'Variabile: {nume}, {judet}', 'fhm' ) );
		echo '</table>';

		echo '<h2>' . esc_html__( 'Texte formular', 'fhm' ) . '</h2><table class="form-table">';
		self::text( 'form_title', __( 'Titlu', 'fhm' ) );
		self::text( 'form_subtitle', __( 'Subtitlu', 'fhm' ) );
		self::text( 'form_button', __( 'Text buton', 'fhm' ) );
		self::text( 'form_success', __( 'Mesaj de succes', 'fhm' ) );
		self::text( 'consent_text', __( 'Text consimțământ GDPR', 'fhm' ) );
		self::text( 'privacy_url', __( 'URL politică confidențialitate', 'fhm' ), __( 'Gol = se folosește pagina de confidențialitate din WordPress.', 'fhm' ) );
		self::checkbox( 'product_required', __( 'Produs obligatoriu', 'fhm' ), __( 'Clientul trebuie să aleagă un produs.', 'fhm' ) );
		self::product_category_field();
		echo '</table>';

		echo '<h2>' . esc_html__( 'Anti-spam', 'fhm' ) . '</h2><table class="form-table">';
		self::checkbox( 'recaptcha_enabled', __( 'Activează reCAPTCHA v3', 'fhm' ) );
		self::text( 'recaptcha_site', __( 'reCAPTCHA Site Key', 'fhm' ), __( 'Gol = se folosește constanta FHM_RECAPTCHA_SITE_KEY.', 'fhm' ) );
		self::text( 'recaptcha_secret', __( 'reCAPTCHA Secret', 'fhm' ), __( 'Gol = se folosește constanta FHM_RECAPTCHA_SECRET.', 'fhm' ) );
		self::number( 'ratelimit_max', __( 'Rate-limit: cereri', 'fhm' ), __( 'pe IP', 'fhm' ) );
		self::number( 'ratelimit_window', __( 'Rate-limit: minute', 'fhm' ), __( 'fereastra de timp', 'fhm' ) );
		echo '</table>';

		echo '<h2>' . esc_html__( 'GDPR & date', 'fhm' ) . '</h2><table class="form-table">';
		self::number( 'retention_months', __( 'Retenție (luni)', 'fhm' ), __( '0 = nu se șterge nimic automat.', 'fhm' ) );
		self::status_field();
		echo '</table>';

		echo '<h2>' . esc_html__( 'Hartă (culori)', 'fhm' ) . '</h2><table class="form-table">';
		self::color( 'map_color_available', __( 'Disponibil', 'fhm' ) );
		self::color( 'map_color_selected', __( 'Selectat', 'fhm' ) );
		self::color( 'map_color_hover', __( 'Hover', 'fhm' ) );
		echo '</table>';

		submit_button();
		echo '</form></div>';
	}

	private static function product_category_field() {
		echo '<tr><th scope="row"><label for="fhm-product_category">' . esc_html__( 'Limitează la categoria', 'fhm' ) . '</label></th><td>';
		echo '<select id="fhm-product_category" name="' . esc_attr( self::OPTION ) . '[product_category]">';
		echo '<option value="">' . esc_html__( 'Toate produsele', 'fhm' ) . '</option>';
		if ( taxonomy_exists( 'product_cat' ) ) {
			$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false ) );
			if ( ! is_wp_error( $terms ) ) {
				$current = self::get( 'product_category' );
				foreach ( $terms as $t ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $t->slug ), selected( $current, $t->slug, false ), esc_html( $t->name ) );
				}
			}
		} else {
			echo '<option value="" disabled>' . esc_html__( 'WooCommerce inactiv', 'fhm' ) . '</option>';
		}
		echo '</select></td></tr>';
	}

	private static function status_field() {
		echo '<tr><th scope="row"><label for="fhm-default_status">' . esc_html__( 'Status implicit lead', 'fhm' ) . '</label></th><td>';
		echo '<select id="fhm-default_status" name="' . esc_attr( self::OPTION ) . '[default_status]">';
		$current = self::get( 'default_status' );
		foreach ( FHM_DB::statuses() as $key => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $current, $key, false ), esc_html( $label ) );
		}
		echo '</select></td></tr>';
	}
}
