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
			'notify_emails'     => '',
			'from_name'         => '',
			'from_email'        => '',
			'notify_subject'    => 'Cerere montaj fose septice — {judet}',
			'autoreply_enabled' => 1,
			'autoreply_subject' => 'Am primit solicitarea ta — FECO',
			'autoreply_body'    => "Bună, {nume}!\n\nAm primit solicitarea ta de montaj fose septice și te vom contacta în cel mai scurt timp cu o ofertă pentru zona ta.\n\nMulțumim,\nEchipa FECO",
			'product_required'  => 0,
			'product_category'  => '',
			'redirect_enabled'  => 0,
			'redirect_page_id'  => 0,
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

		$out['from_name']      = sanitize_text_field( isset( $in['from_name'] ) ? $in['from_name'] : '' );
		$fe                    = sanitize_email( isset( $in['from_email'] ) ? $in['from_email'] : '' );
		$out['from_email']     = is_email( $fe ) ? $fe : '';
		$out['notify_subject'] = sanitize_text_field( isset( $in['notify_subject'] ) ? $in['notify_subject'] : $d['notify_subject'] );

		$out['autoreply_enabled'] = empty( $in['autoreply_enabled'] ) ? 0 : 1;
		$out['autoreply_subject'] = sanitize_text_field( isset( $in['autoreply_subject'] ) ? $in['autoreply_subject'] : $d['autoreply_subject'] );
		$out['autoreply_body']    = sanitize_textarea_field( isset( $in['autoreply_body'] ) ? $in['autoreply_body'] : $d['autoreply_body'] );

		$out['product_required'] = empty( $in['product_required'] ) ? 0 : 1;
		$out['product_category'] = sanitize_title( isset( $in['product_category'] ) ? $in['product_category'] : '' );

		$out['redirect_enabled'] = empty( $in['redirect_enabled'] ) ? 0 : 1;
		$out['redirect_page_id'] = absint( isset( $in['redirect_page_id'] ) ? $in['redirect_page_id'] : 0 );

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

		echo '<h2>' . esc_html__( 'Produse', 'fhm' ) . '</h2><table class="form-table">';
		self::checkbox( 'product_required', __( 'Produs obligatoriu', 'fhm' ), __( 'Clientul trebuie să aleagă un produs.', 'fhm' ) );
		self::product_category_field();
		echo '</table>';

		echo '<h2>' . esc_html__( 'Pagină Thank-you (redirect)', 'fhm' ) . '</h2><table class="form-table">';
		self::checkbox( 'redirect_enabled', __( 'Activează redirect', 'fhm' ), __( 'După trimitere, redirecționează către pagina aleasă.', 'fhm' ) );
		self::redirect_page_field();
		echo '</table>';

		submit_button();
		echo '</form></div>';
	}

	private static function redirect_page_field() {
		echo '<tr><th scope="row"><label for="fhm-redirect_page_id">' . esc_html__( 'Pagina de redirect', 'fhm' ) . '</label></th><td>';
		wp_dropdown_pages( array(
			'name'              => esc_attr( self::OPTION ) . '[redirect_page_id]',
			'id'                => 'fhm-redirect_page_id',
			'selected'          => (int) self::get( 'redirect_page_id' ),
			'show_option_none'  => __( '— Alege pagina —', 'fhm' ),
			'option_none_value' => 0,
		) );
		echo '<p class="description">' . esc_html__( 'Redirectul are loc doar dacă bifa de mai sus e activă și e aleasă o pagină.', 'fhm' ) . '</p>';
		echo '</td></tr>';
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
}
