<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Stocarea lead-urilor într-o tabelă proprie (fără legătură cu posts/options).
 */
class FHM_DB {

	const VERSION = '1.2';

	/** Statusuri permise pentru un lead. */
	public static function statuses() {
		return array(
			'nou'       => __( 'Nou', 'fhm' ),
			'contactat' => __( 'Contactat', 'fhm' ),
			'ofertat'   => __( 'Ofertat', 'fhm' ),
			'inchis'    => __( 'Închis', 'fhm' ),
		);
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'fhm_leads';
	}

	public static function create_table() {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE $table (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			judet VARCHAR(64) NOT NULL DEFAULT '',
			judet_slug VARCHAR(64) NOT NULL DEFAULT '',
			nume VARCHAR(190) NOT NULL DEFAULT '',
			telefon VARCHAR(40) NOT NULL DEFAULT '',
			email VARCHAR(190) NOT NULL DEFAULT '',
			produs VARCHAR(190) NOT NULL DEFAULT '',
			localitate VARCHAR(190) NOT NULL DEFAULT '',
			detalii TEXT NULL,
			ip VARCHAR(45) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'nou',
			PRIMARY KEY  (id),
			KEY judet_slug (judet_slug),
			KEY created_at (created_at)
		) $charset;";
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( 'fhm_db_version', self::VERSION );
	}

	public static function maybe_upgrade() {
		if ( get_option( 'fhm_db_version' ) !== self::VERSION ) {
			self::create_table();
		}
	}

	public static function insert( array $d ) {
		global $wpdb;
		return $wpdb->insert(
			self::table(),
			array(
				'created_at' => current_time( 'mysql' ),
				'judet'      => $d['judet'],
				'judet_slug' => $d['judet_slug'],
				'nume'       => $d['nume'],
				'telefon'    => $d['telefon'],
				'email'      => $d['email'],
				'produs'     => $d['produs'],
				'localitate' => $d['localitate'],
				'detalii'    => $d['detalii'],
				'ip'         => $d['ip'],
				'status'     => ! empty( $d['status'] ) ? $d['status'] : 'nou',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Construiește clauza WHERE din filtre (cu placeholdere) și colectează parametrii.
	 *
	 * @param array $args   Filtre: judet_slug, produs, date_from, date_to, search.
	 * @param array $params Referință — se umple cu valorile pentru prepare().
	 * @return string Clauza WHERE (fără cuvântul „WHERE").
	 */
	private static function build_where( array $args, array &$params ) {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['judet_slug'] ) ) {
			$where[]  = 'judet_slug = %s';
			$params[] = $args['judet_slug'];
		}
		if ( ! empty( $args['produs'] ) ) {
			$where[]  = 'produs = %s';
			$params[] = $args['produs'];
		}
		if ( ! empty( $args['date_from'] ) ) {
			$where[]  = 'created_at >= %s';
			$params[] = $args['date_from'] . ' 00:00:00';
		}
		if ( ! empty( $args['date_to'] ) ) {
			$where[]  = 'created_at <= %s';
			$params[] = $args['date_to'] . ' 23:59:59';
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(nume LIKE %s OR telefon LIKE %s OR email LIKE %s OR localitate LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}

		return implode( ' AND ', $where );
	}

	/**
	 * Lead-uri filtrate, ordonate descrescător după dată, cu paginare opțională.
	 *
	 * @param array $args judet_slug, produs, date_from, date_to, search, limit, offset, output.
	 */
	public static function get_filtered( array $args = array() ) {
		global $wpdb;
		$table  = self::table();
		$params = array();
		$where  = self::build_where( $args, $params );
		$output = isset( $args['output'] ) ? $args['output'] : OBJECT;

		$sql   = "SELECT * FROM $table WHERE $where ORDER BY created_at DESC";
		$limit = isset( $args['limit'] ) ? (int) $args['limit'] : 0;
		if ( $limit > 0 ) {
			$offset   = isset( $args['offset'] ) ? (int) $args['offset'] : 0;
			$sql     .= ' LIMIT %d OFFSET %d';
			$params[] = $limit;
			$params[] = $offset;
		}

		if ( $params ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $params ), $output );
		}
		return $wpdb->get_results( $sql, $output );
	}

	/** Numărul total de lead-uri care corespund filtrelor (pentru paginare). */
	public static function count_filtered( array $args = array() ) {
		global $wpdb;
		$table  = self::table();
		$params = array();
		$where  = self::build_where( $args, $params );
		$sql    = "SELECT COUNT(*) FROM $table WHERE $where";

		if ( $params ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		}
		return (int) $wpdb->get_var( $sql );
	}

	/** Actualizează statusul unui lead (status validat de apelant). */
	public static function update_status( $id, $status ) {
		global $wpdb;
		return $wpdb->update(
			self::table(),
			array( 'status' => $status ),
			array( 'id' => (int) $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	/** Șterge un lead. */
	public static function delete( $id ) {
		global $wpdb;
		return $wpdb->delete( self::table(), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/** Județe distincte prezente în lead-uri (pentru filtrul din admin). */
	public static function distinct_judete() {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( "SELECT DISTINCT judet, judet_slug FROM $table WHERE judet <> '' ORDER BY judet ASC" );
	}

	/** Produse distincte prezente în lead-uri (pentru filtrul din admin). */
	public static function distinct_produse() {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_col( "SELECT DISTINCT produs FROM $table WHERE produs <> '' ORDER BY produs ASC" );
	}

	/** Un singur lead după ID. */
	public static function get( $id ) {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", (int) $id ) );
	}

	/** Mai multe lead-uri după ID-uri (pentru acțiuni în masă / export selectate). */
	public static function get_by_ids( array $ids, $output = OBJECT ) {
		global $wpdb;
		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( empty( $ids ) ) {
			return array();
		}
		$table = self::table();
		$place = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE id IN ($place) ORDER BY created_at DESC", $ids ), $output );
	}
}
