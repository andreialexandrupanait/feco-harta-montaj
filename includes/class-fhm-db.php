<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Stocarea lead-urilor într-o tabelă proprie (fără legătură cu posts/options).
 */
class FHM_DB {

	const VERSION = '1.1';

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
				'detalii'    => $d['detalii'],
				'ip'         => $d['ip'],
				'status'     => 'nou',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	public static function get_recent( $limit = 500 ) {
		global $wpdb;
		$table = self::table();
		$limit = (int) $limit;
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC LIMIT $limit" );
	}

	public static function get_all() {
		global $wpdb;
		$table = self::table();
		return $wpdb->get_results( "SELECT * FROM $table ORDER BY created_at DESC", ARRAY_A );
	}
}
