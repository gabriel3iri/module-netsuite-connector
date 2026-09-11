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

namespace MageOS\NetSuiteConnector\Test\Unit\Shipment\MultiSource\Model\Mapper\ShipmentMultiSource;

use Magento\InventoryApi\Api\Data\SourceInterface;
use MageOS\NetSuiteConnector\Core\Exception\ConnectorRuntimeException;
use MageOS\NetSuiteConnector\Inventory\Multi\Model\MagentoSourceRepository;
use MageOS\NetSuiteConnector\Shipment\MultiSource\Model\Mapper\ShipmentMultiSource\LocationGrouper;
use NetSuite\Classes\ItemFulfillment;
use NetSuite\Classes\ItemFulfillmentItem;
use NetSuite\Classes\ItemFulfillmentItemList;
use NetSuite\Classes\RecordRef;
use PHPUnit\Framework\TestCase;

/**
 * Covers the location grouper that splits a NetSuite fulfillment into one fulfillment per mapped location, keyed
 * by the matched Magento source code.
 */
class LocationGrouperTest extends TestCase
{
    /**
     * A single mapped location yields one fulfillment that keeps every item, keyed by the matched source code.
     */
    public function testItYieldsOneFulfillmentForASingleMappedLocation(): void
    {
        $itemA = $this->fulfillmentItem('10', 5.0);
        $itemB = $this->fulfillmentItem('10', 2.0);
        $fulfillment = $this->fulfillment([$itemA, $itemB]);
        $magentoSourceRepository = $this->createMock(MagentoSourceRepository::class);
        $magentoSourceRepository->expects($this->exactly(2))
            ->method('getSourceByNetSuiteData')
            ->with(10, null)
            ->willReturn($this->mappedSource('source_10'));
        $grouper = new LocationGrouper($magentoSourceRepository);

        $groups = iterator_to_array($grouper->group($fulfillment));

        $this->assertSame(['source_10'], array_keys($groups));
        $this->assertInstanceOf(ItemFulfillment::class, $groups['source_10']);
        $this->assertSame([$itemA, $itemB], array_values($groups['source_10']->itemList->item));
    }

    /**
     * Two mapped locations yield two fulfillments, each keyed by its own source code and holding only its own
     * items and its own item list.
     */
    public function testItYieldsSeparateFulfillmentsForTwoMappedLocations(): void
    {
        $itemLocationA = $this->fulfillmentItem('10', 5.0);
        $itemLocationB = $this->fulfillmentItem('20', 3.0);
        $fulfillment = $this->fulfillment([$itemLocationA, $itemLocationB]);
        $magentoSourceRepository = $this->createStub(MagentoSourceRepository::class);
        $magentoSourceRepository->method('getSourceByNetSuiteData')
            ->willReturnCallback(function (int $netSuiteId) {
                return $this->mappedSource('source_' . $netSuiteId);
            });
        $grouper = new LocationGrouper($magentoSourceRepository);

        $groups = iterator_to_array($grouper->group($fulfillment));

        $this->assertSame(['source_10', 'source_20'], array_keys($groups));
        $groupA = $groups['source_10'];
        $groupB = $groups['source_20'];
        $this->assertInstanceOf(ItemFulfillment::class, $groupA);
        $this->assertInstanceOf(ItemFulfillment::class, $groupB);
        $this->assertSame([$itemLocationA], array_values($groupA->itemList->item));
        $this->assertSame([$itemLocationB], array_values($groupB->itemList->item));
        $this->assertNotSame($groupA->itemList, $groupB->itemList);
        $this->assertNotSame($fulfillment->itemList, $groupA->itemList);
        $this->assertNotSame($fulfillment->itemList, $groupB->itemList);
        $this->assertSame(
            [$itemLocationA, $itemLocationB],
            array_values($fulfillment->itemList->item),
            'the input record must not be mutated'
        );
    }

    /**
     * Items whose location has no mapped Magento source are dropped from every group, not just skipped in place.
     */
    public function testItSkipsItemsWithUnmappedLocations(): void
    {
        $mappedItem = $this->fulfillmentItem('10', 4.0);
        $unmappedItem = $this->fulfillmentItem('99', 1.0);
        $fulfillment = $this->fulfillment([$mappedItem, $unmappedItem]);
        $magentoSourceRepository = $this->createStub(MagentoSourceRepository::class);
        $magentoSourceRepository->method('getSourceByNetSuiteData')
            ->willReturnCallback(function (int $netSuiteId) {
                return $netSuiteId === 10 ? $this->mappedSource('source_10') : null;
            });
        $grouper = new LocationGrouper($magentoSourceRepository);

        $groups = iterator_to_array($grouper->group($fulfillment));

        $this->assertSame(['source_10'], array_keys($groups));
        $this->assertSame([$mappedItem], array_values($groups['source_10']->itemList->item));
    }

    /**
     * When every item is unmapped the fulfillment cannot be grouped at all.
     */
    public function testItThrowsWhenAllItemsAreUnmapped(): void
    {
        $fulfillment = $this->fulfillment([$this->fulfillmentItem('99', 1.0)]);
        $fulfillment->entity = new RecordRef();
        $fulfillment->entity->internalId = '555';
        $magentoSourceRepository = $this->createStub(MagentoSourceRepository::class);
        $magentoSourceRepository->method('getSourceByNetSuiteData')->willReturn(null);
        $grouper = new LocationGrouper($magentoSourceRepository);

        $this->expectException(ConnectorRuntimeException::class);
        $this->expectExceptionMessage('Fulfillment #555 have items with not mapped locations!');

        iterator_to_array($grouper->group($fulfillment));
    }

    /**
     * Builds a NetSuite fulfillment record with the given items.
     *
     * @param ItemFulfillmentItem[] $items
     * @return ItemFulfillment
     */
    private function fulfillment(array $items): ItemFulfillment
    {
        $fulfillment = new ItemFulfillment();
        $fulfillment->entity = new RecordRef();
        $fulfillment->entity->internalId = '1';
        $fulfillment->itemList = new ItemFulfillmentItemList();
        $fulfillment->itemList->item = $items;

        return $fulfillment;
    }

    /**
     * Builds a NetSuite fulfillment item for the given location.
     *
     * @param string $locationInternalId
     * @param float $quantity
     * @return ItemFulfillmentItem
     */
    private function fulfillmentItem(string $locationInternalId, float $quantity): ItemFulfillmentItem
    {
        $item = new ItemFulfillmentItem();
        $item->location = new RecordRef();
        $item->location->internalId = $locationInternalId;
        $item->quantity = $quantity;

        return $item;
    }

    /**
     * Builds a stub Magento source mapped to the given source code.
     *
     * @param string $sourceCode
     * @return SourceInterface
     */
    private function mappedSource(string $sourceCode): SourceInterface
    {
        $source = $this->createStub(SourceInterface::class);
        $source->method('getSourceCode')->willReturn($sourceCode);

        return $source;
    }
}
