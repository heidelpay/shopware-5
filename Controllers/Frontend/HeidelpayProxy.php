<?php

declare(strict_types=1);

use HeidelPayment\Controllers\AbstractHeidelpayPaymentController;
use HeidelPayment\Installers\PaymentMethods;

class Shopware_Controllers_Frontend_HeidelpayProxy extends AbstractHeidelpayPaymentController
{
    /**
     * Proxy action for redirect payments.
     * Forwards to the correct widget payment controller.
     */
    public function indexAction()
    {
        $paymentMethodName = $this->getPaymentShortName();

        if (!array_key_exists($paymentMethodName, PaymentMethods::REDIRECT_CONTROLLER_MAPPING)) {
            $this->redirect([
                'controller' => 'checkout',
                'action'     => 'confirm',
            ]);
        }

        $this->forward('createPayment', PaymentMethods::REDIRECT_CONTROLLER_MAPPING[$paymentMethodName], 'widgets');
    }

    public function initialRecurringPaypalAction()
    {
        $this->forward(
            'paypalFinished',
            PaymentMethods::REDIRECT_CONTROLLER_MAPPING[PaymentMethods::PAYMENT_NAME_PAYPAL],
            'widgets'
        );
    }

    public function recurringAction()
    {
        $orderId = (int) $this->request->getParam('orderId');

        if (!$orderId) {
//            TODO: handle error
        }

        $paymentName = $this->getModelManager()->getDBALQueryBuilder()
            ->select('scp.name')
            ->from('s_core_paymentmeans', 'scp')
            ->innerJoin('scp', 's_order', 'so', 'so.paymentID = scp.id')
            ->where('so.id = :orderId')
            ->setParameter('orderId', $orderId)
            ->execute()->fetchColumn();

        if (!$paymentName) {
//            TODO: handle error
        }

        $this->forward('createRecurringPayment', PaymentMethods::REDIRECT_CONTROLLER_MAPPING[$paymentName], 'widgets');
    }
}
