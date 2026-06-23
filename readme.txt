=== FECO – Harta Montaj Fose Septice ===
Version: 1.0.9
Author: Simplead (https://simplead.ro)
Requires at least: 5.5
Requires PHP: 7.2

Dezvoltat de Simplead.

Harta interactiva a judetelor Romaniei + formular de solicitare montaj.
Lead-urile se salveaza intr-o tabela proprie ({prefix}fhm_leads).

INSTALARE
1. Plugins > Add New > Upload Plugin > alege feco-harta-montaj.zip > Install > Activate.
2. In pagina dorita pune shortcode-ul:  [feco_harta_montaj]
   (in Elementor: widget "Shortcode").
3. Cererile apar in admin la meniul "Lead-uri montaj" (+ Export CSV).

SETARI
Meniu "Lead-uri montaj" > "Setari". De acolo configurezi:
- Adrese email notificari (una/mai multe, separate prin virgula), expeditor (From), subiect.
- Auto-reply catre client (on/off + subiect + mesaj).
- Produs obligatoriu + limitare la o categorie WooCommerce.
- Pagina Thank-you: redirect dupa trimitere, strict cand bifa e activa si e aleasa o pagina.

EMAIL NOTIFICARI
Implicit merge pe emailul de admin daca nu setezi adrese in Setari.
Alternativ, prin filtru:
  add_filter( 'fhm_notify_email', function() { return 'comenzi@feco.ro'; } );

PRODUSE (WooCommerce)
Campul "Produs solicitat" preia produsele publicate din WooCommerce, in ordinea
setata in shop (menu_order). Lista e memorata in cache si reimprospatata automat
cand salvezi/modifici un produs. Fara WooCommerce, campul nu apare.

ANTI-SPAM (reCAPTCHA v3 - optional)
Pe langa honeypot + rate-limit, poti activa reCAPTCHA v3 adaugand in wp-config.php:
  define( 'FHM_RECAPTCHA_SITE_KEY', '...' );
  define( 'FHM_RECAPTCHA_SECRET', '...' );
Fara aceste constante, reCAPTCHA e dezactivat (raman honeypot + rate-limit).

LEAD-URI (admin)
Lista de lead-uri are filtre (judet / produs / interval data), cautare, paginare,
status editabil (nou/contactat/ofertat/inchis), stergere si Export CSV (cu filtre).
Telefonul si emailul sunt clicabile (tel: / mailto:). Fiecare lead are:
- "Vezi" -> vizualizare in detaliu (toate campurile);
- "Retrimite" -> retrimite notificarea catre admin pentru acel lead.
Selectie multipla (bifezi randuri sau "select all") + Actiuni in masa:
stergere, schimbare status, retrimitere notificare, export CSV doar pentru cele bifate.

EMAIL NOTIFICARE
Notificarea catre admin e HTML, cu telefon (tel:) si email (mailto:) apelabile.

EMAIL CATRE CLIENT
Daca lead-ul lasa email, primeste un auto-reply de confirmare, iar notificarea catre
admin are Reply-To = emailul clientului. Dezactivare auto-reply:
  add_filter( 'fhm_autoreply_enabled', '__return_false' );

PERFORMANTA
CSS / JS / SVG se incarca DOAR pe paginile care contin shortcode-ul.
Fisierele sunt externe si memorate in cache de browser.
