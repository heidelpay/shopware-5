;(function ($, window) {
    'use strict';

    $.plugin('heidelpayPayPal', {
        defaults: {
            heidelpayCreatePaymentUrl: '',
            radioButtonSelector: 'input:radio[name="paypalSelection"]',
            selectedRadioButtonSelector: 'input:radio[name="paypalSelection"]:checked',
            radioButtonNewSelector: '#new',
            typeIdProviderSelector: '#typeIdProvider'
        },

        heidelpayPlugin: null,

        init: function () {
            this.heidelpayPlugin = $('*[data-heidelpay-base="true"]').data('plugin_heidelpayBase');

            this.applyDataAttributes();
            this.registerEvents();

            if ($(this.opts.radioButtonSelector).length > 1) {
                $(this.opts.radioButtonNewSelector).prop('checked', true);
            }

            this.heidelpayPlugin.setSubmitButtonActive(true);
            $.publish('plugin/heidelpay/paypal/init', this);
        },

        registerEvents: function () {
            $.subscribe('plugin/heidelpay/onSubmitCheckoutForm/before', $.proxy(this.createResource, this));
            $.subscribe('plugin/heidelpay/onSubmitCheckoutForm/after', $.proxy(this.submitPayment, this));
        },

        createResource: function () {
            $.publish('plugin/heidelpay/paypal/createResource/before', this);

            $(this.heidelpayPlugin.opts.checkoutFormSelector).attr('action', this.opts.heidelpayCreatePaymentUrl);

            if (!$(this.opts.radioButtonNewSelector).is(':checked')) {
                $(this.opts.typeIdProviderSelector).attr('value', $(this.opts.selectedRadioButtonSelector).attr('id'));
            }

            $.publish('plugin/heidelpay/paypal/createResource/after', this);
        },

        submitPayment: function () {
            this.heidelpayPlugin.isAsyncPayment = false;
            $(this.heidelpayPlugin.opts.checkoutFormSelector).submit();
        },

        onError: function (error) {
            $.publish('plugin/heidelpay/paypal/createResourceError', this, error);

            this.heidelpayPlugin.redirectToErrorPage(this.heidelpayPlugin.getMessageFromError(error));
        }
    });

    window.StateManager.addPlugin('*[data-heidelpay-paypal="true"]', 'heidelpayPayPal');
})(jQuery, window);
