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

namespace MageOS\NetSuiteConnector\Test\Unit\CustomerImport\Model\Mapper;

use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Store\Api\StoreRepositoryInterface;
use MageOS\NetSuiteConnector\CustomerImport\Model\Config\CustomerImportConfig;
use MageOS\NetSuiteConnector\CustomerImport\Model\Mapper\Customer;
use MageOS\NetSuiteConnector\CustomerImport\Model\Mapper\Customer\Address;
use NetSuite\Classes\Customer as NetSuiteCustomer;
use PHPUnit\Framework\TestCase;

/**
 * Review Critical: Customer::getMagentoFormat() must hand the existing Magento customer's own
 * addresses to the address mapper, so it can reconcile instead of blindly replacing them.
 */
class CustomerTest extends TestCase
{
    public function testExistingCustomerAddressesArePassedToAddressMapperAndResultIsApplied(): void
    {
        $nsCustomer = new NetSuiteCustomer();
        $nsCustomer->internalId = 10;
        $nsCustomer->email = 'person@person.com';
        $nsCustomer->firstName = 'John';
        $nsCustomer->lastName = 'Doe';

        $existingAddresses = [$this->createStub(AddressInterface::class)];
        $reconciledAddresses = [$this->createStub(AddressInterface::class), $this->createStub(AddressInterface::class)];

        $magentoCustomer = $this->createMock(CustomerInterface::class);
        $magentoCustomer->method('getAddresses')->willReturn($existingAddresses);
        $magentoCustomer->expects($this->once())
            ->method('setAddresses')
            ->with($this->identicalTo($reconciledAddresses));

        $addressMapper = $this->createMock(Address::class);
        $addressMapper->expects($this->once())
            ->method('getMagentoFormat')
            ->with($this->identicalTo($nsCustomer), $this->identicalTo($existingAddresses))
            ->willReturn($reconciledAddresses);

        $customerMapper = new Customer(
            $this->createStub(CustomerInterfaceFactory::class),
            $this->createStub(StoreRepositoryInterface::class),
            $this->createStub(CustomerImportConfig::class),
            $addressMapper
        );

        $result = $customerMapper->getMagentoFormat($nsCustomer, $magentoCustomer);

        $this->assertSame($magentoCustomer, $result);
    }
}
