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

	// Marks are the words the server matched (other forms and synonyms included), or the typed search.
	// A match grows to the end of its word, so "install" marks all of "installing".
	function markPattern(marks) {
		var ws = (Array.isArray(marks) ? marks : words(String(marks || ''))).filter(Boolean)
			.map(function (w) { return w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); });
		if (!ws.length) return null;
		try {
			return new RegExp('((?:' + ws.join('|') + ')[\\p{L}\\p{N}]*)', 'giu');
		} catch (e) {
			return new RegExp('((?:' + ws.join('|') + ')\\w*)', 'gi');
		}
	}

	function appendHighlighted(node, text, marks) {
		var re = markPattern(marks);
		if (!re) { node.appendChild(document.createTextNode(text)); return; }
		String(text).split(re).forEach(function (p, i) {
			if (!p) return;
			node.appendChild(i % 2 ? el('mark', '', p) : document.createTextNode(p));
		});
	}

	// Remember what was searched when a result is opened, so the page can highlight it (initArrival).
	document.addEventListener('click', function (e) {
		var a = e.target.closest && e.target.closest('a[href]');
		var box = a && a.closest('[data-marks]');
		if (!box) return;
		try {
			sessionStorage.setItem('waypost-arrival', JSON.stringify({
				marks: JSON.parse(box.getAttribute('data-marks') || '[]'),
				q: box.getAttribute('data-query') || '',
				url: a.href.split('#')[0],
				time: Date.now()
			}));
		} catch (err) { /* private mode: no highlight */ }
	}, true);

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
		var marks = data && data.marks && data.marks.length ? data.marks : q;
		this.panel.innerHTML = '';
		this.active = -1;
		this.panel.setAttribute('data-marks', JSON.stringify(Array.isArray(marks) ? marks : words(q)));
		this.panel.setAttribute('data-query', q);

		if (!results.length) {
			this.status(t.noResults || 'No results');
			this.logTimer = setTimeout(function () { logSearch(q); }, 1500);
			return;
		}
		if (data.corrected) {
			this.panel.appendChild(el('div', 'waypost-search__status waypost-search__fix', fill(t.showingFor, data.corrected)));
		}

		var idx = 0;
		var answer = data.answer;
		if (answer) {
			var aa = el('a', 'waypost-hit waypost-hit--answer');
			aa.href = answer.url;
			aa.id = self.panel.id + '-o' + (idx++);
			aa.setAttribute('role', 'option');
			aa.appendChild(el('span', 'waypost-hit__label', t.bestAnswer || 'Best answer'));
			var apath = [answer.path, answer.page].filter(Boolean).join(' › ');
			if (apath) aa.appendChild(el('span', 'waypost-hit__section', apath));
			var at = el('span', 'waypost-hit__title');
			appendHighlighted(at, answer.title, marks);
			if (answer.file) at.appendChild(el('span', 'waypost-file', answer.file));
			aa.appendChild(at);
			if (answer.text) {
				var ax = el('span', 'waypost-hit__excerpt');
				appendHighlighted(ax, answer.text, marks);
				aa.appendChild(ax);
			}
			self.panel.appendChild(aa);
		}
		results.forEach(function (r) {
			// The answer's page is already on top: list only its other matching headings.
			var isAnswer = answer && r.id === answer.id;
			if (!isAnswer) {
				var a = el('a', 'waypost-hit');
				a.href = r.url;
				a.id = self.panel.id + '-o' + (idx++);
				a.setAttribute('role', 'option');
				if (r.section) a.appendChild(el('span', 'waypost-hit__section', r.section));
				var title = el('span', 'waypost-hit__title');
				appendHighlighted(title, r.title, marks);
				if (r.file) title.appendChild(el('span', 'waypost-file', r.file));
				a.appendChild(title);
				if (r.excerpt) {
					var ex = el('span', 'waypost-hit__excerpt');
					appendHighlighted(ex, r.excerpt, marks);
					a.appendChild(ex);
				}
				self.panel.appendChild(a);
			}

			(r.headings || []).forEach(function (h) {
				if (isAnswer && h.url === answer.url) return;
				var ha = el('a', 'waypost-hit waypost-hit--heading');
				ha.href = h.url;
				ha.id = self.panel.id + '-o' + (idx++);
				ha.setAttribute('role', 'option');
				var ht = el('span', 'waypost-hit__heading');
				appendHighlighted(ht, h.text, marks);
				ha.appendChild(ht);
				if (h.snippet) {
					var hs = el('span', 'waypost-hit__snippet');
					appendHighlighted(hs, h.snippet, marks);
					ha.appendChild(hs);
				}
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

	/* Drops a pending or running instant search, so its dropdown cannot open after the reader moved on. */
	SearchUI.prototype.cancel = function () {
		clearTimeout(this.timer);
		clearTimeout(this.logTimer);
		if (this.ctrl) this.ctrl.abort();
		this.ctrl = null;
		this.hide();
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
	// Mirrors Waypost_Render::results(). urlFor(q, page, topic) builds links that also work without JavaScript.
	function renderResults(list, data, q, pg, urlFor) {
		var per = cfg.perPage || 10;
		var total = (data && data.total) || 0;
		var results = (data && data.results) || [];
		var marks = data && data.marks && data.marks.length ? data.marks : words(q);
		var topic = (data && data.topic) || 0;
		list.textContent = '';
		var body = el('div', 'waypost-results__body');
		body.setAttribute('data-marks', JSON.stringify(marks));
		body.setAttribute('data-query', q);
		list.appendChild(body);
		// Two plural forms only; enough for the count line.
		var count = el('p', 'waypost-results__count', fill(total === 1 ? t.oneResult : t.manyResults, total.toLocaleString(), q));
		count.setAttribute('role', 'status');
		body.appendChild(count);

		if (data && data.corrected) {
			var fix = el('p', 'waypost-results__notice');
			var parts = String(t.corrected || '').replace('%1$s', q).split('%2$s');
			fix.appendChild(document.createTextNode(parts[0] || ''));
			fix.appendChild(el('strong', '', data.corrected));
			fix.appendChild(document.createTextNode(parts[1] || ''));
			body.appendChild(fix);
		} else if (data && data.partial) {
			body.appendChild(el('p', 'waypost-results__notice', t.partial || ''));
		}

		if (!results.length) {
			var none = el('div', 'waypost-results__none');
			none.appendChild(el('p', '', t.noMatch || ''));
			body.appendChild(none);
			return;
		}

		if (data.topics && data.topics.length) {
			var nav = el('nav', 'waypost-topics');
			nav.setAttribute('aria-label', t.topics || '');
			var chip = function (id, name, n) {
				var a = el('a', 'waypost-topic' + (id === topic ? ' is-current' : ''), name);
				a.href = urlFor(q, 1, id);
				a.setAttribute('data-topic', String(id));
				if (id === topic) a.setAttribute('aria-current', 'true');
				if (n != null) {
					a.appendChild(document.createTextNode(' '));
					a.appendChild(el('span', 'waypost-topic__count', n.toLocaleString()));
				}
				nav.appendChild(a);
			};
			chip(0, t.allTopics || 'All topics', null);
			data.topics.slice(0, 6).forEach(function (tp) { chip(tp.id, tp.name, tp.count); });
			body.appendChild(nav);
		}

		var answer = data.answer;
		if (answer) {
			var card = el('section', 'waypost-answer');
			card.setAttribute('aria-label', t.bestAnswer || '');
			var label = el('p', 'waypost-answer__label');
			label.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.9 5.6L19.5 9.5l-5.6 1.9L12 17l-1.9-5.6L4.5 9.5l5.6-1.9zM19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9z"/></svg>'; // Static icon, no data.
			label.appendChild(document.createTextNode(t.bestAnswer || ''));
			card.appendChild(label);
			var path = [answer.path, answer.page].filter(Boolean).join(' › ');
			if (path) card.appendChild(el('p', 'waypost-answer__path', path));
			var ah = el('h3', 'waypost-answer__title');
			var aa = el('a');
			aa.href = answer.url;
			appendHighlighted(aa, answer.title, marks);
			ah.appendChild(aa);
			if (answer.file) { ah.appendChild(document.createTextNode(' ')); ah.appendChild(el('span', 'waypost-file', answer.file)); }
			card.appendChild(ah);
			if (answer.text) {
				var at = el('p', 'waypost-answer__text');
				appendHighlighted(at, answer.text, marks);
				card.appendChild(at);
			}
			var more = el('a', 'waypost-answer__more', (answer.file ? t.openDoc : t.readSection) + ' ');
			more.href = answer.url;
			var arrow = el('span', '', '→');
			arrow.setAttribute('aria-hidden', 'true');
			more.appendChild(arrow);
			card.appendChild(more);
			if (data.related && data.related.length) {
				var rel = el('div', 'waypost-answer__related');
				rel.appendChild(el('p', 'waypost-answer__sub', t.alsoIn || ''));
				var rul = el('ul');
				data.related.forEach(function (r) {
					var li = el('li');
					var ra = el('a');
					ra.href = r.url;
					ra.appendChild(el('span', 'waypost-answer__page', r.title));
					if (r.heading) ra.appendChild(el('span', 'waypost-answer__heading', ' › ' + r.heading));
					if (r.file) { ra.appendChild(document.createTextNode(' ')); ra.appendChild(el('span', 'waypost-file', r.file)); }
					li.appendChild(ra);
					if (r.text) {
						var rs = el('span', 'waypost-answer__snippet');
						appendHighlighted(rs, r.text, marks);
						li.appendChild(rs);
					}
					rul.appendChild(li);
				});
				rel.appendChild(rul);
				card.appendChild(rel);
			}
			body.appendChild(card);
			body.appendChild(el('h2', 'waypost-results__heading', t.allResults || ''));
		}

		var ol = el('ol', 'waypost-results__list');
		ol.start = (pg - 1) * per + 1;
		results.forEach(function (r) {
			var li = el('li', 'waypost-result');
			if (r.section) li.appendChild(el('p', 'waypost-result__section', r.section));
			var h = el('h3', 'waypost-result__title');
			var a = el('a');
			a.href = r.url;
			appendHighlighted(a, r.title, marks);
			h.appendChild(a);
			if (r.file) {
				h.appendChild(document.createTextNode(' '));
				h.appendChild(el('span', 'waypost-file', r.file));
			}
			li.appendChild(h);
			if (r.excerpt) {
				var ex = el('p', 'waypost-result__excerpt');
				appendHighlighted(ex, r.excerpt, marks);
				li.appendChild(ex);
			}
			if (r.headings && r.headings.length) {
				var ul = el('ul', 'waypost-result__headings');
				r.headings.forEach(function (hd) {
					var hli = el('li');
					var ha = el('a');
					ha.href = hd.url;
					appendHighlighted(ha, hd.text, marks);
					hli.appendChild(ha);
					if (hd.snippet) {
						var hs = el('span', 'waypost-result__snippet');
						appendHighlighted(hs, hd.snippet, marks);
						hli.appendChild(hs);
					}
					ul.appendChild(hli);
				});
				li.appendChild(ul);
			}
			ol.appendChild(li);
		});
		body.appendChild(ol);

		if (data.pages > 1) {
			var pnav = el('nav', 'waypost-results__pages');
			pnav.setAttribute('aria-label', t.pages || '');
			var link = function (n, text, cls) {
				var l = el('a', 'page-numbers' + (cls ? ' ' + cls : ''), text);
				l.href = urlFor(q, n, topic);
				pnav.appendChild(l);
			};
			if (pg > 1) link(pg - 1, t.prev || '‹', 'prev');
			var gap = false;
			for (var n = 1; n <= data.pages; n++) {
				if (n === 1 || n === data.pages || Math.abs(n - pg) <= 2) {
					gap = false;
					if (n === pg) {
						var cur = el('span', 'page-numbers current', String(n));
						cur.setAttribute('aria-current', 'page');
						pnav.appendChild(cur);
					} else {
						link(n, String(n));
					}
				} else if (!gap) {
					gap = true;
					pnav.appendChild(el('span', 'page-numbers dots', '…'));
				}
			}
			if (pg < data.pages) link(pg + 1, t.next || '›', 'next');
			body.appendChild(pnav);
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
		var ctrl = null, current = '', topic = parseInt(new URLSearchParams(location.search).get('topic') || '0', 10) || 0;
		if (!box || !list || !cfg.api) return;

		function urlFor(q, pg, tp) {
			var u = new URL(base, location.href);
			if (q) u.searchParams.set('dq', q);
			if (tp) u.searchParams.set('topic', String(tp));
			if (pg > 1) u.searchParams.set('pg', String(pg));
			return u.toString();
		}
		function show(on) {
			home.classList.toggle('is-searching', on);
			box.hidden = !on;
		}
		function load(q, pg, push, scroll, tp) {
			current = q;
			topic = tp || 0;
			ui.cancel();
			if (ctrl) ctrl.abort();
			ctrl = window.AbortController ? new AbortController() : null;
			show(true);
			list.setAttribute('aria-busy', 'true');
			var url = cfg.api + (cfg.api.indexOf('?') > -1 ? '&' : '?') + 'q=' + encodeURIComponent(q) + '&page=' + pg + '&per=' + (cfg.perPage || 10) + (topic ? '&topic=' + topic : '') + (pg === 1 && !topic ? '&log=1' : '');
			fetch(url, { signal: ctrl ? ctrl.signal : undefined, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					renderResults(list, data, q, pg, urlFor);
					list.removeAttribute('aria-busy');
					if (push) history.pushState({ waypostHome: true }, '', urlFor(q, pg, topic));
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
			var tp = e.target.closest('a.waypost-topic');
			if (tp) {
				e.preventDefault();
				load(current || input.value.trim(), 1, true, false, parseInt(tp.getAttribute('data-topic') || '0', 10));
				return;
			}
			var a = e.target.closest('.waypost-results__pages a.page-numbers');
			if (!a) return;
			e.preventDefault();
			var pg = parseInt(new URL(a.href, location.href).searchParams.get('pg') || '1', 10);
			load(current || input.value.trim(), pg, true, true, topic);
		});
		window.addEventListener('popstate', function () {
			var p = new URLSearchParams(location.search);
			var q = (p.get('dq') || '').trim();
			if (q.length >= 2) {
				input.value = q;
				load(q, parseInt(p.get('pg') || '1', 10), false, false, parseInt(p.get('topic') || '0', 10));
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

	/* ── Docs layout: full width and sticky columns ───── */

	// The layout spans the window (100vw minus the scrollbar) and its sticky columns sit below any
	// fixed header, such as the admin bar or Divi's fixed navigation, which may shrink on scroll.
	function initLayout() {
		var layout = document.querySelector('.waypost-layout');
		if (!layout) return;
		var root = document.documentElement;
		var headers = '#wpadminbar, .waypost-topbar, #main-header, #top-header, header, .site-header, .et-l--header, .elementor-location-header';
		var queued = false;
		function measure() {
			queued = false;
			root.style.setProperty('--waypost-sbw', (window.innerWidth - root.clientWidth) + 'px');
			// Fixed bars stack: the admin bar, then a theme header right below it, and so on.
			var bars = [];
			document.querySelectorAll(headers).forEach(function (h) {
				var pos = getComputedStyle(h).position;
				if (pos !== 'fixed' && pos !== 'sticky') return;
				var r = h.getBoundingClientRect();
				if (r.bottom > 0 && r.height > 0 && r.height < window.innerHeight / 2) bars.push(r);
			});
			bars.sort(function (a, b) { return a.top - b.top; });
			var top = 0;
			bars.forEach(function (r) { if (r.top <= top + 1) top = Math.max(top, r.bottom); });
			root.style.setProperty('--waypost-sticky-top', Math.round(top + 20) + 'px');
			fit(top + 20);
		}
		// A column taller than the window stops being sticky, so it scrolls with the page.
		var columns = layout.querySelectorAll('.waypost-layout__sticky');
		function fit(top) {
			columns.forEach(function (c) { c.classList.toggle('is-tall', c.offsetHeight > window.innerHeight - top - 16); });
		}
		function queue() { if (!queued) { queued = true; requestAnimationFrame(measure); } }
		measure();
		// Opening or closing a menu section changes the sidebar's height.
		if (window.ResizeObserver) {
			var ro = new ResizeObserver(queue);
			columns.forEach(function (c) { ro.observe(c); });
		}
		window.addEventListener('resize', queue);
		window.addEventListener('scroll', queue, { passive: true });
		window.addEventListener('load', queue);
	}

	/* ── Auto light and dark: the reader's switch ─────── */

	// The choice is saved per reader; the head script in Waypost_Frontend applies it before the page draws.
	function initScheme() {
		var root = document.documentElement;
		var media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
		function isDark() {
			if (root.classList.contains('waypost-scheme-dark')) return true;
			if (root.classList.contains('waypost-scheme-light')) return false;
			return !!(media && media.matches);
		}
		function sync() {
			document.querySelectorAll('[data-waypost-scheme]').forEach(function (b) { b.setAttribute('aria-pressed', isDark() ? 'true' : 'false'); });
		}
		document.addEventListener('click', function (e) {
			if (!e.target.closest('[data-waypost-scheme]')) return;
			var next = isDark() ? 'light' : 'dark';
			root.classList.remove('waypost-scheme-dark', 'waypost-scheme-light');
			root.classList.add('waypost-scheme-' + next);
			try { localStorage.setItem('waypost-scheme', next); } catch (err) { /* private mode */ }
			sync();
		});
		if (media && media.addEventListener) media.addEventListener('change', sync);
		sync();
	}

	/* ── Link icons on headings ───────────────────────── */

	function initHeadingLinks() {
		var scope = document.querySelector('.waypost-layout__content') || document.querySelector('.entry-content, .et_pb_post_content, .wp-block-post-content, main');
		if (!scope) return;
		scope.querySelectorAll('h2[id], h3[id], h4[id]').forEach(function (h) {
			if (h.closest('.waypost-toc, .waypost-home, .waypost-results, .waypost-feedback') || h.querySelector('.waypost-anchor')) return;
			var a = el('a', 'waypost-anchor', '#');
			a.href = '#' + h.id;
			a.setAttribute('aria-label', (t.linkTo || 'Copy a link to this section') + ': ' + h.textContent);
			a.addEventListener('click', function () {
				var url = location.href.split('#')[0] + '#' + h.id;
				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(url).then(function () {
						a.setAttribute('data-copied', t.linkCopied || 'Link copied');
						setTimeout(function () { a.removeAttribute('data-copied'); }, 1600);
					}).catch(function () {});
				}
			});
			h.appendChild(a);
		});
	}

	/* ── Was this page helpful? ───────────────────────── */

	function initFeedback(box) {
		var post = box.getAttribute('data-waypost-feedback');
		var key = 'waypost-fb-' + post;
		var done = function () {
			box.classList.add('is-done');
			box.querySelector('.waypost-feedback__buttons').hidden = true;
			box.querySelector('.waypost-feedback__thanks').hidden = false;
		};
		try { if (localStorage.getItem(key)) { done(); return; } } catch (err) { /* private mode */ }
		box.addEventListener('click', function (e) {
			var b = e.target.closest('button[data-vote]');
			if (!b || !cfg.feedbackApi) return;
			box.querySelectorAll('button').forEach(function (x) { x.disabled = true; });
			fetch(cfg.feedbackApi, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ post: parseInt(post, 10), vote: b.getAttribute('data-vote') })
			}).then(function (r) {
				// 429: this reader already answered today. Either way the vote is in.
				if (r.ok || r.status === 429) {
					try { localStorage.setItem(key, b.getAttribute('data-vote')); } catch (err) { /* private mode */ }
					done();
				} else {
					box.querySelectorAll('button').forEach(function (x) { x.disabled = false; });
				}
			}).catch(function () { box.querySelectorAll('button').forEach(function (x) { x.disabled = false; }); });
		});
	}

	/* ── Arriving from a search result ────────────────── */

	// The reader opened a result: highlight the words they searched for on this page, flash the
	// section the result pointed to, and offer a button to clear the highlights.
	function initArrival() {
		var data = null;
		try {
			data = JSON.parse(sessionStorage.getItem('waypost-arrival') || 'null');
			sessionStorage.removeItem('waypost-arrival');
		} catch (e) { return; }
		if (!data || !data.marks || !data.marks.length || Date.now() - data.time > 120000) return;
		if (data.url.replace(/\/$/, '') !== location.href.split('#')[0].replace(/\/$/, '')) return;
		var root = document.querySelector('.waypost-layout__content, .entry-content, .et_pb_post_content, .wp-block-post-content');
		var re = markPattern(data.marks);
		if (!root || !re) return;

		var skip = 'script, style, mark, textarea, .waypost-menu, .waypost-toc, .waypost-search, .waypost-pager, .waypost-crumbs, .waypost-feedback';
		var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
			acceptNode: function (n) {
				if (!n.nodeValue.trim() || (n.parentElement && n.parentElement.closest(skip))) return NodeFilter.FILTER_REJECT;
				return NodeFilter.FILTER_ACCEPT;
			}
		});
		var nodes = [];
		while (walker.nextNode()) nodes.push(walker.currentNode);
		var found = [];
		nodes.forEach(function (n) {
			if (found.length >= 80) return;
			re.lastIndex = 0;
			if (!re.test(n.nodeValue)) return;
			var frag = document.createDocumentFragment();
			n.nodeValue.split(re).forEach(function (p, i) {
				if (!p) return;
				if (i % 2) {
					var m = el('mark', 'waypost-found', p);
					found.push(m);
					frag.appendChild(m);
				} else {
					frag.appendChild(document.createTextNode(p));
				}
			});
			n.parentNode.replaceChild(frag, n);
		});

		var target = location.hash ? document.getElementById(decodeURIComponent(location.hash.slice(1))) : null;
		if (target) {
			target.classList.add('waypost-target');
			setTimeout(function () { target.classList.remove('waypost-target'); }, 2600);
		} else if (found.length && found[0].getBoundingClientRect().top > window.innerHeight * 0.8) {
			found[0].scrollIntoView({ block: 'center', behavior: reduceMotion ? 'auto' : 'smooth' });
		}
		if (!found.length) return;

		var bar = el('div', 'waypost-found-bar');
		bar.setAttribute('role', 'status');
		bar.appendChild(el('span', '', fill(t.highlighted, data.q || data.marks.join(' '))));
		var clear = el('button', 'waypost-found-bar__clear', t.clear || 'Clear');
		clear.type = 'button';
		clear.addEventListener('click', function () {
			found.forEach(function (m) {
				if (m.parentNode) m.parentNode.replaceChild(document.createTextNode(m.textContent), m);
			});
			root.normalize();
			bar.remove();
		});
		bar.appendChild(clear);
		document.body.appendChild(bar);
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
		initLayout();
		initScheme();
		document.querySelectorAll('[data-waypost-feedback]').forEach(initFeedback);
		if (cfg.headingLinks && cfg.isDoc) initHeadingLinks();
		initQuickSearches();
		if (cfg.shortcut && (cfg.isDoc || boxes.length)) initShortcut();
		if (cfg.copyCode && cfg.isDoc) initCopy();
		initArrival();
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
