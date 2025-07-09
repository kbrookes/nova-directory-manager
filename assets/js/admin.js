/**
 * Admin JavaScript for Business Directory Registration Manager
 *
 * @package BusinessDirectoryRegistrationManager
 * @since 1.0.0
 */

(function($) {
	'use strict';

	// Initialize when document is ready
	$(document).ready(function() {
		// Form validation
		$('form').on('submit', function(e) {
			var formId = $('#fluent_form_id').val();
			var postType = $('#post_type').val();
			var categoryField = $('#category_field').val();
			
			if (!formId) {
				e.preventDefault();
				alert('Please select a Fluent Form.');
				$('#fluent_form_id').focus();
				return false;
			}
			
			if (!postType) {
				e.preventDefault();
				alert('Please select a Post Type.');
				$('#post_type').focus();
				return false;
			}
			
			if (!categoryField) {
				e.preventDefault();
				alert('Please enter a Category Field name.');
				$('#category_field').focus();
				return false;
			}
		});

		// Role name validation
		$('#user_role_name').on('input', function() {
			var value = $(this).val();
			// Only allow lowercase letters, numbers, and underscores
			var sanitized = value.replace(/[^a-z0-9_]/g, '').toLowerCase();
			if (value !== sanitized) {
				$(this).val(sanitized);
			}
		});

		// Show/hide help text
		$('.form-table th').each(function() {
			var helpText = $(this).next('td').find('.description').text();
			if (helpText) {
				$(this).attr('title', helpText);
			}
		});
	});

})(jQuery); 