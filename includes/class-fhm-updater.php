<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Update „cu 1 click" din WordPress, alimentat din GitHub Releases.
 *
 * Folosește biblioteca Plugin Update Checker (YahnisElsts/plugin-update-checker, v5),
 * aflată în lib/plugin-update-checker/. Sursa de update sunt Release-urile/tag-urile
 * GitHub (update CONTROLAT), nu ramura de dezvoltare.
 *
 * Configurare:
 *   - FHM_GITHUB_REPO  : URL-ul repo-ului (definit în fișierul principal).
 *   - FHM_GITHUB_TOKEN : token de acces pentru repo PRIVAT (definit în wp-config.php,
 *                        NU se comite în repo). Dacă lipsește, funcționează ca repo public.
 *
 * Rulează DOAR în /wp-admin sau pe cron, ca să nu adauge cost pe front-end.
 */
class FHM_Updater {

	/**
	 * Instanța update checker-ului (păstrată ca referință).
	 *
	 * @var object|null
	 */
	private static $checker = null;

	public static function init() {
		// Rulează numai în admin sau pe cron — niciodată pe front-end.
		if ( ! ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) ) ) {
			return;
		}

		// Fără URL de repo nu avem ce verifica.
		if ( ! defined( 'FHM_GITHUB_REPO' ) || '' === FHM_GITHUB_REPO ) {
			return;
		}

		// Încarcă biblioteca (o singură dată).
		$loader = FHM_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
		if ( ! class_exists( PucFactory::class ) ) {
			if ( ! file_exists( $loader ) ) {
				return;
			}
			require_once $loader;
		}

		$checker = PucFactory::buildUpdateChecker(
			FHM_GITHUB_REPO,
			FHM_FILE,
			'feco-harta-montaj'
		);

		// Update CONTROLAT din Release-uri/tag-uri (NU din ramură).
		// Dacă NU apelăm setBranch(), PUC folosește ultimul Release GitHub
		// (cu fallback pe ultimul tag) — exact comportamentul dorit.
		$api = $checker->getVcsApi();
		if ( $api && method_exists( $api, 'enableReleaseAssets' ) ) {
			// Dacă atașezi un .zip ca asset la Release, e preferat în locul
			// zip-ului generat automat din tag. Numele asset-ului: *.zip.
			$api->enableReleaseAssets( '/feco-harta-montaj.*\.zip/i' );
		}

		// Repo PRIVAT: autentificare cu token din wp-config.php.
		if ( defined( 'FHM_GITHUB_TOKEN' ) && FHM_GITHUB_TOKEN ) {
			$checker->setAuthentication( FHM_GITHUB_TOKEN );
		}

		self::$checker = $checker;
	}
}
