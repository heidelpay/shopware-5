;(function ($, window) {
    'use strict';

    $.plugin('heidelpayInvoiceGuaranteed', {
        defaults: {
            heidelpayCreatePaymentUrl: '',
            birthdayElementSelector: '#heidelpayBirthday',
            generatedBirthdayElementSelector: '.flatpickr-input',
            isB2bCustomer: false,
            heidelpayCustomerDataUrl: ''
        },

        heidelpayPlugin: null,
        heidelpayInvoiceGuaranteed: null,
        customerId: null,
        customerProvider: null,

        init: function () {
            var heidelpayInstance;

            this.heidelpayPlugin = $('*[data-heidelpay-base="true"]').data('plugin_heidelpayBase');
            heidelpayInstance = this.heidelpayPlugin.getHeidelpayInstance();

            if (!heidelpayInstance) {
                return;
            }

            this.heidelpayInvoiceGuaranteed = heidelpayInstance.InvoiceGuaranteed();

            this.applyDataAttributes();
            this.registerEvents();

            this.heidelpayPlugin.setSubmitButtonActive(false);

            if (this.opts.isB2bCustomer) {
                this.createB2BForm();
            } else {
                this.createB2CForm();
            }

            $.publish('plugin/heidelpay/invoice_guaranteed/init', this);
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
                        containerId: 'heidelpay--invoice-guaranteed-container'
                    });

                    $.publish('plugin/heidelpay/invoice_guaranteed/createB2bForm', [this, this.customerProvider]);
                }
            });
        },

        createB2CForm: function () {
            $(this.opts.generatedBirthdayElementSelector).attr('required', 'required');
            $(this.opts.generatedBirthdayElementSelector).attr('form', 'confirm--form');

            this.heidelpayPlugin.setSubmitButtonActive(true);
            $.publish('plugin/heidelpay/invoice_guaranteed/createB2cForm', [this, this.customerProvider]);
        },

        registerEvents: function () {
            $.subscribe('plugin/heidelpay/onSubmitCheckoutForm/after', $.proxy(this.createResource, this));
        },

        createResource: function () {
            var me = this;
            $.publish('plugin/heidelpay/invoice_guaranteed/beforeCreateResource', this);

            if (this.opts.isB2bCustomer) {
                this.customerProvider.updateCustomer().then(function(customer) {
                    me.customerId = customer.id;

                    me.heidelpayInvoiceGuaranteed.createResource()
                        .then($.proxy(me.onResourceCreated, me))
                        .catch($.proxy(me.onError, me));
                }).catch(function(error) {
                    me.onError(error);
                });
            } else {
                this.heidelpayInvoiceGuaranteed.createResource()
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

            $.publish('plugin/heidelpay/invoice_guaranteed/createPayment', this, resource);

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

            $.publish('plugin/heidelpay/invoice_guaranteed/onValidateB2bForm', [this, validationResult]);
        },

        onError: function (error) {
            $.publish('plugin/heidelpay/invoice_guaranteed/createResourceError', this, error);

            this.heidelpayPlugin.redirectToErrorPage(this.heidelpayPlugin.getMessageFromError(error));
        }
    });

    window.StateManager.addPlugin('*[data-heidelpay-invoice-guaranteed="true"]', 'heidelpayInvoiceGuaranteed');
})(jQuery, window);
