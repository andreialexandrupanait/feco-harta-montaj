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
		var list = (typeof FHM_DATA !== 'undefined' && FHM_DATA.products) ? FHM_DATA.products : [];
		if (!list.length) { return ''; }
		var opts = '<option value="">— Alege produsul (opțional) —</option>';
		for (var i = 0; i < list.length; i++) {
			opts += '<option value="' + esc(list[i].id) + '">' + esc(list[i].name) + '</option>';
		}
		return '<div class="fhm-field"><label>Produs solicitat</label><select id="fhm-produs">' + opts + '</select></div>';
	}

	function renderForm(name, sl) {
		slot.innerHTML =
			'<div class="fhm-form">' +
			'<h3>Montaj fose septice</h3>' +
			'<p class="fhm-sub">Completează datele — te contactăm cu ofertă pentru zona ta.</p>' +
			'<div class="fhm-field"><label>Județ</label><input class="fhm-locked" id="fhm-judet" value="' + esc(name) + '" readonly></div>' +
			'<input type="hidden" id="fhm-slug" value="' + esc(sl) + '">' +
			'<div class="fhm-row">' +
			'<div class="fhm-field"><label>Nume *</label><input id="fhm-nume" placeholder="Numele tău"></div>' +
			'<div class="fhm-field"><label>Telefon *</label><input id="fhm-tel" placeholder="07xx xxx xxx"></div>' +
			'</div>' +
			'<div class="fhm-field"><label>Email</label><input id="fhm-email" placeholder="email@exemplu.ro"></div>' +
			productOptions() +
			'<div class="fhm-field"><label>Detalii (opțional)</label><textarea id="fhm-det" rows="2" placeholder="Capacitate fosă, localitate, termen..."></textarea></div>' +
			'<div class="fhm-hp"><label>Website</label><input id="fhm-website" autocomplete="off" tabindex="-1"></div>' +
			'<div class="fhm-field fhm-consent-field"><label class="fhm-consent"><input type="checkbox" id="fhm-consent"> Sunt de acord cu prelucrarea datelor în scopul contactării.</label></div>' +
			'<button class="fhm-btn" id="fhm-send">Trimite solicitarea</button>' +
			'<div class="fhm-msg" id="fhm-msg"></div>' +
			'</div>';
		var btn = document.getElementById('fhm-send');
		if (btn) { btn.addEventListener('click', send); }
	}

	function send() {
		var btn = document.getElementById('fhm-send');
		var msg = document.getElementById('fhm-msg');
		msg.className = 'fhm-msg'; msg.textContent = '';
		var fd = new FormData();
		fd.append('action', 'fhm_submit');
		fd.append('nonce', FHM_DATA.nonce);
		fd.append('judet', val('fhm-judet'));
		fd.append('judet_slug', val('fhm-slug'));
		fd.append('nume', val('fhm-nume'));
		fd.append('telefon', val('fhm-tel'));
		fd.append('email', val('fhm-email'));
		fd.append('produs_id', val('fhm-produs'));
		fd.append('detalii', val('fhm-det'));
		fd.append('website', val('fhm-website'));
		fd.append('consent', document.getElementById('fhm-consent').checked ? '1' : '');
		btn.disabled = true; btn.textContent = 'Se trimite...';
		fetch(FHM_DATA.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (res && res.success) {
					slot.innerHTML = '<div class="fhm-ok"><div class="fhm-ic">&#10003;</div><div style="font-weight:800;font-size:16px">Solicitare trimisă</div><div style="font-size:12.5px;color:#6b7785;margin-top:6px">' + esc(res.data && res.data.message ? res.data.message : 'Mulțumim!') + '</div></div>';
				} else {
					btn.disabled = false; btn.textContent = 'Trimite solicitarea';
					msg.className = 'fhm-msg fhm-err'; msg.textContent = (res.data && res.data.message) ? res.data.message : 'A apărut o eroare.';
				}
			})
			.catch(function () {
				btn.disabled = false; btn.textContent = 'Trimite solicitarea';
				msg.className = 'fhm-msg fhm-err'; msg.textContent = 'Eroare de rețea. Reîncearcă.';
			});
	}
})();
