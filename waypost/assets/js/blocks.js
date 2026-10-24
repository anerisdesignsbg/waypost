/* Waypost blocks. Previews are rendered by the server, so they always match the front end. */
(function (wp) {
	'use strict';
	if (!wp || !wp.blocks) return;

	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var SSR = wp.serverSideRender;
	var be = wp.blockEditor;
	var c = wp.components;

	var icon = el('svg', { width: 24, height: 24, viewBox: '0 0 24 24' },
		el('path', { d: 'M12 2 4 6v12l8 4 8-4V6l-8-4Zm0 2.2 5.6 2.8L12 9.8 6.4 7 12 4.2ZM6 8.6l5 2.5v8.3l-5-2.5V8.6Zm7 10.8v-8.3l5-2.5v8.3l-5 2.5Z', fill: 'currentColor' })
	);

	function block(name, title, description, controls) {
		wp.blocks.registerBlockType(name, {
			apiVersion: 2,
			title: title,
			description: description,
			category: 'widgets',
			icon: icon,
			keywords: ['docs', 'documentation', 'waypost'],
			supports: { html: false },
			usesContext: ['postId'],
			edit: function (props) {
				var blockProps = be.useBlockProps();
				return el('div', blockProps,
					controls ? el(be.InspectorControls, null, el(c.PanelBody, { title: __('Settings', 'waypost') }, controls(props))) : null,
					el(c.Disabled, null, el(SSR, { block: name, attributes: props.attributes, urlQueryArgs: props.context && props.context.postId ? { post_id: props.context.postId } : {} }))
				);
			},
			save: function () { return null; }
		});
	}

	block('waypost/menu', __('Docs Menu', 'waypost'), __('Sidebar navigation built from your doc sections.', 'waypost'), function (p) {
		return el(c.TextControl, { label: __('Heading above the menu (optional)', 'waypost'), value: p.attributes.title, onChange: function (v) { p.setAttributes({ title: v }); } });
	});

	block('waypost/search', __('Docs Search', 'waypost'), __('Instant search across your documentation.', 'waypost'), function (p) {
		return [
			el(c.TextControl, { key: 'ph', label: __('Placeholder text', 'waypost'), value: p.attributes.placeholder, onChange: function (v) { p.setAttributes({ placeholder: v }); } }),
			el(c.ToggleControl, { key: 'btn', label: __('Show search button', 'waypost'), checked: p.attributes.button, onChange: function (v) { p.setAttributes({ button: v }); } })
		];
	});

	block('waypost/search-home', __('Docs Search Home', 'waypost'), __('Heading, large search box, quick searches and topic cards for your docs home page. Set them up in Waypost, Settings.', 'waypost'), function (p) {
		return el(c.TextControl, { label: __('Heading', 'waypost'), placeholder: __('How can we help?', 'waypost'), value: p.attributes.title, onChange: function (v) { p.setAttributes({ title: v }); } });
	});

	block('waypost/toc', __('Docs: On This Page', 'waypost'), __('Table of contents for the headings on the current page.', 'waypost'), function (p) {
		return el(c.TextControl, { label: __('Title', 'waypost'), placeholder: __('On this page', 'waypost'), value: p.attributes.title, onChange: function (v) { p.setAttributes({ title: v }); } });
	});

	block('waypost/prev-next', __('Docs: Previous / Next', 'waypost'), __('Links to the previous and next page in menu order.', 'waypost'));
	block('waypost/breadcrumbs', __('Docs: Breadcrumbs', 'waypost'), __('Shows where the current page sits in your docs.', 'waypost'));
})(window.wp);
