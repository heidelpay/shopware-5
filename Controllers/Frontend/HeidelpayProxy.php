<?php

declare(strict_types=1);

use HeidelPayment\Installers\PaymentMethods;

class Shopware_Controllers_Frontend_HeidelpayProxy extends Shopware_Controllers_Frontend_Payment
{
    /**
     * Proxy action for redirect payments.
     * Forwards to the correct widget payment controller.
     */
    public function indexAction(): void
    {
        $paymentMethodName = $this->getPaymentShortName();

        if (array_key_exists($paymentMethodName, PaymentMethods::REDIRECT_CONTROLLER_MAPPING)) {
            $this->forward('createPayment', PaymentMethods::REDIRECT_CONTROLLER_MAPPING[$paymentMethodName], 'widgets');

            return;
        }

        if (array_key_exists($paymentMethodName, PaymentMethods::RECURRING_CONTROLLER_MAPPING)) {
            $this->forward('createPayment', PaymentMethods::RECURRING_CONTROLLER_MAPPING[$paymentMethodName], 'widgets');

            return;
        }

        $this->redirect([
            'controller' => 'checkout',
            'action'     => 'confirm',
        ]);
    }

    /**
     * Proxy action for the initial response after redirect (currently PayPal specific)
     */
    public function initialRecurringAction(): void
    {
        $this->forward(
            'recurringFinished',
            PaymentMethods::RECURRING_CONTROLLER_MAPPING[$this->getPaymentShortName()],
            'widgets'
        );
    }

    /**
     * Proxy action for recurring payments.
     * Forwards to the correct widget payment controller.
     */
    public function recurringAction(): void
    {
        $this->Front()->Plugins()->Json()->setRenderer();

        $orderId = (int) $this->request->getParam('orderId');

        if (!$orderId) {
            $this->container->get('heidel_payment.logger')->error(sprintf('No order id was given!', $orderId));

            return;
        }

        $paymentName = $this->getModelManager()->getDBALQueryBuilder()
            ->select('scp.name')
            ->from('s_core_paymentmeans', 'scp')
            ->innerJoin('scp', 's_order', 'so', 'so.paymentID = scp.id')
            ->where('so.id = :orderId')
            ->setParameter('orderId', $orderId)
            ->execute()->fetchColumn();

        if (!$paymentName || PaymentMethods::RECURRING_CONTROLLER_MAPPING[$paymentName] === null) {
            $this->container->get('heidel_payment.logger')->error(sprintf('No payment for order with id %s was found!', $orderId));
            $this->view->assign('success', false);

            return;
        }

        $this->forward('chargeRecurringPayment', PaymentMethods::RECURRING_CONTROLLER_MAPPING[$paymentName], 'widgets');
    }
}
