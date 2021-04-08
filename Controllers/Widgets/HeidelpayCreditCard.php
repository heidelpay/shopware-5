<?php

declare(strict_types=1);

use HeidelPayment\Components\BookingMode;
use HeidelPayment\Components\PaymentHandler\Traits\CanAuthorize;
use HeidelPayment\Components\PaymentHandler\Traits\CanCharge;
use HeidelPayment\Components\PaymentHandler\Traits\CanRecur;
use HeidelPayment\Controllers\AbstractHeidelpayPaymentController;
use HeidelPayment\Services\PaymentVault\Struct\VaultedDeviceStruct;
use heidelpayPHP\Exceptions\HeidelpayApiException;

class Shopware_Controllers_Widgets_HeidelpayCreditCard extends AbstractHeidelpayPaymentController
{
    use CanAuthorize;
    use CanCharge;
    use CanRecur;

    /** @var bool */
    protected $isAsync = true;

    public function createPaymentAction(): void
    {
        parent::pay();

        if (!$this->paymentType && $this->paymentDataStruct->isRecurring()) {
            $activateRecurring = false;

            try {
                $activateRecurring = $this->handleRecurringPayment();
            } catch (HeidelpayApiException $apiException) {
                if ((string) $apiException->getCode() === AbstractHeidelpayPaymentController::ALREADY_RECURRING_ERROR_CODE) {
                    $activateRecurring = true;
                }
            }

            if (!$activateRecurring) {
                $this->view->assign('redirectUrl',
                    $this->getHeidelpayErrorUrlFromSnippet('recurringError')
                );

                return;
            }

            if ($this->recurring->getRedirectUrl() !== null) {
                $this->view->assign('redirectUrl', $this->recurring->getRedirectUrl());

                return;
            }
        }

        if ($this->paymentType && $this->paymentType->isRecurring()) {
            $this->recurringFinishedAction();
        } else {
            $this->handleNormalPayment();
        }
    }

    public function chargeRecurringPaymentAction(): void
    {
        parent::recurring();

        if (!$this->paymentDataStruct || empty($this->paymentDataStruct)) {
            $this->getApiLogger()->getPluginLogger()->error('The payment data struct could not be created');
            $this->view->assign('success', false);

            return;
        }

        try {
            $this->charge($this->paymentDataStruct->getReturnUrl());
            $orderNumber = $this->createRecurringOrder();
        } catch (HeidelpayApiException $ex) {
            $this->getApiLogger()->logException($ex->getMessage(), $ex);
        } finally {
            $this->view->assign([
                'success' => isset($orderNumber) && !empty($orderNumber),
                'data'    => [
                    'orderNumber' => $orderNumber ?: '',
                ],
            ]);
        }
    }

    protected function recurringFinishedAction(): void
    {
        try {
            parent::pay();

            if (!$this->paymentType) {
                $session           = $this->container->get('session');
                $paymentTypeId     = $session->offsetGet('PaymentTypeId');
                $this->paymentType = $this->heidelpayClient->fetchPaymentType($paymentTypeId);
            }

            if (!$this->paymentType->isRecurring()) {
                $this->getApiLogger()->getPluginLogger()->warning('Recurring could not be activated for basket', [$this->paymentDataStruct->getBasket()->jsonSerialize()]);
                $redirectUrl = $this->getHeidelpayErrorUrlFromSnippet('recurringError');
            }

            $bookingMode = $this->container->get('heidel_payment.services.config_reader')->get('credit_card_bookingmode');

            if (in_array($bookingMode, [BookingMode::CHARGE, BookingMode::CHARGE_REGISTER])) {
                $redirectUrl = $this->charge($this->paymentDataStruct->getReturnUrl());
            } elseif (in_array($bookingMode, [BookingMode::AUTHORIZE, BookingMode::AUTHORIZE_REGISTER])) {
                $redirectUrl = $this->authorize($this->paymentDataStruct->getReturnUrl());
            }

            $this->saveToDeviceVault($bookingMode);
        } catch (HeidelpayApiException $ex) {
            $this->getApiLogger()->logException('Error while creating CreditCard recurring payment', $ex);
            $redirectUrl = $this->getHeidelpayErrorUrl($ex->getClientMessage());
        } catch (RuntimeException $ex) {
            $redirectUrl = $this->getHeidelpayErrorUrlFromSnippet('communicationError');
        } finally {
            if (!$redirectUrl) {
                $this->getApiLogger()->getPluginLogger()->warning('CreditCard is not chargeable for basket', [$this->paymentDataStruct->getBasket()->jsonSerialize()]);

                $redirectUrl = $this->getHeidelpayErrorUrlFromSnippet('communicationError');
            }

            $this->view->assign('redirectUrl', $redirectUrl);
        }
    }

    private function handleNormalPayment(): void
    {
        $bookingMode = $this->container->get('heidel_payment.services.config_reader')->get('credit_card_bookingmode');

        try {
            if ($bookingMode === BookingMode::CHARGE || $bookingMode === BookingMode::CHARGE_REGISTER) {
                $redirectUrl = $this->charge($this->paymentDataStruct->getReturnUrl());
            } else {
                $redirectUrl = $this->authorize($this->paymentDataStruct->getReturnUrl());
            }

            $this->saveToDeviceVault($bookingMode);
        } catch (HeidelpayApiException $apiException) {
            $this->getApiLogger()->logException('Error while creating credit card payment', $apiException);
            $redirectUrl = $this->getHeidelpayErrorUrl($apiException->getClientMessage());
        } catch (RuntimeException $runtimeException) {
            $this->getApiLogger()->getPluginLogger()->error('Error while fetching payment', $runtimeException->getTrace());
            $redirectUrl = $this->getHeidelpayErrorUrlFromSnippet('communicationError');
        } finally {
            $this->view->assign('redirectUrl', $redirectUrl);
        }
    }

    private function handleRecurringPayment(): bool
    {
        $this->activateRecurring($this->paymentDataStruct->getReturnUrl());

        if (!$this->recurring) {
            $this->getApiLogger()->getPluginLogger()->warning(
                'Recurring could not be activated for basket',
                [$this->paymentDataStruct->getBasket()->jsonSerialize()]
            );

            return false;
        }

        return true;
    }

    private function saveToDeviceVault(string $bookingMode): void
    {
        $typeId = $this->request->get('typeId');

        if (($bookingMode === BookingMode::CHARGE_REGISTER || $bookingMode === BookingMode::AUTHORIZE_REGISTER) && $typeId === null) {
            $deviceVault = $this->container->get('heidel_payment.services.payment_device_vault');
            $userData    = $this->getUser();

            $deviceVault->saveDeviceToVault($this->paymentType, VaultedDeviceStruct::DEVICE_TYPE_CARD, $userData['billingaddress'], $userData['shippingaddress']);
        }
    }
}
