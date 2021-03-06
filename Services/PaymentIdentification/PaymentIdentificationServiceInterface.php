<?php

declare(strict_types=1);

namespace HeidelPayment\Services\PaymentIdentification;

interface PaymentIdentificationServiceInterface
{
    public function isHeidelpayPayment(array $payment): bool;

    public function isHeidelpayPaymentWithFrame(array $payment): bool;
}
