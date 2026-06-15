<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

/**
 * Implicit, lead-urile sunt PĂSTRATE la dezinstalare (să nu pierzi date).
 * Pentru ștergere completă, adaugă în wp-config.php:
 *   define( 'FHM_DELETE_DATA_ON_UNINSTALL', true );
 */
if ( defined( 'FHM_DELETE_DATA_ON_UNINSTALL' ) && FHM_DELETE_DATA_ON_UNINSTALL ) {
	global $wpdb;
	$table = $wpdb->prefix . 'fhm_leads';
	$wpdb->query( "DROP TABLE IF EXISTS $table" );
	delete_option( 'fhm_db_version' );
}
