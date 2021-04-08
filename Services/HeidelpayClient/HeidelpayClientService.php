<?php

declare(strict_types=1);

namespace HeidelPayment\Services\HeidelpayClient;

use HeidelPayment\Components\HeidelpayDebugHandler;
use HeidelPayment\Services\ConfigReader\ConfigReaderServiceInterface;
use HeidelPayment\Services\HeidelpayApiLogger\HeidelpayApiLoggerServiceInterface;
use heidelpayPHP\Heidelpay;
use RuntimeException;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;

class HeidelpayClientService implements HeidelpayClientServiceInterface
{
    /** @var ConfigReaderServiceInterface */
    private $configReaderService;

    /** @var null|ContextServiceInterface */
    private $contextService;

    /** @var HeidelpayApiLoggerServiceInterface */
    private $apiLoggerService;

    public function __construct(ConfigReaderServiceInterface $configReaderService, ContextServiceInterface $contextService, HeidelpayApiLoggerServiceInterface $apiLoggerService)
    {
        $this->configReaderService = $configReaderService;
        $this->contextService      = $contextService;
        $this->apiLoggerService    = $apiLoggerService;
    }

    /**
     * {@inheritdoc}
     */
    public function getHeidelpayClient(): ?Heidelpay
    {
        $locale = 'en-GB';

        if ($this->contextService !== null) {
            $locale = $this->contextService->getShopContext()->getShop()->getLocale()->getLocale();

            if ($locale) {
                $locale = str_replace('_', '-', $locale);
            }
        }

        try {
            $heidelpay = new Heidelpay($this->getPrivateKey(), $locale);

            $heidelpay->setDebugMode((bool) $this->configReaderService->get('extended_logging'));
            $heidelpay->setDebugHandler((new HeidelpayDebugHandler($this->apiLoggerService->getPluginLogger())));

            return $heidelpay;
        } catch (RuntimeException $ex) {
            $this->apiLoggerService->getPluginLogger()->error(sprintf('Could not initialize Heidelpay client due to the following error: %s', $ex->getMessage()));
        }

        return null;
    }

    public function getPrivateKey(): string
    {
        $privateKey = $this->configReaderService->get('private_key');

        return $this->getApiKey($privateKey);
    }

    public function getPublicKey(): string
    {
        $publicKey = $this->configReaderService->get('public_key');

        return $this->getApiKey($publicKey);
    }

    private function getApiKey(string $key): string
    {
        if ($key === '') {
            return $key;
        }

        $transMode = $this->configReaderService->get('transaction_mode');

        $explodedKey = explode('-', $key);

        $explodedKey[0] = $transMode === 'live' ? 'p' : 's';

        return implode('-', $explodedKey);
    }
}
