;(function ($, window) {
    'use strict';

    $.plugin('heidelHirePurchase', {
        defaults: {
            hirePurchaseContainerId: 'heidelpay--hire-purchase-container',
            heidelpayCreatePaymentUrl: '',
            basketAmount: 0.00,
            currencyIso: '',
            locale: '',
            effectiveInterest: 0.00,
            starSign: '*',
            hirePurchaseTotalElementId: '#heidelpay-total-interest',
            hirePurchaseInterestElementId: '#heidelpay-interest',
            hirePurchaseValueElementSelector: '.entry--value',
            birthdayElementSelector: '#heidelpayBirthday',
            generatedBirthdayElementSelector: '.flatpickr-input'
        },

        heidelpayPlugin: null,
        hirePurchase: null,

        init: function () {
            var heidelpayInstance;

            this.heidelpayPlugin = $('*[data-heidelpay-base="true"]').data('plugin_heidelpayBase');
            heidelpayInstance = this.heidelpayPlugin.getHeidelpayInstance();

            if (!heidelpayInstance) {
                return;
            }

            this.hirePurchase = heidelpayInstance.HirePurchase();
            this.applyDataAttributes();
            this.registerEvents();
            this.createForm();

            $.publish('plugin/heidelpay/hire_purchase/init', this);
        },

        registerEvents: function () {
            $.subscribe('plugin/heidelpay/createResource', $.proxy(this.createResource, this));
            this.hirePurchase.addEventListener('hirePurchaseEvent', $.proxy(this.onChangeHirePurchaseForm, this));
        },

        createForm: function() {
            var me = this;
            this.heidelpayPlugin.setSubmitButtonActive(false);
            this.hirePurchase.create({
                containerId: this.opts.hirePurchaseContainerId,
                amount: this.opts.basketAmount,
                currency: this.opts.currencyIso,
                effectiveInterest: this.opts.effectiveInterest
            }).then(function() {
                $(me.opts.generatedBirthdayElementSelector).attr('required', 'required');
                $(me.opts.generatedBirthdayElementSelector).attr('form', 'confirm--form');
            }).catch(function() {
                me.heidelpayPlugin.showCommunicationError();
            });
        },

        createResource: function () {
            $.publish('plugin/heidelpay/hire_purchase/beforeCreateResource', this);

            this.hirePurchase.createResource()
                .then($.proxy(this.onResourceCreated, this))
                .catch($.proxy(this.onError, this));
        },

        onResourceCreated: function (resource) {
            var me = this,
                birthDate = this.heidelpayPlugin.getFormattedBirthday(this.opts.birthdayElementSelector);

            if (!birthDate) {
                me.onError({ message: me.heidelpayPlugin.opts.heidelpayBirthdayError });

                return;
            }

            $.publish('plugin/heidelpay/hire_purchase/createPayment', this, resource);

            $.ajax({
                url: this.opts.heidelpayCreatePaymentUrl,
                method: 'POST',
                data: {
                    resource: resource,
                    additional: {
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

        onChangeHirePurchaseForm: function(event) {
            if (event.action === 'validate') {
                if (event.success) {
                    this.heidelpayPlugin.setSubmitButtonActive(true);
                } else {
                    this.heidelpayPlugin.setSubmitButtonActive(false);
                }
            }

            if (event.currentStep === 'plan-detail' && undefined !== this.hirePurchase.selectedInstallmentPlan) {
                var totalAmount = this.hirePurchase.selectedInstallmentPlan.totalAmount,
                    totalInterestAmount = this.hirePurchase.selectedInstallmentPlan.totalInterestAmount;

                $(this.opts.hirePurchaseTotalElementId + ' ' + this.opts.hirePurchaseValueElementSelector).text(this.heidelpayPlugin.formatCurrency(totalAmount, this.opts.locale, this.opts.currencyIso));
                $(this.opts.hirePurchaseInterestElementId + ' ' + this.opts.hirePurchaseValueElementSelector).text(this.heidelpayPlugin.formatCurrency(totalInterestAmount, this.opts.locale, this.opts.currencyIso) + this.opts.starSign);
            }
        },

        onError: function (error) {
            $.publish('plugin/heidelpay/hire_purchase/createResourceError', this, error);

            this.heidelpayPlugin.redirectToErrorPage(this.heidelpayPlugin.getMessageFromError(error));
        }
    });

    window.StateManager.addPlugin('*[data-heidelpay-hire-purchase="true"]', 'heidelHirePurchase');
})(jQuery, window);
