<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class FHM_Plugin {

	const CLEANUP_HOOK = 'fhm_cleanup';

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

		// Retenție automată lead-uri (cron zilnic, doar dacă e configurată).
		add_action( self::CLEANUP_HOOK, array( 'FHM_DB', 'run_cleanup' ) );
		self::schedule_cleanup( (int) FHM_Settings::get( 'retention_months' ) );
	}

	/** Programează/oprește cron-ul de retenție în funcție de numărul de luni. */
	public static function schedule_cleanup( $months ) {
		$scheduled = wp_next_scheduled( self::CLEANUP_HOOK );
		if ( $months > 0 ) {
			if ( ! $scheduled ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
			}
		} elseif ( $scheduled ) {
			wp_unschedule_event( $scheduled, self::CLEANUP_HOOK );
		}
	}
}
