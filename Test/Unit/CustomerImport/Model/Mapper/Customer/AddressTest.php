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
 */

namespace MageOS\NetSuiteConnector\Test\Unit\CustomerImport\Model\Mapper\Customer;

use Magento\Customer\Api\Data\AddressExtensionInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\RegionInterface;
use Magento\Framework\Api\AttributeInterface;
use Magento\Framework\Api\AttributeValue;
use MageOS\NetSuiteConnector\Core\Model\Logger\Logger;
use MageOS\NetSuiteConnector\CustomerImport\Model\Config\CustomerImportConfig;
use MageOS\NetSuiteConnector\CustomerImport\Model\Mapper\Customer\Address;
use MageOS\NetSuiteConnector\CustomerImport\Model\Mapper\Customer\Address\Map;
use NetSuite\Classes\Address as NetSuiteAddress;
use NetSuite\Classes\Customer as NetSuiteCustomer;
use NetSuite\Classes\CustomerAddressbook;
use NetSuite\Classes\CustomerAddressbookList;
use PHPUnit\Framework\TestCase;

/**
 * CustomerImportConfig resolves every getter through AbstractConfig::__call(), so PHPUnit cannot mock
 * one method of it in isolation. This double overrides only the getter Address::validate() reads.
 */
class AddressMapperTestConfig extends CustomerImportConfig
{
    public function __construct()
    {
    }

    public function getRequiredAddressFields()
    {
        return [];
    }
}

/**
 * Magento\Customer\Model\Data\Address only stores a custom attribute when its metadata service
 * lists the attribute code, which needs a full EAV metadata double. This fake carries real state
 * for every getter/setter AddressInterface declares, which is what the address reconciliation
 * in Address::getMagentoFormat() actually reads back.
 */
class FakeAddress implements AddressInterface
{
    private $id;
    private $customerId;
    private ?RegionInterface $region = null;
    private $regionId;
    private $countryId;
    private array $street = [];
    private $company;
    private $telephone;
    private $fax;
    private $postcode;
    private $city;
    private $firstname;
    private $lastname;
    private $middlename;
    private $prefix;
    private $suffix;
    private $vatId;
    private $defaultShipping;
    private $defaultBilling;
    private array $customAttributes = [];
    private ?AddressExtensionInterface $extensionAttributes = null;

    public function getId()
    {
        return $this->id;
    }

    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    public function getCustomerId()
    {
        return $this->customerId;
    }

    public function setCustomerId($customerId)
    {
        $this->customerId = $customerId;
        return $this;
    }

    public function getRegion()
    {
        return $this->region;
    }

    public function setRegion(?RegionInterface $region = null)
    {
        $this->region = $region;
        return $this;
    }

    public function getRegionId()
    {
        return $this->regionId;
    }

    public function setRegionId($regionId)
    {
        $this->regionId = $regionId;
        return $this;
    }

    public function getCountryId()
    {
        return $this->countryId;
    }

    public function setCountryId($countryId)
    {
        $this->countryId = $countryId;
        return $this;
    }

    public function getStreet()
    {
        return $this->street;
    }

    public function setStreet(array $street)
    {
        $this->street = $street;
        return $this;
    }

    public function getCompany()
    {
        return $this->company;
    }

    public function setCompany($company)
    {
        $this->company = $company;
        return $this;
    }

    public function getTelephone()
    {
        return $this->telephone;
    }

    public function setTelephone($telephone)
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getFax()
    {
        return $this->fax;
    }

    public function setFax($fax)
    {
        $this->fax = $fax;
        return $this;
    }

    public function getPostcode()
    {
        return $this->postcode;
    }

    public function setPostcode($postcode)
    {
        $this->postcode = $postcode;
        return $this;
    }

    public function getCity()
    {
        return $this->city;
    }

    public function setCity($city)
    {
        $this->city = $city;
        return $this;
    }

    public function getFirstname()
    {
        return $this->firstname;
    }

    public function setFirstname($firstName)
    {
        $this->firstname = $firstName;
        return $this;
    }

    public function getLastname()
    {
        return $this->lastname;
    }

    public function setLastname($lastName)
    {
        $this->lastname = $lastName;
        return $this;
    }

    public function getMiddlename()
    {
        return $this->middlename;
    }

    public function setMiddlename($middleName)
    {
        $this->middlename = $middleName;
        return $this;
    }

    public function getPrefix()
    {
        return $this->prefix;
    }

    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
        return $this;
    }

    public function getSuffix()
    {
        return $this->suffix;
    }

    public function setSuffix($suffix)
    {
        $this->suffix = $suffix;
        return $this;
    }

    public function getVatId()
    {
        return $this->vatId;
    }

    public function setVatId($vatId)
    {
        $this->vatId = $vatId;
        return $this;
    }

    public function isDefaultShipping()
    {
        return $this->defaultShipping;
    }

    public function setIsDefaultShipping($isDefaultShipping)
    {
        $this->defaultShipping = $isDefaultShipping;
        return $this;
    }

    public function isDefaultBilling()
    {
        return $this->defaultBilling;
    }

    public function setIsDefaultBilling($isDefaultBilling)
    {
        $this->defaultBilling = $isDefaultBilling;
        return $this;
    }

    public function getExtensionAttributes()
    {
        return $this->extensionAttributes;
    }

    public function setExtensionAttributes(AddressExtensionInterface $extensionAttributes)
    {
        $this->extensionAttributes = $extensionAttributes;
        return $this;
    }

    public function getCustomAttribute($attributeCode)
    {
        return $this->customAttributes[$attributeCode] ?? null;
    }

    public function setCustomAttribute($attributeCode, $attributeValue)
    {
        $attribute = new AttributeValue();
        $attribute->setAttributeCode($attributeCode)->setValue($attributeValue);
        $this->customAttributes[$attributeCode] = $attribute;
        return $this;
    }

    public function getCustomAttributes()
    {
        return array_values($this->customAttributes);
    }

    public function setCustomAttributes(array $attributes)
    {
        foreach ($attributes as $attribute) {
            /** @var AttributeInterface $attribute */
            $this->customAttributes[$attribute->getAttributeCode()] = $attribute;
        }
        return $this;
    }
}

class FakeRegion implements RegionInterface
{
    private $regionCode;
    private $region;
    private $regionId;

    public function getRegionCode()
    {
        return $this->regionCode;
    }

    public function setRegionCode($regionCode)
    {
        $this->regionCode = $regionCode;
        return $this;
    }

    public function getRegion()
    {
        return $this->region;
    }

    public function setRegion($region)
    {
        $this->region = $region;
        return $this;
    }

    public function getRegionId()
    {
        return $this->regionId;
    }

    public function setRegionId($regionId)
    {
        $this->regionId = $regionId;
        return $this;
    }

    public function getExtensionAttributes()
    {
        return null;
    }

    public function setExtensionAttributes(\Magento\Customer\Api\Data\RegionExtensionInterface $extensionAttributes)
    {
        return $this;
    }
}

class AddressTest extends TestCase
{
    private function createAddressMapper(): Address
    {
        $addressFactory = $this->createStub(AddressInterfaceFactory::class);
        $addressFactory->method('create')->willReturnCallback(static fn (): AddressInterface => new FakeAddress());

        $addressMap = $this->createStub(Map::class);
        $addressMap->method('mapNetSuiteToMagento')->willReturnCallback(
            static function (NetSuiteAddress $nsAddress, AddressInterface $magentoAddress): void {
                $magentoAddress->setPostcode((string)$nsAddress->zip);
                $magentoAddress->setCountryId((string)$nsAddress->country);
                $magentoAddress->setCity((string)$nsAddress->city);
                $magentoAddress->setStreet([(string)$nsAddress->addr1]);
                $magentoAddress->setTelephone((string)$nsAddress->addrPhone);
                if ($nsAddress->state) {
                    $region = new FakeRegion();
                    $region->setRegionCode((string)$nsAddress->state);
                    $magentoAddress->setRegionId(1);
                    $magentoAddress->setRegion($region);
                }
            }
        );

        return new Address(
            $addressFactory,
            $this->createStub(Logger::class),
            new AddressMapperTestConfig(),
            $addressMap
        );
    }

    private function buildNsCustomer(array $nsAddressBookEntries): NetSuiteCustomer
    {
        $customer = new NetSuiteCustomer();
        $customer->internalId = 10;
        $customer->firstName = 'John';
        $customer->lastName = 'Doe';

        if (!empty($nsAddressBookEntries)) {
            $customer->addressbookList = new CustomerAddressbookList();
            $customer->addressbookList->addressbook = $nsAddressBookEntries;
        }

        return $customer;
    }

    private function buildNsAddressBookEntry(
        string $internalId,
        string $city,
        string $zip,
        string $addr1,
        string $phone,
        bool $defaultBilling = false,
        bool $defaultShipping = false,
        string $country = 'US',
        ?string $state = null
    ): CustomerAddressbook {
        $address = new NetSuiteAddress();
        $address->internalId = $internalId;
        $address->city = $city;
        $address->zip = $zip;
        $address->addr1 = $addr1;
        $address->addrPhone = $phone;
        $address->country = $country;
        $address->state = $state;

        $entry = new CustomerAddressbook();
        $entry->defaultBilling = $defaultBilling;
        $entry->defaultShipping = $defaultShipping;
        $entry->addressbookAddress = $address;

        return $entry;
    }

    public function testExistingAddressSurvivesImportWithNoAddressBook(): void
    {
        $existingAddress = new FakeAddress();
        $existingAddress->setId(7);
        $existingAddress->setCity('Springfield');

        $result = $this->createAddressMapper()->getMagentoFormat(
            $this->buildNsCustomer([]),
            [$existingAddress]
        );

        $this->assertCount(1, $result, 'the existing address must not be deleted when NetSuite sends no address book');
        $this->assertSame($existingAddress, $result[0]);
        $this->assertSame(7, $result[0]->getId());
    }

    public function testIncomingAddressEqualToExistingUpdatesInPlaceAndDoesNotDuplicate(): void
    {
        $existingAddress = new FakeAddress();
        $existingAddress->setId(42);
        $existingAddress->setFirstname('John');
        $existingAddress->setLastname('Doe');
        $existingAddress->setCity('Beverly Hills');
        $existingAddress->setPostcode('90210');
        $existingAddress->setCountryId('US');
        $existingAddress->setStreet(['Alpine Dr']);
        $existingAddress->setTelephone('2025550124');
        $existingAddress->setIsDefaultBilling(false);
        $existingAddress->setIsDefaultShipping(false);

        $nsCustomer = $this->buildNsCustomer([
            $this->buildNsAddressBookEntry('99', ' BEVERLY HILLS ', '90210', ' Alpine Dr ', '2025550124', true, true),
        ]);

        $result = $this->createAddressMapper()->getMagentoFormat($nsCustomer, [$existingAddress]);

        $this->assertCount(1, $result, 'a matching incoming address must update in place, not add a duplicate');
        $this->assertSame($existingAddress, $result[0]);
        $this->assertSame(42, $result[0]->getId(), 'a matched address keeps its Magento entity id');
        $this->assertTrue($result[0]->isDefaultBilling());
        $this->assertTrue($result[0]->isDefaultShipping());
    }

    public function testGenuinelyNewIncomingAddressIsAdded(): void
    {
        $existingAddress = new FakeAddress();
        $existingAddress->setId(1);
        $existingAddress->setFirstname('John');
        $existingAddress->setLastname('Doe');
        $existingAddress->setCity('Chicago');
        $existingAddress->setPostcode('60601');
        $existingAddress->setCountryId('US');
        $existingAddress->setStreet(['Michigan Ave']);
        $existingAddress->setTelephone('3125550100');

        $nsCustomer = $this->buildNsCustomer([
            $this->buildNsAddressBookEntry('55', 'Denver', '80202', 'Colfax Ave', '3035550100'),
        ]);

        $result = $this->createAddressMapper()->getMagentoFormat($nsCustomer, [$existingAddress]);

        $this->assertCount(2, $result);

        $byId = [];
        foreach ($result as $address) {
            $byId[$address->getId() ?? 'new'] = $address;
        }

        $this->assertSame($existingAddress, $byId[1], 'the untouched existing address must still be present');
        $this->assertArrayHasKey('new', $byId, 'the unmatched incoming address must be added');
        $this->assertNull($byId['new']->getId());
        $this->assertSame('Denver', $byId['new']->getCity());
    }

    public function testDefaultFlagsSurviveForAddressImportDidNotTouch(): void
    {
        $untouchedAddress = new FakeAddress();
        $untouchedAddress->setId(3);
        $untouchedAddress->setFirstname('John');
        $untouchedAddress->setLastname('Doe');
        $untouchedAddress->setCity('Miami');
        $untouchedAddress->setPostcode('33101');
        $untouchedAddress->setCountryId('US');
        $untouchedAddress->setStreet(['Ocean Dr']);
        $untouchedAddress->setTelephone('3055550100');
        $untouchedAddress->setIsDefaultBilling(true);
        $untouchedAddress->setIsDefaultShipping(false);

        $touchedAddress = new FakeAddress();
        $touchedAddress->setId(4);
        $touchedAddress->setFirstname('John');
        $touchedAddress->setLastname('Doe');
        $touchedAddress->setCity('Denver');
        $touchedAddress->setPostcode('80202');
        $touchedAddress->setCountryId('US');
        $touchedAddress->setStreet(['Colfax Ave']);
        $touchedAddress->setTelephone('3035550100');
        $touchedAddress->setIsDefaultBilling(false);
        $touchedAddress->setIsDefaultShipping(false);

        $nsCustomer = $this->buildNsCustomer([
            $this->buildNsAddressBookEntry('77', 'Denver', '80202', 'Colfax Ave', '3035550100', true, true),
        ]);

        $result = $this->createAddressMapper()->getMagentoFormat(
            $nsCustomer,
            [$untouchedAddress, $touchedAddress]
        );

        $this->assertCount(2, $result);

        $byId = [];
        foreach ($result as $address) {
            $byId[$address->getId()] = $address;
        }

        $this->assertTrue($byId[3]->isDefaultBilling(), 'an address the import did not touch keeps its default billing flag');
        $this->assertFalse($byId[3]->isDefaultShipping(), 'an address the import did not touch keeps its default shipping flag');
        $this->assertTrue($byId[4]->isDefaultBilling(), 'a matched address takes the incoming default billing flag');
        $this->assertTrue($byId[4]->isDefaultShipping(), 'a matched address takes the incoming default shipping flag');
    }

    public function testMatchByStoredNetSuiteIdTakesPriorityOverDataMatch(): void
    {
        $existingAddress = new FakeAddress();
        $existingAddress->setId(9);
        $existingAddress->setCustomAttribute('netsuite_internal_id', '123');
        $existingAddress->setFirstname('John');
        $existingAddress->setLastname('Doe');
        $existingAddress->setCity('Old City');
        $existingAddress->setPostcode('00000');
        $existingAddress->setCountryId('US');
        $existingAddress->setStreet(['Old Street']);
        $existingAddress->setTelephone('0000000000');

        $nsCustomer = $this->buildNsCustomer([
            $this->buildNsAddressBookEntry('123', 'New City', '11111', 'New Street', '1111111111'),
        ]);

        $result = $this->createAddressMapper()->getMagentoFormat($nsCustomer, [$existingAddress]);

        $this->assertCount(1, $result, 'an id match must update in place even though the address data changed');
        $this->assertSame($existingAddress, $result[0]);
        $this->assertSame(9, $result[0]->getId());
        $this->assertSame('New City', $result[0]->getCity());
    }

    public function testNewCustomerWithNoExistingAddressesGetsAllIncomingAddressesAdded(): void
    {
        $nsCustomer = $this->buildNsCustomer([
            $this->buildNsAddressBookEntry('1', 'Chicago', '60601', 'Michigan Ave', '3125550100'),
            $this->buildNsAddressBookEntry('2', 'Denver', '80202', 'Colfax Ave', '3035550100'),
        ]);

        $result = $this->createAddressMapper()->getMagentoFormat($nsCustomer, []);

        $this->assertCount(2, $result);
        $this->assertNull($result[0]->getId());
        $this->assertNull($result[1]->getId());
    }

    public function testIdMatchAppliesTheNewPostcodeAndKeepsTheStoredNetSuiteId(): void
    {
        $existingAddress = new FakeAddress();
        $existingAddress->setId(9);
        $existingAddress->setCustomAttribute('netsuite_internal_id', '123');
        $existingAddress->setFirstname('John');
        $existingAddress->setLastname('Doe');
        $existingAddress->setCity('Old City');
        $existingAddress->setPostcode('00000');
        $existingAddress->setCountryId('US');
        $existingAddress->setStreet(['Old Street']);
        $existingAddress->setTelephone('0000000000');

        $nsCustomer = $this->buildNsCustomer([
            $this->buildNsAddressBookEntry('123', 'New City', '11111', 'New Street', '1111111111'),
        ]);

        $result = $this->createAddressMapper()->getMagentoFormat($nsCustomer, [$existingAddress]);

        $this->assertSame('11111', $result[0]->getPostcode(), 'a matched address must take the new postcode');
        $this->assertSame('123', $result[0]->getCustomAttribute('netsuite_internal_id')->getValue());
    }

    public function testCompanyOnlyOnTheMagentoAddressStillMatches(): void
    {
        $existingAddress = new FakeAddress();
        $existingAddress->setId(77);
        $existingAddress->setFirstname('John');
        $existingAddress->setLastname('Doe');
        $existingAddress->setCompany('ACME Inc');
        $existingAddress->setCity('Beverly Hills');
        $existingAddress->setPostcode('90210');
        $existingAddress->setCountryId('US');
        $existingAddress->setStreet(['Alpine Dr']);
        $existingAddress->setTelephone('2025550124');

        $nsCustomer = $this->buildNsCustomer([
            $this->buildNsAddressBookEntry('88', 'Beverly Hills', '90210', 'Alpine Dr', '2025550124'),
        ]);

        $result = $this->createAddressMapper()->getMagentoFormat($nsCustomer, [$existingAddress]);

        $this->assertCount(1, $result, 'the import never maps company, so company must not block the match');
        $this->assertSame(77, $result[0]->getId());
        $this->assertSame('ACME Inc', $result[0]->getCompany(), 'the unmapped company must survive the update');
    }
}
