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

namespace MageOS\NetSuiteConnector\Test\Unit\Shipment\MultiSource\Model\Mapper\TrackingNumber;

use MageOS\NetSuiteConnector\Shipment\MultiSource\Model\Mapper\TrackingNumber\ItemCustomFieldNormalizer;
use NetSuite\Classes\CustomFieldList;
use NetSuite\Classes\ItemFulfillment;
use NetSuite\Classes\ItemFulfillmentItem;
use NetSuite\Classes\ItemFulfillmentItemList;
use NetSuite\Classes\StringCustomFieldRef;
use PHPUnit\Framework\TestCase;

/**
 * Pins the two known defects in the multi source tracking number normalizer: comparing the whole original list
 * instead of the current original entry, and appending the fallback entry twice. Both come verbatim from the
 * former plugin, MageOS\NetSuiteConnector\Shipment\MultiSource\Plugin\TrackingNumberMultiSource.
 */
class ItemCustomFieldNormalizerTest extends TestCase
{
    public function testItKeepsTheOriginalDescriptionForAMatchedNumberAndAddsANewNumberOnce(): void
    {
        $netsuiteShipment = $this->fulfillmentWithTrackingNumbers(['ABC123', 'XYZ999']);
        $originalTrackingNumbers = [
            ['number' => 'ABC123', 'description' => 'Existing desc'],
        ];

        $result = (new ItemCustomFieldNormalizer())->normalize($netsuiteShipment, $originalTrackingNumbers);

        $this->assertSame(
            [
                ['number' => 'ABC123', 'description' => 'Existing desc'],
                ['number' => 'XYZ999', 'description' => ''],
            ],
            array_values($result)
        );
    }

    public function testItReturnsThePackageListUnchangedWhenNoItemHasTheCustomField(): void
    {
        $netsuiteShipment = $this->fulfillmentWithTrackingNumbers([]);
        $originalTrackingNumbers = [
            ['number' => 'ABC123', 'description' => 'From the package'],
        ];

        $result = (new ItemCustomFieldNormalizer())->normalize($netsuiteShipment, $originalTrackingNumbers);

        $this->assertSame($originalTrackingNumbers, $result);
    }

    /**
     * @param string[] $trackingNumbers
     */
    private function fulfillmentWithTrackingNumbers(array $trackingNumbers): ItemFulfillment
    {
        $items = [];
        foreach ($trackingNumbers as $trackingNumber) {
            $customField = new StringCustomFieldRef();
            $customField->scriptId = ItemCustomFieldNormalizer::CUST_FIELD_FOR_TRACKING_NUMBER;
            $customField->value = $trackingNumber;

            $item = new ItemFulfillmentItem();
            $item->customFieldList = new CustomFieldList();
            $item->customFieldList->customField = [$customField];
            $items[] = $item;
        }

        if (empty($items)) {
            $item = new ItemFulfillmentItem();
            $item->customFieldList = new CustomFieldList();
            $item->customFieldList->customField = [];
            $items[] = $item;
        }

        $fulfillment = new ItemFulfillment();
        $fulfillment->itemList = new ItemFulfillmentItemList();
        $fulfillment->itemList->item = $items;

        return $fulfillment;
    }
}
