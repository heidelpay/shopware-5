;(function ($, window) {
    'use strict';

    $.plugin('heidelpaySepaDirectDebitGuaranteed', {
        defaults: {
            heidelpayCreatePaymentUrl: '',
            mandateCheckboxSelector: '#acceptMandate',
            radioButtonNewSelector: '#new',
            radioButtonSelector: 'input:radio[name="mandateSelection"]',
            selectedRadioButtonSelector: 'input:radio[name="mandateSelection"]:checked',
            birthdayElementSelector: '#heidelpayBirthday',
            generatedBirthdayElementSelecotr: '.flatpickr-input'
        },

        heidelpayPlugin: null,
        heidelpaySepaDirectDebit: null,
        newRadioButton: null,
        ibanValid: false,

        init: function () {
            var heidelpayInstance;

            this.heidelpayPlugin = $('*[data-heidelpay-base="true"]').data('plugin_heidelpayBase');
            heidelpayInstance = this.heidelpayPlugin.getHeidelpayInstance();

            if (!heidelpayInstance) {
                return;
            }

            this.heidelpaySepaDirectDebit = heidelpayInstance.SepaDirectDebitGuaranteed();

            this.applyDataAttributes();
            this.registerEvents();
            this.createForm();

            this.newRadioButton = $(this.opts.radioButtonNewSelector);

            if (this.newRadioButton.length === 0 || this.newRadioButton.prop('checked')) {
                this.heidelpayPlugin.setSubmitButtonActive(false);

                $(this.opts.generatedBirthdayElementSelecotr).attr('required', 'required');
                $(this.opts.generatedBirthdayElementSelecotr).attr('form', 'confirm--form');
            } else {
                $(this.opts.mandateCheckboxSelector).removeAttr('required');
                $(this.opts.generatedBirthdayElementSelecotr).removeAttr('required');
            }

            $.publish('plugin/heidelpay/sepa_direct_debit_guaranteed/init', this);
        },

        createForm: function () {
            this.heidelpaySepaDirectDebit.create('sepa-direct-debit-guaranteed', {
                containerId: 'heidelpay--sepa-direct-debit-container'
            });

            this.heidelpaySepaDirectDebit.addEventListener('change', $.proxy(this.onFormChange, this));

            $.publish('plugin/heidelpay/sepa_direct_debit_guaranteed/createForm', this, this.heidelpaySepaDirectDebit);
        },

        registerEvents: function () {
            $.subscribe('plugin/heidelpay/onSubmitCheckoutForm/after', $.proxy(this.createResource, this));
            $(this.opts.radioButtonSelector).on('change', $.proxy(this.onChangeMandateSelection, this));
        },

        createResource: function () {
            $.publish('plugin/heidelpay/sepa_direct_debit_guaranteed/beforeCreateResource', this);

            if (this.newRadioButton.length === 0 || this.newRadioButton.prop('checked')) {
                this.heidelpaySepaDirectDebit.createResource()
                    .then($.proxy(this.onResourceCreated, this))
                    .catch($.proxy(this.onError, this));
            } else {
                this.createPaymentFromVault($(this.opts.selectedRadioButtonSelector).attr('id'));
            }
        },

        createPaymentFromVault: function (typeId) {
            var me = this,
                birthDateTarget = '#' + typeId + '_birthDate',
                birthDate = $(birthDateTarget).val();

            if (!birthDate) {
                me.onError({ message: me.heidelpayPlugin.opts.heidelpayBirthdayError });

                return;
            }

            $.ajax({
                url: this.opts.heidelpayCreatePaymentUrl,
                method: 'POST',
                data: {
                    typeId: typeId,
                    additional: {
                        isPaymentFromVault: true,
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

        onFormChange: function (event) {
            if (!this.newRadioButton) {
                return;
            }

            this.newRadioButton.prop('checked', true);
            this.heidelpayPlugin.setSubmitButtonActive(event.success);
            this.ibanValid = event.success;
            $(this.opts.mandateCheckboxSelector).prop('required', 'required');

            $(this.opts.generatedBirthdayElementSelecotr).attr('required', 'required');
            $(this.opts.generatedBirthdayElementSelecotr).attr('form', 'confirm--form');
        },

        onChangeMandateSelection: function (event) {
            if (event.target.id === 'new') {
                this.heidelpayPlugin.setSubmitButtonActive(this.ibanValid);
                $(this.opts.mandateCheckboxSelector).prop('required', 'required');
            } else {
                this.heidelpayPlugin.setSubmitButtonActive(true);
                $(this.opts.mandateCheckboxSelector).removeAttr('required');
            }
        },

        onResourceCreated: function (resource) {
            var me = this,
                birthDate = this.heidelpayPlugin.getFormattedBirthday(this.opts.birthdayElementSelector);

            if (!birthDate) {
                me.onError({ message: me.heidelpayPlugin.opts.heidelpayBirthdayError });

                return;
            }

            $.publish('plugin/heidelpay/sepa_direct_debit_guaranteed/createPayment', this, resource);

            $.ajax({
                url: this.opts.heidelpayCreatePaymentUrl,
                method: 'POST',
                data: {
                    resource: resource,
                    additional: {
                        mandateAccepted: $(this.opts.mandateCheckboxSelector).is(':checked'),
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

        onError: function (error) {
            $.publish('plugin/heidelpay/sepa_direct_debit_guaranteed/createResourceError', this, error);

            this.heidelpayPlugin.redirectToErrorPage(this.heidelpayPlugin.getMessageFromError(error));
        }
    });

    window.StateManager.addPlugin('*[data-heidelpay-sepa-direct-debit-guaranteed="true"]', 'heidelpaySepaDirectDebitGuaranteed');
})(jQuery, window);
