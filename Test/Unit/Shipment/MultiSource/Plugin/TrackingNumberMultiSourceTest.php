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

namespace MageOS\NetSuiteConnector\Test\Unit\Shipment\MultiSource\Plugin;

use MageOS\NetSuiteConnector\Inventory\Model\Config\InventoryMode;
use MageOS\NetSuiteConnector\Shipment\Model\Mapper\TrackingNumber;
use MageOS\NetSuiteConnector\Shipment\MultiSource\Plugin\TrackingNumberMultiSource;
use NetSuite\Classes\CustomFieldList;
use NetSuite\Classes\ItemFulfillment;
use NetSuite\Classes\ItemFulfillmentItem;
use NetSuite\Classes\ItemFulfillmentItemList;
use NetSuite\Classes\StringCustomFieldRef;
use PHPUnit\Framework\TestCase;

/**
 * Covers the two known defects in the multi source tracking number plugin: comparing the whole original list
 * instead of the current original entry, and appending the fallback entry twice.
 */
class TrackingNumberMultiSourceTest extends TestCase
{
    /**
     * A number that already has an original entry keeps its description exactly once, and a number with no
     * original entry gets a single fresh entry with an empty description.
     */
    public function testItKeepsTheOriginalDescriptionForAMatchedNumberAndAddsANewNumberOnce(): void
    {
        $inventoryMode = $this->createStub(InventoryMode::class);
        $inventoryMode->method('isMulti')->willReturn(true);
        $plugin = new TrackingNumberMultiSource($inventoryMode);
        $subject = $this->createStub(TrackingNumber::class);
        $netsuiteShipment = $this->fulfillmentWithTrackingNumbers(['ABC123', 'XYZ999']);
        $originalTrackingNumbers = [
            ['number' => 'ABC123', 'description' => 'Existing desc'],
        ];

        $result = $plugin->afterGetNormalizedTrackingNumberData(
            $subject,
            $originalTrackingNumbers,
            $netsuiteShipment
        );

        $this->assertSame(
            [
                ['number' => 'ABC123', 'description' => 'Existing desc'],
                ['number' => 'XYZ999', 'description' => ''],
            ],
            array_values($result)
        );
    }

    /**
     * Builds a fulfillment whose items carry the multi source tracking number custom field.
     *
     * @param string[] $trackingNumbers
     * @return ItemFulfillment
     */
    private function fulfillmentWithTrackingNumbers(array $trackingNumbers): ItemFulfillment
    {
        $items = [];
        foreach ($trackingNumbers as $trackingNumber) {
            $customField = new StringCustomFieldRef();
            $customField->scriptId = TrackingNumberMultiSource::CUST_FIELD_FOR_TRACKING_NUMBER;
            $customField->value = $trackingNumber;

            $item = new ItemFulfillmentItem();
            $item->customFieldList = new CustomFieldList();
            $item->customFieldList->customField = [$customField];
            $items[] = $item;
        }

        $fulfillment = new ItemFulfillment();
        $fulfillment->itemList = new ItemFulfillmentItemList();
        $fulfillment->itemList->item = $items;

        return $fulfillment;
    }
}
