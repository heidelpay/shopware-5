;(function ($, window) {
    'use strict';

    $.plugin('heidelpayEps', {
        defaults: {
            heidelpayCreatePaymentUrl: ''
        },

        heidelpayPlugin: null,
        heidelpayEps: null,

        selectedBank: null,

        init: function () {
            var heidelpayInstance;

            this.heidelpayPlugin = $('*[data-heidelpay-base="true"]').data('plugin_heidelpayBase');
            heidelpayInstance = this.heidelpayPlugin.getHeidelpayInstance();

            if (!heidelpayInstance) {
                return;
            }

            this.heidelpayEps = heidelpayInstance.EPS();
            this.heidelpayPlugin.setSubmitButtonActive(false);

            this.applyDataAttributes();
            this.registerEvents();
            this.createForm();

            $.publish('plugin/heidelpay/eps/init', this);
        },

        createForm: function () {
            this.heidelpayEps.create('eps', {
                containerId: 'heidelpay--eps-container'
            });

            this.heidelpayEps.addEventListener('change', $.proxy(this.onFormChange, this));

            $.publish('plugin/heidelpay/eps/createForm', this, this.heidelpayEps);
        },

        registerEvents: function () {
            $.subscribe('plugin/heidelpay/onSubmitCheckoutForm/after', $.proxy(this.createResource, this));
        },

        createResource: function () {
            $.publish('plugin/heidelpay/eps/beforeCreateResource', this);

            this.heidelpayEps.createResource()
                .then($.proxy(this.onResourceCreated, this))
                .catch($.proxy(this.onError, this));
        },

        onFormChange: function (event) {
            if (event.value) {
                this.heidelpayPlugin.setSubmitButtonActive(true);
            }
        },

        onResourceCreated: function (resource) {
            var me = this;
            $.publish('plugin/heidelpay/eps/createPayment', this, resource);

            $.ajax({
                url: this.opts.heidelpayCreatePaymentUrl,
                method: 'POST',
                data: {
                    resource: resource
                }
            }).done(function (data) {
                if (undefined !== data.redirectUrl) {
                    window.location = data.redirectUrl;

                    return;
                }

                me.onError({ message: me.heidelpayPlugin.opts.heidelpayGenericRedirectError });
            });
        },

        onError: function (error) {
            $.publish('plugin/heidelpay/eps/createResourceError', this, error);

            this.heidelpayPlugin.redirectToErrorPage(this.heidelpayPlugin.getMessageFromError(error));
        }
    });

    window.StateManager.addPlugin('*[data-heidelpay-eps="true"]', 'heidelpayEps');
})(jQuery, window);
