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
 *
 */

namespace MageOS\NetSuiteConnector\Discount\Model\Provider\Line;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use NetSuite\Classes\RecordRef;
use NetSuite\Classes\RecordType;
use NetSuite\Classes\SalesOrder;
use NetSuite\Classes\SalesOrderItem;
use MageOS\NetSuiteConnector\Discount\Model\Config\Source\LogicSwitcher;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderItemProcessorInterface;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderProcessorInterface;

/**
 * With disable_order_level_discount set, every item and the shipping get their own discount line.
 * Otherwise the order gets a single discount line.
 */
class OrderDiscount implements OrderProcessorInterface, OrderItemProcessorInterface
{
    private const DISCOUNT_ITEM_DESCRIPTION = 'Discount';
    private const SHIPPING_DISCOUNT_ITEM_DESCRIPTION = 'Shipping Discount';

    public function __construct(
        private readonly \MageOS\NetSuiteConnector\Discount\Model\Config\DiscountConfig $discountConfig,
        private readonly \Magento\Framework\Event\ManagerInterface $eventManager,
        private readonly \MageOS\NetSuiteConnector\Order\Model\Mapper\OrderExport\OrderItemList $nsOrderItemList,
        private readonly \MageOS\NetSuiteConnector\Order\Model\Mapper\OrderExport\Location $nsLocation
    ) {
    }

    public function processItem(
        SalesOrder $netsuiteOrder,
        SalesOrderItem $netsuiteItem,
        OrderItemInterface $magentoItem,
        ProductInterface $product,
        OrderInterface $magentoOrder
    ): void {
        if (!$this->discountConfig->isLogicSwitchActive(LogicSwitcher::LINE)
            || !$this->discountConfig->getDisableOrderLevelDiscount()
        ) {
            return;
        }
        $parentItem = $magentoItem->getParentItem();
        if ($magentoItem->getProductType() === Type::TYPE_SIMPLE
            && $parentItem
            && $parentItem->getProductType() === Configurable::TYPE_CODE
        ) {
            $discountAmount = (float)$parentItem->getDiscountAmount();
        } else {
            $discountAmount = (float)$magentoItem->getDiscountAmount();
        }
        if (abs($discountAmount) > 0.001) {
            $netsuiteOrderItem = $this->createDiscountItem($magentoOrder, -(abs($discountAmount)));
            $this->nsOrderItemList->addOrderItemToList($netsuiteOrder, $netsuiteOrderItem);
        }
    }

    public function process(SalesOrder $netsuiteOrder, OrderInterface $magentoOrder): void
    {
        if (!$this->discountConfig->isLogicSwitchActive(LogicSwitcher::LINE)) {
            return;
        }
        if ($this->discountConfig->getDisableOrderLevelDiscount()) {
            $this->addShippingDiscount($netsuiteOrder, $magentoOrder);
            return;
        }
        $this->addOrderLevelDiscount($netsuiteOrder, $magentoOrder);
    }

    private function addOrderLevelDiscount(SalesOrder $netsuiteOrder, OrderInterface $magentoOrder): void
    {
        $discountAmount = (float)$magentoOrder->getDiscountAmount();
        if (abs($discountAmount) > 0.001) {
            $netsuiteOrderItem = $this->createDiscountItem($magentoOrder, $discountAmount);
            $this->eventManager->dispatch(
                'netsuite_new_order_add_discount_before',
                ['magento_order' => $magentoOrder, 'discount' => $netsuiteOrderItem]
            );
            $this->nsOrderItemList->addOrderItemToList($netsuiteOrder, $netsuiteOrderItem);
        }
    }

    private function addShippingDiscount(SalesOrder $netsuiteOrder, OrderInterface $magentoOrder): void
    {
        $shippingDiscountAmount = (float)$magentoOrder->getShippingDiscountAmount();
        if (abs($shippingDiscountAmount) > 0.001) {
            $netsuiteOrderItem = $this->createDiscountItem($magentoOrder, -(abs($shippingDiscountAmount)));
            $netsuiteOrderItem->description = self::SHIPPING_DISCOUNT_ITEM_DESCRIPTION;
            $this->nsOrderItemList->addOrderItemToList($netsuiteOrder, $netsuiteOrderItem);
        }
    }

    private function createDiscountItem(OrderInterface $magentoOrder, float $discountAmount): SalesOrderItem
    {
        $netsuiteOrderItem = new SalesOrderItem();
        $netsuiteOrderItem->description = $magentoOrder->getCouponCode() ?? self::DISCOUNT_ITEM_DESCRIPTION;
        $netsuiteOrderItem->item = new RecordRef();
        $netsuiteOrderItem->item->type = RecordType::discountItem;
        $netsuiteOrderItem->item->internalId = $this->discountConfig->getDiscountItemId();
        $netsuiteOrderItem->price = new RecordRef();
        $netsuiteOrderItem->price->internalId = -1;
        $netsuiteOrderItem->amount = $discountAmount;
        $netsuiteOrderItem->rate = $discountAmount;
        $netsuiteOrderItem->isTaxable = false;
        $this->nsLocation->addLocation($netsuiteOrderItem);
        return $netsuiteOrderItem;
    }
}
