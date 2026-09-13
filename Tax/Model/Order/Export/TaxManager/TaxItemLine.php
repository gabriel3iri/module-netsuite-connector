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
 *
 */
declare(strict_types=1);

namespace MageOS\NetSuiteConnector\Tax\Model\Order\Export\TaxManager;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use NetSuite\Classes\RecordRef;
use NetSuite\Classes\SalesOrder;
use NetSuite\Classes\SalesOrderItem;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderItemProcessorInterface;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderProcessorInterface;
use MageOS\NetSuiteConnector\Tax\Model\Config\Source\Tax as TaxLogic;

class TaxItemLine implements OrderProcessorInterface, OrderItemProcessorInterface
{
    private const ITEM_DESCRIPTION = 'Sales tax';

    /** @var \WeakMap<SalesOrder, float> */
    private readonly \WeakMap $collectedItemTax;

    public function __construct(
        private readonly \MageOS\NetSuiteConnector\Tax\Model\Config\Tax $taxConfig,
        private readonly \Magento\Framework\Event\ManagerInterface $eventManager,
        private readonly \MageOS\NetSuiteConnector\Order\Model\Mapper\OrderExport\OrderItemList $netSuiteOrderItemList,
        private readonly \MageOS\NetSuiteConnector\Tax\Model\Order\Export\TaxManager\TaxCalculation\TaxPerItem $taxPerItem,
        private readonly \MageOS\NetSuiteConnector\Order\Model\Mapper\OrderExport\Location $nsLocation
    ) {
        $this->collectedItemTax = new \WeakMap();
    }

    public function processItem(
        SalesOrder $netsuiteOrder,
        SalesOrderItem $netsuiteItem,
        OrderItemInterface $magentoItem,
        ProductInterface $product,
        OrderInterface $magentoOrder
    ): void {
        if (!$this->taxConfig->isTaxLogicActive(TaxLogic::TAX_HANDLING_TAX_ITEM, 'order_export')) {
            return;
        }
        $this->collectedItemTax[$netsuiteOrder] = ($this->collectedItemTax[$netsuiteOrder] ?? 0.0)
            + $this->taxPerItem->getTaxAmount($magentoItem, $product);
    }

    /**
     * An observer of netsuite_new_order_add_tax_item_before sets "ignore" on add_tax_item to skip the tax line.
     */
    public function process(SalesOrder $netsuiteOrder, OrderInterface $magentoOrder): void
    {
        if (!$this->taxConfig->isTaxLogicActive(TaxLogic::TAX_HANDLING_TAX_ITEM, 'order_export')) {
            return;
        }
        $collectedItemTax = $this->collectedItemTax[$netsuiteOrder] ?? 0.0;
        unset($this->collectedItemTax[$netsuiteOrder]);

        $addTaxItem = new \Magento\Framework\DataObject();
        $this->eventManager->dispatch(
            'netsuite_new_order_add_tax_item_before',
            [
                'magento_order' => $magentoOrder,
                'netsuite_order' => $netsuiteOrder,
                'add_tax_item' => $addTaxItem
            ]
        );

        $taxAmount = round($collectedItemTax + $magentoOrder->getShippingTaxAmount(), 2);
        if ($taxAmount && !$addTaxItem->getIgnore()) {
            $this->netSuiteOrderItemList->addOrderItemToList($netsuiteOrder, $this->createTaxItem($taxAmount));
        }
    }

    private function createTaxItem(float $taxAmount): SalesOrderItem
    {
        $netsuiteOrderItem = new SalesOrderItem();
        $netsuiteOrderItem->description = self::ITEM_DESCRIPTION;
        $netsuiteOrderItem->quantity = 1;
        $netsuiteOrderItem->quantityCommitted = 1;
        $netsuiteOrderItem->item = new RecordRef();
        $netsuiteOrderItem->item->internalId = $this->taxConfig->getTaxItemInternalNetsuiteId();
        $netsuiteOrderItem->price = new RecordRef();
        $netsuiteOrderItem->price->internalId = -1;
        $netsuiteOrderItem->amount = $taxAmount;
        $netsuiteOrderItem->rate = $taxAmount;
        $netsuiteOrderItem->isTaxable = false;
        $this->nsLocation->addLocation($netsuiteOrderItem);
        return $netsuiteOrderItem;
    }
}
