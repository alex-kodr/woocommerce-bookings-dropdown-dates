jQuery(function ($) {
	'use strict';

	console.log('WooCommerce Bookings Dropdown: Script loaded');
	console.log('WooBookingsDropdown object:', WooBookingsDropdown);

	// Check if dropdown exists
	var $dateSelect = $('#wc_bookings_field_start_date');
	console.log('Date select found:', $dateSelect.length);

	if ($dateSelect.length) {
		console.log('Dropdown HTML:', $dateSelect.parent().html());
	}

	/**
	 * Handle resource field changes
	 */
	$(document).on('change', '#wc_bookings_field_resource', function () {
		console.log('Resource changed');

		var $this = $(this);
		var productId = $('input[name="add-to-cart"]').val();
		var resourceId = $this.val();
		var $dateSelect = $('#wc_bookings_field_start_date');
		var $noCourses = $('.no-courses');

		console.log('Product ID:', productId);
		console.log('Resource ID:', resourceId);

		// Validate required data
		if (!productId) {
			console.error('Product ID not found');
			return;
		}

		// Show loading state
		$dateSelect.prop('disabled', true).html('<option value="">Loading...</option>');
		$noCourses.remove();

		// AJAX request to refresh dates
		$.ajax({
			url: WooBookingsDropdown.ajax_url,
			type: 'POST',
			data: {
				action: 'wswp_refresh_dates',
				security: WooBookingsDropdown.secure,
				product_id: productId,
				resource_id: resourceId
			},
			success: function (response) {
				console.log('AJAX response:', response);

				if (response.success && response.dates) {
					// Populate dropdown with new dates
					$dateSelect.prop('disabled', false).html('');

					$.each(response.dates, function (key, value) {
						$dateSelect.append($('<option>', {
							value: key,
							text: value
						}));
					});

					// Remove any error messages
					$noCourses.remove();
				} else {
					// Handle no dates available
					handleNoAvailability($dateSelect);
				}
			},
			error: function (xhr, status, error) {
				console.error('AJAX error:', status, error);
				console.error('XHR:', xhr);
				handleNoAvailability($dateSelect);
			}
		});
	});

	/**
	 * Handle when no dates are available
	 *
	 * @param {jQuery} $select The select element
	 */
	function handleNoAvailability($select) {
		var message = 'The dates for this method of delivery will be matched to your individual requirements, please contact us to arrange dates.';

		$select
			.prop('disabled', true)
			.html('<option value="">No dates available</option>')
			.parent()
			.after('<p class="no-courses">' + message + '</p>');
	}
});