/**
 * Advanced Woo Checkout Fields - Checkout JavaScript
 *
 * Handles dynamic visibility of company fields based on checkbox state.
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
        },

        /**
         * Cache DOM elements
         */
        cacheElements: function() {
            this.$checkbox = $('#billing_is_company');
            this.$companyFields = $('.awcf-company-field');
            this.$infoMessage = $('.awcf-company-info-message');
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
            });
        },

        /**
         * Toggle company fields visibility
         */
        toggleCompanyFields: function() {
            var isChecked = this.$checkbox.is(':checked');

            if (isChecked) {
                this.$companyFields.addClass('awcf-visible').slideDown(200);
                this.$infoMessage.addClass('awcf-visible').slideDown(200);
            } else {
                this.$companyFields.removeClass('awcf-visible').slideUp(200);
                this.$infoMessage.removeClass('awcf-visible').slideUp(200);
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
