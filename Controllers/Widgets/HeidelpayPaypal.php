<?php

use HeidelPayment\Components\BookingMode;
use HeidelPayment\Controllers\AbstractHeidelpayPaymentController;
use heidelpayPHP\Exceptions\HeidelpayApiException;
use heidelpayPHP\Resources\PaymentTypes\Paypal as PaypalPaymentType;

class Shopware_Controllers_Widgets_HeidelpayPaypal extends AbstractHeidelpayPaymentController
{
    /** @var PaypalPaymentType */
    protected $paymentType;

    public function createPaymentAction(): void
    {
        $this->paymentType = new PaypalPaymentType();
        $this->paymentType->setParentResource($this->heidelpayClient);

        $heidelBasket   = $this->getHeidelpayBasket();
        $heidelCustomer = $this->getHeidelpayCustomer();
        $heidelMetadata = $this->getHeidelpayMetadata();
        $returnUrl      = $this->getHeidelpayReturnUrl();
        $bookingMode    = $this->container->get('heidel_payment.services.config_reader')->get('paypal_bookingmode');

        try {
            $heidelCustomer = $this->heidelpayClient->createOrUpdateCustomer($heidelCustomer);

            if ($bookingMode === BookingMode::CHARGE) {
                $result = $this->paymentType->charge(
                    $heidelBasket->getAmountTotal(),
                    $heidelBasket->getCurrencyCode(),
                    $returnUrl,
                    $heidelCustomer,
                    $heidelBasket->getOrderId(),
                    $heidelMetadata,
                    $heidelBasket
                );
            } else {
                $result = $this->paymentType->authorize(
                    $heidelBasket->getAmountTotal(),
                    $heidelBasket->getCurrencyCode(),
                    $returnUrl,
                    $heidelCustomer,
                    $heidelBasket->getOrderId(),
                    $heidelMetadata,
                    $heidelBasket
                );
            }

            $this->redirect($result->getPayment()->getRedirectUrl());
        } catch (HeidelpayApiException $apiException) {
            $this->redirect($this->getHeidelpayErrorUrl($apiException->getClientMessage()));
        }

        if (isset($result)) {
            $this->session->offsetSet('heidelPaymentId', $result->getPaymentId());
        }
    }
}