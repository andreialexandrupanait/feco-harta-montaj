(function () {
	if (typeof FHM_DATA === 'undefined') { return; }
	var wrap = document.querySelector('.fhm-wrap');
	if (!wrap) { return; }
	var svgBox  = document.getElementById('fhm-svg');
	var slot    = document.getElementById('fhm-slot');
	var selname = document.getElementById('fhm-selname');
	var isMobile = function () { return window.matchMedia('(max-width:720px)').matches; };

	function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }
	function val(id) { var el = document.getElementById(id); return el ? el.value : ''; }

	// Nonce „cache-safe": pe pagini cu page-cache, nonce-ul randat poate fi expirat,
	// așa că cerem unul proaspăt la deschiderea formularului.
	var currentNonce = FHM_DATA.nonce;
	function refreshNonce() {
		var fd = new FormData();
		fd.append('action', 'fhm_nonce');
		fetch(FHM_DATA.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) { if (res && res.success && res.data && res.data.nonce) { currentNonce = res.data.nonce; } })
			.catch(function () {});
	}

	function validPhone(p) {
		var d = (p || '').replace(/[^0-9+]/g, '').replace(/^(\+40|0040)/, '0');
		return /^0(7\d{8}|[23]\d{8})$/.test(d);
	}
	function validEmail(e) { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(e); }

	// SVG extern, incarcat o singura data si memorat in cache de browser.
	fetch(FHM_DATA.svgUrl, { credentials: 'same-origin' })
		.then(function (r) { return r.text(); })
		.then(function (svg) { svgBox.innerHTML = svg; wire(); })
		.catch(function () { svgBox.innerHTML = '<div class="fhm-loading">Harta nu a putut fi încărcată.</div>'; });

	function wire() {
		var juds = svgBox.querySelectorAll('.fhm-jud');
		Array.prototype.forEach.call(juds, function (p) {
			p.addEventListener('click', function () {
				Array.prototype.forEach.call(juds, function (x) { x.classList.remove('fhm-active'); });
				p.classList.add('fhm-active');
				var name = p.getAttribute('data-name'), sl = p.getAttribute('data-slug');
				if (selname) { selname.textContent = name; }
				renderForm(name, sl);
				if (isMobile()) {
					var fp = wrap.querySelector('.fhm-formpanel');
					if (fp) { fp.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
				}
			});
		});
	}

	function productOptions() {
		var list = FHM_DATA.products || [];
		if (!list.length) { return ''; }
		var opts = '<option value="">— Alege produsul (opțional) —</option>';
		for (var i = 0; i < list.length; i++) {
			opts += '<option value="' + esc(list[i].id) + '">' + esc(list[i].name) + '</option>';
		}
		return '<div class="fhm-field"><label>Produs solicitat</label><select id="fhm-produs">' + opts + '</select><div class="fhm-fielderr" id="fhm-err-produs"></div></div>';
	}

	function consentLabel() {
		var base = 'Sunt de acord cu prelucrarea datelor în scopul contactării.';
		if (FHM_DATA.privacyUrl) {
			base += ' <a href="' + esc(FHM_DATA.privacyUrl) + '" target="_blank" rel="noopener">Politica de confidențialitate</a>.';
		}
		return base;
	}

	function renderForm(name, sl) {
		refreshNonce();
		slot.innerHTML =
			'<div class="fhm-form">' +
			'<h3>Montaj fose septice</h3>' +
			'<p class="fhm-sub">Completează datele — te contactăm cu ofertă pentru zona ta.</p>' +
			'<div class="fhm-field"><label>Județ</label><input class="fhm-locked" id="fhm-judet" value="' + esc(name) + '" readonly></div>' +
			'<input type="hidden" id="fhm-slug" value="' + esc(sl) + '">' +
			'<div class="fhm-row">' +
			'<div class="fhm-field"><label>Nume *</label><input id="fhm-nume" placeholder="Numele tău"><div class="fhm-fielderr" id="fhm-err-nume"></div></div>' +
			'<div class="fhm-field"><label>Telefon *</label><input id="fhm-tel" placeholder="07xx xxx xxx"><div class="fhm-fielderr" id="fhm-err-telefon"></div></div>' +
			'</div>' +
			'<div class="fhm-row">' +
			'<div class="fhm-field"><label>Email</label><input id="fhm-email" placeholder="email@exemplu.ro"><div class="fhm-fielderr" id="fhm-err-email"></div></div>' +
			'<div class="fhm-field"><label>Localitate</label><input id="fhm-localitate" placeholder="Localitatea ta"></div>' +
			'</div>' +
			productOptions() +
			'<div class="fhm-field"><label>Detalii (opțional)</label><textarea id="fhm-det" rows="2" placeholder="Capacitate fosă, termen..."></textarea></div>' +
			'<div class="fhm-hp"><label>Website</label><input id="fhm-website" autocomplete="off" tabindex="-1"></div>' +
			'<div class="fhm-field fhm-consent-field"><label class="fhm-consent"><input type="checkbox" id="fhm-consent"> ' + consentLabel() + '</label><div class="fhm-fielderr" id="fhm-err-consent"></div></div>' +
			'<button class="fhm-btn" id="fhm-send">Trimite solicitarea</button>' +
			'<div class="fhm-msg" id="fhm-msg"></div>' +
			'</div>';
		var btn = document.getElementById('fhm-send');
		if (btn) { btn.addEventListener('click', send); }
	}

	function setErr(field, message) {
		var e = document.getElementById('fhm-err-' + field);
		if (e) { e.textContent = message || ''; }
	}
	function clearErrs() { ['nume', 'telefon', 'email', 'produs', 'consent'].forEach(function (f) { setErr(f, ''); }); }
	function focusField(field) {
		var map = { nume: 'fhm-nume', telefon: 'fhm-tel', email: 'fhm-email', produs: 'fhm-produs', consent: 'fhm-consent' };
		var el = document.getElementById(map[field] || '');
		if (el) { el.focus(); }
	}

	function withRecaptcha(cb) {
		if (FHM_DATA.recaptchaKey && typeof grecaptcha !== 'undefined') {
			grecaptcha.ready(function () {
				grecaptcha.execute(FHM_DATA.recaptchaKey, { action: 'submit' }).then(function (token) { cb(token); }, function () { cb(''); });
			});
		} else {
			cb('');
		}
	}

	function send() {
		var btn = document.getElementById('fhm-send');
		var msg = document.getElementById('fhm-msg');
		msg.className = 'fhm-msg'; msg.textContent = '';
		clearErrs();

		var ok = true, first = '';
		if (!val('fhm-nume').trim()) { setErr('nume', 'Completează numele.'); ok = false; first = first || 'nume'; }
		if (!validPhone(val('fhm-tel'))) { setErr('telefon', 'Telefon invalid (ex: 07xx xxx xxx).'); ok = false; first = first || 'telefon'; }
		var email = val('fhm-email').trim();
		if (email && !validEmail(email)) { setErr('email', 'Adresa de email nu este validă.'); ok = false; first = first || 'email'; }
		var prodEl = document.getElementById('fhm-produs');
		if (FHM_DATA.productRequired && prodEl && !prodEl.value) { setErr('produs', 'Te rugăm alege un produs.'); ok = false; first = first || 'produs'; }
		if (!document.getElementById('fhm-consent').checked) { setErr('consent', 'Trebuie să accepți prelucrarea datelor.'); ok = false; first = first || 'consent'; }
		if (!ok) { focusField(first); return; }

		btn.disabled = true; btn.textContent = 'Se trimite...';
		withRecaptcha(function (token) {
			var fd = new FormData();
			fd.append('action', 'fhm_submit');
			fd.append('nonce', currentNonce);
			fd.append('judet', val('fhm-judet'));
			fd.append('judet_slug', val('fhm-slug'));
			fd.append('nume', val('fhm-nume'));
			fd.append('telefon', val('fhm-tel'));
			fd.append('email', val('fhm-email'));
			fd.append('localitate', val('fhm-localitate'));
			fd.append('produs_id', val('fhm-produs'));
			fd.append('detalii', val('fhm-det'));
			fd.append('website', val('fhm-website'));
			fd.append('consent', document.getElementById('fhm-consent').checked ? '1' : '');
			fd.append('recaptcha_token', token);
			fetch(FHM_DATA.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res && res.success) {
						if (FHM_DATA.redirectUrl) { window.location.href = FHM_DATA.redirectUrl; return; }
						slot.innerHTML = '<div class="fhm-ok"><div class="fhm-ic">&#10003;</div><div style="font-weight:800;font-size:16px">Solicitare trimisă</div><div style="font-size:12.5px;color:#6b7785;margin-top:6px">' + esc(res.data && res.data.message ? res.data.message : 'Mulțumim!') + '</div></div>';
					} else {
						btn.disabled = false; btn.textContent = 'Trimite solicitarea';
						var m = (res.data && res.data.message) ? res.data.message : 'A apărut o eroare.';
						if (res.data && res.data.field) { setErr(res.data.field, m); focusField(res.data.field); }
						else { msg.className = 'fhm-msg fhm-err'; msg.textContent = m; }
					}
				})
				.catch(function () {
					btn.disabled = false; btn.textContent = 'Trimite solicitarea';
					msg.className = 'fhm-msg fhm-err'; msg.textContent = 'Eroare de rețea. Reîncearcă.';
				});
		});
	}
})();
