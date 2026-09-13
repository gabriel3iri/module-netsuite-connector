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

namespace MageOS\NetSuiteConnector\Test\Unit\Order\Model\Mapper\OrderExport;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use MageOS\NetSuiteConnector\Order\Model\Mapper\OrderExport\OrderItemList;
use NetSuite\Classes\SalesOrder;
use NetSuite\Classes\SalesOrderItem;
use PHPUnit\Framework\TestCase;

class OrderItemListTest extends TestCase
{
    public function testTheSendBeforeEventSeesTheItemBeforeItIsAppended(): void
    {
        $netsuiteOrder = new SalesOrder();
        $netsuiteItem = new SalesOrderItem();
        $magentoOrder = $this->createStub(OrderInterface::class);
        $magentoItem = $this->createStub(OrderItemInterface::class);

        $itemsAtDispatch = null;
        $eventManager = $this->createMock(ManagerInterface::class);
        $eventManager->expects($this->once())
            ->method('dispatch')
            ->with(
                'netsuite_new_order_item_send_before',
                [
                    'magento_order' => $magentoOrder,
                    'netsuite_order' => $netsuiteOrder,
                    'magento_order_item' => $magentoItem,
                    'netsuite_order_item' => $netsuiteItem
                ]
            )
            ->willReturnCallback(static function () use ($netsuiteOrder, &$itemsAtDispatch): void {
                $itemsAtDispatch = $netsuiteOrder->itemList->item ?? [];
            });

        (new OrderItemList($eventManager))->processItem(
            $netsuiteOrder,
            $netsuiteItem,
            $magentoItem,
            $this->createStub(ProductInterface::class),
            $magentoOrder
        );

        $this->assertSame([], $itemsAtDispatch);
        $this->assertSame([$netsuiteItem], $netsuiteOrder->itemList->item);
    }
}
