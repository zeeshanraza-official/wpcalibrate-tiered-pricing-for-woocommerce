/**
 * WPCalibrate Tiered Pricing - Admin JavaScript
 */

(function($) {
	'use strict';

	var adminI18n = (window.wpcttp_admin && window.wpcttp_admin.i18n) || {};

	var WPCalibrateTieredPricingAdmin = {
		init: function() {
			this.bindEvents();
		},

		bindEvents: function() {
			var self = this;

			// Add Tier button.
			$(document).on('click', '.wpcttp-add-tier-btn', function(e) {
				e.preventDefault();
				self.addTierRow($(this));
			});

			// Remove Tier button.
			$(document).on('click', '.wpcttp-remove-tier-btn', function(e) {
				e.preventDefault();
				$(this).closest('tr.wpcttp-tier-editor-row').fadeOut(150, function() {
					$(this).remove();
				});
			});

			// Variation override toggle.
			$(document).on('change', '.wpcttp-variation-override-toggle', function() {
				var $body = $(this).closest('.wpcttp-variation-tiered-pricing-section').find('.wpcttp-variation-rule-body');
				if ($(this).is(':checked')) {
					$body.slideDown(200);
				} else {
					$body.slideUp(200);
				}
			});
		},

		addTierRow: function($btn) {
			var prefix = $btn.attr('data-prefix');
			var $table = $btn.closest('.wpcttp-admin-tiers-table');
			var $tbody = $table.find('tbody.wpcttp-tiers-tbody');
			var newIndex = $tbody.find('tr.wpcttp-tier-editor-row').length + Date.now();

			// Auto calculate sensible next minimum from previous tier
			var nextMin = 1;
			var $lastRow = $tbody.find('tr.wpcttp-tier-editor-row').last();
			if ($lastRow.length) {
				var prevMax = parseFloat($lastRow.find('input[name*="[max_qty]"]').val());
				var prevMin = parseFloat($lastRow.find('input[name*="[min_qty]"]').val()) || 1;
				if (!isNaN(prevMax) && prevMax >= prevMin) {
					nextMin = prevMax + 1;
				} else if (!isNaN(prevMin)) {
					nextMin = prevMin + 5;
				}
			}

			var html = '<tr class="wpcttp-tier-editor-row">';
			html += '<td><input type="number" step="1" min="1" class="short" name="' + prefix + '[tiers][' + newIndex + '][min_qty]" value="' + nextMin + '" required /></td>';
			html += '<td><input type="number" step="1" min="1" class="short" name="' + prefix + '[tiers][' + newIndex + '][max_qty]" value="" placeholder="' + (adminI18n.open || 'Open (e.g. 10+)') + '" /></td>';
			html += '<td><select name="' + prefix + '[tiers][' + newIndex + '][type]">';
			html += '<option value="fixed">' + (adminI18n.fixed || 'Fixed Unit Price') + '</option>';
			html += '<option value="percentage">' + (adminI18n.percentage || 'Percentage Discount (%)') + '</option>';
			html += '</select></td>';
			html += '<td><input type="number" step="0.01" min="0" class="short" name="' + prefix + '[tiers][' + newIndex + '][value]" value="" required /></td>';
			html += '<td style="text-align: center;"><button type="button" class="button button-link-delete wpcttp-remove-tier-btn" aria-label="' + (adminI18n.remove || 'Remove tier') + '">&times;</button></td>';
			html += '</tr>';

			$tbody.append(html);
		}
	};

	$(document).ready(function() {
		WPCalibrateTieredPricingAdmin.init();
	});

})(jQuery);
