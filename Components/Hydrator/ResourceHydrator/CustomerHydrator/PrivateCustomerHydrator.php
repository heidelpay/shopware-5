<?php

declare(strict_types=1);

namespace HeidelPayment\Components\Hydrator\ResourceHydrator\CustomerHydrator;

use HeidelPayment\Components\Hydrator\ResourceHydrator\ResourceHydratorInterface;
use heidelpayPHP\Exceptions\HeidelpayApiException;
use heidelpayPHP\Heidelpay;
use heidelpayPHP\Resources\AbstractHeidelpayResource;
use heidelpayPHP\Resources\Customer;

class PrivateCustomerHydrator extends AbstractCustomerHydrator implements ResourceHydratorInterface
{
    /**
     * {@inheritdoc}
     *
     * @return Customer
     */
    public function hydrateOrFetch(
        array $data,
        Heidelpay $heidelpayObj = null,
        string $resourceId = null
    ): AbstractHeidelpayResource {
        $result          = new Customer();
        $user            = $data['additional']['user'];
        $shippingAddress = $data['shippingaddress'];
        $billingAddress  = $data['billingaddress'];

        $phoneNumber = \preg_replace(self::PHONE_NUMBER_REGEX, '', $billingAddress['phone']);

        try {
            if ($heidelpayObj) {
                $result = $heidelpayObj->fetchCustomerByExtCustomerId($resourceId);
            }
        } catch (HeidelpayApiException $ex) {
            //Customer not found. No need to handle this exception here,
            //because it's being created below
        }

        $result->setCompany($billingAddress['company']);
        $result->setFirstname($billingAddress['firstname']);
        $result->setLastname($billingAddress['lastname']);
        $result->setBirthDate((string) $user['birthday']);
        $result->setEmail($user['email']);
        $result->setSalutation($this->getSalutation($billingAddress['salutation']));
        $result->setCustomerId($user['customernumber']);
        $result->setPhone($phoneNumber);

        $result->setBillingAddress($this->getHeidelpayAddress($billingAddress));
        $result->setShippingAddress($this->getHeidelpayAddress($shippingAddress));

        return $result;
    }
}
