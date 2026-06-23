<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Shortcode [feco_harta_montaj] + încărcare condiționată a asset-urilor.
 * Asset-urile sunt doar ÎNREGISTRATE global (zero output) și ÎNCĂRCATE
 * exclusiv când shortcode-ul se randează efectiv -> doar pe pagina respectivă.
 */
class FHM_Shortcode {

	const PRODUCTS_CACHE = 'fhm_products_cache';

	public static function init() {
		add_shortcode( 'feco_harta_montaj', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );

		// Invalidează cache-ul listei de produse când se schimbă produsele.
		$clear = array( __CLASS__, 'clear_products_cache' );
		add_action( 'save_post_product', $clear );
		add_action( 'woocommerce_update_product', $clear );
		add_action( 'woocommerce_new_product', $clear );
		add_action( 'trashed_post', $clear );
		add_action( 'untrashed_post', $clear );
	}

	public static function clear_products_cache() {
		delete_transient( self::PRODUCTS_CACHE );
	}

	public static function register_assets() {
		$css = FHM_DIR . 'assets/css/fhm.css';
		$js  = FHM_DIR . 'assets/js/fhm.js';
		wp_register_style( 'fhm', FHM_URL . 'assets/css/fhm.css', array(), file_exists( $css ) ? filemtime( $css ) : FHM_VERSION );
		wp_register_script( 'fhm', FHM_URL . 'assets/js/fhm.js', array(), file_exists( $js ) ? filemtime( $js ) : FHM_VERSION, true );
	}

	/**
	 * Lista de produse pentru câmpul „Produs solicitat", luată din WooCommerce.
	 * Ordinea respectă sortarea din shop (menu_order). Rezultatul e cache-uit.
	 * Dacă WooCommerce nu e activ, întoarce array gol => câmpul nu se randează.
	 *
	 * @return array Listă de array-uri { id, name }.
	 */
	private static function products() {
		$cached = get_transient( self::PRODUCTS_CACHE );
		if ( is_array( $cached ) ) {
			return apply_filters( 'fhm_products', $cached );
		}

		$products = array();
		if ( function_exists( 'wc_get_products' ) ) {
			$args = array(
				'status'  => 'publish',
				'limit'   => -1,
				'orderby' => 'menu_order',
				'order'   => 'ASC',
				'return'  => 'objects',
			);
			$cat = FHM_Settings::get( 'product_category' );
			if ( $cat ) {
				$args['category'] = array( $cat );
			}
			$items = wc_get_products( $args );
			foreach ( $items as $p ) {
				$products[] = array(
					'id'   => (int) $p->get_id(),
					'name' => $p->get_name(),
				);
			}
		}

		set_transient( self::PRODUCTS_CACHE, $products, 12 * HOUR_IN_SECONDS );
		return apply_filters( 'fhm_products', $products );
	}

	/** Cheia site reCAPTCHA v3 (setări→constantă), doar dacă e activat. */
	private static function recaptcha_key() {
		$key = FHM_Settings::get( 'recaptcha_site' );
		if ( '' === $key && defined( 'FHM_RECAPTCHA_SITE_KEY' ) && FHM_RECAPTCHA_SITE_KEY ) {
			$key = FHM_RECAPTCHA_SITE_KEY;
		}
		$enabled = FHM_Settings::get( 'recaptcha_enabled' ) || ( defined( 'FHM_RECAPTCHA_SITE_KEY' ) && FHM_RECAPTCHA_SITE_KEY );
		return ( $enabled && $key ) ? (string) $key : '';
	}

	public static function render( $atts = array() ) {
		// Enqueue numai la randarea shortcode-ului => încarcă doar pe această pagină.
		wp_enqueue_style( 'fhm' );
		wp_enqueue_script( 'fhm' );

		// Culori hartă din setări (variabile CSS).
		$col_avail = sanitize_hex_color( FHM_Settings::get( 'map_color_available' ) );
		$col_sel   = sanitize_hex_color( FHM_Settings::get( 'map_color_selected' ) );
		$col_hover = sanitize_hex_color( FHM_Settings::get( 'map_color_hover' ) );
		if ( $col_avail && $col_sel && $col_hover ) {
			wp_add_inline_style( 'fhm', '.fhm-wrap{--fhm-jud:' . $col_avail . ';--fhm-accent:' . $col_sel . ';--fhm-jud-hover:' . $col_hover . ';}' );
		}

		$recaptcha_key = self::recaptcha_key();
		if ( '' !== $recaptcha_key ) {
			wp_enqueue_script( 'fhm-recaptcha', 'https://www.google.com/recaptcha/api.js?render=' . rawurlencode( $recaptcha_key ), array(), null, true );
		}

		$privacy = FHM_Settings::get( 'privacy_url' );
		if ( '' === $privacy ) {
			$privacy = get_privacy_policy_url();
		}

		wp_localize_script( 'fhm', 'FHM_DATA', array(
			'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'fhm_submit' ),
			'svgUrl'          => FHM_URL . 'assets/img/romania.svg?v=' . FHM_VERSION,
			'products'        => self::products(),
			'productRequired' => (bool) FHM_Settings::get( 'product_required' ),
			'privacyUrl'      => $privacy,
			'recaptchaKey'    => $recaptcha_key,
			'texts'           => array(
				'title'    => FHM_Settings::get( 'form_title' ),
				'subtitle' => FHM_Settings::get( 'form_subtitle' ),
				'button'   => FHM_Settings::get( 'form_button' ),
				'success'  => FHM_Settings::get( 'form_success' ),
				'consent'  => FHM_Settings::get( 'consent_text' ),
			),
		) );

		$sw_avail = $col_avail ? $col_avail : '#cfe0f3';
		$sw_sel   = $col_sel ? $col_sel : '#3e72bb';

		ob_start();
		?>
		<div class="fhm-wrap">
			<div class="fhm-panel fhm-mappanel">
				<div class="fhm-head"><span><?php esc_html_e( 'Harta României', 'fhm' ); ?></span><span class="fhm-sel" id="fhm-selname">&mdash;</span></div>
				<div class="fhm-mapbox"><div class="fhm-svg" id="fhm-svg"><div class="fhm-loading"><?php esc_html_e( 'Se încarcă harta…', 'fhm' ); ?></div></div></div>
				<div class="fhm-legend">
					<span class="fhm-lg"><span class="fhm-sw" style="background:<?php echo esc_attr( $sw_avail ); ?>"></span> <?php esc_html_e( 'Disponibil', 'fhm' ); ?></span>
					<span class="fhm-lg"><span class="fhm-sw" style="background:<?php echo esc_attr( $sw_sel ); ?>"></span> <?php esc_html_e( 'Selectat', 'fhm' ); ?></span>
				</div>
			</div>
			<div class="fhm-panel fhm-formpanel">
				<div class="fhm-head"><span><?php esc_html_e( 'Solicită montaj', 'fhm' ); ?></span></div>
				<div id="fhm-slot"><div class="fhm-empty"><b><?php esc_html_e( 'Selectează un județ', 'fhm' ); ?></b><?php esc_html_e( 'Dă click pe hartă pentru a începe.', 'fhm' ); ?></div></div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}
}
