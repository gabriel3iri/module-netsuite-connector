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

namespace MageOS\NetSuiteConnector\Test\Unit\Order\Model\Export\Processor;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Event\ManagerInterface;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\OrderAddressRepositoryInterface;
use PHPUnit\Framework\TestCase;
use MageOS\NetSuiteConnector\Core\Helper\Transform;
use MageOS\NetSuiteConnector\Order\Model\Export\Processor\Addresses;

/**
 * Review High 8: an address country that is outside the store's allowed
 * country list must still map to its own NetSuite country value, not
 * silently fall back to US.
 */
class AddressesTest extends TestCase
{
    public function testAddressCountryOutsideStoreAllowedListIsNotForcedToUs(): void
    {
        $transformHelper = $this->createStub(Transform::class);
        $transformHelper->method('transformCountryCode')
            ->willReturnMap([
                ['US', '_unitedStates'],
                ['CA', '_canada'],
            ]);

        $eventManager = $this->createStub(ManagerInterface::class);
        $orderAddressRepository = $this->createStub(OrderAddressRepositoryInterface::class);
        $searchCriteriaBuilder = $this->createStub(SearchCriteriaBuilder::class);

        $processor = new Addresses(
            $transformHelper,
            $eventManager,
            $orderAddressRepository,
            $searchCriteriaBuilder
        );

        $address = $this->createStub(OrderAddressInterface::class);
        $address->method('getCountryId')->willReturn('CA');
        $address->method('getStreet')->willReturn(['123 Main St']);
        $address->method('getCity')->willReturn('Toronto');
        $address->method('getFirstname')->willReturn('Jane');
        $address->method('getLastname')->willReturn('Doe');
        $address->method('getTelephone')->willReturn('4165551234');
        $address->method('getRegionCode')->willReturn('ON');
        $address->method('getPostcode')->willReturn('M5V 2T6');

        $netsuiteAddress = $processor->createAddress($address);

        $this->assertSame(
            '_canada',
            $netsuiteAddress->country,
            'a CA address must map to Canada even when the store only allows US'
        );
    }
}
