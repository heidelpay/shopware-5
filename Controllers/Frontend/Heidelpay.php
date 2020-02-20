<?php

declare(strict_types=1);

use HeidelPayment\Installers\PaymentMethods;
use HeidelPayment\Services\Heidelpay\Webhooks\Handlers\WebhookHandlerInterface;
use HeidelPayment\Services\Heidelpay\Webhooks\Struct\WebhookStruct;
use HeidelPayment\Services\Heidelpay\Webhooks\WebhookSecurityException;
use HeidelPayment\Services\HeidelpayApiLoggerServiceInterface;
use heidelpayPHP\Exceptions\HeidelpayApiException;
use heidelpayPHP\Resources\Payment;
use heidelpayPHP\Resources\TransactionTypes\Authorization;
use Shopware\Components\CSRFWhitelistAware;

class Shopware_Controllers_Frontend_Heidelpay extends Shopware_Controllers_Frontend_Payment implements CSRFWhitelistAware
{
    /**
     * Stores a list of all redirect payment methods which should be handled in this controller.
     */
    public const PAYMENT_CONTROLLER_MAPPING = [
        PaymentMethods::PAYMENT_NAME_ALIPAY        => 'HeidelpayAlipay',
        PaymentMethods::PAYMENT_NAME_FLEXIPAY      => 'HeidelpayFlexipayDirect',
        PaymentMethods::PAYMENT_NAME_GIROPAY       => 'HeidelpayGiropay',
        PaymentMethods::PAYMENT_NAME_HIRE_PURCHASE => 'HeidelpayHirePurchase',
        PaymentMethods::PAYMENT_NAME_INVOICE       => 'HeidelpayInvoice',
        PaymentMethods::PAYMENT_NAME_PAYPAL        => 'HeidelpayPaypal',
        PaymentMethods::PAYMENT_NAME_PRE_PAYMENT   => 'HeidelpayPrepayment',
        PaymentMethods::PAYMENT_NAME_PRZELEWY      => 'HeidelpayPrzelewy',
        PaymentMethods::PAYMENT_NAME_WE_CHAT       => 'HeidelpayWeChat',
        PaymentMethods::PAYMENT_NAME_SOFORT        => 'HeidelpaySofort',
    ];

    private const WHITELISTED_CSRF_ACTIONS = [
        'executeWebhook',
    ];

    /**
     * Proxy action for redirect payments.
     * Forwards to the correct widget payment controller.
     */
    public function proxyAction()
    {
        $paymentMethodName = $this->getPaymentShortName();

        if (!array_key_exists($paymentMethodName, self::PAYMENT_CONTROLLER_MAPPING)) {
            $this->redirect([
                'controller' => 'checkout',
                'action'     => 'confirm',
            ]);
        }

        $this->forward('createPayment', self::PAYMENT_CONTROLLER_MAPPING[$paymentMethodName], 'widgets');
    }

    public function completePaymentAction()
    {
        $session   = $this->container->get('session');
        $paymentId = $session->offsetGet('heidelPaymentId');

        if (!$paymentId) {
            $this->getApiLogger()->getPluginLogger()->error(sprintf('There is no payment-id [%s]', $paymentId));

            $this->redirect([
                'controller' => 'checkout',
                'action'     => 'confirm',
            ]);

            return;
        }

        $paymentStateFactory = $this->container->get('heidel_payment.services.payment_status_factory');

        try {
            $heidelpayClient = $this->container->get('heidel_payment.services.api_client')->getHeidelpayClient();

            $paymentObject = $heidelpayClient->fetchPayment($paymentId);
        } catch (HeidelpayApiException $apiException) {
            $this->getApiLogger()->logException(sprintf('Error while receiving payment details on finish page for payment-id [%s]', $paymentId), $apiException);

            $this->redirect([
                'controller' => 'checkout',
                'action'     => 'confirm',
            ]);

            return;
        } catch (RuntimeException $ex) {
            $this->getApiLogger()->getPluginLogger()->error(sprintf('Error while receiving payment details on finish page for payment-id [%s]', $paymentId), $ex->getTrace);

            $this->redirect([
                'controller' => 'checkout',
                'action'     => 'confirm',
            ]);

            return;
        }

        $errorMessage = $this->container->get('heidel_payment.services.payment_validator')
            ->validatePaymentObject($paymentObject, $this->getPaymentShortName());

        if (!empty($errorMessage)) {
            $this->redirectToErrorPage($errorMessage);

            return;
        }

        $basketSignatureHeidelpay = $paymentObject->getMetadata()->getMetadata('basketSignature');
        $this->loadBasketFromSignature($basketSignatureHeidelpay);

        $this->saveOrder($paymentObject->getOrderId(), $paymentObject->getId(), $paymentStateFactory->getPaymentStatusId($paymentObject));

        // Done, redirect to the finish page
        $this->redirect([
            'module'     => 'frontend',
            'controller' => 'checkout',
            'action'     => 'finish',
        ]);
    }

    public function executeWebhookAction()
    {
        $webhookStruct = new WebhookStruct($this->request->getRawBody());

        $webhookHandlerFactory  = $this->container->get('heidel_payment.webhooks.factory');
        $heidelpayClientService = $this->container->get('heidel_payment.services.api_client');
        $handlers               = $webhookHandlerFactory->getWebhookHandlers($webhookStruct->getEvent());

        /** @var WebhookHandlerInterface $webhookHandler */
        foreach ($handlers as $webhookHandler) {
            if ($webhookStruct->getPublicKey() !== $heidelpayClientService->getPublicKey()) {
                throw new WebhookSecurityException();
            }

            $webhookHandler->execute($webhookStruct);
        }

        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
        $this->Response()->setHttpResponseCode(200);
    }

    public function getCustomerDataAction()
    {
        $this->Front()->Plugins()->Json()->setRenderer();

        $session                  = $this->container->get('session');
        $userData                 = $session->offsetGet('sOrderVariables')['sUserData'];
        $customerHydrationService = $this->container->get('heidel_payment.resource_hydrator.business_customer');

        if (!empty($userData)) {
            $heidelpayCustomer = $customerHydrationService->hydrateOrFetch($userData);
        }

        $this->view->assign([
            'success'  => isset($heidelpayCustomer),
            'customer' => $heidelpayCustomer->expose(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getWhitelistedCSRFActions(): array
    {
        return self::WHITELISTED_CSRF_ACTIONS;
    }

    protected function getApiLogger(): HeidelpayApiLoggerServiceInterface
    {
        return $this->container->get('heidel_payment.services.api_logger');
    }

    private function redirectToErrorPage(string $message)
    {
        $this->redirect([
            'controller'       => 'checkout',
            'action'           => 'shippingPayment',
            'heidelpayMessage' => base64_encode($message),
        ]);
    }

    private function getMessageFromPaymentTransaction(Payment $payment): string
    {
        // Check the result message of the transaction to find out what went wrong.
        $transaction = $payment->getAuthorization();

        if ($transaction instanceof Authorization) {
            return $transaction->getMessage()->getCustomer();
        }

        $transaction = $payment->getChargeByIndex(0);

        return $transaction->getMessage()->getCustomer();
    }
}
