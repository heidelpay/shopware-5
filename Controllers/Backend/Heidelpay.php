<?php

use HeidelPayment\Services\HeidelpayApiLoggerServiceInterface;
use heidelpayPHP\Exceptions\HeidelpayApiException;
use heidelpayPHP\Heidelpay;
use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Order\Order;
use Shopware\Models\Shop\Shop;

class Shopware_Controllers_Backend_Heidelpay extends Shopware_Controllers_Backend_Application implements CSRFWhitelistAware
{
    private const WHITELISTED_CSRF_ACTIONS = [
        'registerWebhooks',
    ];

    /**
     * {@inheritdoc}
     */
    protected $model = Order::class;

    /**
     * {@inheritdoc}
     */
    protected $alias = 'sOrder';

    /** @var Heidelpay */
    private $heidelpayClient;

    /**
     * {@inheritdoc}
     */
    public function preDispatch()
    {
        $modelManager = $this->container->get('models');
        $shopId       = $this->request->get('shopId');

        /** @var Shop $shop */
        $shop = null;

        if (!$shopId) {
            $shop = $modelManager->getRepository(Shop::class)->getActiveDefault();
        } else {
            $shop = $this->container->get('models')->find(Shop::class, $shopId);
        }

        if ($shop === null) {
            throw new RuntimeException('Could not determine shop context');
        }

        $locale       = $this->container->get('Locale')->toString();
        $configReader = $this->container->get('shopware.plugin.cached_config_reader');

        $pluginConfig = $configReader->getByPluginName(
            $this->container->getParameter('heidel_payment.plugin_name'),
            $shop
        );

        $this->heidelpayClient = new Heidelpay($pluginConfig['private_key'], $locale);

        $this->Front()->Plugins()->Json()->setRenderer();
    }

    public function paymentDetailsAction(): void
    {
        $transactionId = $this->Request()->get('transactionId');
        $arrayHydrator = $this->container->get('heidel_payment.array_hydrator.payment');

        try {
            $result = $this->heidelpayClient->fetchPaymentByOrderId($transactionId);
            $data   = $arrayHydrator->hydrateArray($result);

            $this->view->assign([
                'success' => true,
                'data'    => $data,
            ]);

            $this->getApiLogger()->logResponse(sprintf('Requested payment details for order-id [%s]', $transactionId), $result);
        } catch (HeidelpayApiException $apiException) {
            $this->view->assign([
                'success' => false,
                'message' => $apiException->getClientMessage(),
            ]);

            $this->getApiLogger()->logException(sprintf('Error while requesting payment details for order-id [%s]', $transactionId), $apiException);
        }
    }

    public function chargeAction(): void
    {
        $paymentId = $this->request->get('paymentId');
        $amount    = $this->request->get('amount');

        try {
            $result = $this->heidelpayClient->chargeAuthorization($paymentId, $amount);

            $this->view->assign([
                'success' => true,
                'data'    => $result->expose(),
                'message' => $result->getMessage(),
            ]);

            $this->getApiLogger()->logResponse(sprintf('Charged payment with id [%s] with an amount of [%s]', $paymentId, $amount), $result);
        } catch (HeidelpayApiException $apiException) {
            $this->view->assign([
                'success' => false,
                'message' => $apiException->getClientMessage(),
            ]);

            $this->getApiLogger()->logException(sprintf('Error while charging payment with id [%s] with an amount of [%s]', $paymentId, $amount), $apiException);
        }
    }

    public function refundAction(): void
    {
        $paymentId = $this->request->get('paymentId');
        $amount    = $this->request->get('amount');
        $chargeId  = $this->request->get('chargeId');

        try {
            $result = $this->heidelpayClient->cancelChargeById($paymentId, $chargeId, $amount);

            $this->view->assign([
                'success' => true,
                'data'    => $result->expose(),
                'message' => $result->getMessage(),
            ]);

            $this->getApiLogger()->logResponse(sprintf('Refunded charge with id [%s] (Payment-Id: [%s]) with an amount of [%s]', $chargeId, $paymentId, $amount), $result);
        } catch (HeidelpayApiException $apiException) {
            $this->view->assign([
                'success' => false,
                'message' => $apiException->getClientMessage(),
            ]);

            $this->getApiLogger()->logException(sprintf('Error while refunding the charge with id [%s] (Payment-Id: [%s]) with an amount of [%s]', $chargeId, $paymentId, $amount), $apiException);
        }
    }

    public function finalizeAction(): void
    {
        $paymentId = $this->request->get('paymentId');

        try {
            $result = $this->heidelpayClient->ship($paymentId);

            $this->view->assign([
                'success' => true,
                'data'    => $result->expose(),
                'message' => $result->getMessage(),
            ]);

            $this->getApiLogger()->logResponse(sprintf('Sent shipping notification for the payment-id [%s]', $paymentId), $result);
        } catch (HeidelpayApiException $apiException) {
            $this->view->assign([
                'success' => false,
                'message' => $apiException->getClientMessage(),
            ]);

            $this->getApiLogger()->logException(sprintf('Error while sending shipping notification for the payment-id [%s]', $paymentId), $apiException);
        }
    }

    public function registerWebhooksAction(): void
    {
        $url = $this->container->get('router')->assemble([
            'controller' => 'heidelpay',
            'action'     => 'executeWebhook',
            'module'     => 'frontend',
        ]);

        try {
            $this->heidelpayClient->deleteAllWebhooks();

            $result = $this->heidelpayClient->createWebhook($url, 'all');

            echo sprintf('The webhook [%s] has been registered to the following URL: %s', $result->getEvent(), $result->getUrl());

            $this->getApiLogger()->logResponse(sprintf('Registered webhooks [%s] to [%s]', $result->getEvent(), $url), $result);
        } catch (HeidelpayApiException $apiException) {
            echo $apiException->getMerchantMessage();

            $this->getApiLogger()->logException(sprintf('Error while registering the webhooks to [%s]', $url), $apiException);
        }
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
}
