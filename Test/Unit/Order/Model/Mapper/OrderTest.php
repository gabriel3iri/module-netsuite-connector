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

namespace MageOS\NetSuiteConnector\Test\Unit\Order\Model\Mapper;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use MageOS\NetSuiteConnector\Core\Model\Pipeline\ProcessorSorter;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderItemProcessorInterface;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderProcessorInterface;
use MageOS\NetSuiteConnector\Order\Model\Mapper\Order;
use NetSuite\Classes\SalesOrder;
use NetSuite\Classes\SalesOrderItem;
use PHPUnit\Framework\TestCase;

class OrderTestWrongProcessor implements OrderItemProcessorInterface
{
    public function processItem(
        SalesOrder $netsuiteOrder,
        SalesOrderItem $netsuiteItem,
        OrderItemInterface $magentoItem,
        ProductInterface $product,
        OrderInterface $magentoOrder
    ): void {
    }
}

class OrderTestRecordingProcessor implements OrderProcessorInterface
{
    public function __construct(private readonly string $name)
    {
    }

    public function process(SalesOrder $netsuiteOrder, OrderInterface $magentoOrder): void
    {
        $netsuiteOrder->memo .= $this->name . ',';
    }
}

class OrderTest extends TestCase
{
    public function testItRejectsAProcessorOfTheWrongInterface(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Order(new ProcessorSorter(), [
            'wrong' => ['processor' => new OrderTestWrongProcessor(), 'sortOrder' => 100],
        ]);
    }

    public function testItRunsTheProcessorsInSortOrderOnTheReturnedOrder(): void
    {
        $order = new Order(new ProcessorSorter(), [
            'second' => ['processor' => new OrderTestRecordingProcessor('second'), 'sortOrder' => 200],
            'first' => ['processor' => new OrderTestRecordingProcessor('first'), 'sortOrder' => 100],
        ]);

        $netsuiteOrder = $order->getNetsuiteFormat($this->createStub(OrderInterface::class));

        $this->assertSame('first,second,', $netsuiteOrder->memo);
    }
}
