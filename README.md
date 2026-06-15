# FECO · Hartă Montaj Fose Septice

Plugin WordPress cu **hartă interactivă a județelor României** (SVG) și **formular
de solicitare montaj fose septice**. Lead-urile se salvează într-o tabelă proprie
(`{prefix}fhm_leads`) și pot fi exportate CSV din admin. Asset-urile (CSS/JS/SVG)
se încarcă **doar** pe paginile care conțin shortcode-ul.

- **Versiune**: 1.0.0
- **Requires WP**: 5.5+
- **Requires PHP**: 7.2+
- **Licență**: GPL-2.0-or-later

## Instalare

1. Plugins → Add New → Upload Plugin → alege `feco-harta-montaj.zip` → Install → Activate.
2. Pune shortcode-ul în pagina dorită (în Elementor: widget „Shortcode"):
   ```
   [feco_harta_montaj]
   ```
3. Cererile apar în admin la meniul **„Lead-uri montaj"** (+ Export CSV).

### Email notificări

Implicit merge pe emailul de admin (Setări → General). Pentru alt destinatar:

```php
add_filter( 'fhm_notify_email', function () { return 'comenzi@feco.ro'; } );
```

## Update „cu 1 click" din GitHub Releases

Plugin-ul include un mecanism de update direct din WordPress, alimentat din
**GitHub Releases**, folosind biblioteca
[Plugin Update Checker v5](https://github.com/YahnisElsts/plugin-update-checker)
(`lib/plugin-update-checker/`, nemodificată).

- Sursa de update sunt **Release-urile/tag-urile** GitHub (update controlat), nu ramura.
- Updater-ul (`FHM_Updater`) rulează **doar** în admin sau pe cron — zero cost pe front-end.
- URL-ul repo-ului se configurează prin constanta `FHM_GITHUB_REPO` din
  `feco-harta-montaj.php`:
  ```php
  define( 'FHM_GITHUB_REPO', 'https://github.com/andreialexandrupanait/feco-harta-montaj/' );
  ```

### Repo privat (token)

Dacă repo-ul este **privat**, definește un token de acces în `wp-config.php`
(**niciodată** în codul plugin-ului / în repo):

```php
define( 'FHM_GITHUB_TOKEN', 'ghp_xxxxxxxxxxxxxxxxxxxx' );
```

Fără token, plugin-ul tratează repo-ul ca public.

## Cum lansezi o versiune nouă (release)

1. Fă modificările și testează local.
2. **Bump versiune** în header-ul din `feco-harta-montaj.php` (linia `Version:`),
   plus opțional `FHM_VERSION` și `Version:` din `readme.txt`. Trebuie să fie
   strict mai mare ca versiunea instalată (semver), ex. `1.0.1`.
3. Commit + push pe ramura principală.
4. Creează un **Release/tag** pe GitHub cu tag-ul `v<versiune>` (ex. `v1.0.1`).
   Opțional, atașează un `.zip` numit `feco-harta-montaj*.zip` ca asset al Release-ului.
5. În WordPress apare **„Update available"** → actualizare cu un click.

> Tag-ul (`v1.0.1`) și versiunea din header (`1.0.1`) trebuie să corespundă.
> Dacă uiți bump-ul din header, WordPress nu va detecta update-ul.

## Structură

```
feco-harta-montaj.php   Bootstrap (constante, require, hooks)
uninstall.php           Dezinstalare (păstrează datele implicit)
includes/               Clasele FHM_* (DB, Shortcode, Ajax, Admin, Updater, Plugin)
assets/                 css/ js/ img/romania.svg (42 județe)
lib/plugin-update-checker/  Bibliotecă terță (PUC v5) — nu se modifică
```

Pentru convenții de dezvoltare vezi [`CLAUDE.md`](CLAUDE.md).
