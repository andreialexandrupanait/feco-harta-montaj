<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class FHM_Plugin {

	public static function init() {
		load_plugin_textdomain( 'fhm', false, dirname( plugin_basename( FHM_FILE ) ) . '/languages' );
		FHM_DB::maybe_upgrade();      // siguranță: creează tabela dacă lipsește
		FHM_Shortcode::init();
		FHM_Ajax::init();
		FHM_Updater::init();         // update „cu 1 click" din GitHub Releases (doar admin/cron)

		if ( is_admin() ) {
			FHM_Admin::init();
			FHM_Settings::init();
		}

		// Curățenie: oprește un eventual cron de retenție rămas din versiuni anterioare.
		$leftover = wp_next_scheduled( 'fhm_cleanup' );
		if ( $leftover ) {
			wp_unschedule_event( $leftover, 'fhm_cleanup' );
		}
	}
}
