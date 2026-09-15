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

namespace MageOS\NetSuiteConnector\Test\Unit\Inventory\Service\PostProcess;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\OrderRepositoryInterface;
use MageOS\NetSuiteConnector\Core\Api\Data\MonitorItemInterface;
use MageOS\NetSuiteConnector\Core\Enum\Message\Status;
use MageOS\NetSuiteConnector\Core\Model\Monitor\Data\Process;
use MageOS\NetSuiteConnector\Inventory\Model\Sales\Order\InventoryReservation;
use MageOS\NetSuiteConnector\Inventory\Service\PostProcess\CompensateReservation;
use MageOS\NetSuiteConnector\Order\Model\Process\Export\OrderPlace;
use PHPUnit\Framework\TestCase;

/**
 * Covers the branches that must never touch the database or release stock: an ERROR status,
 * because NetSuite never received the order, and any message that is not an order_place export.
 */
class CompensateReservationTest extends TestCase
{
    private const ORDER_ID = 42;

    public function testErrorStatusNeverCompensates(): void
    {
        $service = $this->buildService();
        $message = $this->buildMessage(Process::EXPORT(), OrderPlace::MESSAGE_ACTION);

        $service->process($message, Status::ERROR());
    }

    public function testNonOrderPlaceExportNeverCompensates(): void
    {
        $service = $this->buildService();
        $message = $this->buildMessage(Process::EXPORT(), 'inventory_update');

        $service->process($message, Status::DONE());
    }

    public function testImportProcessNeverCompensates(): void
    {
        $service = $this->buildService();
        $message = $this->buildMessage(Process::IMPORT(), OrderPlace::MESSAGE_ACTION);

        $service->process($message, Status::DONE());
    }

    private function buildMessage(Process $process, string $entity): MonitorItemInterface
    {
        $message = $this->createStub(MonitorItemInterface::class);
        $message->method('getProcess')->willReturn($process);
        $message->method('getEntity')->willReturn($entity);
        $message->method('getItemId')->willReturn(self::ORDER_ID);

        return $message;
    }

    private function buildService(): CompensateReservation
    {
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $orderRepository->expects($this->never())->method('get');

        $inventoryReservation = $this->createMock(InventoryReservation::class);
        $inventoryReservation->expects($this->never())->method('execute');

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->expects($this->never())->method('getConnection');

        return new CompensateReservation($orderRepository, $inventoryReservation, $resourceConnection);
    }
}
