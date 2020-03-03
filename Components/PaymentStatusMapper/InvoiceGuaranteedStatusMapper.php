<?php

declare(strict_types=1);

namespace HeidelPayment\Components\PaymentStatusMapper;

use HeidelPayment\Components\PaymentStatusMapper\Exception\StatusMapperException;
use heidelpayPHP\Resources\Payment;
use heidelpayPHP\Resources\PaymentTypes\BasePaymentType;
use heidelpayPHP\Resources\PaymentTypes\InvoiceGuaranteed;

class InvoiceGuaranteedStatusMapper extends AbstractStatusMapper implements StatusMapperInterface
{
    public function supports(BasePaymentType $paymentType): bool
    {
        return $paymentType instanceof InvoiceGuaranteed;
    }

    public function getTargetPaymentStatus(Payment $paymentObject): int
    {
        if ($paymentObject->isCanceled()) {
            throw new StatusMapperException(InvoiceGuaranteed::getResourceName());
        }

        return $this->mapPaymentStatus($paymentObject);
    }
}