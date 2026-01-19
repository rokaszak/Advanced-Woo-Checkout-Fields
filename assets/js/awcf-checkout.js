/**
 * Advanced Woo Checkout Fields - Checkout JavaScript
 *
 * Handles dynamic visibility and required state of company fields based on checkbox state.
 * Also handles forced shipping address checkbox removal.
 *
 * @package Advanced_Woo_Checkout_Fields
 */

(function($) {
    'use strict';

    /**
     * AWCF Checkout handler
     */
    var AWCFCheckout = {
        /**
         * Initialize
         */
        init: function() {
            this.cacheElements();
            this.bindEvents();
            this.toggleCompanyFields();
            this.handleShipToDifferentAddress();
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$checkbox = $('#billing_is_company');
            this.$companyFields = $('.awcf-company-field');
            this.$infoMessage = $('.awcf-company-info-message');
            this.$shipToDifferentCheckbox = $('#ship-to-different-address-checkbox');
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            var self = this;

            // Listen for checkbox changes
            this.$checkbox.on('change', function() {
                self.toggleCompanyFields();
            });

            // Also listen for WooCommerce checkout updates (AJAX)
            $(document.body).on('updated_checkout', function() {
                self.cacheElements();
                self.toggleCompanyFields();
                self.handleShipToDifferentAddress();
            });
        },

        /**
         * Handle ship to different address checkbox removal when forced
         */
        handleShipToDifferentAddress: function() {
            // Check if we should force ship to different address (set via localized script)
            if (typeof awcf_params !== 'undefined' && awcf_params.force_ship_to_different) {
                // Remove the checkbox from DOM entirely
                this.$shipToDifferentCheckbox.remove();
                
                // Also ensure the shipping address is visible
                $('.woocommerce-shipping-fields .shipping_address').show();
            }
        },

        /**
         * Toggle company fields visibility and required state
         */
        toggleCompanyFields: function() {
            var isChecked = this.$checkbox.is(':checked');
            var self = this;

            if (isChecked) {
                // Show fields
                this.$companyFields.addClass('awcf-visible').slideDown(200);
                this.$infoMessage.addClass('awcf-visible').slideDown(200);
                
                // Make fields required
                this.$companyFields.each(function() {
                    self.setFieldRequired($(this), true);
                });
            } else {
                // Hide fields
                this.$companyFields.removeClass('awcf-visible').slideUp(200);
                this.$infoMessage.removeClass('awcf-visible').slideUp(200);
                
                // Make fields optional
                this.$companyFields.each(function() {
                    self.setFieldRequired($(this), false);
                });
            }
        },

        /**
         * Set field required state
         *
         * @param {jQuery} $fieldWrapper The field wrapper element
         * @param {boolean} required Whether the field should be required
         */
        setFieldRequired: function($fieldWrapper, required) {
            var $label = $fieldWrapper.find('label');
            var $input = $fieldWrapper.find('input, select, textarea');
            var $requiredSpan = $label.find('span.required');
            var $optional = $label.find('.optional');

            if (required) {
                // Add required class to wrapper
                $fieldWrapper.addClass('validate-required');
                
                // Add required attribute to input
                $input.attr('required', 'required');
                
                // Remove optional text if exists
                $optional.remove();
                
                // Add required asterisk if not exists (WooCommerce format)
                if ($requiredSpan.length === 0) {
                    $label.append('&nbsp;<span class="required" aria-hidden="true">*</span>');
                }
            } else {
                // Remove required class from wrapper
                $fieldWrapper.removeClass('validate-required woocommerce-invalid woocommerce-invalid-required-field');
                
                // Remove required attribute from input
                $input.removeAttr('required');
                
                // Remove required asterisk
                $requiredSpan.remove();
                
                // Add optional text if not exists and if awcf_params is available
                if ($optional.length === 0 && typeof awcf_params !== 'undefined' && awcf_params.optional_text) {
                    $label.append('&nbsp;<span class="optional">' + awcf_params.optional_text + '</span>');
                }
            }
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        AWCFCheckout.init();
    });

})(jQuery);
