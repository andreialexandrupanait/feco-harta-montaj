# CLAUDE.md — FECO · Hartă Montaj Fose Septice

Ghid pentru orice agent/dezvoltator care lucrează în acest repo. Respectă convențiile
de mai jos; nu schimba logica existentă fără motiv.

## Ce este

Plugin WordPress: hartă interactivă a județelor României (SVG) + formular de
solicitare montaj fose septice. Lead-urile se salvează într-o tabelă proprie și
apar în admin (cu Export CSV). Asset-urile se încarcă **doar** pe paginile cu
shortcode-ul `[feco_harta_montaj]`.

## Convenții (OBLIGATORIU)

- **Prefix**: tot ce e public folosește prefixul `FHM_` (clase/constante) și `fhm`
  / `fhm-` (handle-uri asset, opțiuni, chei CSS/JS, tabelă). Fără excepții.
- **WordPress Coding Standards**: tab pentru indentare, spații în interiorul
  parantezelor `( ... )`, Yoda conditions, escaping la output (`esc_html`,
  `esc_url`, `esc_attr`), sanitizare la input (`sanitize_*`, `wp_unslash`),
  nonce la fiecare acțiune (AJAX + admin-post).
- **Încărcare condiționată a asset-urilor**: CSS/JS/SVG sunt doar `wp_register_*`
  global (zero output) și `wp_enqueue_*` **exclusiv** la randarea shortcode-ului
  (`FHM_Shortcode::render`). NU muta enqueue-ul în `wp_enqueue_scripts` global —
  ar încărca pe tot site-ul. Acesta e un principiu de bază al plugin-ului.
- **Tabelă proprie**: `{prefix}fhm_leads`, gestionată de `FHM_DB` (creată cu
  `dbDelta` la activare + `maybe_upgrade`). Fără legătură cu `posts`/`options`.
- **Securitate DB**: `$wpdb->insert()` cu format specifiers; orice `LIMIT` din input
  se cast-uiește la `(int)`. Niciun input neparametrizat în SQL.

## Structură

```
feco-harta-montaj.php        Bootstrap: constante, require-uri, hooks (activare + plugins_loaded)
uninstall.php                Dezinstalare (păstrează datele implicit)
includes/
  class-fhm-plugin.php       Orchestrare init() (apelat pe plugins_loaded)
  class-fhm-db.php           Tabela fhm_leads (create/insert/get)
  class-fhm-shortcode.php    Shortcode [feco_harta_montaj] + enqueue condiționat
  class-fhm-ajax.php         admin-ajax fhm_submit (nonce, sanitizare, honeypot, rate-limit, GDPR)
  class-fhm-admin.php        Meniu „Lead-uri montaj" + Export CSV
  class-fhm-updater.php      Update „cu 1 click" din GitHub Releases (PUC v5)
assets/
  css/fhm.css  js/fhm.js  img/romania.svg   (42 județe: 41 + București)
lib/
  plugin-update-checker/     Bibliotecă terță (YahnisElsts/plugin-update-checker v5). NU se modifică.
```

## Stilul claselor

Toate clasele sunt statice, cu guard `if ( ! defined( 'ABSPATH' ) ) { exit; }` în
capul fișierului și o metodă `public static function init()` care înregistrează
hook-urile. Inițializarea se face din `FHM_Plugin::init()`.

## Mecanismul de update („cu 1 click")

- Bibliotecă: **Plugin Update Checker v5** (`lib/plugin-update-checker/`) — terță,
  nemodificată.
- Clasa `FHM_Updater` rulează **doar** în admin sau pe cron
  (`is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON )`).
- Sursa = **GitHub Releases/tag-uri** (update controlat, NU ramura). În PUC asta
  înseamnă că NU apelăm `setBranch()` — folosește ultimul Release (fallback pe tag).
- Repo configurat prin constanta `FHM_GITHUB_REPO` (în `feco-harta-montaj.php`).
- Repo **privat**: token din constanta `FHM_GITHUB_TOKEN`, definită în
  `wp-config.php`. **NU comite niciun token în repo.** Fără token → repo public.

### Cum lansezi o versiune nouă (release)

1. Fă modificările și testează local.
2. **Bump versiune** în header-ul din `feco-harta-montaj.php` — linia `Version:`
   (și, opțional, constanta `FHM_VERSION` + `Version:` din `readme.txt`).
   Versiunea din header TREBUIE să fie mai mare ca cea instalată (semver), ex. `1.0.1`.
3. Commit + push pe ramura principală.
4. Creează un **Release/tag** pe GitHub cu tag-ul `v<versiune>` (ex. `v1.0.1`).
   Opțional, atașează un `.zip` numit `feco-harta-montaj*.zip` ca asset (PUC îl
   preferă în locul zip-ului auto-generat din tag).
5. În WordPress (Plugins / Dashboard → Updates) apare **„Update available"** →
   actualizare cu un click. Verificarea rulează automat (la ~12h) sau imediat dacă
   apeși „Check for updates".

> Tag-ul (`v1.0.1`) și versiunea din header (`1.0.1`) trebuie să corespundă.
> Dacă uiți să faci bump în header, WordPress NU va vedea update-ul.
