/* Waypost admin screens and setup wizard. */
(function ($) {
	'use strict';

	var cfg = window.waypostAdmin || {};
	var t = cfg.i18n || {};

	function post(action, data) {
		return $.post(cfg.ajaxUrl, $.extend({ action: 'waypost_' + action, nonce: cfg.nonce }, data || {}));
	}

	function status($el, msg, isError) {
		$el.text(msg || '').toggleClass('is-error', !!isError);
		if (msg && !isError) setTimeout(function () { $el.text(''); }, 3000);
	}

	function errorText(res) {
		return (res && res.data && typeof res.data === 'string') ? res.data : t.failed;
	}

	/* ── Copy buttons ─────────────────────────────────── */
	$(document).on('click', '.wpst-copy__btn', function () {
		var $b = $(this), text = $b.data('copy'), label = $b.text();
		var done = function () { $b.text(t.copied || 'Copied'); setTimeout(function () { $b.text(label); }, 1500); };
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done);
		} else {
			var $tmp = $('<textarea>').val(text).appendTo('body').select();
			document.execCommand('copy');
			$tmp.remove();
			done();
		}
	});

	/* ── Menu order ───────────────────────────────────── */
	var dirty = false;
	if ($('#waypost-order').length) {
		$('.wpst-sortable').sortable({
			handle: '.wpst-node__handle',
			items: '> .wpst-node',
			axis: 'y',
			tolerance: 'pointer',
			placeholder: 'wpst-node wpst-node--placeholder',
			forcePlaceholderSize: true,
			update: function () { dirty = true; }
		});
		window.addEventListener('beforeunload', function (e) {
			if (dirty) { e.preventDefault(); e.returnValue = t.unsaved; }
		});
	}

	$('#waypost-save-order').on('click', function () {
		var $b = $(this), $s = $('#waypost-order-status'), order = [];
		$('.wpst-sortable').each(function () {
			$(this).children('.wpst-node').each(function (i) {
				order.push({ key: String($(this).data('key')), position: i });
			});
		});
		$b.prop('disabled', true);
		status($s, t.saving);
		post('save_order', { order: JSON.stringify(order) })
			.done(function (r) { if (r.success) { dirty = false; status($s, t.saved); } else status($s, errorText(r), true); })
			.fail(function () { status($s, t.failed, true); })
			.always(function () { $b.prop('disabled', false); });
	});

	var resetArmed = false;
	$('#waypost-reset-order').on('click', function () {
		var $s = $('#waypost-order-status');
		if (!resetArmed) {
			resetArmed = true;
			status($s, t.confirmReset, true);
			setTimeout(function () { resetArmed = false; }, 4000);
			return;
		}
		post('reset_order').done(function (r) { if (r.success) { dirty = false; location.reload(); } else status($s, errorText(r), true); });
	});

	$('#waypost-flush').on('click', function () {
		var $s = $('#waypost-order-status');
		post('flush').done(function (r) { status($s, r.success ? t.saved : errorText(r), !r.success); });
	});

	/* ── Section links ────────────────────────────────── */
	$(document).on('click', '.wpst-link-save', function () {
		var $row = $(this).closest('tr'), $s = $row.find('.wpst-status');
		status($s, t.saving);
		post('save_link', { term: $row.data('term'), post: $row.find('select').val() })
			.done(function (r) { status($s, r.success ? t.saved : errorText(r), !r.success); })
			.fail(function () { status($s, t.failed, true); });
	});

	/* ── Assign pages ─────────────────────────────────── */
	$('#waypost-assign-filter').on('input', function () {
		var q = $(this).val().toLowerCase().trim();
		$('#waypost-assign tbody tr').each(function () {
			$(this).toggle(!q || String($(this).data('title')).indexOf(q) > -1);
		});
	});

	$('#waypost-assign-all').on('change', function () {
		$('#waypost-assign tbody tr:visible input[type=checkbox]').prop('checked', this.checked);
	});

	$('[data-assign]').on('click', function () {
		var mode = $(this).data('assign'), $s = $('#waypost-assign-status');
		var ids = $('#waypost-assign tbody input[type=checkbox]:checked').map(function () { return this.value; }).get();
		var term = $('#waypost-assign-term').val();
		if (!ids.length) return status($s, t.pickPages, true);
		if (mode !== 'remove' && (!term || term === '0')) return status($s, t.pickSection, true);
		status($s, t.saving);
		post('assign', { mode: mode, term: term, ids: ids })
			.done(function (r) {
				if (!r.success) return status($s, errorText(r), true);
				$.each(r.data.names || {}, function (id, names) {
					var $row = $('#waypost-assign input[value="' + id + '"]').prop('checked', false).closest('tr');
					$row.find('.wpst-assign__terms').html(names ? $('<span>').text(names) : '<span class="wpst-muted">–</span>');
					$row.addClass('is-updated');
				});
				$('#waypost-assign-all').prop('checked', false);
				status($s, t.saved);
			})
			.fail(function () { status($s, t.failed, true); });
	});

	$('#waypost-add-section').on('click', function () {
		var $s = $('#waypost-section-status'), name = $('#waypost-new-name').val().trim();
		if (!name) return status($s, t.nameNeeded, true);
		post('add_section', { name: name, parent: $('#waypost-new-parent').val() })
			.done(function (r) {
				if (!r.success) return status($s, errorText(r), true);
				$('#waypost-assign-term').html(r.data.options);
				$('#waypost-new-parent').html(r.data.parents);
				$('#waypost-new-name').val('');
				status($s, t.saved);
			})
			.fail(function () { status($s, t.failed, true); });
	});

	/* ── Wizard: outline preview ──────────────────────── */
	var $outline = $('#wpst-outline');
	function renderOutline() {
		var $p = $('#wpst-outline-preview').empty();
		var lines = ($outline.val() || '').split(/\r?\n/).filter(function (l) { return l.trim(); });
		if (!lines.length) { $p.append($('<li class="wpst-muted">').text('–')); return; }
		var prev = -1;
		lines.forEach(function (l) {
			var exp = l.replace(/\t/g, '  ');
			var depth = Math.floor((exp.length - exp.replace(/^\s+/, '').length) / 2);
			depth = Math.min(depth, prev + 1);
			prev = depth;
			$p.append($('<li>').css('padding-left', (depth * 18) + 'px').toggleClass('is-top', depth === 0).text(l.trim().replace(/^[-*•]\s*/, '')));
		});
	}
	if ($outline.length) {
		$outline.on('input', renderOutline);
		$outline.on('keydown', function (e) {
			if (e.key !== 'Tab') return;
			e.preventDefault(); // Tab indents instead of leaving the box.
			var el = this, s = el.selectionStart, v = el.value;
			var lineStart = v.lastIndexOf('\n', s - 1) + 1;
			if (e.shiftKey) {
				if (v.substr(lineStart, 2) === '  ') { el.value = v.slice(0, lineStart) + v.slice(lineStart + 2); el.selectionStart = el.selectionEnd = Math.max(lineStart, s - 2); }
			} else {
				el.value = v.slice(0, lineStart) + '  ' + v.slice(lineStart);
				el.selectionStart = el.selectionEnd = s + 2;
			}
			renderOutline();
		});
		renderOutline();
	}

	/* ── Wizard: placing tabs ─────────────────────────── */
	$('[data-tabs]').each(function () {
		var $wrap = $(this), $tabs = $wrap.find('[role=tab]');
		function select($tab) {
			$tabs.attr({ 'aria-selected': 'false', tabindex: -1 });
			$wrap.find('[role=tabpanel]').attr('hidden', true);
			$tab.attr({ 'aria-selected': 'true', tabindex: 0 }).trigger('focus');
			$('#' + $tab.attr('aria-controls')).removeAttr('hidden');
		}
		$tabs.on('click', function () { select($(this)); });
		$tabs.on('keydown', function (e) {
			var i = $tabs.index(this);
			if (e.key === 'ArrowRight') select($tabs.eq((i + 1) % $tabs.length));
			if (e.key === 'ArrowLeft') select($tabs.eq((i - 1 + $tabs.length) % $tabs.length));
		});
	});

	/* ── Wizard: choosing "existing page" focuses its select ── */
	$('select[name=results_page]').on('change', function () {
		$('input[name=results_mode][value=existing]').prop('checked', true);
	});
	$('select[name=taxonomy]').on('change', function () {
		$('input[name=taxonomy_mode][value=existing]').prop('checked', true);
	});

	/* ── Help page: highlight topic in nav ────────────── */
	var $helpLinks = $('.wpst-help__nav a[href^="#"]');
	if ($helpLinks.length && 'IntersectionObserver' in window) {
		var obs = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (en.isIntersecting) {
					$helpLinks.removeClass('is-active').filter('[href="#' + en.target.id + '"]').addClass('is-active');
				}
			});
		}, { rootMargin: '-20% 0px -70% 0px' });
		$('.wpst-topic').each(function () { obs.observe(this); });
	}
})(jQuery);
