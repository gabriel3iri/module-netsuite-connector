<?php
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

declare(strict_types=1);

namespace MageOS\NetSuiteConnector\Test\Unit\Shipment\Model\Mapper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Api\Data\ShipmentTrackInterfaceFactory;
use MageOS\NetSuiteConnector\Inventory\Model\Config\InventoryMode;
use MageOS\NetSuiteConnector\Shipment\Model\Config\ShippingConfig;
use MageOS\NetSuiteConnector\Shipment\Model\Mapper\TrackingNumber;
use MageOS\NetSuiteConnector\Shipment\MultiSource\Model\Mapper\TrackingNumber\ItemCustomFieldNormalizer;
use NetSuite\Classes\ItemFulfillment;
use NetSuite\Classes\ItemFulfillmentPackage;
use NetSuite\Classes\ItemFulfillmentPackageList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TrackingNumberTest extends TestCase
{
    #[DataProvider('singleModeValueProvider')]
    public function testItReturnsThePackageTrackingNumbersOutsideMultiMode(string $configuredMode): void
    {
        $shipment = $this->fulfillmentWithPackage('PKG1', 'Box');
        $itemCustomFieldNormalizer = $this->createMock(ItemCustomFieldNormalizer::class);
        $itemCustomFieldNormalizer->expects($this->never())->method('normalize');

        $trackingNumbers = $this->trackingNumber($configuredMode, $itemCustomFieldNormalizer)
            ->getNormalizedTrackingNumberData($shipment);

        $this->assertSame([['number' => 'PKG1', 'description' => 'Box']], $trackingNumbers);
    }

    public static function singleModeValueProvider(): array
    {
        return [
            'explicit single mode' => [InventoryMode::MODE_SINGLE],
            'unset configuration' => [''],
            'unrecognised value' => ['multi_source'],
        ];
    }

    public function testItHandsThePackageTrackingNumbersToTheItemCustomFieldNormalizerInMultiMode(): void
    {
        $shipment = $this->fulfillmentWithPackage('PKG1', 'Box');
        $expected = [['number' => 'custom-field', 'description' => '']];
        $itemCustomFieldNormalizer = $this->createMock(ItemCustomFieldNormalizer::class);
        $itemCustomFieldNormalizer->expects($this->once())
            ->method('normalize')
            ->with($this->identicalTo($shipment), [['number' => 'PKG1', 'description' => 'Box']])
            ->willReturn($expected);

        $trackingNumbers = $this->trackingNumber(InventoryMode::MODE_MULTI, $itemCustomFieldNormalizer)
            ->getNormalizedTrackingNumberData($shipment);

        $this->assertSame($expected, $trackingNumbers);
    }

    private function trackingNumber(string $configuredMode, ItemCustomFieldNormalizer $normalizer): TrackingNumber
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn($configuredMode);

        return new TrackingNumber(
            $this->createStub(ShipmentTrackInterfaceFactory::class),
            $this->createStub(ShippingConfig::class),
            new InventoryMode($scopeConfig),
            $normalizer
        );
    }

    private function fulfillmentWithPackage(string $number, string $description): ItemFulfillment
    {
        $package = new ItemFulfillmentPackage();
        $package->packageTrackingNumber = $number;
        $package->packageDescr = $description;

        $fulfillment = new ItemFulfillment();
        $fulfillment->packageList = new ItemFulfillmentPackageList();
        $fulfillment->packageList->package = [$package];

        return $fulfillment;
    }
}
