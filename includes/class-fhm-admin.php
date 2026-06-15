<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Pagina de administrare: listă lead-uri + export CSV. Se încarcă doar în /wp-admin.
 */
class FHM_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_fhm_export', array( __CLASS__, 'export_csv' ) );
	}

	public static function menu() {
		add_menu_page(
			__( 'Lead-uri montaj', 'fhm' ),
			__( 'Lead-uri montaj', 'fhm' ),
			'manage_options',
			'fhm-leads',
			array( __CLASS__, 'page' ),
			'dashicons-location-alt',
			58
		);
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$rows   = FHM_DB::get_recent( 500 );
		$export = wp_nonce_url( admin_url( 'admin-post.php?action=fhm_export' ), 'fhm_export' );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Lead-uri montaj fose septice', 'fhm' ) . '</h1>';
		echo '<p><a href="' . esc_url( $export ) . '" class="button button-primary">' . esc_html__( 'Export CSV', 'fhm' ) . '</a></p>';
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Data', 'fhm' ) . '</th>';
		echo '<th>' . esc_html__( 'Județ', 'fhm' ) . '</th>';
		echo '<th>' . esc_html__( 'Nume', 'fhm' ) . '</th>';
		echo '<th>' . esc_html__( 'Telefon', 'fhm' ) . '</th>';
		echo '<th>' . esc_html__( 'Email', 'fhm' ) . '</th>';
		echo '<th>' . esc_html__( 'Detalii', 'fhm' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'fhm' ) . '</th>';
		echo '</tr></thead><tbody>';
		if ( $rows ) {
			foreach ( $rows as $r ) {
				echo '<tr>';
				echo '<td>' . esc_html( $r->created_at ) . '</td>';
				echo '<td>' . esc_html( $r->judet ) . '</td>';
				echo '<td>' . esc_html( $r->nume ) . '</td>';
				echo '<td>' . esc_html( $r->telefon ) . '</td>';
				echo '<td>' . esc_html( $r->email ) . '</td>';
				echo '<td>' . esc_html( $r->detalii ) . '</td>';
				echo '<td>' . esc_html( $r->status ) . '</td>';
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="7">' . esc_html__( 'Încă nu există cereri.', 'fhm' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Acces interzis.', 'fhm' ) ); }
		check_admin_referer( 'fhm_export' );

		$rows = FHM_DB::get_all();
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=lead-uri-montaj.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM pentru diacritice corecte în Excel
		fputcsv( $out, array( 'ID', 'Data', 'Judet', 'Slug', 'Nume', 'Telefon', 'Email', 'Detalii', 'IP', 'Status' ) );
		if ( $rows ) {
			foreach ( $rows as $r ) {
				fputcsv( $out, array( $r['id'], $r['created_at'], $r['judet'], $r['judet_slug'], $r['nume'], $r['telefon'], $r['email'], $r['detalii'], $r['ip'], $r['status'] ) );
			}
		}
		fclose( $out );
		exit;
	}
}
