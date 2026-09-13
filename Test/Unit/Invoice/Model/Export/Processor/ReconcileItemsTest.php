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

namespace MageOS\NetSuiteConnector\Test\Unit\Invoice\Model\Export\Processor;

use Magento\Sales\Api\Data\InvoiceItemInterface;
use Magento\Sales\Api\Data\OrderInterface;
use MageOS\NetSuiteConnector\Core\Model\Pipeline\ProcessorSorter;
use MageOS\NetSuiteConnector\Invoice\Model\Export\CashSaleLineMatcherInterface;
use MageOS\NetSuiteConnector\Invoice\Model\Export\Processor\ReconcileItems;
use NetSuite\Classes\CashSale;
use NetSuite\Classes\CashSaleItem;
use NetSuite\Classes\CashSaleItemList;
use NetSuite\Classes\RecordRef;
use PHPUnit\Framework\TestCase;

class ReconcileItemsRecordingMatcher implements CashSaleLineMatcherInterface
{
    public int $callCount = 0;

    public function __construct(private readonly bool $matches)
    {
    }

    public function match(
        CashSaleItem $netsuiteItem,
        InvoiceItemInterface $magentoItem,
        mixed $netsuiteInternalId,
        OrderInterface $magentoOrder,
        ?float &$pendingLineDiscount
    ): bool {
        $this->callCount++;
        return $this->matches;
    }
}

class ReconcileItemsTest extends TestCase
{
    public function testEveryMatcherRunsForAPairAndTheSearchStopsAfterAMatch(): void
    {
        $matchingMatcher = new ReconcileItemsRecordingMatcher(true);
        $observingMatcher = new ReconcileItemsRecordingMatcher(false);

        $reconcileItems = $this->reconcileItems([
            'observing' => ['processor' => $observingMatcher, 'sortOrder' => 200],
            'matching' => ['processor' => $matchingMatcher, 'sortOrder' => 100],
        ]);

        $cashSale = $this->cashSaleWithLine('1');

        $firstMagentoItem = $this->createStub(InvoiceItemInterface::class);
        $firstMagentoItem->method('getProductId')->willReturn(1);
        $secondMagentoItem = $this->createStub(InvoiceItemInterface::class);
        $secondMagentoItem->method('getProductId')->willReturn(2);

        $this->process($reconcileItems, $cashSale, [$firstMagentoItem, $secondMagentoItem]);

        $this->assertSame(
            1,
            $matchingMatcher->callCount,
            'The search must stop at the first matched pair, so the matcher is never asked about the second item'
        );
        $this->assertSame(1, $observingMatcher->callCount, 'Every matcher runs for the same pair');
        $this->assertCount(1, $cashSale->itemList->item, 'The matched line stays in the item list');
    }

    public function testAnUnmatchedLineIsRemoved(): void
    {
        $reconcileItems = $this->reconcileItems([
            'never' => ['processor' => new ReconcileItemsRecordingMatcher(false), 'sortOrder' => 100],
        ]);

        $cashSale = $this->cashSaleWithLine('999');

        $magentoItem = $this->createStub(InvoiceItemInterface::class);
        $magentoItem->method('getProductId')->willReturn(1);

        $this->process($reconcileItems, $cashSale, [$magentoItem]);

        $this->assertCount(0, $cashSale->itemList->item);
    }

    private function reconcileItems(array $matchers): ReconcileItems
    {
        $productResource = $this->createStub(\Magento\Catalog\Model\ResourceModel\Product::class);
        $productResource->method('getAttributeRawValue')->willReturn('1');

        return new ReconcileItems($productResource, new ProcessorSorter(), $matchers);
    }

    private function cashSaleWithLine(string $internalId): CashSale
    {
        $netsuiteItem = new CashSaleItem();
        $netsuiteItem->item = new RecordRef();
        $netsuiteItem->item->internalId = $internalId;

        $cashSale = new CashSale();
        $cashSale->itemList = new CashSaleItemList();
        $cashSale->itemList->item = [$netsuiteItem];

        return $cashSale;
    }

    private function process(ReconcileItems $reconcileItems, CashSale $cashSale, array $magentoItems): void
    {
        $magentoInvoice = $this->createStub(\Magento\Sales\Api\Data\InvoiceInterface::class);
        $magentoInvoice->method('getItems')->willReturn($magentoItems);

        $reconcileItems->process($cashSale, $magentoInvoice, $this->createStub(OrderInterface::class));
    }
}
