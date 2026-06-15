<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class FHM_Plugin {

	public static function init() {
		load_plugin_textdomain( 'fhm', false, dirname( plugin_basename( FHM_FILE ) ) . '/languages' );
		FHM_DB::maybe_upgrade();      // siguranță: creează tabela dacă lipsește
		FHM_Shortcode::init();
		FHM_Ajax::init();
		if ( is_admin() ) {
			FHM_Admin::init();
		}
	}
}
