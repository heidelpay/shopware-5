<?php

declare(strict_types=1);

namespace HeidelPayment\Components\Hydrator\ResourceHydrator;

use heidelpayPHP\Heidelpay;
use heidelpayPHP\Resources\AbstractHeidelpayResource;

interface ResourceHydratorInterface
{
    /**
     * Will create a customer object from provided data inside the array
     */
    public function hydrateOrFetch(array $data, Heidelpay $heidelpayObj = null, string $resourceId = null): AbstractHeidelpayResource;
}
