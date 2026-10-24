/* Waypost front end: menu, instant search, Cmd/Ctrl+K, contents highlighting, copy buttons. No dependencies. */
(function () {
	'use strict';

	var cfg = window.waypostConfig || {};
	var t = cfg.i18n || {};
	var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	var isMac = /Mac|iPhone|iPad/.test(navigator.platform || navigator.userAgent);

	function el(tag, cls, text) {
		var n = document.createElement(tag);
		if (cls) n.className = cls;
		if (text != null) n.textContent = text;
		return n;
	}

	/* ── Menu ─────────────────────────────────────────── */

	function setOpen(menu, list, open, animate) {
		var controls = menu.querySelectorAll('[aria-controls="' + list.id + '"]');
		controls.forEach(function (c) { c.setAttribute('aria-expanded', open ? 'true' : 'false'); });
		if (!animate || reduceMotion) {
			list.hidden = !open;
			return;
		}
		if (open) {
			list.hidden = false;
			var h = list.scrollHeight;
			list.style.height = '0px';
			list.classList.add('is-animating');
			requestAnimationFrame(function () { list.style.height = h + 'px'; });
		} else {
			list.style.height = list.scrollHeight + 'px';
			list.classList.add('is-animating');
			requestAnimationFrame(function () { list.style.height = '0px'; });
		}
		var done = function () {
			list.classList.remove('is-animating');
			list.style.height = '';
			if (!open) list.hidden = true;
			list.removeEventListener('transitionend', done);
		};
		list.addEventListener('transitionend', done);
		setTimeout(done, 400);
	}

	function initMenu(menu) {
		menu.classList.add('is-collapsible');
		var accordion = menu.getAttribute('data-accordion') === '1';
		var root = menu.querySelector('.waypost-menu__list--root');

		menu.addEventListener('click', function (e) {
			var btn = e.target.closest('.waypost-menu__toggle, button.waypost-menu__label, .waypost-menu__mobile');
			if (!btn || !menu.contains(btn)) return;
			e.preventDefault();

			if (btn.classList.contains('waypost-menu__mobile')) {
				var open = !menu.classList.contains('is-open');
				menu.classList.toggle('is-open', open);
				btn.setAttribute('aria-expanded', open ? 'true' : 'false');
				return;
			}

			var list = document.getElementById(btn.getAttribute('aria-controls'));
			if (!list) return;
			var willOpen = btn.getAttribute('aria-expanded') !== 'true';
			var item = btn.closest('.waypost-menu__item');

			if (willOpen && accordion && item && item.parentElement === root) {
				Array.prototype.forEach.call(root.children, function (li) {
					if (li === item) return;
					var sub = li.querySelector(':scope > .waypost-menu__list');
					if (sub && !sub.hidden) setOpen(menu, sub, false, true);
				});
			}
			setOpen(menu, list, willOpen, true);
		});
	}

	/* ── Search ───────────────────────────────────────── */

	function words(q) {
		return q.toLowerCase().split(/\s+/).filter(function (w) { return w.length > 1; });
	}

	function appendHighlighted(node, text, q) {
		var ws = words(q).map(function (w) { return w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); });
		if (!ws.length) { node.appendChild(document.createTextNode(text)); return; }
		var parts = text.split(new RegExp('(' + ws.join('|') + ')', 'ig'));
		parts.forEach(function (p, i) {
			if (!p) return;
			node.appendChild(i % 2 ? el('mark', '', p) : document.createTextNode(p));
		});
	}

	function logSearch(q) {
		if (!cfg.api || !q) return;
		try {
			fetch(cfg.api + (cfg.api.indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(q) + '&log=1', { keepalive: true, credentials: 'same-origin' });
		} catch (e) { /* logging is best effort */ }
	}

	function SearchUI(form, opts) {
		this.form = form;
		this.opts = opts || {};
		this.input = form.querySelector('.waypost-search__input');
		this.panel = form.querySelector('.waypost-search__panel');
		this.timer = null;
		this.logTimer = null;
		this.ctrl = null;
		this.active = -1;
		this.q = '';
		var self = this;

		var kbd = form.querySelector('.waypost-search__kbd');
		if (kbd) {
			kbd.textContent = isMac ? '⌘K' : 'Ctrl K';
			if (!cfg.shortcut || this.opts.inModal) kbd.remove();
		}

		this.input.addEventListener('input', function () { self.onInput(); });
		this.input.addEventListener('keydown', function (e) { self.onKey(e); });
		this.input.addEventListener('focus', function () { if (self.panel.childNodes.length && self.q) self.show(); });
		form.addEventListener('submit', function (e) {
			if (self.opts.inHome) return; // initHome() lists the results on the page instead.
			var q = self.input.value.trim();
			if (!cfg.resultsUrl || q.length < 2) {
				e.preventDefault();
				if (q.length >= 2) self.fetch(q);
			}
		});
		if (!this.opts.inModal) {
			document.addEventListener('click', function (e) { if (!form.contains(e.target)) self.hide(); });
		}
		this.panel.addEventListener('click', function (e) {
			var a = e.target.closest('a.waypost-hit');
			if (a) logSearch(self.q);
		});
	}

	SearchUI.prototype.onInput = function () {
		var q = this.input.value.trim();
		var self = this;
		clearTimeout(this.timer);
		clearTimeout(this.logTimer);
		if (q.length < 2) {
			this.q = '';
			if (!this.suggest()) {
				this.panel.innerHTML = '';
				this.hide();
			}
			return;
		}
		this.timer = setTimeout(function () { self.fetch(q); }, 180);
	};

	SearchUI.prototype.fetch = function (q) {
		var self = this;
		if (this.ctrl) this.ctrl.abort();
		this.ctrl = window.AbortController ? new AbortController() : null;
		this.q = q;
		if (!this.panel.childNodes.length) this.status(t.searching || 'Searching…');
		var url = cfg.api + (cfg.api.indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(q);
		fetch(url, { signal: this.ctrl ? this.ctrl.signal : undefined, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (q !== self.input.value.trim()) return;
				self.render(data, q);
			})
			.catch(function (err) { if (err && err.name !== 'AbortError') self.status(t.noResults || 'No results'); });
	};

	SearchUI.prototype.status = function (msg) {
		this.panel.innerHTML = '';
		this.panel.appendChild(el('div', 'waypost-search__status', msg));
		this.show();
	};

	SearchUI.prototype.render = function (data, q) {
		var self = this;
		var results = (data && data.results) || [];
		this.panel.innerHTML = '';
		this.active = -1;

		if (!results.length) {
			this.status(t.noResults || 'No results');
			this.logTimer = setTimeout(function () { logSearch(q); }, 1500);
			return;
		}

		var idx = 0;
		results.forEach(function (r) {
			var a = el('a', 'waypost-hit');
			a.href = r.url;
			a.id = self.panel.id + '-o' + (idx++);
			a.setAttribute('role', 'option');
			if (r.section) a.appendChild(el('span', 'waypost-hit__section', r.section));
			var title = el('span', 'waypost-hit__title');
			appendHighlighted(title, r.title, q);
			a.appendChild(title);
			if (r.excerpt) {
				var ex = el('span', 'waypost-hit__excerpt');
				appendHighlighted(ex, r.excerpt, q);
				a.appendChild(ex);
			}
			self.panel.appendChild(a);

			(r.headings || []).forEach(function (h) {
				var ha = el('a', 'waypost-hit waypost-hit--heading');
				ha.href = h.url;
				ha.id = self.panel.id + '-o' + (idx++);
				ha.setAttribute('role', 'option');
				appendHighlighted(ha, h.text, q);
				self.panel.appendChild(ha);
			});
		});

		if (cfg.resultsUrl && data.total > results.length) {
			var all = el('a', 'waypost-hit waypost-search__all', (t.seeAll || 'See all results') + ' (' + data.total + ')');
			all.href = cfg.resultsUrl + (cfg.resultsUrl.indexOf('?') > -1 ? '&' : '?') + 'dq=' + encodeURIComponent(q);
			all.id = self.panel.id + '-o' + idx;
			all.setAttribute('role', 'option');
			this.panel.appendChild(all);
		}
		this.show();
	};

	/* Quick searches from Settings, shown in the Ctrl+K window before anything is typed. */
	SearchUI.prototype.suggest = function () {
		var self = this, quick = cfg.quick || [];
		if (!this.opts.inModal || !quick.length) return false;
		this.panel.innerHTML = '';
		this.active = -1;
		var box = el('div', 'waypost-search__suggest');
		box.appendChild(el('div', 'waypost-search__suggest-title', t.popular || 'Popular searches'));
		var chips = el('div', 'waypost-chips');
		quick.forEach(function (it, i) {
			var b = el('button', 'waypost-chip', it.label);
			b.type = 'button';
			b.id = self.panel.id + '-s' + i;
			b.setAttribute('role', 'option');
			b.setAttribute('data-waypost-query', it.query);
			chips.appendChild(b);
		});
		box.appendChild(chips);
		this.panel.appendChild(box);
		this.show();
		return true;
	};

	SearchUI.prototype.items = function () {
		return Array.prototype.slice.call(this.panel.querySelectorAll('a.waypost-hit, .waypost-chip'));
	};

	SearchUI.prototype.move = function (dir) {
		var items = this.items();
		if (!items.length) return;
		this.active = (this.active + dir + items.length) % items.length;
		items.forEach(function (a, i) { a.classList.toggle('is-active', i === this.active); }, this);
		var cur = items[this.active];
		this.input.setAttribute('aria-activedescendant', cur.id);
		cur.scrollIntoView({ block: 'nearest' });
	};

	SearchUI.prototype.onKey = function (e) {
		if (e.key === 'ArrowDown') { e.preventDefault(); this.show(); this.move(1); }
		else if (e.key === 'ArrowUp') { e.preventDefault(); this.move(-1); }
		else if (e.key === 'Enter') {
			var items = this.items();
			if (this.active > -1 && items[this.active]) {
				e.preventDefault();
				if (items[this.active].tagName === 'BUTTON') { items[this.active].click(); return; }
				logSearch(this.q);
				window.location.href = items[this.active].href;
			}
		} else if (e.key === 'Escape') {
			if (this.opts.onEscape) this.opts.onEscape();
			else this.hide();
		}
	};

	SearchUI.prototype.show = function () {
		if (!this.panel.childNodes.length) return;
		this.panel.hidden = false;
		this.input.setAttribute('aria-expanded', 'true');
	};

	SearchUI.prototype.hide = function () {
		if (this.opts.inModal) return;
		this.panel.hidden = true;
		this.input.setAttribute('aria-expanded', 'false');
		this.input.removeAttribute('aria-activedescendant');
	};

	/* ── Cmd/Ctrl + K modal ───────────────────────────── */

	var modal = null, modalUI = null, lastFocus = null;

	function buildModal() {
		modal = el('div', 'waypost-modal');
		modal.hidden = true;
		modal.setAttribute('role', 'dialog');
		modal.setAttribute('aria-modal', 'true');
		modal.setAttribute('aria-label', t.results || 'Search');

		var box = el('div', 'waypost-modal__box');
		var form = el('form', 'waypost-search');
		form.setAttribute('role', 'search');
		form.method = 'get';
		form.action = cfg.resultsUrl || '';
		var field = el('div', 'waypost-search__field');
		field.innerHTML = '<svg class="waypost-search__icon" width="18" height="18" viewBox="0 0 16 16" aria-hidden="true"><circle cx="7" cy="7" r="4.8" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m10.6 10.6 3.4 3.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>';
		var input = el('input', 'waypost-search__input');
		input.type = 'search';
		input.name = 'dq';
		input.autocomplete = 'off';
		input.placeholder = cfg.placeholder || '';
		input.setAttribute('role', 'combobox');
		input.setAttribute('aria-label', cfg.placeholder || 'Search');
		input.setAttribute('aria-controls', 'waypost-modal-list');
		input.setAttribute('aria-expanded', 'false');
		field.appendChild(input);
		var panel = el('div', 'waypost-search__panel');
		panel.id = 'waypost-modal-list';
		panel.setAttribute('role', 'listbox');
		form.appendChild(field);
		form.appendChild(panel);
		box.appendChild(form);
		box.appendChild(el('div', 'waypost-modal__foot', t.navHint || ''));
		modal.appendChild(box);
		document.body.appendChild(modal);

		modal.addEventListener('mousedown', function (e) { if (e.target === modal) closeModal(); });
		modalUI = new SearchUI(form, { inModal: true, onEscape: closeModal });
	}

	function openModal() {
		if (!modal) buildModal();
		lastFocus = document.activeElement;
		modal.hidden = false;
		document.body.classList.add('waypost-modal-open');
		modalUI.input.focus();
		modalUI.input.select();
		if (modalUI.input.value.trim().length < 2) modalUI.suggest();
	}

	function closeModal() {
		if (!modal) return;
		modal.hidden = true;
		document.body.classList.remove('waypost-modal-open');
		if (lastFocus && lastFocus.focus) lastFocus.focus();
	}

	function initShortcut() {
		document.addEventListener('keydown', function (e) {
			var typing = /^(INPUT|TEXTAREA|SELECT)$/.test(e.target.tagName) || e.target.isContentEditable;
			if ((e.metaKey || e.ctrlKey) && (e.key === 'k' || e.key === 'K')) {
				e.preventDefault();
				if (modal && !modal.hidden) closeModal(); else openModal();
			} else if (e.key === '/' && !typing && !(modal && !modal.hidden)) {
				e.preventDefault();
				openModal();
			} else if (e.key === 'Tab' && modal && !modal.hidden) {
				e.preventDefault(); // Keep focus inside the dialog; arrows move through results.
				modalUI.input.focus();
			}
		});
	}

	/* ── Quick search buttons ─────────────────────────── */

	// In the search home a button runs a full search on the page; in the Ctrl+K window it fills the box
	// for instant results. Anywhere else it stays a link to where results are listed.
	function initQuickSearches() {
		document.addEventListener('click', function (e) {
			var chip = e.target.closest('.waypost-chip[data-waypost-query]');
			if (!chip) return;
			var scope = chip.closest('.waypost-home, .waypost-modal');
			var input = scope && scope.querySelector('.waypost-search__input');
			if (!input) return;
			e.preventDefault();
			input.value = chip.getAttribute('data-waypost-query');
			if (scope.classList.contains('waypost-home')) {
				input.form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
				return;
			}
			input.dispatchEvent(new Event('input', { bubbles: true }));
			input.focus();
		});
	}

	/* ── Full results inside the search home ──────────── */

	function fill(str, a, b) { return String(str || '').replace('%1$s', a).replace('%2$s', b); }

	// Same markup as Waypost_Render::results(), built with textContent only.
	function renderResults(list, data, q, pg, urlFor) {
		var per = cfg.perPage || 10;
		var total = (data && data.total) || 0;
		var results = (data && data.results) || [];
		list.textContent = '';
		// Two plural forms only; enough for the count line.
		var count = el('p', 'waypost-results__count', fill(total === 1 ? t.oneResult : t.manyResults, total.toLocaleString(), q));
		count.setAttribute('role', 'status');
		list.appendChild(count);
		if (!results.length) {
			var none = el('div', 'waypost-results__none');
			none.appendChild(el('p', '', t.noMatch || ''));
			list.appendChild(none);
			return;
		}
		var ol = el('ol', 'waypost-results__list');
		ol.start = (pg - 1) * per + 1;
		results.forEach(function (r) {
			var li = el('li', 'waypost-result');
			if (r.section) li.appendChild(el('p', 'waypost-result__section', r.section));
			var h = el('h3', 'waypost-result__title');
			var a = el('a');
			a.href = r.url;
			appendHighlighted(a, r.title, q);
			h.appendChild(a);
			li.appendChild(h);
			if (r.excerpt) {
				var ex = el('p', 'waypost-result__excerpt');
				appendHighlighted(ex, r.excerpt, q);
				li.appendChild(ex);
			}
			if (r.headings && r.headings.length) {
				var ul = el('ul', 'waypost-result__headings');
				r.headings.forEach(function (hd) {
					var hli = el('li');
					var ha = el('a');
					ha.href = hd.url;
					appendHighlighted(ha, hd.text, q);
					hli.appendChild(ha);
					ul.appendChild(hli);
				});
				li.appendChild(ul);
			}
			ol.appendChild(li);
		});
		list.appendChild(ol);

		if (data.pages > 1) {
			var nav = el('nav', 'waypost-results__pages');
			nav.setAttribute('aria-label', t.pages || '');
			var link = function (n, text, cls) {
				var l = el('a', 'page-numbers' + (cls ? ' ' + cls : ''), text);
				l.href = urlFor(q, n);
				nav.appendChild(l);
			};
			if (pg > 1) link(pg - 1, t.prev || '‹', 'prev');
			var gap = false;
			for (var n = 1; n <= data.pages; n++) {
				if (n === 1 || n === data.pages || Math.abs(n - pg) <= 2) {
					gap = false;
					if (n === pg) {
						var cur = el('span', 'page-numbers current', String(n));
						cur.setAttribute('aria-current', 'page');
						nav.appendChild(cur);
					} else {
						link(n, String(n));
					}
				} else if (!gap) {
					gap = true;
					nav.appendChild(el('span', 'page-numbers dots', '…'));
				}
			}
			if (pg < data.pages) link(pg + 1, t.next || '›', 'next');
			list.appendChild(nav);
		}
	}

	// Pressing Enter in the search home lists every result below the box, on the same page, so the
	// page layout and any sidebar stay put. The address gets ?dq= so results can be shared and Back works.
	function initHome(home, ui) {
		var input = ui.input;
		var base = home.getAttribute('data-base') || location.href.split('?')[0];
		var box = home.querySelector('.waypost-home__results');
		var list = home.querySelector('.waypost-home__list');
		var back = home.querySelector('.waypost-home__back a');
		var ctrl = null, current = '';
		if (!box || !list || !cfg.api) return;

		function urlFor(q, pg) {
			var u = new URL(base, location.href);
			if (q) u.searchParams.set('dq', q);
			if (pg > 1) u.searchParams.set('pg', String(pg));
			return u.toString();
		}
		function show(on) {
			home.classList.toggle('is-searching', on);
			box.hidden = !on;
		}
		function load(q, pg, push, scroll) {
			current = q;
			ui.hide();
			if (ctrl) ctrl.abort();
			ctrl = window.AbortController ? new AbortController() : null;
			show(true);
			list.setAttribute('aria-busy', 'true');
			var url = cfg.api + (cfg.api.indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(q) + '&page=' + pg + '&per=' + (cfg.perPage || 10) + (pg === 1 ? '&log=1' : '');
			fetch(url, { signal: ctrl ? ctrl.signal : undefined, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					renderResults(list, data, q, pg, urlFor);
					list.removeAttribute('aria-busy');
					if (push) history.pushState({ waypostHome: true }, '', urlFor(q, pg));
					if (scroll) box.scrollIntoView({ block: 'start', behavior: reduceMotion ? 'auto' : 'smooth' });
				})
				.catch(function (err) { if (err && err.name !== 'AbortError') list.removeAttribute('aria-busy'); });
		}
		function clear(push) {
			if (ctrl) ctrl.abort();
			current = '';
			show(false);
			list.textContent = '';
			input.value = '';
			if (push) history.pushState({ waypostHome: true }, '', base);
		}

		ui.form.addEventListener('submit', function (e) {
			e.preventDefault();
			var q = input.value.trim();
			if (q.length >= 2) load(q, 1, true, false);
		});
		if (back) back.addEventListener('click', function (e) { e.preventDefault(); clear(true); input.focus(); });
		list.addEventListener('click', function (e) {
			var a = e.target.closest('.waypost-results__pages a.page-numbers');
			if (!a) return;
			e.preventDefault();
			var pg = parseInt(new URL(a.href, location.href).searchParams.get('pg') || '1', 10);
			load(current || input.value.trim(), pg, true, true);
		});
		window.addEventListener('popstate', function () {
			var p = new URLSearchParams(location.search);
			var q = (p.get('dq') || '').trim();
			if (q.length >= 2) {
				input.value = q;
				load(q, parseInt(p.get('pg') || '1', 10), false, false);
			} else {
				clear(false);
			}
		});
		current = input.value.trim(); // Page loaded with ?dq= and server-rendered results.
	}

	/* ── Table of contents highlighting ───────────────── */

	function initToc(toc) {
		if (!('IntersectionObserver' in window)) return;
		var links = Array.prototype.slice.call(toc.querySelectorAll('a[href^="#"]'));
		var map = {};
		var targets = [];
		links.forEach(function (a) {
			var id = decodeURIComponent(a.getAttribute('href').slice(1));
			var h = document.getElementById(id);
			if (h) { map[id] = a; targets.push(h); }
		});
		if (!targets.length) return;
		var visible = {};
		var obs = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) { visible[en.target.id] = en.isIntersecting; });
			var first = targets.find(function (h) { return visible[h.id]; });
			if (!first) return;
			links.forEach(function (a) { a.classList.remove('is-active'); });
			map[first.id].classList.add('is-active');
		}, { rootMargin: '0px 0px -65% 0px' });
		targets.forEach(function (h) { obs.observe(h); });
	}

	/* ── Copy buttons on code blocks ──────────────────── */

	function initCopy() {
		document.querySelectorAll('.entry-content pre, .et_pb_post_content pre, .wp-block-post-content pre, main pre').forEach(function (pre) {
			if (pre.closest('.waypost-code')) return;
			var wrap = el('div', 'waypost-code');
			pre.parentNode.insertBefore(wrap, pre);
			wrap.appendChild(pre);
			var btn = el('button', 'waypost-copy', t.copy || 'Copy');
			btn.type = 'button';
			btn.addEventListener('click', function () {
				var text = pre.innerText;
				var ok = function () { btn.textContent = t.copied || 'Copied'; setTimeout(function () { btn.textContent = t.copy || 'Copy'; }, 1600); };
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(text).then(ok).catch(function () {});
				}
			});
			wrap.appendChild(btn);
		});
	}

	/* ── Boot ─────────────────────────────────────────── */

	function boot() {
		document.querySelectorAll('.waypost-menu').forEach(initMenu);
		var boxes = document.querySelectorAll('form[data-waypost-search]');
		boxes.forEach(function (f) {
			var home = f.closest('[data-waypost-home]');
			var ui = new SearchUI(f, { inHome: !!home });
			if (home) initHome(home, ui);
		});
		document.querySelectorAll('.waypost-toc').forEach(initToc);
		initQuickSearches();
		if (cfg.shortcut && (cfg.isDoc || boxes.length)) initShortcut();
		if (cfg.copyCode && cfg.isDoc) initCopy();
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
