<?php

declare(strict_types=1);

namespace HeidelPayment\Components\Hydrator\ResourceHydrator;

use heidelpayPHP\Constants\BasketItemTypes;
use heidelpayPHP\Heidelpay;
use heidelpayPHP\Resources\AbstractHeidelpayResource;
use heidelpayPHP\Resources\Basket;
use heidelpayPHP\Resources\EmbeddedResources\BasketItem;
use Shopware\Components\Random;

class BasketHydrator implements ResourceHydratorInterface
{
    public const UNZER_DEFAULT_PRECISION = 4;

    private const SW_VOUCHER_MODE      = '2';
    private const SW_DISCOUNT          = '3';
    private const SW_ABO_DISCOUNT_MODE = '10';

    /**
     * {@inheritdoc}
     *
     * @return Basket
     */
    public function hydrateOrFetch(
        array $data,
        Heidelpay $heidelpayObj = null,
        string $resourceId = null
    ): AbstractHeidelpayResource {
        if ($resourceId !== null && $heidelpayObj !== null) {
            return $heidelpayObj->fetchBasket($resourceId);
        }

        $isAmountInNet    = isset($data['sAmountWithTax']);
        $isTaxFree        = $data['taxFree'];
        $amountTotalGross = $isAmountInNet && !$isTaxFree ? $data['sAmountWithTax'] : $data['sAmount'];

        $basket = new Basket(
            $this->generateOrderId(),
            round($amountTotalGross, self::UNZER_DEFAULT_PRECISION),
            $data['sCurrencyName']
        );

        $basket->setAmountTotalVat(round($data['sAmountTax'], self::UNZER_DEFAULT_PRECISION));

        $this->hydrateBasketItems($basket, $data['content'], $isAmountInNet);
        $this->hydrateDispatch($basket, $data);
        $this->hydrateDiscount($basket);

        return $basket;
    }

    protected function hydrateBasketItems(Basket $basket, array $lineItems, bool $isAmountInNet): void
    {
        foreach ($lineItems as $lineItem) {
            $basketItem = new BasketItem();
            $basketItem->setType($this->getBasketItemType($lineItem));
            $basketItem->setTitle($lineItem['articlename']);
            $basketItem->setQuantity((int) $lineItem['quantity']);

            $amountGross = round(abs(
                $isAmountInNet ? $lineItem['amountWithTax'] : $lineItem['amountNumeric']
            ), self::UNZER_DEFAULT_PRECISION);

            if ($this->isBasketItemVoucher($lineItem)) {
                $basketItem->setAmountDiscount($amountGross);
            } else {
                $amountPerUnit = $isAmountInNet
                    ? $amountGross / $lineItem['quantity']
                    : abs($lineItem['additional_details']['price_numeric']);

                if (!$amountPerUnit) {
                    $amountPerUnit = abs($lineItem['priceNumeric']);
                }

                $basketItem->setAmountPerUnit(round($amountPerUnit, self::UNZER_DEFAULT_PRECISION));
                $basketItem->setAmountGross($amountGross);
                $basketItem->setAmountNet(round(abs($lineItem['amountnetNumeric']), self::UNZER_DEFAULT_PRECISION));
                $basketItem->setAmountVat(round(abs(str_replace(',', '.', $lineItem['tax'])), self::UNZER_DEFAULT_PRECISION));
                $basketItem->setVat((float) $lineItem['tax_rate']);
            }

            if (array_key_exists('abo_attributes', $lineItem) && !empty($lineItem['abo_attributes'])
                && array_key_exists('isAboArticle', $lineItem['abo_attributes']) && !empty($lineItem['abo_attributes']['isAboArticle'])) {
                $basket->setSpecialParams(array_merge($basket->getSpecialParams(), ['isAbo' => true]));
                $basketItem->setSpecialParams([
                    'aboCommerce' => $lineItem['aboCommerce'],
                ]);
            }

            $basket->addBasketItem($basketItem);
        }
    }

    protected function hydrateDispatch(Basket $basket, array $data): void
    {
        if (!array_key_exists('sDispatch', $data) || empty($data['sDispatch'])) {
            return;
        }

        //Shipping cost line item
        $dispatchBasketItem = new BasketItem();
        $dispatchBasketItem->setType(BasketItemTypes::SHIPMENT);
        $dispatchBasketItem->setTitle($data['sDispatch']['name']);
        $dispatchBasketItem->setAmountGross(round($data['sShippingcostsWithTax'], self::UNZER_DEFAULT_PRECISION));
        $dispatchBasketItem->setAmountPerUnit(round($data['sShippingcostsWithTax'], self::UNZER_DEFAULT_PRECISION));
        $dispatchBasketItem->setAmountNet(round($data['sShippingcostsNet'], self::UNZER_DEFAULT_PRECISION));
        $dispatchBasketItem->setAmountVat(round($data['sShippingcostsWithTax'] - $data['sShippingcostsNet'], self::UNZER_DEFAULT_PRECISION));
        $dispatchBasketItem->setVat((float) $data['sShippingcostsTax']);
        $dispatchBasketItem->setQuantity(1);

        $basket->addBasketItem($dispatchBasketItem);
    }

    protected function hydrateDiscount(Basket $basket): void
    {
        $calculatedDiscount = 0;

        /** @var BasketItem $basketItem */
        foreach ($basket->getBasketItems() as $basketItem) {
            if ((int) round($basketItem->getAmountDiscount(), self::UNZER_DEFAULT_PRECISION) !== 0) {
                $calculatedDiscount += round($basketItem->getAmountDiscount(), self::UNZER_DEFAULT_PRECISION);
            }
        }

        $basket->setAmountTotalDiscount($calculatedDiscount);
    }

    private function generateOrderId(): string
    {
        return Random::getAlphanumericString(32);
    }

    private function getBasketItemType(array $lineItem): string
    {
        if ($lineItem['esd'] || $lineItem['esdarticle']) {
            return BasketItemTypes::DIGITAL;
        }

        if ($this->isBasketItemVoucher($lineItem)) {
            return BasketItemTypes::VOUCHER;
        }

        return BasketItemTypes::GOODS;
    }

    private function isBasketItemVoucher(array $lineItem): bool
    {
        return in_array($lineItem['modus'], [self::SW_ABO_DISCOUNT_MODE, self::SW_VOUCHER_MODE, self::SW_DISCOUNT], true)
            || !empty($lineItem['__s_order_basket_attributes_swag_promotion_id']);
    }
}
