<?php

declare(strict_types=1);

namespace HeidelPayment\Services\OrderStatus;

use Doctrine\DBAL\Connection;
use HeidelPayment\Components\DependencyInjection\Factory\StatusMapper\PaymentStatusMapperFactoryInterface;
use HeidelPayment\Components\PaymentStatusMapper\Exception\NoStatusMapperFoundException;
use HeidelPayment\Components\PaymentStatusMapper\Exception\StatusMapperException;
use HeidelPayment\Services\ConfigReader\ConfigReaderServiceInterface;
use HeidelPayment\Services\DependencyProvider\DependencyProviderServiceInterface;
use heidelpayPHP\Resources\Payment;
use Psr\Log\LoggerInterface;
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

    /** @var PaymentStatusMapperFactoryInterface */
    private $paymentStatusFactory;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        Connection $connection,
        DependencyProviderServiceInterface $dependencyProviderService,
        ConfigReaderServiceInterface $configReaderService,
        PaymentStatusMapperFactoryInterface $paymentStatusFactory,
        LoggerInterface $logger
    ) {
        $this->connection           = $connection;
        $this->orderModule          = $dependencyProviderService->getModule('order');
        $this->configReaderService  = $configReaderService;
        $this->paymentStatusFactory = $paymentStatusFactory;
        $this->logger               = $logger;
    }

    public function updatePaymentStatusByTransactionId(string $transactionId, int $statusId): void
    {
        if ($this->orderModule === null) {
            throw new RuntimeException('Unable to update the payment status since the order module is not available!');
        }

        $orderId = $this->connection->createQueryBuilder()
            ->select('id')
            ->from('s_order')
            ->where('transactionID = :transactionId')
            ->setParameter('transactionId', $transactionId)
            ->execute()
            ->fetchColumn();

        $this->orderModule->setPaymentStatus($orderId, $statusId, $this->configReaderService->get('automatic_payment_notification'), 'Heidelpay - Webhook');
    }

    public function updatePaymentStatusByPayment(Payment $payment): void
    {
        $transactionId = $payment->getOrderId();

        if (empty($transactionId)) {
            return;
        }

        try {
            $paymentStatusMapper = $this->paymentStatusFactory->getStatusMapper($payment->getPaymentType());

            $paymentStatusId = $paymentStatusMapper->getTargetPaymentStatus($payment);
        } catch (NoStatusMapperFoundException | StatusMapperException $ex) {
            $this->logger->error($ex->getMessage(), $ex->getTrace());

            return;
        }

        $this->updatePaymentStatusByTransactionId($transactionId, $paymentStatusId);
    }
}
