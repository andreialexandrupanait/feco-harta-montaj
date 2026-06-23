<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Pagina de administrare: listă lead-uri cu filtre, status editabil, ștergere
 * și export CSV. Se încarcă doar în /wp-admin.
 */
class FHM_Admin {

	const PER_PAGE = 50;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_post_fhm_export', array( __CLASS__, 'export_csv' ) );
		add_action( 'admin_post_fhm_delete', array( __CLASS__, 'delete_lead' ) );
		add_action( 'admin_post_fhm_resend', array( __CLASS__, 'resend_notification' ) );
		add_action( 'wp_ajax_fhm_set_status', array( __CLASS__, 'set_status' ) );
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

	/** Citește și sanitizează filtrele din GET. */
	private static function filters() {
		return array(
			'judet_slug' => isset( $_GET['judet'] ) ? sanitize_title( wp_unslash( $_GET['judet'] ) ) : '',
			'produs'     => isset( $_GET['produs'] ) ? sanitize_text_field( wp_unslash( $_GET['produs'] ) ) : '',
			'date_from'  => isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '',
			'date_to'    => isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '',
			'search'     => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		);
	}

	public static function page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		// Vizualizare lead în detaliu.
		if ( isset( $_GET['view'] ) ) {
			self::render_detail( (int) $_GET['view'] );
			return;
		}

		$filters = self::filters();
		$paged   = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$total   = FHM_DB::count_filtered( $filters );
		$pages   = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$paged   = min( $paged, $pages );

		$rows = FHM_DB::get_filtered( array_merge( $filters, array(
			'limit'  => self::PER_PAGE,
			'offset' => ( $paged - 1 ) * self::PER_PAGE,
		) ) );

		$statuses = FHM_DB::statuses();
		$ajax_nonce = wp_create_nonce( 'fhm_admin' );

		// Export cu filtrele curente.
		$export = wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'fhm_export' ), array_filter( array(
			'judet'  => $filters['judet_slug'],
			'produs' => $filters['produs'],
			'from'   => $filters['date_from'],
			'to'     => $filters['date_to'],
			's'      => $filters['search'],
		) ) ), admin_url( 'admin-post.php' ) ), 'fhm_export' );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Lead-uri montaj fose septice', 'fhm' ) . '</h1>';

		if ( isset( $_GET['fhm_deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Lead șters.', 'fhm' ) . '</p></div>';
		}
		if ( isset( $_GET['fhm_sent'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Notificare retrimisă către admin.', 'fhm' ) . '</p></div>';
		}

		// Bară de filtre.
		echo '<form method="get" style="margin:12px 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center">';
		echo '<input type="hidden" name="page" value="fhm-leads">';

		echo '<select name="judet"><option value="">' . esc_html__( 'Toate județele', 'fhm' ) . '</option>';
		foreach ( FHM_DB::distinct_judete() as $j ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $j->judet_slug ), selected( $filters['judet_slug'], $j->judet_slug, false ), esc_html( $j->judet ) );
		}
		echo '</select>';

		echo '<select name="produs"><option value="">' . esc_html__( 'Toate produsele', 'fhm' ) . '</option>';
		foreach ( FHM_DB::distinct_produse() as $p ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $p ), selected( $filters['produs'], $p, false ), esc_html( $p ) );
		}
		echo '</select>';

		echo '<input type="date" name="from" value="' . esc_attr( $filters['date_from'] ) . '">';
		echo '<input type="date" name="to" value="' . esc_attr( $filters['date_to'] ) . '">';
		echo '<input type="search" name="s" value="' . esc_attr( $filters['search'] ) . '" placeholder="' . esc_attr__( 'Caută nume/telefon/email…', 'fhm' ) . '">';
		echo '<button class="button">' . esc_html__( 'Filtrează', 'fhm' ) . '</button>';
		echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=fhm-leads' ) ) . '">' . esc_html__( 'Resetează', 'fhm' ) . '</a>';
		echo '<a href="' . esc_url( $export ) . '" class="button button-primary">' . esc_html__( 'Export CSV', 'fhm' ) . '</a>';
		echo '</form>';

		echo '<p class="description">' . sprintf( esc_html__( '%d rezultate', 'fhm' ), (int) $total ) . '</p>';

		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'Data', 'Județ', 'Localitate', 'Nume', 'Telefon', 'Email', 'Produs', 'Detalii', 'Status', 'Acțiuni' ) as $h ) {
			echo '<th>' . esc_html( $h ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( $rows ) {
			foreach ( $rows as $r ) {
				$del    = wp_nonce_url( admin_url( 'admin-post.php?action=fhm_delete&id=' . (int) $r->id ), 'fhm_delete_' . (int) $r->id );
				$resend = wp_nonce_url( admin_url( 'admin-post.php?action=fhm_resend&id=' . (int) $r->id ), 'fhm_resend_' . (int) $r->id );
				$view   = admin_url( 'admin.php?page=fhm-leads&view=' . (int) $r->id );

				echo '<tr>';
				echo '<td>' . esc_html( $r->created_at ) . '</td>';
				echo '<td>' . esc_html( $r->judet ) . '</td>';
				echo '<td>' . esc_html( $r->localitate ) . '</td>';
				echo '<td>' . esc_html( $r->nume ) . '</td>';
				echo '<td>' . ( $r->telefon ? '<a href="tel:' . esc_attr( $r->telefon ) . '">' . esc_html( $r->telefon ) . '</a>' : '' ) . '</td>';
				echo '<td>' . ( $r->email ? '<a href="mailto:' . esc_attr( $r->email ) . '">' . esc_html( $r->email ) . '</a>' : '' ) . '</td>';
				echo '<td>' . esc_html( $r->produs ) . '</td>';
				echo '<td>' . esc_html( $r->detalii ) . '</td>';

				echo '<td><select class="fhm-status-select" data-id="' . (int) $r->id . '">';
				foreach ( $statuses as $key => $label ) {
					printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $r->status, $key, false ), esc_html( $label ) );
				}
				echo '</select></td>';

				echo '<td>';
				echo '<a href="' . esc_url( $view ) . '">' . esc_html__( 'Vezi', 'fhm' ) . '</a> | ';
				echo '<a href="' . esc_url( $resend ) . '">' . esc_html__( 'Retrimite', 'fhm' ) . '</a> | ';
				echo '<a href="' . esc_url( $del ) . '" style="color:#b3261e" onclick="return confirm(\'' . esc_js( __( 'Ștergi acest lead?', 'fhm' ) ) . '\')">' . esc_html__( 'Șterge', 'fhm' ) . '</a>';
				echo '</td>';
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="10">' . esc_html__( 'Niciun rezultat.', 'fhm' ) . '</td></tr>';
		}
		echo '</tbody></table>';

		// Paginare (pattern canonic WP: numar mare inlocuit cu %#%, fara probleme de encoding).
		if ( $pages > 1 ) {
			$big  = 999999999;
			$base = str_replace( $big, '%#%', esc_url( add_query_arg( 'paged', $big ) ) );
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo wp_kses_post( paginate_links( array(
				'base'    => $base,
				'format'  => '',
				'current' => $paged,
				'total'   => $pages,
			) ) );
			echo '</div></div>';
		}

		echo '</div>';

		self::status_script( $ajax_nonce );
	}

	/** Script inline pentru salvarea statusului prin AJAX (folosit în listă și în detaliu). */
	private static function status_script( $ajax_nonce ) {
		$ajax_url = admin_url( 'admin-ajax.php' );
		?>
		<script>
		( function () {
			var url = <?php echo wp_json_encode( $ajax_url ); ?>;
			var nonce = <?php echo wp_json_encode( $ajax_nonce ); ?>;
			document.querySelectorAll( '.fhm-status-select' ).forEach( function ( sel ) {
				sel.addEventListener( 'change', function () {
					var fd = new FormData();
					fd.append( 'action', 'fhm_set_status' );
					fd.append( 'nonce', nonce );
					fd.append( 'id', sel.getAttribute( 'data-id' ) );
					fd.append( 'status', sel.value );
					sel.disabled = true;
					fetch( url, { method: 'POST', body: fd, credentials: 'same-origin' } )
						.then( function ( r ) { return r.json(); } )
						.then( function ( res ) { sel.disabled = false; sel.style.outline = res && res.success ? '2px solid #46b450' : '2px solid #dc3232'; setTimeout( function () { sel.style.outline = ''; }, 1200 ); } )
						.catch( function () { sel.disabled = false; sel.style.outline = '2px solid #dc3232'; } );
				} );
			} );
		} )();
		</script>
		<?php
	}

	/** Vizualizare lead în detaliu. */
	private static function render_detail( $id ) {
		$lead = FHM_DB::get( $id );
		$list = admin_url( 'admin.php?page=fhm-leads' );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Detalii lead', 'fhm' ) . ' <a href="' . esc_url( $list ) . '" class="page-title-action">' . esc_html__( '← Înapoi la listă', 'fhm' ) . '</a></h1>';

		if ( ! $lead ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Lead inexistent.', 'fhm' ) . '</p></div></div>';
			return;
		}

		$ajax_nonce = wp_create_nonce( 'fhm_admin' );
		$resend     = wp_nonce_url( admin_url( 'admin-post.php?action=fhm_resend&id=' . (int) $lead->id ), 'fhm_resend_' . (int) $lead->id );
		$del        = wp_nonce_url( admin_url( 'admin-post.php?action=fhm_delete&id=' . (int) $lead->id ), 'fhm_delete_' . (int) $lead->id );

		$tel_html   = $lead->telefon ? '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $lead->telefon ) ) . '">' . esc_html( $lead->telefon ) . '</a>' : '—';
		$email_html = $lead->email ? '<a href="mailto:' . esc_attr( $lead->email ) . '">' . esc_html( $lead->email ) . '</a>' : '—';

		$status_sel  = '<select class="fhm-status-select" data-id="' . (int) $lead->id . '">';
		foreach ( FHM_DB::statuses() as $key => $label ) {
			$status_sel .= '<option value="' . esc_attr( $key ) . '"' . selected( $lead->status, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$status_sel .= '</select>';

		$rows = array(
			__( 'Data', 'fhm' )       => esc_html( $lead->created_at ),
			__( 'Județ', 'fhm' )      => esc_html( $lead->judet ),
			__( 'Localitate', 'fhm' ) => esc_html( $lead->localitate ),
			__( 'Nume', 'fhm' )       => esc_html( $lead->nume ),
			__( 'Telefon', 'fhm' )    => $tel_html,
			__( 'Email', 'fhm' )      => $email_html,
			__( 'Produs', 'fhm' )     => esc_html( $lead->produs ),
			__( 'Detalii', 'fhm' )    => nl2br( esc_html( $lead->detalii ) ),
			__( 'IP', 'fhm' )         => esc_html( $lead->ip ),
			__( 'Status', 'fhm' )     => $status_sel,
		);

		echo '<table class="form-table">';
		foreach ( $rows as $label => $value ) {
			echo '<tr><th scope="row" style="width:160px">' . esc_html( $label ) . '</th><td>' . $value . '</td></tr>';
		}
		echo '</table>';

		echo '<p>';
		echo '<a href="' . esc_url( $resend ) . '" class="button button-primary">' . esc_html__( 'Retrimite notificare', 'fhm' ) . '</a> ';
		echo '<a href="' . esc_url( $del ) . '" class="button" style="color:#b3261e" onclick="return confirm(\'' . esc_js( __( 'Ștergi acest lead?', 'fhm' ) ) . '\')">' . esc_html__( 'Șterge', 'fhm' ) . '</a>';
		echo '</p>';

		echo '</div>';
		self::status_script( $ajax_nonce );
	}

	public static function resend_notification() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Acces interzis.', 'fhm' ) ); }
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'fhm_resend_' . $id );
		$lead = $id ? FHM_DB::get( $id ) : null;
		if ( $lead ) {
			FHM_Ajax::notify_admin( (array) $lead );
		}
		$back = wp_get_referer();
		if ( ! $back ) { $back = admin_url( 'admin.php?page=fhm-leads' ); }
		wp_safe_redirect( add_query_arg( 'fhm_sent', '1', remove_query_arg( 'fhm_sent', $back ) ) );
		exit;
	}

	public static function set_status() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }
		check_ajax_referer( 'fhm_admin', 'nonce' );

		$id     = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( ! $id || ! array_key_exists( $status, FHM_DB::statuses() ) ) {
			wp_send_json_error();
		}
		FHM_DB::update_status( $id, $status );
		wp_send_json_success();
	}

	public static function delete_lead() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Acces interzis.', 'fhm' ) ); }
		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		check_admin_referer( 'fhm_delete_' . $id );
		if ( $id ) {
			FHM_DB::delete( $id );
		}
		$back = wp_get_referer();
		if ( ! $back ) { $back = admin_url( 'admin.php?page=fhm-leads' ); }
		wp_safe_redirect( add_query_arg( 'fhm_deleted', '1', remove_query_arg( 'fhm_deleted', $back ) ) );
		exit;
	}

	public static function export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Acces interzis.', 'fhm' ) ); }
		check_admin_referer( 'fhm_export' );

		$rows = FHM_DB::get_filtered( array_merge( self::filters(), array( 'output' => ARRAY_A ) ) );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=lead-uri-montaj.csv' );
		$out = fopen( 'php://output', 'w' );
		fwrite( $out, "\xEF\xBB\xBF" ); // BOM pentru diacritice corecte în Excel
		fputcsv( $out, array( 'ID', 'Data', 'Judet', 'Slug', 'Localitate', 'Nume', 'Telefon', 'Email', 'Produs', 'Detalii', 'IP', 'Status' ) );
		if ( $rows ) {
			foreach ( $rows as $r ) {
				fputcsv( $out, array( $r['id'], $r['created_at'], $r['judet'], $r['judet_slug'], $r['localitate'], $r['nume'], $r['telefon'], $r['email'], $r['produs'], $r['detalii'], $r['ip'], $r['status'] ) );
			}
		}
		fclose( $out );
		exit;
	}
}
