<?php

declare(strict_types=1);

use HeidelPayment\Components\PaymentHandler\Traits\CanCharge;
use HeidelPayment\Controllers\AbstractHeidelpayPaymentController;
use heidelpayPHP\Exceptions\HeidelpayApiException;
use heidelpayPHP\Resources\PaymentTypes\Wechatpay;

class Shopware_Controllers_Widgets_HeidelpayWeChat extends AbstractHeidelpayPaymentController
{
    use CanCharge;

    public function createPaymentAction(): void
    {
        try {
            parent::pay();
            $this->paymentType = $this->heidelpayClient->createPaymentType(new Wechatpay());
            $redirectUrl       = $this->charge($this->paymentDataStruct->getReturnUrl());
        } catch (HeidelpayApiException $apiException) {
            $this->getApiLogger()->logException('Error while creating WeChatPay payment', $apiException);
            $redirectUrl = $this->getHeidelpayErrorUrl($apiException->getClientMessage());
        } catch (RuntimeException $runtimeException) {
            $redirectUrl = $this->getHeidelpayErrorUrlFromSnippet('communicationError');
        } finally {
            $this->view->assign('redirectUrl', $redirectUrl);
        }
    }
}
