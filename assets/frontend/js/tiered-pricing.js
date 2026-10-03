/**
 * WPCalibrate Tiered Pricing - Frontend JavaScript
 */

(function($) {
	'use strict';

	var params = window.wpcttp_params || {};

	/**
	 * Main controller for frontend tiered pricing.
	 */
	var WPCalibrateTieredPricing = {
		debounceTimer: null,

		init: function() {
			this.bindEvents();
			this.initialHighlight();
		},

		bindEvents: function() {
			var self = this;

			// Quantity input change handler.
			$(document).on('input change keyup', 'form.cart input.qty, form.cart input[name="quantity"]', function() {
				var qty = parseFloat($(this).val()) || 1;
				self.updateActiveTier(qty);
				self.debouncedPriceCheck(qty);
			});

			// Variable product variation selection.
			$(document).on('show_variation', 'form.variations_form', function(event, variation) {
				self.handleVariationSelected(variation);
			});

			$(document).on('hide_variation reset_data', 'form.variations_form', function() {
				self.handleVariationReset();
			});
		},

		initialHighlight: function() {
			var $qtyInput = $('form.cart input.qty, form.cart input[name="quantity"]').first();
			var currentQty = parseFloat($qtyInput.val()) || 1;
			this.updateActiveTier(currentQty);
		},

		updateActiveTier: function(qty) {
			if (!params.highlight_active) {
				return;
			}

			$('.wpcttp-pricing-table-container').each(function() {
				var $container = $(this);
				var matched = false;

				$container.find('.wpcttp-tier-row').each(function() {
					var $row = $(this);
					var min = parseFloat($row.attr('data-min')) || 1;
					var maxAttr = $row.attr('data-max');
					var max = (maxAttr !== '' && maxAttr !== undefined) ? parseFloat(maxAttr) : null;

					var isMatch = false;
					if (qty >= min) {
						if (max === null || qty <= max) {
							isMatch = true;
						}
					}

					if (isMatch && !matched) {
						$row.addClass('is-active').attr('aria-selected', 'true');
						matched = true;
					} else {
						$row.removeClass('is-active').removeAttr('aria-selected');
					}
				});
			});
		},

		handleVariationSelected: function(variation) {
			var $wrapper = $('#wpcttp-variable-pricing-wrapper');
			if (!$wrapper.length) {
				return;
			}

			var $content = $wrapper.find('.wpcttp-table-content');

			if (variation && variation.wpcttp_has_tiers && variation.wpcttp_tiers && variation.wpcttp_tiers.length) {
				var html = this.buildTableHtml(variation.wpcttp_tiers);
				$content.html(html).show();

				var $qtyInput = $('form.cart input.qty, form.cart input[name="quantity"]').first();
				var qty = parseFloat($qtyInput.val()) || 1;
				this.updateActiveTier(qty);
				this.debouncedPriceCheck(qty, variation.variation_id);
			} else {
				$content.empty().hide();
			}
		},

		handleVariationReset: function() {
			var $wrapper = $('#wpcttp-variable-pricing-wrapper');
			if ($wrapper.length) {
				$wrapper.find('.wpcttp-table-content').empty().hide();
			}
		},

		buildTableHtml: function(tiers) {
			var i18n = params.i18n || {};
			var out = '<table class="wpcttp-pricing-table" role="table">';
			out += '<thead><tr role="row">';
			out += '<th scope="col" class="wpcttp-col-qty">Quantity</th>';
			out += '<th scope="col" class="wpcttp-col-price">Price per Unit</th>';
			out += '<th scope="col" class="wpcttp-col-savings">Savings</th>';
			out += '</tr></thead><tbody>';

			for (var i = 0; i < tiers.length; i++) {
				var t = tiers[i];
				out += '<tr class="wpcttp-tier-row" data-min="' + t.min_qty + '" data-max="' + (t.max_qty !== null ? t.max_qty : '') + '" role="row">';
				out += '<td class="wpcttp-col-qty" role="cell">' + t.qty_display + '</td>';
				out += '<td class="wpcttp-col-price" role="cell">' + t.price_display + '</td>';
				out += '<td class="wpcttp-col-savings" role="cell">' + t.savings_display + '</td>';
				out += '</tr>';
			}

			out += '</tbody></table>';
			out += '<div class="wpcttp-live-summary" aria-live="polite" style="display:none;"></div>';
			return out;
		},

		debouncedPriceCheck: function(qty, variationId) {
			var self = this;
			clearTimeout(this.debounceTimer);

			this.debounceTimer = setTimeout(function() {
				self.fetchLivePrice(qty, variationId);
			}, 250);
		},

		fetchLivePrice: function(qty, variationId) {
			var self = this;
			var $form = $('form.cart');
			var productId = parseInt($form.find('[name="add-to-cart"]').val(), 10) || 0;

			if (!productId) {
				productId = parseInt($('.wpcttp-pricing-table-container').attr('data-product-id'), 10) || 0;
			}

			if (!variationId) {
				variationId = parseInt($form.find('input[name="variation_id"]').val(), 10) || 0;
			}

			if (!productId && !variationId) {
				return;
			}

			var payload = {
				product_id: productId,
				variation_id: variationId,
				quantity: qty,
				nonce: params.nonce
			};

			// Use REST endpoint with graceful fallback to AJAX.
			if (params.rest_url) {
				$.ajax({
					url: params.rest_url,
					method: 'POST',
					contentType: 'application/json',
					data: JSON.stringify(payload),
					dataType: 'json'
				}).done(function(response) {
					self.handlePriceResponse(response);
				}).fail(function() {
					// Fallback to AJAX.
					self.fetchLivePriceAjax(payload);
				});
			} else {
				self.fetchLivePriceAjax(payload);
			}
		},

		fetchLivePriceAjax: function(payload) {
			var self = this;
			var data = $.extend({ action: 'wpcttp_calculate_price' }, payload);

			$.post(params.ajax_url, data, function(res) {
				if (res && res.success && res.data) {
					self.handlePriceResponse(res.data);
				}
			}, 'json');
		},

		handlePriceResponse: function(data) {
			if (!data || !params.show_line_total) {
				return;
			}

			var $summary = $('.wpcttp-live-summary');
			if (!$summary.length) {
				return;
			}

			if (data.is_tiered_pricing_applied) {
				var i18n = params.i18n || {};
				var html = '<span class="wpcttp-label">' + (i18n.unit_price || 'Unit Price:') + '</span> ';
				html += '<span class="wpcttp-val">' + data.unit_price_html + '</span>';

				if (params.show_line_total && data.line_total_html) {
					html += ' | <span class="wpcttp-label">' + (i18n.line_total || 'Subtotal:') + '</span> ';
					html += '<span class="wpcttp-val">' + data.line_total_html + '</span>';
				}

				$summary.html(html).slideDown(150);
			} else {
				$summary.slideUp(150).empty();
			}
		}
	};

	$(document).ready(function() {
		WPCalibrateTieredPricing.init();
	});

})(jQuery);
