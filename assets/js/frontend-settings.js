/**
 * WC Product Sidebar — live settings bridge.
 *
 * Fetches the saved WordPress settings after the main app loads. This is
 * deliberately independent of the compiled React bundle so an old cached
 * storefront page cannot keep old admin settings alive.
 */
(function () {
	'use strict';

	function cssValue(value, fallback) {
		return typeof value === 'string' && value.trim() ? value.trim() : fallback;
	}

	function applySettings(settings) {
		var root = document.getElementById('wcps-root');
		if (!root || !settings) return;

		var bg = cssValue(settings.bgColor, '#ffffff');
		var icon = cssValue(settings.iconColor, '#2563eb');
		var ribbon = cssValue(settings.ribbonColor, '#2563eb');
		var fontColor = cssValue(settings.fontColor, '#1f2937');
		var fontSize = cssValue(settings.fontSize, '14px');
		var fontFamily = cssValue(settings.fontFamily, 'inherit');

		root.style.setProperty('--wcps-bg', bg);
		root.style.setProperty('--wcps-icon', icon);
		root.style.setProperty('--wcps-ribbon', ribbon);
		root.style.setProperty('--wcps-font-color', fontColor);
		root.style.setProperty('--wcps-font-size', fontSize);
		root.style.setProperty('--wcps-font-family', fontFamily);

		var ribbonButton = root.querySelector('.wcps-ribbon-btn');
		var sidebar = root.querySelector('.wcps-sidebar');

		if (ribbonButton) {
			ribbonButton.classList.toggle('wcps-ribbon-btn--left', settings.ribbonPosition === 'left');
			var label = ribbonButton.querySelector('.wcps-ribbon-label');
			if (label) label.textContent = settings.buttonLabel || 'Products';
			ribbonButton.setAttribute(
				'aria-label',
				(settings.buttonLabel || 'Products') + ' — open product sidebar'
			);
		}

		if (sidebar) {
			sidebar.classList.toggle('wcps-sidebar--left', settings.ribbonPosition === 'left');
			sidebar.setAttribute('aria-label', settings.buttonLabel || 'Products');

			var title = sidebar.querySelector('.wcps-sidebar-title');
			if (title) title.textContent = settings.buttonLabel || 'Products';
		}

		root.setAttribute('data-wcps-settings-version', settings.version || '');

		if (!settings.showCategoryHeadings) {
			root.classList.add('wcps-hide-category-headings');
		} else {
			root.classList.remove('wcps-hide-category-headings');
		}

		// Keep the public config synchronized for code that reads it later.
		window.wcpsConfig = window.wcpsConfig || {};
		window.wcpsConfig.settings = window.wcpsConfig.settings || {};
		window.wcpsConfig.settings.buttonLabel = settings.buttonLabel;
		window.wcpsConfig.settings.ribbonPosition = settings.ribbonPosition;
		window.wcpsConfig.settings.showCategoryHeadings = !!settings.showCategoryHeadings;
		window.wcpsConfig.settings.featuredFirst = !!settings.featuredFirst;
		window.wcpsConfig.settings.hiddenCategories = settings.hiddenCategories || [];
	}

	function fetchSettings() {
		var config = window.wcpsConfig || {};
		var url = config.settingsUrl;
		if (!url) return;

		var separator = url.indexOf('?') === -1 ? '?' : '&';
		url += separator + '_wcps_settings=' + Date.now();

		fetch(url, {
			method: 'GET',
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { 'Accept': 'application/json' }
		})
		.then(function (response) {
			if (!response.ok) throw new Error('Settings request failed: ' + response.status);
			return response.json();
		})
		.then(applySettings)
		.catch(function (error) {
			if (window.console && console.warn) {
				console.warn('[WC Product Sidebar] Could not refresh settings.', error);
			}
		});
	}

	function start() {
		fetchSettings();

		// React may mount after this script. Re-apply once when the root appears.
		var attempts = 0;
		var timer = setInterval(function () {
			attempts++;
			var root = document.getElementById('wcps-root');
			if (root) {
				fetchSettings();
				clearInterval(timer);
			}
			if (attempts > 30) clearInterval(timer);
		}, 100);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
})();
