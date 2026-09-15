<?php

namespace MageOS\NetSuiteConnector\Test\Integration\Inventory\Service\PostProcess;

use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use MageOS\NetSuiteConnector\Core\Enum\Message\Status;
use MageOS\NetSuiteConnector\Core\Model\Monitor\Data\Process;
use MageOS\NetSuiteConnector\Inventory\Service\PostProcess\CompensateReservation;
use MageOS\NetSuiteConnector\Order\Model\Process\Export\OrderPlace;
use MageOS\NetSuiteConnector\Queue\Model\Monitor\MonitorItem;
use MageOS\NetSuiteConnector\Test\Integration\Core\Fixtures\Locator;

/**
 * @magentoDbIsolation enabled
 */
class CompensateReservationTest extends \PHPUnit\Framework\TestCase
{
    private const RELATIVE_PATH_TO_FIXTURES = '../../../Order/';

    private \Magento\TestFramework\ObjectManager $objectManager;

    public static function setUpBeforeClass(): void
    {
        $fixturesUsed = [
            '_files/default_rollback.php',
            '_files/product_simple.php',
            '_files/address_data.php',
            '_files/customer.php',
            '_files/order.php',
        ];

        $path = realpath(__DIR__ . "/" . self::RELATIVE_PATH_TO_FIXTURES) . "/";

        Locator::copy($path, $fixturesUsed);
    }

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
    }

    /**
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/customer.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/order.php
     */
    public function testDoneTwiceCompensatesExactlyOnce(): void
    {
        $order = $this->getOrder();
        $service = $this->objectManager->create(CompensateReservation::class);

        $service->process($this->getMessage($order), Status::DONE());
        $service->process($this->getMessage($order), Status::DONE());

        $this->assertSame(1, $this->countCompensationReservations((int)$order->getEntityId()));

        $reloadedOrder = $this->getOrder();
        $this->assertEquals(1, $reloadedOrder->getData('netsuite_reservation_compensated'));
    }

    /**
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/customer.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/order.php
     */
    public function testErrorNeverCompensates(): void
    {
        $order = $this->getOrder();
        $service = $this->objectManager->create(CompensateReservation::class);

        $service->process($this->getMessage($order), Status::ERROR());

        $this->assertSame(0, $this->countCompensationReservations((int)$order->getEntityId()));

        $reloadedOrder = $this->getOrder();
        $this->assertNull($reloadedOrder->getData('netsuite_reservation_compensated'));
    }

    /**
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/customer.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/order.php
     */
    public function testErrorThenDoneCompensatesExactlyOnce(): void
    {
        $order = $this->getOrder();
        $service = $this->objectManager->create(CompensateReservation::class);

        $service->process($this->getMessage($order), Status::ERROR());
        $service->process($this->getMessage($order), Status::DONE());

        $this->assertSame(1, $this->countCompensationReservations((int)$order->getEntityId()));
    }

    private function getOrder(): \Magento\Sales\Api\Data\OrderInterface
    {
        $order = $this->objectManager->create(\Magento\Sales\Model\Order::class);
        $order->loadByIncrementId('100000001');

        return $order;
    }

    private function getMessage(\Magento\Sales\Api\Data\OrderInterface $order): MonitorItem
    {
        $message = $this->objectManager->create(MonitorItem::class);
        $message->setProcess(Process::EXPORT());
        $message->setEntity(OrderPlace::MESSAGE_ACTION);
        $message->setItemId((int)$order->getEntityId());

        return $message;
    }

    private function countCompensationReservations(int $orderId): int
    {
        $resourceConnection = $this->objectManager->get(ResourceConnection::class);
        $connection = $resourceConnection->getConnection();
        $table = $resourceConnection->getTableName('inventory_reservation');

        $select = $connection->select()
            ->from($table, ['metadata'])
            ->where('metadata LIKE ?', '%"event_type":"shipment_created"%');

        $count = 0;
        foreach ($connection->fetchCol($select) as $metadataJson) {
            $metadata = json_decode((string)$metadataJson, true);
            if (($metadata['object_id'] ?? null) === (string)$orderId) {
                $count++;
            }
        }

        return $count;
    }
}
