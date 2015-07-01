(function($) {

	$(document).ready(function() {
		// The form that contains all settings
		var $form = $('form#aws-settings');
		// Radio buttons for selecting where credentials come from
		var $inputs = $('input:radio[name=credentials_source]', $form);
		// The access key and secret key fields for WP database setup
		var $dbFields = $('tr.aws-db-setting', $form);
		// The radio that selects storing credentials in the WP database
		var $wpDbRadio = $($inputs).filter('[value=wp-database]');

		// Hide WP DB configuration fields when not configuring via DB
		function update_db_field_display() {
			$dbFields.css('display', ('checked' == $wpDbRadio.attr('checked') ? '' : 'none'));
		}

		// Ensure hiding/showing WP DB fields is consistent with initial choice
		update_db_field_display();

		// Update whenever the selection changes
		$inputs.change(update_db_field_display);

		// Clear the WP DB fields before submitting the form
		$('.button.remove-keys').click(function() {
			$('input[name=secret_access_key],input[name=access_key_id]').val('');
		});
	});

})(jQuery);
