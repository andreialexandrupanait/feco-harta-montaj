<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Shortcode [feco_harta_montaj] + încărcare condiționată a asset-urilor.
 * Asset-urile sunt doar ÎNREGISTRATE global (zero output) și ÎNCĂRCATE
 * exclusiv când shortcode-ul se randează efectiv -> doar pe pagina respectivă.
 */
class FHM_Shortcode {

	public static function init() {
		add_shortcode( 'feco_harta_montaj', array( __CLASS__, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
	}

	public static function register_assets() {
		$css = FHM_DIR . 'assets/css/fhm.css';
		$js  = FHM_DIR . 'assets/js/fhm.js';
		wp_register_style( 'fhm', FHM_URL . 'assets/css/fhm.css', array(), file_exists( $css ) ? filemtime( $css ) : FHM_VERSION );
		wp_register_script( 'fhm', FHM_URL . 'assets/js/fhm.js', array(), file_exists( $js ) ? filemtime( $js ) : FHM_VERSION, true );
	}

	/**
	 * Lista de produse pentru câmpul „Produs solicitat", luată din WooCommerce.
	 * Dacă WooCommerce nu e activ, întoarce array gol => câmpul nu se randează.
	 *
	 * @return array Listă de array-uri { id, name }.
	 */
	private static function products() {
		$products = array();
		if ( function_exists( 'wc_get_products' ) ) {
			$items = wc_get_products( array(
				'status'  => 'publish',
				'limit'   => -1,
				'orderby' => 'title',
				'order'   => 'ASC',
				'return'  => 'objects',
			) );
			foreach ( $items as $p ) {
				$products[] = array(
					'id'   => (int) $p->get_id(),
					'name' => $p->get_name(),
				);
			}
		}
		return apply_filters( 'fhm_products', $products );
	}

	public static function render( $atts = array() ) {
		// Enqueue numai la randarea shortcode-ului => încarcă doar pe această pagină.
		wp_enqueue_style( 'fhm' );
		wp_enqueue_script( 'fhm' );
		wp_localize_script( 'fhm', 'FHM_DATA', array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'fhm_submit' ),
			'svgUrl'   => FHM_URL . 'assets/img/romania.svg?v=' . FHM_VERSION,
			'products' => self::products(),
		) );

		ob_start();
		?>
		<div class="fhm-wrap">
			<div class="fhm-panel fhm-mappanel">
				<div class="fhm-head"><span><?php esc_html_e( 'Harta României', 'fhm' ); ?></span><span class="fhm-sel" id="fhm-selname">&mdash;</span></div>
				<div class="fhm-mapbox"><div class="fhm-svg" id="fhm-svg"><div class="fhm-loading"><?php esc_html_e( 'Se încarcă harta…', 'fhm' ); ?></div></div></div>
				<div class="fhm-legend">
					<span class="fhm-lg"><span class="fhm-sw" style="background:#cfe0f3"></span> <?php esc_html_e( 'Disponibil', 'fhm' ); ?></span>
					<span class="fhm-lg"><span class="fhm-sw" style="background:#3e72bb"></span> <?php esc_html_e( 'Selectat', 'fhm' ); ?></span>
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
