;(function ($, window) {
    'use strict';

    $.plugin('heidelpayInvoiceFactoring', {
        defaults: {
            heidelpayCreatePaymentUrl: '',
            birthdayElementSelector: '#heidelpayBirthday',
            generatedBirthdayElementSelector: '.flatpickr-input',
            isB2bCustomer: false,
            heidelpayCustomerDataUrl: ''
        },

        heidelpayPlugin: null,
        heidelpayInvoiceFactoring: null,
        customerId: null,
        customerProvider: null,

        init: function () {
            var heidelpayInstance;

            this.heidelpayPlugin = $('*[data-heidelpay-base="true"]').data('plugin_heidelpayBase');
            heidelpayInstance = this.heidelpayPlugin.getHeidelpayInstance();

            if (!heidelpayInstance) {
                return;
            }

            this.heidelpayInvoiceFactoring = heidelpayInstance.InvoiceFactoring();
            this.heidelpayPlugin.setSubmitButtonActive(true);

            this.applyDataAttributes();
            this.registerEvents();

            this.heidelpayPlugin.setSubmitButtonActive(false);

            if (this.opts.isB2bCustomer) {
                this.createB2BForm();
            } else {
                this.createB2CForm();
            }

            $.publish('plugin/heidelpay/invoice_factoring/init', this);
        },

        registerEvents: function () {
            $.subscribe('plugin/heidelpay/onSubmitCheckoutForm/after', $.proxy(this.createResource, this));
        },

        createB2BForm: function () {
            var me = this,
                heidelpayInstance = this.heidelpayPlugin.getHeidelpayInstance();

            this.customerProvider = heidelpayInstance.B2BCustomer();

            $.ajax({
                url: this.opts.heidelpayCustomerDataUrl,
                method: 'GET',
                success: function (data) {
                    if (data.success) {
                        me.customerProvider.b2bCustomerEventHandler = $.proxy(me.onValidateB2bForm, me);
                        me.customerProvider.initFormFields(data.customer);
                    }
                },
                complete: function () {
                    me.customerProvider.create({
                        containerId: 'heidelpay--invoice-factoring-container'
                    });

                    $.publish('plugin/heidelpay/invoice_factoring/createB2bForm', [this, this.customerProvider]);
                }
            });
        },

        createB2CForm: function () {
            $(this.opts.generatedBirthdayElementSelector).attr('required', 'required');
            $(this.opts.generatedBirthdayElementSelector).attr('form', 'confirm--form');

            this.heidelpayPlugin.setSubmitButtonActive(true);
            $.publish('plugin/heidelpay/invoice_factoring/createB2cForm', [this, this.customerProvider]);
        },

        createResource: function () {
            var me = this;
            $.publish('plugin/heidelpay/invoice_factoring/beforeCreateResource', this);

            if (this.opts.isB2bCustomer) {
                this.customerProvider.updateCustomer().then(function(customer) {
                    me.customerId = customer.id;

                    me.heidelpayInvoiceFactoring.createResource()
                        .then($.proxy(me.onResourceCreated, me))
                        .catch($.proxy(me.onError, me));
                }).catch(function(error) {
                    me.onError(error);
                });
            } else {
                this.heidelpayInvoiceFactoring.createResource()
                    .then($.proxy(this.onResourceCreated, this))
                    .catch($.proxy(this.onError, this));
            }
        },

        onResourceCreated: function (resource) {
            var me = this,
                birthDate = this.heidelpayPlugin.getFormattedBirthday(this.opts.birthdayElementSelector);

            if (!birthDate && !this.opts.isB2bCustomer) {
                me.onError({ message: me.heidelpayPlugin.opts.heidelpayBirthdayError });

                return;
            }

            $.publish('plugin/heidelpay/invoice_factoring/createPayment', this, resource);

            $.ajax({
                url: this.opts.heidelpayCreatePaymentUrl,
                method: 'POST',
                data: {
                    resource: resource,
                    additional: {
                        customerId: this.customerId,
                        birthday: birthDate
                    }
                }
            }).done(function (data) {
                if (undefined !== data.redirectUrl) {
                    window.location = data.redirectUrl;

                    return;
                }

                me.onError({ message: me.heidelpayPlugin.opts.heidelpayGenericRedirectError });
            });
        },

        onValidateB2bForm: function (validationResult) {
            this.heidelpayPlugin.setSubmitButtonActive(validationResult.success);

            $.publish('plugin/heidelpay/invoice_factoring/onValidateB2bForm', [this, validationResult]);
        },

        onError: function (error) {
            $.publish('plugin/heidelpay/invoice_factoring/createResourceError', this, error);

            this.heidelpayPlugin.redirectToErrorPage(this.heidelpayPlugin.getMessageFromError(error));
        }
    });

    window.StateManager.addPlugin('*[data-heidelpay-invoice-factoring="true"]', 'heidelpayInvoiceFactoring');
})(jQuery, window);
