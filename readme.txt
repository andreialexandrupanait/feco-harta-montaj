=== FECO – Harta Montaj Fose Septice ===
Version: 1.0.4
Author: Simplead (https://simplead.ro)
Requires at least: 5.5
Requires PHP: 7.2

Dezvoltat de Simplead folosind Claude Code.

Harta interactiva a judetelor Romaniei + formular de solicitare montaj.
Lead-urile se salveaza intr-o tabela proprie ({prefix}fhm_leads).

INSTALARE
1. Plugins > Add New > Upload Plugin > alege feco-harta-montaj.zip > Install > Activate.
2. In pagina dorita pune shortcode-ul:  [feco_harta_montaj]
   (in Elementor: widget "Shortcode").
3. Cererile apar in admin la meniul "Lead-uri montaj" (+ Export CSV).

EMAIL NOTIFICARI
Implicit merge pe emailul de admin (Setari > General).
Pentru alt destinatar, intr-un snippet mic:
  add_filter( 'fhm_notify_email', function() { return 'comenzi@feco.ro'; } );

PERFORMANTA
CSS / JS / SVG se incarca DOAR pe paginile care contin shortcode-ul.
Fisierele sunt externe si memorate in cache de browser.
