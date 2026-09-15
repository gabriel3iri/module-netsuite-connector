<?php declare(strict_types=1);
/**
 * RocketWeb
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @category  RocketWeb
 * @package   MageOS_NetSuiteConnector
 * @copyright Copyright (c) 2026 RocketWeb (http://rocketweb.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 * @author    Rocket Web Inc.
 *
 */

namespace MageOS\NetSuiteConnector\CustomerImport\Model\Mapper\Customer;

use Magento\Customer\Api\Data\AddressInterface;
use NetSuite\Classes\Address as NetSuiteAddress;
use NetSuite\Classes\Customer as NetSuiteCustomer;
use NetSuite\Classes\CustomerAddressbook;
use MageOS\NetSuiteConnector\Core\Exception\DataIntegrityException;

/**
 * Class CustomerAddress - mapper for Importing Customers' Address
 */
class Address
{
    private const NETSUITE_ID_ATTRIBUTE_CODE = 'netsuite_internal_id';

    private \MageOS\NetSuiteConnector\CustomerImport\Model\Config\CustomerImportConfig $customerImportConfig;
    private \MageOS\NetSuiteConnector\Core\Model\Logger\Logger $logger;
    private \MageOS\NetSuiteConnector\CustomerImport\Model\Mapper\Customer\Address\Map $addressMap;
    private \Magento\Customer\Api\Data\AddressInterfaceFactory $addressFactory;

    /**
     * Address constructor.
     * @param \Magento\Customer\Api\Data\AddressInterfaceFactory $addressFactory
     * @param \MageOS\NetSuiteConnector\Core\Model\Logger\Logger $logger
     * @param \MageOS\NetSuiteConnector\CustomerImport\Model\Config\CustomerImportConfig $customerImportConfig
     * @param Address\Map $addressMap
     */
    public function __construct(
        \Magento\Customer\Api\Data\AddressInterfaceFactory $addressFactory,
        \MageOS\NetSuiteConnector\Core\Model\Logger\Logger $logger,
        \MageOS\NetSuiteConnector\CustomerImport\Model\Config\CustomerImportConfig $customerImportConfig,
        \MageOS\NetSuiteConnector\CustomerImport\Model\Mapper\Customer\Address\Map $addressMap
    ) {
        $this->customerImportConfig = $customerImportConfig;
        $this->logger = $logger;
        $this->addressMap = $addressMap;
        $this->addressFactory = $addressFactory;
    }

    /**
     * An existing address that the address book does not match is carried through, never removed,
     * because CustomerRepository::save() deletes every address that the saved array leaves out.
     *
     * @param AddressInterface[] $existingAddresses
     * @return AddressInterface[]
     */
    public function getMagentoFormat(NetSuiteCustomer $nsCustomer, array $existingAddresses = []): array
    {
        if (empty($nsCustomer->addressbookList) || empty($nsAddressBooks = $nsCustomer->addressbookList->addressbook)) {
            return array_values($existingAddresses);
        }

        $unmatchedExisting = $existingAddresses;
        $result = [];

        foreach ($nsAddressBooks as $nsAddressBook) {
            $nsAddress = $nsAddressBook->addressbookAddress;

            try {
                $this->validate($nsAddress);
            } catch (DataIntegrityException $e) {
                $this->logger->debug(sprintf(
                    'Skipping address import (ID: %s) for NS Customer ID: %s (Fields: %s) ',
                    $nsAddress->internalId,
                    $nsCustomer->internalId,
                    implode(', ', $e->getMessage())
                ));

                // Skip to next AddressBook as this one doesn't have all the fields Required for the import
                continue;
            }

            $candidateAddress = $this->buildCandidateAddress($nsCustomer, $nsAddressBook, $nsAddress);

            $matchedAddress = $this->findMatchingAddress($candidateAddress, $nsAddress, $unmatchedExisting);
            if ($matchedAddress === null) {
                $result[] = $candidateAddress;
                continue;
            }

            $unmatchedExisting = array_filter(
                $unmatchedExisting,
                static fn (AddressInterface $address): bool => $address !== $matchedAddress
            );
            $result[] = $this->applyCandidateData($matchedAddress, $candidateAddress);
        }

        foreach ($unmatchedExisting as $leftoverAddress) {
            $result[] = $leftoverAddress;
        }

        return $result;
    }

    private function buildCandidateAddress(
        NetSuiteCustomer $nsCustomer,
        CustomerAddressbook $nsAddressBook,
        NetSuiteAddress $nsAddress
    ): AddressInterface {
        $magentoAddress = $this->addressFactory->create();

        //main information about default address role
        $magentoAddress->setIsDefaultShipping($nsAddressBook->defaultShipping);
        $magentoAddress->setIsDefaultBilling($nsAddressBook->defaultBilling);

        //personal information
        $magentoAddress->setLastname($nsCustomer->lastName);
        $magentoAddress->setFirstname($nsCustomer->firstName);
        $magentoAddress->setMiddlename($nsCustomer->middleName);

        $magentoAddress->setCustomAttribute(self::NETSUITE_ID_ATTRIBUTE_CODE, $nsAddress->internalId);

        $this->addressMap->mapNetSuiteToMagento($nsAddress, $magentoAddress);

        return $magentoAddress;
    }

    /**
     * @param AddressInterface[] $existingAddresses
     */
    private function findMatchingAddress(
        AddressInterface $candidateAddress,
        NetSuiteAddress $nsAddress,
        array $existingAddresses
    ): ?AddressInterface {
        $incomingId = $this->normalizeId($nsAddress->internalId);
        if ($incomingId !== null) {
            foreach ($existingAddresses as $existingAddress) {
                if ($incomingId === $this->normalizeId($this->getStoredNetSuiteId($existingAddress))) {
                    return $existingAddress;
                }
            }
        }

        foreach ($existingAddresses as $existingAddress) {
            if ($this->addressDataMatches($candidateAddress, $existingAddress)) {
                return $existingAddress;
            }
        }

        return null;
    }

    private function applyCandidateData(AddressInterface $target, AddressInterface $candidate): AddressInterface
    {
        $target->setFirstname($candidate->getFirstname());
        $target->setMiddlename($candidate->getMiddlename());
        $target->setLastname($candidate->getLastname());
        $target->setIsDefaultShipping($candidate->isDefaultShipping());
        $target->setIsDefaultBilling($candidate->isDefaultBilling());
        $target->setCountryId($candidate->getCountryId());
        $target->setRegion($candidate->getRegion());
        $target->setRegionId($candidate->getRegionId());
        $target->setCity($candidate->getCity());
        $target->setPostcode($candidate->getPostcode());
        $target->setStreet((array)$candidate->getStreet());
        $target->setTelephone($candidate->getTelephone());

        $netsuiteId = $this->normalizeId($this->getStoredNetSuiteId($candidate));
        if ($netsuiteId !== null) {
            $target->setCustomAttribute(self::NETSUITE_ID_ATTRIBUTE_CODE, $netsuiteId);
        }

        return $target;
    }

    private function addressDataMatches(AddressInterface $incoming, AddressInterface $existing): bool
    {
        $identityMatches = $this->normalizeStreet($incoming) === $this->normalizeStreet($existing)
            && $this->normalizeValue($incoming->getCity()) === $this->normalizeValue($existing->getCity())
            && $this->normalizeRegion($incoming) === $this->normalizeRegion($existing)
            && $this->normalizeValue($incoming->getPostcode()) === $this->normalizeValue($existing->getPostcode())
            && $this->normalizeValue($incoming->getCountryId()) === $this->normalizeValue($existing->getCountryId());
        if (!$identityMatches) {
            return false;
        }

        return $this->optionalValueMatches($incoming->getCompany(), $existing->getCompany())
            && $this->optionalValueMatches($incoming->getTelephone(), $existing->getTelephone())
            && $this->optionalValueMatches($incoming->getFirstname(), $existing->getFirstname())
            && $this->optionalValueMatches($incoming->getLastname(), $existing->getLastname());
    }

    /**
     * NetSuite does not map every Magento address field, so an empty incoming value never blocks a match.
     */
    private function optionalValueMatches(mixed $incoming, mixed $existing): bool
    {
        $incoming = $this->normalizeValue($incoming);

        return $incoming === '' || $incoming === $this->normalizeValue($existing);
    }

    private function getStoredNetSuiteId(AddressInterface $address): ?string
    {
        $attribute = $address->getCustomAttribute(self::NETSUITE_ID_ATTRIBUTE_CODE);
        return $attribute ? (string)$attribute->getValue() : null;
    }

    private function normalizeId(mixed $value): ?string
    {
        $value = trim((string)$value);
        return $value === '' ? null : $value;
    }

    private function normalizeValue(mixed $value): string
    {
        return strtolower(trim((string)$value));
    }

    private function normalizeStreet(AddressInterface $address): string
    {
        $lines = array_map(
            fn ($line): string => $this->normalizeValue($line),
            (array)$address->getStreet()
        );
        return implode('|', $lines);
    }

    private function normalizeRegion(AddressInterface $address): string
    {
        $region = $address->getRegion();
        if ($region === null) {
            return '';
        }
        return $this->normalizeValue($region->getRegionCode() ?: $region->getRegion());
    }

    /**
     * @param NetSuiteAddress $nsAddress
     * @throws DataIntegrityException
     */
    private function validate(NetSuiteAddress $nsAddress):void
    {
        $requiredAddressFields = (array)$this->customerImportConfig->getRequiredAddressFields();

        $missingFields = [];
        foreach ($requiredAddressFields as $requiredAddressField) {
            if (isset($nsAddress->$requiredAddressField) && $nsAddress->$requiredAddressField === null) {
                $missingFields[] = $requiredAddressField;
            }
        }

        if (!empty($missingFields)) {
            throw new DataIntegrityException(implode(', ', $missingFields));
        }
    }
}
