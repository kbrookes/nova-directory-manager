/**
 * Nova Directory Manager Frontend JavaScript
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        NDM.init();
    });

    // Main NDM object
    var NDM = {
        
        /**
         * Initialize the frontend functionality
         */
        init: function() {
            this.bindEvents();
            this.setupFormHandlers();
            this.setupMobileAccordion();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Handle form submissions
            $(document).on('submit', '.ndm-acf-form', this.handleFormSubmit);
            
            // Handle edit button clicks
            $(document).on('click', '.ndm-edit-business', this.handleEditClick);
            
            // Handle delete confirmations
            $(document).on('click', '.ndm-delete-business', this.handleDeleteClick);
            
            // Handle success messages
            if (window.location.search.indexOf('updated=1') > -1) {
                this.showSuccessMessage('Business updated successfully!');
            }
        },

        /**
         * Setup form handlers
         */
        setupFormHandlers: function() {
            // Add loading states to forms
            $('.ndm-acf-form').each(function() {
                var $form = $(this);
                var $submitBtn = $form.find('input[type="submit"]');
                
                if ($submitBtn.length) {
                    $submitBtn.on('click', function() {
                        $form.addClass('ndm-loading');
                    });
                }
            });
        },

        /**
         * Handle form submission
         */
        handleFormSubmit: function(e) {
            var $form = $(this);
            var $submitBtn = $form.find('input[type="submit"]');
            var originalText = $submitBtn.val();
            
            // Show loading state
            $submitBtn.val(ndm_ajax.strings.saving);
            $form.addClass('ndm-loading');
            
            // Let ACF handle the form submission
            // We'll hook into the ACF save process via PHP
            
            // Remove loading state after a delay
            setTimeout(function() {
                $submitBtn.val(originalText);
                $form.removeClass('ndm-loading');
            }, 2000);
        },

        /**
         * Handle edit button click
         */
        handleEditClick: function(e) {
            e.preventDefault();
            
            var businessId = $(this).data('business-id');
            var editUrl = $(this).attr('href');
            
            if (businessId && editUrl) {
                window.location.href = editUrl;
            }
        },

        /**
         * Handle delete button click
         */
        handleDeleteClick: function(e) {
            e.preventDefault();
            
            if (!confirm(ndm_ajax.strings.confirm_delete)) {
                return false;
            }
            
            var businessId = $(this).data('business-id');
            var $button = $(this);
            
            if (businessId) {
                $button.addClass('ndm-loading');
                
                $.ajax({
                    url: ndm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'ndm_delete_business',
                        business_id: businessId,
                        nonce: ndm_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $button.closest('.ndm-business-item').fadeOut(300, function() {
                                $(this).remove();
                            });
                            NDM.showSuccessMessage(response.data.message);
                        } else {
                            NDM.showErrorMessage(response.data.message || 'Error deleting business');
                        }
                    },
                    error: function() {
                        NDM.showErrorMessage('Network error. Please try again.');
                    },
                    complete: function() {
                        $button.removeClass('ndm-loading');
                    }
                });
            }
        },

        /**
         * Show success message
         */
        showSuccessMessage: function(message) {
            this.showMessage(message, 'success');
        },

        /**
         * Show error message
         */
        showErrorMessage: function(message) {
            this.showMessage(message, 'error');
        },

        /**
         * Show message
         */
        showMessage: function(message, type) {
            var $notice = $('<div class="ndm-notice ndm-notice-' + type + '">' + message + '</div>');
            
            // Add to page
            $('body').append($notice);
            
            // Show with animation
            $notice.css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                zIndex: 9999,
                padding: '15px 20px',
                borderRadius: '4px',
                color: '#fff',
                fontWeight: '600',
                boxShadow: '0 4px 12px rgba(0,0,0,0.15)',
                transform: 'translateX(100%)',
                transition: 'transform 0.3s ease'
            });
            
            if (type === 'success') {
                $notice.css({
                    background: '#28a745',
                    border: '1px solid #1e7e34'
                });
            } else {
                $notice.css({
                    background: '#dc3545',
                    border: '1px solid #c82333'
                });
            }
            
            // Animate in
            setTimeout(function() {
                $notice.css('transform', 'translateX(0)');
            }, 100);
            
            // Auto remove after 5 seconds
            setTimeout(function() {
                $notice.css('transform', 'translateX(100%)');
                setTimeout(function() {
                    $notice.remove();
                }, 300);
            }, 5000);
        },

        /**
         * Validate form fields
         */
        validateForm: function($form) {
            var isValid = true;
            var errors = [];
            
            // Check required fields
            $form.find('[required]').each(function() {
                var $field = $(this);
                var value = $field.val();
                
                if (!value || value.trim() === '') {
                    isValid = false;
                    errors.push($field.attr('name') + ' is required');
                    $field.addClass('ndm-error');
                } else {
                    $field.removeClass('ndm-error');
                }
            });
            
            // Check email fields
            $form.find('input[type="email"]').each(function() {
                var $field = $(this);
                var value = $field.val();
                
                if (value && !this.isValidEmail(value)) {
                    isValid = false;
                    errors.push($field.attr('name') + ' must be a valid email');
                    $field.addClass('ndm-error');
                }
            });
            
            // Check URL fields
            $form.find('input[type="url"]').each(function() {
                var $field = $(this);
                var value = $field.val();
                
                if (value && !this.isValidUrl(value)) {
                    isValid = false;
                    errors.push($field.attr('name') + ' must be a valid URL');
                    $field.addClass('ndm-error');
                }
            });
            
            return {
                isValid: isValid,
                errors: errors
            };
        },

        /**
         * Validate email format
         */
        isValidEmail: function(email) {
            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        },

        /**
         * Validate URL format
         */
        isValidUrl: function(url) {
            try {
                new URL(url);
                return true;
            } catch (e) {
                return false;
            }
        },

        /**
         * Format phone number
         */
        formatPhoneNumber: function(phone) {
            // Remove all non-numeric characters
            var cleaned = phone.replace(/\D/g, '');
            
            // Format as (XXX) XXX-XXXX
            if (cleaned.length === 10) {
                return '(' + cleaned.slice(0, 3) + ') ' + cleaned.slice(3, 6) + '-' + cleaned.slice(6);
            }
            
            return phone;
        },

        /**
         * Initialize phone number formatting
         */
        initPhoneFormatting: function() {
            $('input[name="business_phone"]').on('blur', function() {
                var $field = $(this);
                var value = $field.val();
                
                if (value) {
                    $field.val(NDM.formatPhoneNumber(value));
                }
            });
        },

        /**
         * Setup mobile accordion for ACF tabs
         */
        setupMobileAccordion: function() {
            // Only run on mobile devices
            if (window.innerWidth > 768) {
                return;
            }

            // Handle tab clicks for accordion behavior
            $(document).on('click', '.ndm-acf-form .acf-tab-wrap .acf-tab-group li a', function(e) {
                e.preventDefault();
                
                var $tab = $(this);
                var $tabGroup = $tab.closest('.acf-tab-group');
                var $tabContent = $tabGroup.next('.acf-tab-content');
                var isActive = $tab.parent().hasClass('active');
                
                // Close all tabs
                $tabGroup.find('li').removeClass('active');
                $tabContent.removeClass('active');
                
                // Open clicked tab if it wasn't active
                if (!isActive) {
                    $tab.parent().addClass('active');
                    $tabContent.addClass('active');
                }
            });

            // Handle window resize
            $(window).on('resize', function() {
                if (window.innerWidth > 768) {
                    // Reset to normal tab behavior on desktop
                    $('.ndm-acf-form .acf-tab-wrap .acf-tab-group li').removeClass('active');
                    $('.ndm-acf-form .acf-tab-wrap .acf-tab-content').removeClass('active');
                }
            });
        }
    };

    // Make NDM available globally
    window.NDM = NDM;

})(jQuery); 