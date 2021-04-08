;(function ($, window) {
    'use strict';

    $.plugin('heidelpayCreditCard', {
        defaults: {
            heidelpayCreatePaymentUrl: '',
            cvcLabelSelector: '#card-element-label-cvc',
            elementInvalidClass: 'is--invalid',
            elementFocusedClass: 'is--focused',
            elementHiddenClass: 'is--hidden',
            creditCardContainerSelector: '.heidelpay--credit-card-container',
            radioButtonSelector: 'input:radio[name="cardSelection"]',
            selectedRadioButtonSelector: 'input:radio[name="cardSelection"]:checked',
            radioButtonNewSelector: '#new'
        },

        heidelpayPlugin: null,
        heidelpayCard: null,

        numberValid: false,
        cvcValid: false,
        expiryValid: false,

        init: function () {
            var me = this,
                heidelpayInstance;

            this.heidelpayPlugin = $('*[data-heidelpay-base="true"]').data('plugin_heidelpayBase');
            heidelpayInstance = this.heidelpayPlugin.getHeidelpayInstance();

            if (!heidelpayInstance) {
                return;
            }

            this.heidelpayCard = heidelpayInstance.Card();
            this.heidelpayCard.config.jsessionId.then(function (val) {
                if (!val) {
                    me.heidelpayPlugin.showCommunicationError();
                }
            });

            this.applyDataAttributes();
            this.registerEvents();
            this.createForm();

            if ($(this.opts.radioButtonSelector).length === 1) {
                $(this.opts.radioButtonNewSelector).prop('checked', true);

                this.applySelectionState('new');
                this.heidelpayPlugin.setSubmitButtonActive(false);
            } else {
                this.heidelpayPlugin.setSubmitButtonActive(true);
            }

            $.publish('plugin/heidelpay/credit_card/init', this);
        },

        registerEvents: function () {
            $.subscribe('plugin/heidelpay/onSubmitCheckoutForm/after', $.proxy(this.createResource, this));
            $(this.opts.radioButtonSelector).on('change', $.proxy(this.onChangeCardSelection, this));
        },

        createForm: function () {
            this.heidelpayCard.create('number', {
                containerId: 'card-element-id-number',
                onlyIframe: true
            });

            this.heidelpayCard.create('expiry', {
                containerId: 'card-element-id-expiry',
                onlyIframe: true
            });

            this.heidelpayCard.create('cvc', {
                containerId: 'card-element-id-cvc',
                onlyIframe: true
            });

            this.heidelpayCard.addEventListener('change', $.proxy(this.onFormChange, this));

            $.publish('plugin/heidelpay/credit_card/createForm', this, this.heidelpayCard);
        },

        createResource: function () {
            var $newRadioButton = $(this.opts.radioButtonNewSelector);

            $.publish('plugin/heidelpay/credit_card/beforeCreateResource', this);

            if ($newRadioButton.is(':checked')) {
                this.heidelpayCard.createResource()
                    .then($.proxy(this.onResourceCreated, this))
                    .catch($.proxy(this.onError, this));
            } else {
                this.createPaymentFromVault($(this.opts.selectedRadioButtonSelector).attr('id'));
            }
        },

        updateCvcLabel: function (newLabel) {
            var $label = $(this.opts.cvcLabelSelector);

            $label.text(newLabel);
        },

        setValidationError: function (type, message) {
            var $element = this.getEventElement(type),
                $elementLabel = $('#card-element-error-' + type + '-label');

            if (message) {
                $elementLabel.removeClass(this.opts.elementHiddenClass);
                $elementLabel.text(message);

                $element.addClass(this.opts.elementInvalidClass);
            } else {
                $elementLabel.addClass(this.opts.elementHiddenClass);

                $element.removeClass(this.opts.elementInvalidClass);
            }
        },

        applySelectionState: function (state) {
            var $creditCardContainer = $(this.opts.creditCardContainerSelector);

            if (state === 'new') {
                $creditCardContainer.removeClass(this.opts.elementHiddenClass);

                this.heidelpayPlugin.setSubmitButtonActive(
                    this.cvcValid === true &&
                    this.numberValid === true &&
                    this.expiryValid === true
                );
            } else {
                $creditCardContainer.addClass(this.opts.elementHiddenClass);

                this.heidelpayPlugin.setSubmitButtonActive(true);
            }
        },

        getEventElement: function (type) {
            return $('*[data-type="' + type + '"]');
        },

        onFormChange: function (event) {
            var $element = this.getEventElement(event.type);

            if (!$element) {
                return;
            }

            if (event.type === 'cvc') {
                this.cvcValid = event.success;
            } else if (event.type === 'number') {
                this.numberValid = event.success;
            } else if (event.type === 'expiry') {
                this.expiryValid = event.success;
            }

            this.setValidationError(event.type, event.error);

            if (event.focus === true) {
                $element.addClass(this.opts.elementFocusedClass);
            } else if (event.blur === true) {
                $element.removeClass(this.opts.elementFocusedClass);
            }

            if (event.cardType) {
                this.updateCvcLabel(event.cardType.code.name);
            }

            this.heidelpayPlugin.setSubmitButtonActive(
                this.cvcValid === true &&
                this.numberValid === true &&
                this.expiryValid === true
            );

            $.publish('plugin/heidelpay/credit_card/changeForm', this, event);
        },

        onResourceCreated: function (resource) {
            var me = this;
            $.publish('plugin/heidelpay/credit_card/createPayment', this, resource);

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

        createPaymentFromVault: function (typeId) {
            var me = this;
            $.ajax({
                url: this.opts.heidelpayCreatePaymentUrl,
                method: 'POST',
                data: {
                    typeId: typeId
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
            $.publish('plugin/heidelpay/credit_card/createResourceError', this, error);

            this.heidelpayPlugin.redirectToErrorPage(this.heidelpayPlugin.getMessageFromError(error));
        },

        onChangeCardSelection: function (event) {
            this.applySelectionState(event.target.id);
        }
    });

    window.StateManager.addPlugin('*[data-heidelpay-credit-card="true"]', 'heidelpayCreditCard');
})(jQuery, window);
