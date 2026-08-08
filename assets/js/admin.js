/* WC Product Sidebar — modern admin interactions */
(function ($) {
	'use strict';

	$(function () {
		var $form = $('#wcps-settings-form');
		var $unsaved = $('.wcps-unsaved');

		function markDirty() {
			$unsaved.prop('hidden', false);
		}

		$form.on('input change', 'input, select', markDirty);

		$('.wcps-tab').on('click', function () {
			var tab = $(this).data('tab');
			$('.wcps-tab').removeClass('is-active');
			$(this).addClass('is-active');
			$('.wcps-tab-panel').removeClass('is-active');
			$('.wcps-tab-panel[data-panel="' + tab + '"]').addClass('is-active');
			window.scrollTo({ top: 0, behavior: 'smooth' });
		});

		$('.wcps-color-picker').wpColorPicker({
			change: function () { updatePreview(); markDirty(); },
			clear: function () { updatePreview(); markDirty(); }
		});

		function updatePreview() {
			var ribbonColor = $('#ribbon_color').val() || '#2563eb';
			var iconColor = $('#icon_color').val() || '#ffffff';
			var label = $('#button_label').val() || 'Products';

			$('#wcps-ribbon-preview').css({
				backgroundColor: ribbonColor,
				color: iconColor
			});
			$('.wcps-ribbon-label').text(label);
		}

		$('#button_label').on('input', updatePreview);
		updatePreview();

		$('#wcps-category-search').on('input', function () {
			var query = $.trim($(this).val().toLowerCase());
			$('.wcps-cat-item').each(function () {
				var match = !query || ($(this).data('category') || '').indexOf(query) !== -1;
				$(this).toggle(match);
			});
		});

		function setVisibleCategories(checked) {
			$('.wcps-cat-item:visible input[type="checkbox"]').prop('checked', checked).trigger('change');
		}

		$('.wcps-select-visible').on('click', function () { setVisibleCategories(true); });
		$('.wcps-clear-visible').on('click', function () { setVisibleCategories(false); });

		$('.wcps-bust-cache').on('click', function () {
			var $btn = $(this);
			if ($btn.prop('disabled')) return;
			$btn.prop('disabled', true).text('Clearing…');

			$.post(ajaxurl, {
				action: 'wcps_bust_cache',
				nonce: $btn.data('nonce')
			}).done(function (response) {
				$btn.text(response.success ? 'Cache cleared' : 'Try again');
			}).fail(function () {
				$btn.text('Try again');
			}).always(function () {
				setTimeout(function () {
					$btn.prop('disabled', false).text('Clear product cache');
				}, 1600);
			});
		});

		$('.wcps-reset-settings').on('click', function () {
			if (!window.confirm('Restore all Product Sidebar settings to their defaults?')) return;

			var $btn = $(this);
			$btn.prop('disabled', true).text('Resetting…');

			$.post(ajaxurl, {
				action: 'wcps_reset_settings',
				nonce: $btn.data('nonce')
			}).done(function (response) {
				if (response.success) {
					window.location.reload();
					return;
				}
				$btn.prop('disabled', false).text('Reset');
			}).fail(function () {
				$btn.prop('disabled', false).text('Reset');
			});
		});

		$form.on('submit', function () {
			// Do not rely on the submit button's name/value: the button is
			// disabled here for UX and disabled controls are omitted from POST.
			// The hidden wcps_save field is the authoritative save flag.
			$('.wcps-save-button').prop('disabled', true).text('Saving…');
		});
	});
}(jQuery));
