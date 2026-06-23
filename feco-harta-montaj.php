<?php
/**
 * Plugin Name: FECO – Hartă Montaj Fose Septice
 * Description: Hartă interactivă a județelor + formular de solicitare montaj. Lead-urile se salvează într-o tabelă proprie. Asset-urile se încarcă DOAR pe paginile cu shortcode-ul [feco_harta_montaj]. Dezvoltat de Simplead.
 * Version:     1.0.9
 * Author:      Simplead
 * Author URI:  https://simplead.ro
 * Text Domain: fhm
 * License:     GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'FHM_VERSION', '1.0.9' );
define( 'FHM_FILE', __FILE__ );
define( 'FHM_DIR', plugin_dir_path( __FILE__ ) );
define( 'FHM_URL', plugin_dir_url( __FILE__ ) );

// Sursa pentru update „cu 1 click" (GitHub Releases). Pune URL-ul repo-ului tău.
// Pentru repo PRIVAT, adaugă în wp-config.php:  define( 'FHM_GITHUB_TOKEN', 'ghp_...' );
if ( ! defined( 'FHM_GITHUB_REPO' ) ) {
	define( 'FHM_GITHUB_REPO', 'https://github.com/andreialexandrupanait/feco-harta-montaj/' );
}

require_once FHM_DIR . 'includes/class-fhm-db.php';
require_once FHM_DIR . 'includes/class-fhm-settings.php';
require_once FHM_DIR . 'includes/class-fhm-shortcode.php';
require_once FHM_DIR . 'includes/class-fhm-ajax.php';
require_once FHM_DIR . 'includes/class-fhm-admin.php';
require_once FHM_DIR . 'includes/class-fhm-updater.php';
require_once FHM_DIR . 'includes/class-fhm-plugin.php';

register_activation_hook( __FILE__, array( 'FHM_DB', 'create_table' ) );
add_action( 'plugins_loaded', array( 'FHM_Plugin', 'init' ) );
