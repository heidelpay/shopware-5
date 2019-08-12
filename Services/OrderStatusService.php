<?php

namespace HeidelPayment\Services;

use Doctrine\DBAL\Connection;
use heidelpayPHP\Resources\Payment;
use RuntimeException;
use sOrder;

class OrderStatusService implements OrderStatusServiceInterface
{
    /** @var Connection */
    private $connection;

    /** @var sOrder */
    private $orderModule;

    /** @var ConfigReaderServiceInterface */
    private $configReaderService;

    /** @var PaymentStatusFactoryInterface */
    private $paymentStatusFactory;

    public function __construct(
        Connection $connection,
        DependencyProviderServiceInterface $dependencyProviderService,
        ConfigReaderServiceInterface $configReaderService,
        PaymentStatusFactoryInterface $paymentStatusFactory
    ) {
        $this->connection           = $connection;
        $this->orderModule          = $dependencyProviderService->getModule('order');
        $this->configReaderService  = $configReaderService;
        $this->paymentStatusFactory = $paymentStatusFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function updatePaymentStatusByTransactionId(string $transactionId, int $statusId): void
    {
        if ($this->orderModule === null) {
            throw new RuntimeException('Unable to update the payment status since the order module is not available!');
        }

        $queryBuilder = $this->connection->createQueryBuilder();
        $orderId      = $queryBuilder
            ->select('o.id')
            ->from('s_order', 'o')
            ->where('o.transactionID = :transactionId')
            ->setParameter('transactionId', $transactionId)
            ->execute()
            ->fetchColumn();

        $this->orderModule->setPaymentStatus($orderId, $statusId, $this->configReaderService->get('automatic_payment_notification'));
    }

    /**
     * {@inheritdoc}
     */
    public function updatePaymentStatusByPayment(Payment $payment): void
    {
        $transactionId   = $payment->getOrderId();
        $paymentStatusId = $this->paymentStatusFactory->getPaymentStatusId($payment);

        $this->updatePaymentStatusByTransactionId($transactionId, $paymentStatusId);
    }
}