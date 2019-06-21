<?php

namespace HeidelPayment\Services\Heidelpay;

use HeidelPayment\Services\ConfigReaderServiceInterface;
use heidelpayPHP\Heidelpay;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;

class HeidelpayClientService implements HeidelpayClientServiceInterface
{
    /** @var ConfigReaderServiceInterface */
    private $configReaderService;

    /** @var null|ContextServiceInterface */
    private $contextService;

    public function __construct(ConfigReaderServiceInterface $configReaderService, ?ContextServiceInterface $contextService = null)
    {
        $this->configReaderService = $configReaderService;
        $this->contextService      = $contextService;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeidelpayClient(): Heidelpay
    {
        $locale = 'en_GB';

        if ($this->contextService !== null) {
            $locale = $this->contextService->getShopContext()->getShop()->getLocale()->getLocale();
        }

        return new Heidelpay($this->configReaderService->get('private_key'), $locale);
    }
}
