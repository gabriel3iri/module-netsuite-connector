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

// phpcs:disable
namespace MageOS\NetSuiteConnector\Test\Integration\Shipment\MultiSource\Model\Process\Import;

use NetSuite\Classes\ItemFulfillment;
use NetSuite\Classes\ItemFulfillmentItem;
use NetSuite\Classes\ItemFulfillmentItemList;
use NetSuite\Classes\ItemFulfillmentShipStatus;
use MageOS\NetSuiteConnector\Test\Integration\Core\Fixtures\Locator;
use Magento\Sales\Model\Order\Shipment;
use Magento\TestFramework\Helper\Bootstrap;
use NetSuite\Classes\Address;
use NetSuite\Classes\Country;
use MageOS\NetSuiteConnector\Test\Integration\Core\Model\NSRecordBuilder;

/**
 * Covers the multi source shipment mapper importing a single NetSuite fulfillment that spans two mapped locations.
 * @SuppressWarnings(PHPMD)
 */
class ShipmentTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Magento\TestFramework\ObjectManager
     */
    private $objectManager;

    /**
     * @var \MageOS\NetSuiteConnector\Core\Helper\Data|\PHPUnit\Framework\MockObject\MockObject
     */
    private static $nsHelper;

    /**
     * @var \MageOS\NetSuiteConnector\Test\Integration\Core\Helper\NetSuiteServiceFaker
     */
    private static $netsuiteServiceFaker;

    /**
     * @var array
     */
    private $parameters = [
        'by_field' => 'externalIdString',
        'search_success' => 0,
        'netsuite_internal_id' => 2,
        'get_success' => 1,
        'add_success' => 1
    ];

    /**
     * Path to _files/_files_ns/... folders
     */
    const RELATIVE_PATH_TO_FIXTURES = '../../../';

    public static function setUpBeforeClass(): void
    {
        $fixturesUsed = [
            '_files_ns_response/Customer_get',
            '_files/address_data.php',
            '_files/customer.php',
            '_files/customer_rollback.php',
            '_files/default_rollback.php',
            '_files/locations.php',
            '_files/locations_rollback.php',
            '_files/order.php',
            '_files/order_rollback.php',
            '_files/order_set_netsuite_id.php',
            '_files/product_simple.php',
            '_files/product_simple_rollback.php',
        ];

        $path = realpath(__DIR__ . "/" . self::RELATIVE_PATH_TO_FIXTURES) . "/";

        Locator::copy($path, $fixturesUsed);
    }

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();

        if (!self::$netsuiteServiceFaker) {
            $path = realpath(__DIR__ . "/" . self::RELATIVE_PATH_TO_FIXTURES) . "/";
            self::$netsuiteServiceFaker = new \MageOS\NetSuiteConnector\Test\Integration\Core\Helper\NetSuiteServiceFaker($path);
        }

        if (!self::$nsHelper) {
            self::$nsHelper = $this->getMockBuilder(\MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management::class)
                ->onlyMethods(['get'])
                ->setConstructorArgs(
                    [
                        $this->objectManager->create(\MageOS\NetSuiteConnector\Core\Registry\ModuleRegistry::class),
                        $this->objectManager->create(\MageOS\NetSuiteConnector\Core\Model\Config\ConnectorConfig::class),
                        $this->objectManager->create(\MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\ConstructorFactory::class)
                    ]
                )
                ->getMock();
        }

        $this->objectManager->configure([\MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management::class => ['shared' => true]]);
        $this->objectManager->addSharedInstance(self::$nsHelper,
            \MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management::class);
        self::$netsuiteServiceFaker->setParameters($this->parameters);
        $this->setNetSuiteFaker();
    }

    /**
     * Helper methods for tests
     */
    private function setNetSuiteFaker()
    {
        self::$nsHelper->method('get')
            ->willReturn(self::$netsuiteServiceFaker);
    }

    /**
     * A fulfillment whose items belong to two different mapped NetSuite locations becomes two Magento shipments,
     * one per location, each carrying only its own items, when mapped through the shipment mapper resolver
     * (the production entry point that picks the multi source mapper for the configured inventory mode).
     *
     * @magentoConfigFixture default/mageos_netsuite/general/inventory_mode multi
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/locations.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/customer.php
     * @magentoDataFixtureBeforeTransaction MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/order.php
     * @magentoDataFixtureBeforeTransaction MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/order_set_netsuite_id.php
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     */
    public function testThatItSplitsAShipmentAcrossMappedLocations()
    {
        $objectManager = Bootstrap::getObjectManager();

        $shipment = NSRecordBuilder::aRecord(ItemFulfillment::class)
            ->withInternalId(1001)
            ->withCreatedFrom(1)
            ->withShippingAddress($this->createAddress())
            ->withShipStatus(ItemFulfillmentShipStatus::_shipped)
            ->withEntity(NSRecordBuilder::createRecordRef(1))
            ->withItemList($this->createShipmentItemListForTwoLocations())
            ->build();

        $order = $objectManager->create(\Magento\Sales\Model\Order::class);
        $order->loadByIncrementId('100000001');

        /** @var \MageOS\NetSuiteConnector\Shipment\Model\Mapper\ShipmentInterface $shipmentMapper */
        $shipmentMapper = $objectManager->get(\MageOS\NetSuiteConnector\Shipment\Model\Mapper\ShipmentInterface::class);
        $magentoShipments = $shipmentMapper->getMagentoFormat($shipment);

        $this->assertCount(2, $magentoShipments);
        $this->assertNotSame($magentoShipments[0], $magentoShipments[1]);
        $this->assertNotSame($magentoShipments[0]->getAllItems(), $magentoShipments[1]->getAllItems());

        $totalQty = 0.0;
        foreach ($magentoShipments as $magentoShipment) {
            /** @var Shipment $magentoShipment */
            $this->assertEquals($order->getEntityId(), $magentoShipment->getOrderId());
            $this->assertEquals($order->getStoreId(), $magentoShipment->getStoreId());
            $this->assertEquals($order->getCustomerId(), $magentoShipment->getCustomerId());
            $this->assertEquals($order->getBillingAddressId(), $magentoShipment->getBillingAddressId());

            $items = $magentoShipment->getAllItems();
            $this->assertCount(1, $items);
            $item = reset($items);
            $this->assertEquals('simple', $item->getSku());
            $this->assertEquals(1, $item->getQty());
            $totalQty += (float)$magentoShipment->getTotalQty();
        }
        $this->assertEquals(2.0, $totalQty);

        $reloadedOrder = $objectManager->create(\Magento\Sales\Model\Order::class);
        $reloadedOrder->loadByIncrementId('100000001');
        $shippingAddress = $reloadedOrder->getShippingAddress();
        $this->assertEquals('US', $shippingAddress->getCountryId());
        $this->assertEquals(['4th avenue'], $shippingAddress->getStreet());
        $this->assertEquals('NY', $shippingAddress->getRegionCode());
        $this->assertEquals('10001', $shippingAddress->getPostcode());
    }

    /**
     * A fulfillment with two mapped locations goes through the full import pipeline in multi mode and produces
     * one Magento shipment per location, each carrying the matched Magento source code. Importing the very same
     * fulfillment again updates those two shipments in place and creates no third one.
     *
     * @magentoConfigFixture default/mageos_netsuite/general/inventory_mode multi
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/locations.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/customer.php
     * @magentoDataFixtureBeforeTransaction MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/order.php
     * @magentoDataFixtureBeforeTransaction MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/order_set_netsuite_id.php
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     */
    public function testThatFullImportProducesTwoShipmentsAndReimportUpdatesThemInPlace()
    {
        $objectManager = Bootstrap::getObjectManager();

        $shipment = NSRecordBuilder::aRecord(ItemFulfillment::class)
            ->withInternalId(1001)
            ->withCreatedFrom(1)
            ->withShippingAddress($this->createAddress())
            ->withShipStatus(ItemFulfillmentShipStatus::_shipped)
            ->withEntity(NSRecordBuilder::createRecordRef(1))
            ->withItemList($this->createShipmentItemListForTwoLocations())
            ->build();

        /** @var \MageOS\NetSuiteConnector\Shipment\Model\Process\Import\Shipment $shipmentImport */
        $shipmentImport = $objectManager->get(\MageOS\NetSuiteConnector\Shipment\Model\Process\Import\Shipment::class);
        $shipmentImport->process($shipment);

        $order = $objectManager->create(\Magento\Sales\Model\Order::class);
        $order->loadByIncrementId('100000001');

        $magentoShipments = $this->shipmentsForOrder((int)$order->getEntityId());
        $this->assertCount(2, $magentoShipments, 'the fulfillment must produce one shipment per mapped location');

        $getSourceCodeByShipmentId = $objectManager->get(
            \Magento\InventoryShipping\Model\ResourceModel\ShipmentSource\GetSourceCodeByShipmentId::class
        );
        $sourceCodesByShipmentId = [];
        $itemsCountByShipmentId = [];
        foreach ($magentoShipments as $magentoShipment) {
            $entityId = (int)$magentoShipment->getEntityId();
            $sourceCodesByShipmentId[$entityId] = $getSourceCodeByShipmentId->execute($entityId);
            $itemsCountByShipmentId[$entityId] = \count($magentoShipment->getAllItems());
        }
        $this->assertEqualsCanonicalizing(['source_1', 'source_2'], array_values($sourceCodesByShipmentId));
        $this->assertSame([1, 1], array_values($itemsCountByShipmentId));

        $shipmentImport->process($shipment);

        $magentoShipmentsAfterReimport = $this->shipmentsForOrder((int)$order->getEntityId());
        $this->assertCount(2, $magentoShipmentsAfterReimport, 'a re-import must not create a new shipment');

        $entityIdsAfterReimport = [];
        foreach ($magentoShipmentsAfterReimport as $magentoShipment) {
            $entityIdsAfterReimport[] = (int)$magentoShipment->getEntityId();
            $this->assertCount(
                1,
                $magentoShipment->getAllItems(),
                'a re-import must not duplicate the items on the shipment it updates'
            );
        }
        sort($entityIdsAfterReimport);
        $originalEntityIds = array_keys($sourceCodesByShipmentId);
        sort($originalEntityIds);
        $this->assertSame(
            $originalEntityIds,
            $entityIdsAfterReimport,
            'a re-import must update the same two shipments, not replace them'
        );
    }

    /**
     * Loads every Magento shipment saved for the given order.
     *
     * @param int $orderId
     * @return \Magento\Sales\Api\Data\ShipmentInterface[]
     */
    private function shipmentsForOrder(int $orderId): array
    {
        $objectManager = Bootstrap::getObjectManager();
        $searchCriteriaBuilder = $objectManager->create(\Magento\Framework\Api\SearchCriteriaBuilder::class);
        $searchCriteria = $searchCriteriaBuilder->addFilter('order_id', $orderId)->create();
        $shipmentRepository = $objectManager->get(\Magento\Sales\Api\ShipmentRepositoryInterface::class);

        return array_values($shipmentRepository->getList($searchCriteria)->getItems());
    }

    /**
     * @return Address
     */
    private function createAddress(): Address
    {
        $address = new Address();
        $address->country = Country::_unitedStates;
        $address->state = 'NY';
        $address->zip = '10001';
        $address->addr1 = '4th avenue';
        return $address;
    }

    /**
     * Builds a fulfillment item list where two items of the same order line ship from two different NetSuite
     * locations, each mapped to its own Magento source by the "locations.php" fixture.
     *
     * @return ItemFulfillmentItemList
     */
    private function createShipmentItemListForTwoLocations(): ItemFulfillmentItemList
    {
        $itemAtLocationOne = new ItemFulfillmentItem();
        $itemAtLocationOne->item = NSRecordBuilder::createRecordRef(1);
        $itemAtLocationOne->quantity = 1;
        $itemAtLocationOne->location = NSRecordBuilder::createRecordRef(1);

        $itemAtLocationTwo = new ItemFulfillmentItem();
        $itemAtLocationTwo->item = NSRecordBuilder::createRecordRef(1);
        $itemAtLocationTwo->quantity = 1;
        $itemAtLocationTwo->location = NSRecordBuilder::createRecordRef(2);

        $itemList = new ItemFulfillmentItemList();
        $itemList->item = [
            $itemAtLocationOne,
            $itemAtLocationTwo,
        ];

        return $itemList;
    }
}
