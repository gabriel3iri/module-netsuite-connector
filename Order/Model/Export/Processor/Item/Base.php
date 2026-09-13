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

namespace MageOS\NetSuiteConnector\Order\Model\Export\Processor\Item;

use Magento\Bundle\Model\Product\Type as BundleProduct;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use NetSuite\Classes\RecordRef;
use NetSuite\Classes\SalesOrder;
use NetSuite\Classes\SalesOrderItem;
use MageOS\NetSuiteConnector\Core\Exception\DataIntegrityException;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderItemProcessorInterface;

class Base implements OrderItemProcessorInterface
{
    /** @var \WeakMap<SalesOrder, array<int, true>> the inner key is the order item id of a fixed price bundle */
    private readonly \WeakMap $fixedPriceBundles;

    public function __construct(
        private readonly \MageOS\NetSuiteConnector\Core\Helper\EavHelper $eavHelper,
        private readonly \MageOS\NetSuiteConnector\Order\Model\Mapper\OrderExport\Location $location
    ) {
        $this->fixedPriceBundles = new \WeakMap();
    }

    public function processItem(
        SalesOrder $netsuiteOrder,
        SalesOrderItem $netsuiteItem,
        OrderItemInterface $magentoItem,
        ProductInterface $product,
        OrderInterface $magentoOrder
    ): void {
        $netsuiteItem->description = $this->getProductDescription($magentoItem);
        $netsuiteItem->quantity = $magentoItem->getQtyOrdered();
        $netsuiteItem->quantityCommitted = $magentoItem->getQtyOrdered();
        $netsuiteItem->item = new RecordRef();
        $netsuiteItem->item->internalId = $this->getItemInternalId($magentoItem, $product);
        $netsuiteItem->price = new RecordRef();
        $netsuiteItem->price->internalId = -1;
        $netsuiteItem->isTaxable = false;
        $netsuiteItem->rate = $this->getPrice($netsuiteOrder, $magentoItem, $product);
        $this->location->addLocation($netsuiteItem);
    }

    private function getProductDescription(OrderItemInterface $orderItem)
    {
        $description = $orderItem->getName();
        $customOptions = $orderItem->getProductOptions();
        if (\is_array($customOptions) && isset($customOptions['options']) && \count($customOptions['options'])) {
            $customOptions = $customOptions['options'];
            $description .= ' - ';
            foreach ($customOptions as $option) {
                $description .= $option['label'] . ':' . $option['print_value'] . ', ';
            }
            $description = preg_replace('/, $/', '', $description);
        }
        return $description;
    }

    private function getItemInternalId(OrderItemInterface $magentoOrderItem, ProductInterface $product)
    {
        $internalId = $product->getCustomAttribute('netsuite_internal_id') ?
            (string)$product->getCustomAttribute('netsuite_internal_id')->getValue() :
            0;

        $productType = $magentoOrderItem->getProductType();
        if ($productType === BundleProduct::TYPE_CODE) {
            $internalId = $this->eavHelper->mapBundleOptionToInternalId($magentoOrderItem) ?? $internalId;
        }

        if (!$internalId) {
            throw new DataIntegrityException('Product ' . $magentoOrderItem->getProductId()
                . ' has empty NetSuite internal ID');
        }
        return $internalId;
    }

    /**
     * A zero priced bundle is sent at price 0, because its parts are sent as their own lines.
     * A fixed price bundle keeps its price, and its simple children are sent at price 0.
     */
    private function getPrice(SalesOrder $netsuiteOrder, OrderItemInterface $magentoOrderItem, ProductInterface $product)
    {
        $price = $magentoOrderItem->getPrice();
        if (!((float)$magentoOrderItem->getRowTotal()) && $magentoOrderItem->getParentItemId()) {
            $price = $magentoOrderItem->getParentItem()->getPrice();
        }

        if ($magentoOrderItem->getProductType() == BundleProduct::TYPE_CODE) {
            if ($product->getPrice() == 0) {
                $price = 0;
            } else {
                $fixedPriceBundles = $this->fixedPriceBundles[$netsuiteOrder] ?? [];
                $fixedPriceBundles[(int)$magentoOrderItem->getId()] = true;
                $this->fixedPriceBundles[$netsuiteOrder] = $fixedPriceBundles;
            }
        }
        if ($magentoOrderItem->getProductType() == Type::TYPE_SIMPLE && $magentoOrderItem->getParentItemId()
            && isset($this->fixedPriceBundles[$netsuiteOrder][(int)$magentoOrderItem->getParentItemId()])
        ) {
            $price = 0;
        }
        return $price;
    }
}
