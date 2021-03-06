<?php

declare(strict_types=1);

namespace HeidelPayment\Components\PaymentStatusMapper\Exception;

use HeidelPayment\Components\AbstractHeidelPaymentException;

class StatusMapperException extends AbstractHeidelPaymentException
{
    public function __construct(string $paymentName)
    {
        parent::__construct(sprintf('Payment status is not allowed for payment method: %s', $paymentName));
    }
}
