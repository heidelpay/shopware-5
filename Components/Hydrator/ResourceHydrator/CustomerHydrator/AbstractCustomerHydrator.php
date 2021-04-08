<?php

declare(strict_types=1);

namespace HeidelPayment\Components\Hydrator\ResourceHydrator\CustomerHydrator;

use Doctrine\DBAL\Connection;
use heidelpayPHP\Constants\Salutations;
use heidelpayPHP\Resources\EmbeddedResources\Address;

abstract class AbstractCustomerHydrator
{
    /*
     * This regex is used to remove any non whitespace, plus or number parts,
     * while also removing occurrences of more than one whitespace at a time.
     */
    public const PHONE_NUMBER_REGEX = '/([^0-9 +]|\s{2,})/';

    /** @var Connection */
    protected $connection;

    public function __construct(Connection $dbalConnection)
    {
        $this->connection = $dbalConnection;
    }

    protected function getHeidelpayAddress(array $shopwareAddress): Address
    {
        $result = new Address();
        $result->setName(sprintf('%s %s', $shopwareAddress['firstname'], $shopwareAddress['lastname']));
        $result->setStreet($shopwareAddress['street']);
        $result->setZip($shopwareAddress['zipcode']);
        $result->setCity($shopwareAddress['city']);
        $result->setState($shopwareAddress['stateId'] ? $this->getStateShortCode($shopwareAddress['stateId']) : '');
        $result->setCountry($this->getCountryIso($shopwareAddress['countryId']));

        return $result;
    }

    protected function getCountryIso(int $countryId): ?string
    {
        $countryIso = $this->connection->createQueryBuilder()
            ->select('countryiso')
            ->from('s_core_countries')
            ->where('id = :countryId')
            ->setParameter('countryId', $countryId)
            ->execute()->fetchColumn();

        return $countryIso ?: null;
    }

    protected function getStateShortCode(int $stateId): ?string
    {
        $countryIso = $this->connection->createQueryBuilder()
            ->select('shortcode')
            ->from('s_core_countries_states')
            ->where('id = :countryId')
            ->setParameter('countryId', $stateId)
            ->execute()->fetchColumn();

        return $countryIso ?: null;
    }

    protected function getSalutation(string $salutation): string
    {
        switch (strtolower($salutation)) {
            case 'ms':
            case 'mrs':
            case 'frau':
                return Salutations::MRS;
            case 'mr':
            case 'herr':
                return Salutations::MR;
            default:
                return Salutations::UNKNOWN;
        }
    }
}
