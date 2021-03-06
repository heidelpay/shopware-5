<?php

declare(strict_types=1);

use HeidelPayment\Components\BookingMode;
use HeidelPayment\Components\PaymentHandler\Structs\PaymentDataStruct;
use HeidelPayment\Components\PaymentHandler\Traits\CanCharge;
use HeidelPayment\Controllers\AbstractHeidelpayPaymentController;
use HeidelPayment\Services\PaymentVault\Struct\VaultedDeviceStruct;
use heidelpayPHP\Exceptions\HeidelpayApiException;
use heidelpayPHP\Resources\PaymentTypes\SepaDirectDebitGuaranteed;

/**
 * @property PaymentDataStruct $paymentDataStruct
 * @property SepaDirectDebitGuaranteed $paymentType
 */
class Shopware_Controllers_Widgets_HeidelpaySepaDirectDebitGuaranteed extends AbstractHeidelpayPaymentController
{
    use CanCharge;

    /** @var bool */
    protected $isAsync = true;

    public function createPaymentAction(): void
    {
        $additionalRequestData = $this->request->get('additional', []);
        $isPaymentFromVault    = array_key_exists('isPaymentFromVault', $additionalRequestData) ? (bool) filter_var($additionalRequestData['isPaymentFromVault'], FILTER_VALIDATE_BOOLEAN) : false;
        $mandateAccepted       = array_key_exists('mandateAccepted', $additionalRequestData) ? (bool) filter_var($additionalRequestData['mandateAccepted'], FILTER_VALIDATE_BOOLEAN) : false;
        $userData              = $this->getUser();

        if ((!$mandateAccepted && !$isPaymentFromVault) || !$this->isValidData($userData)) {
            $this->view->assign([
                'success'     => false,
                'redirectUrl' => $this->getHeidelpayErrorUrlFromSnippet('communicationError'),
            ]);

            return;
        }

        try {
            parent::pay();
            $redirectUrl = $this->charge($this->paymentDataStruct->getReturnUrl());

            $this->saveToDeviceVault($userData);
        } catch (HeidelpayApiException $apiException) {
            $this->getApiLogger()->logException('Error while creating SEPA direct debit guaranteed payment', $apiException);
            $redirectUrl = $this->getHeidelpayErrorUrl($apiException->getClientMessage());
        } catch (RuntimeException $runtimeException) {
            $redirectUrl = $this->getHeidelpayErrorUrlFromSnippet('communicationError');
        } finally {
            $this->view->assign('redirectUrl', $redirectUrl);
        }
    }

    private function isValidData(array $userData): bool
    {
        if (empty($this->paymentType) || empty($this->paymentType->getIban())
            || !$userData['additional']['user']['id']
            || empty($userData['billingaddress']) || empty($userData['shippingaddress'])) {
            return false;
        }

        return true;
    }

    private function saveToDeviceVault(array $userData): void
    {
        $bookingMode = $this->container->get('heidel_payment.services.config_reader')->get('direct_debit_bookingmode');

        if ($bookingMode === BookingMode::CHARGE_REGISTER && !empty($this->paymentType)) {
            $deviceVault = $this->container->get('heidel_payment.services.payment_device_vault');

            if (!$deviceVault->hasVaultedSepaGuaranteedMandate((int) $userData['additional']['user']['id'], $this->paymentType->getIban(), $userData['billingaddress'], $userData['shippingaddress'])) {
                $heidelpayCustomer = $this->paymentDataStruct->getCustomer();
                $additionalData    = [];

                if ($heidelpayCustomer !== null) {
                    $additionalData['birthDate'] = $heidelpayCustomer->getBirthDate();
                }

                $deviceVault->saveDeviceToVault(
                    $this->paymentType,
                    VaultedDeviceStruct::DEVICE_TYPE_SEPA_MANDATE_GUARANTEED,
                    $userData['billingaddress'],
                    $userData['shippingaddress'],
                    $additionalData
                );
            }
        }
    }
}
