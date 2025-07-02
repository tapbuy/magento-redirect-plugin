/**
 * Tapbuy Checkout JavaScript
 */
define([
    'jquery',
    'mage/cookies'
], function($) {
    'use strict';

    return {
        /**
         * Initialize Tapbuy tracking
         * 
         * @param {String} abTestId
         */
        initialize: function(abTestId) {
            // Store the A/B test ID
            if (abTestId) {
                $.mage.cookies.set('tb-abtest-id', abTestId, {
                    domain: window.location.hostname,
                    path: '/',
                    secure: true,
                    lifetime: 86400 // 1 day in seconds
                });
            }

            // Track checkout page view
            this.trackCheckoutPageView();

            // Attach event listeners
            this.attachEventListeners();
        },

        /**
         * Track checkout page view
         */
        trackCheckoutPageView: function() {
            // Get current path
            var path = window.location.pathname + window.location.search;
            
            // Get A/B test ID from cookie
            var abTestId = $.mage.cookies.get('tb-abtest-id');
            
            // We could send a tracking event here if needed
            console.log('Tapbuy checkout tracking initialized:', {
                path: path,
                abTestId: abTestId
            });
        },

        /**
         * Attach event listeners to checkout elements
         */
        attachEventListeners: function() {
            // Listen for checkout step changes
            $(document).on('checkout.goto.step', function(event, step) {
                // Track step change
                console.log('Checkout step changed:', step);
            });

            // Listen for payment method selection
            $(document).on('payment-method:selected', function(event, method) {
                // Track payment method selection
                console.log('Payment method selected:', method);
            });

            // Listen for shipping method selection
            $(document).on('shipping-method:selected', function(event, method) {
                // Track shipping method selection
                console.log('Shipping method selected:', method);
            });
        }
    };
});