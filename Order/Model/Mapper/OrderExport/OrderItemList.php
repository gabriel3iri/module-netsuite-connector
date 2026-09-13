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

namespace MageOS\NetSuiteConnector\Order\Model\Mapper\OrderExport;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use NetSuite\Classes\SalesOrder;
use NetSuite\Classes\SalesOrderItem;
use NetSuite\Classes\SalesOrderItemList;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderItemProcessorInterface;

class OrderItemList implements OrderItemProcessorInterface
{
    public function __construct(
        private readonly \Magento\Framework\Event\ManagerInterface $eventManager
    ) {
    }

    public function processItem(
        SalesOrder $netsuiteOrder,
        SalesOrderItem $netsuiteItem,
        OrderItemInterface $magentoItem,
        ProductInterface $product,
        OrderInterface $magentoOrder
    ): void {
        $this->eventManager->dispatch('netsuite_new_order_item_send_before', [
            'magento_order' => $magentoOrder,
            'netsuite_order' => $netsuiteOrder,
            'magento_order_item' => $magentoItem,
            'netsuite_order_item' => $netsuiteItem
        ]);
        $this->addOrderItemToList($netsuiteOrder, $netsuiteItem);
    }

    public function initOrderItemList(SalesOrder $netsuiteOrder)
    {
        $netsuiteOrder->itemList = new SalesOrderItemList();
        $netsuiteOrder->itemList->item = [];
    }

    public function addOrderItemToList(SalesOrder $netsuiteOrder, SalesOrderItem $netsuiteOrderItem)
    {
        if (!$netsuiteOrder->itemList || !$netsuiteOrder->itemList->item) {
            $this->initOrderItemList($netsuiteOrder);
        }
        $netsuiteOrder->itemList->item[] = $netsuiteOrderItem;
    }
}
