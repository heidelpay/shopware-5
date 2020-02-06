<?php

declare(strict_types=1);

use HeidelPayment\Components\PaymentHandler\Traits\CanCharge;
use HeidelPayment\Controllers\AbstractHeidelpayPaymentController;
use heidelpayPHP\Exceptions\HeidelpayApiException;
use heidelpayPHP\Resources\PaymentTypes\Prepayment;

class Shopware_Controllers_Widgets_HeidelpayPrepayment extends AbstractHeidelpayPaymentController
{
    use CanCharge;

    public function createPaymentAction(): void
    {
        try {
            parent::pay();
            $this->paymentType = $this->heidelpayClient->createPaymentType(new Prepayment());
            $redirectUrl       = $this->charge($this->paymentDataStruct->getReturnUrl());
        } catch (HeidelpayApiException $apiException) {
            $this->getApiLogger()->logException('Error while creating prepayment payment', $apiException);
            $redirectUrl = $this->getHeidelpayErrorUrl($apiException->getClientMessage());
        } finally {
            $this->view->assign('redirectUrl', $redirectUrl);
        }
    }
}
