<?php
//phpcs:ignoreFile

namespace MageOS\NetSuiteConnector\Test\Integration\Order\Model\Process\Export;

use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use NetSuite\Classes\AddRequest;
use MageOS\NetSuiteConnector\Core\Api\Data\MessageInterface;
use MageOS\NetSuiteConnector\Core\Enum\Message\Queue;
use MageOS\NetSuiteConnector\Core\Exception\DataIntegrityException;
use MageOS\NetSuiteConnector\Test\Integration\Core\Fixtures\Locator;
use MageOS\NetSuiteConnector\Test\Integration\Core\Helper\RequestSnapshot;

/**
 * Pins the line order of an exported sales order when discounts and taxes
 * are both present, which no pre-existing test covers together, and pins
 * that a failed order's tax never leaks into a later order.
 *
 * @SuppressWarnings(PHPMD)
 */
class OrderLinesTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Path to _files/_files_ns/... folders
     */
    private const RELATIVE_PATH_TO_FIXTURES = '../../../';

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
     * @var RequestSnapshot
     */
    private $requestSnapshot;

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
     * {@inheritDoc}
     */
    public static function setUpBeforeClass(): void
    {
        $fixturesUsed = [
            '_files/default_rollback.php',
            '_files/product_simple_taxable.php',
            '_files/address_data.php',
            '_files/customer.php',
            '_files/quote.php',
            '_files/order_with_discounts_and_tax.php',
            '_files/order_tax_isolation_pair.php',
            '_files/order_with_body_shipping_discount.php',
            '_files/submit_order_to_ns_queue.php',
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
                        $this->objectManager->create(
                            \MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\ConstructorFactory::class
                        )
                    ]
                )
                ->getMock();
        }

        $this->objectManager->configure([
            \MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management::class => ['shared' => true]
        ]);
        $this->objectManager->addSharedInstance(
            self::$nsHelper,
            \MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management::class
        );
        self::$netsuiteServiceFaker->setParameters($this->parameters);
        $this->setNetSuiteFaker();
        $this->requestSnapshot = new RequestSnapshot();
    }

    /**
     * C2: order-level discount mode. The discount line sits before the tax
     * line, and no shipping discount line appears even though the order has
     * a non-zero shipping discount amount, because that line is gated by
     * disable_order_level_discount, not by the amount.
     *
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/customer.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/quote.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/order_with_discounts_and_tax.php
     * phpcs:disable
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/submit_order_to_ns_queue.php
     * @magentoConfigFixture default/mageos_netsuite/payment_methods/netsuite_mapping [{"payment_method":"checkmo","payment_cc":"","internal_netsuite_id":"1"}]
     * @magentoConfigFixture default/mageos_netsuite/shipping_methods/netsuite_default_shipping_id 2
     * @magentoConfigFixture default/mageos_netsuite/shipping_methods/netsuite_mapping {"_1598626367813_813":{"shipping_method":"flatrate_flatrate","shipping_description":"","internal_netsuite_id":"2"}}
     * phpcs:enable
     * @magentoConfigFixture default/mageos_netsuite/orders/logic_switch line
     * @magentoConfigFixture default/mageos_netsuite/tax/tax_logic tax_item_line
     * @magentoConfigFixture default/mageos_netsuite/tax/tax_item_internal_netsuite_id 9
     * @magentoConfigFixture default/mageos_netsuite/orders/discount_item_id 8
     * @magentoAppIsolation enabled
     */
    public function testOrderLevelDiscountBeforeTax()
    {
        \Magento\TestFramework\Helper\Bootstrap::getInstance()
            ->loadArea(\Magento\Framework\App\Area::AREA_FRONTEND);

        $message = $this->getMessage();
        $orderPlaceProcess = $this->objectManager->create(
            \MageOS\NetSuiteConnector\Order\Model\Process\Export\OrderPlace::class
        );
        $orderPlaceProcess->process($message);

        /** @var AddRequest $addRequest */
        $addRequest = self::$netsuiteServiceFaker->getAddRequest();
        $this->assertNotNull($addRequest);

        $items = $addRequest->record->itemList->item;
        $descriptions = array_map(static fn ($item) => $item->description, $items);

        $this->assertCount(4, $items, 'Expected 2 product lines, 1 discount line, 1 tax line');
        $this->assertNotContains('Shipping Discount', $descriptions);
        $this->assertSame('Discount', $descriptions[2]);
        $this->assertSame('Sales tax', $descriptions[3]);
        $this->assertEqualsWithDelta(3.5, $items[3]->amount, 0.001, 'item tax (2.25+1.00) + shipping tax (0.25)');

        $this->requestSnapshot->assertRecordMatches(
            $this->getSnapshotFile('OrderLinesOrderLevel-new'),
            $addRequest,
            ['tranDate', 'externalId']
        );

        $this->cleanUpQueue();
    }

    /**
     * C3: item-level discount mode with promotion data. Each product line
     * is followed immediately by its own discount line, then the shipping
     * discount line, then the tax line; each product line carries the
     * promotion amount custom field.
     *
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/customer.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/quote.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/order_with_discounts_and_tax.php
     * phpcs:disable
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/submit_order_to_ns_queue.php
     * @magentoConfigFixture default/mageos_netsuite/payment_methods/netsuite_mapping [{"payment_method":"checkmo","payment_cc":"","internal_netsuite_id":"1"}]
     * @magentoConfigFixture default/mageos_netsuite/shipping_methods/netsuite_default_shipping_id 2
     * @magentoConfigFixture default/mageos_netsuite/shipping_methods/netsuite_mapping {"_1598626367813_813":{"shipping_method":"flatrate_flatrate","shipping_description":"","internal_netsuite_id":"2"}}
     * phpcs:enable
     * @magentoConfigFixture default/mageos_netsuite/orders/logic_switch line
     * @magentoConfigFixture default/mageos_netsuite/tax/tax_logic tax_item_line
     * @magentoConfigFixture default/mageos_netsuite/tax/tax_item_internal_netsuite_id 9
     * @magentoConfigFixture default/mageos_netsuite/orders/discount_item_id 8
     * @magentoConfigFixture default/mageos_netsuite/orders/disable_order_level_discount 1
     * @magentoConfigFixture default/mageos_netsuite/orders/add_promotion_data 1
     * @magentoAppIsolation enabled
     */
    public function testItemLevelDiscountWithPromotionData()
    {
        \Magento\TestFramework\Helper\Bootstrap::getInstance()
            ->loadArea(\Magento\Framework\App\Area::AREA_FRONTEND);

        $message = $this->getMessage();
        $orderPlaceProcess = $this->objectManager->create(
            \MageOS\NetSuiteConnector\Order\Model\Process\Export\OrderPlace::class
        );
        $orderPlaceProcess->process($message);

        /** @var AddRequest $addRequest */
        $addRequest = self::$netsuiteServiceFaker->getAddRequest();
        $this->assertNotNull($addRequest);

        $items = $addRequest->record->itemList->item;
        $descriptions = array_map(static fn ($item) => $item->description, $items);

        $this->assertCount(6, $items, 'item1, discount1, item2, discount2, shipping discount, tax');
        $this->assertSame('Discount', $descriptions[1]);
        $this->assertSame('Discount', $descriptions[3]);
        $this->assertSame('Shipping Discount', $descriptions[4]);
        $this->assertSame('Sales tax', $descriptions[5]);

        $this->assertEqualsWithDelta(-1.25, $items[1]->amount, 0.001);
        $this->assertEqualsWithDelta(-0.50, $items[3]->amount, 0.001);
        $this->assertEqualsWithDelta(-1.00, $items[4]->amount, 0.001);

        foreach ([$items[0], $items[2]] as $productLine) {
            $this->assertNotNull($productLine->customFieldList, 'product line must carry promotion custom field');
            $scriptIds = array_map(
                static fn ($field) => $field->scriptId,
                $productLine->customFieldList->customField
            );
            $this->assertContains('custcol_rw_cc_promotion_amount', $scriptIds);
        }

        $this->requestSnapshot->assertRecordMatches(
            $this->getSnapshotFile('OrderLinesItemLevel-new'),
            $addRequest,
            ['tranDate', 'externalId']
        );

        $this->cleanUpQueue();
    }

    /**
     * C4: body discount and netsuite-processor tax mode. The customFieldList
     * order is: the mapped simple custom field, the coupon code field, then
     * the tax amount and total amount fields the tax processor appends last.
     *
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/customer.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/quote.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/order_with_discounts_and_tax.php
     * phpcs:disable
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/submit_order_to_ns_queue.php
     * @magentoConfigFixture default/mageos_netsuite/payment_methods/netsuite_mapping [{"payment_method":"checkmo","payment_cc":"","internal_netsuite_id":"1"}]
     * @magentoConfigFixture default/mageos_netsuite/shipping_methods/netsuite_default_shipping_id 2
     * @magentoConfigFixture default/mageos_netsuite/shipping_methods/netsuite_mapping {"_1598626367813_813":{"shipping_method":"flatrate_flatrate","shipping_description":"","internal_netsuite_id":"2"}}
     * @magentoConfigFixture default/mageos_netsuite/orders/custom_fields_mapping [{"netsuite_field_type":"simple","netsuite_field_name":"custbody_test_mapped_field","value_type":"fixed","value":"mapped-value"}]
     * phpcs:enable
     * @magentoConfigFixture default/mageos_netsuite/orders/logic_switch body
     * @magentoConfigFixture default/mageos_netsuite/orders/discount_item_id 8
     * @magentoConfigFixture default/mageos_netsuite/tax/tax_logic netsuite_processor
     * @magentoConfigFixture default/mageos_netsuite/tax/sales_order_tax_amount_id custbody_magento_tax_amount
     * @magentoConfigFixture default/mageos_netsuite/tax/sales_order_total_amount_id custbody_magento_total_amount
     * @magentoAppIsolation enabled
     */
    public function testBodyModeCustomFieldOrderWithNetsuiteTax()
    {
        \Magento\TestFramework\Helper\Bootstrap::getInstance()
            ->loadArea(\Magento\Framework\App\Area::AREA_FRONTEND);

        $message = $this->getMessage();
        $orderPlaceProcess = $this->objectManager->create(
            \MageOS\NetSuiteConnector\Order\Model\Process\Export\OrderPlace::class
        );
        $orderPlaceProcess->process($message);

        /** @var AddRequest $addRequest */
        $addRequest = self::$netsuiteServiceFaker->getAddRequest();
        $this->assertNotNull($addRequest);
        $this->assertNotNull($addRequest->record->customFieldList);

        $scriptIds = array_map(
            static fn ($field) => $field->scriptId,
            $addRequest->record->customFieldList->customField
        );

        $this->assertSame(
            [
                'custbody_test_mapped_field',
                'custbody_rw_cf_coupon_codes',
                'custbody_magento_tax_amount',
                'custbody_magento_total_amount',
            ],
            $scriptIds
        );

        $this->requestSnapshot->assertRecordMatches(
            $this->getSnapshotFile('OrderBodyNetsuiteTax-new'),
            $addRequest,
            ['tranDate', 'externalId']
        );

        $this->cleanUpQueue();
    }

    /**
     * Review High 7: body mode must not count the shipping discount twice.
     * Magento's order-level discount_amount already includes
     * shipping_discount_amount, so adding the shipping amount again on top
     * of it doubles the shipping portion of the discount. A discount_amount
     * of -11 that already contains a shipping_discount_amount of 1 must
     * produce a discountRate of 11, not 12.
     *
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/customer.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/quote.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/order_with_body_shipping_discount.php
     * phpcs:disable
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/submit_order_to_ns_queue.php
     * @magentoConfigFixture default/mageos_netsuite/payment_methods/netsuite_mapping [{"payment_method":"checkmo","payment_cc":"","internal_netsuite_id":"1"}]
     * @magentoConfigFixture default/mageos_netsuite/shipping_methods/netsuite_default_shipping_id 2
     * @magentoConfigFixture default/mageos_netsuite/shipping_methods/netsuite_mapping {"_1598626367813_813":{"shipping_method":"flatrate_flatrate","shipping_description":"","internal_netsuite_id":"2"}}
     * phpcs:enable
     * @magentoConfigFixture default/mageos_netsuite/orders/logic_switch body
     * @magentoConfigFixture default/mageos_netsuite/orders/discount_item_id 8
     * @magentoAppIsolation enabled
     */
    public function testBodyModeShippingDiscountNotDoubleCounted()
    {
        \Magento\TestFramework\Helper\Bootstrap::getInstance()
            ->loadArea(\Magento\Framework\App\Area::AREA_FRONTEND);

        $message = $this->getMessage();
        $orderPlaceProcess = $this->objectManager->create(
            \MageOS\NetSuiteConnector\Order\Model\Process\Export\OrderPlace::class
        );
        $orderPlaceProcess->process($message);

        /** @var AddRequest $addRequest */
        $addRequest = self::$netsuiteServiceFaker->getAddRequest();
        $this->assertNotNull($addRequest);

        $this->assertEqualsWithDelta(
            11.0,
            $addRequest->record->discountRate,
            0.001,
            'discount_amount already includes shipping_discount_amount, must not be added twice'
        );

        $this->cleanUpQueue();
    }

    /**
     * C5: a failed order's collected item tax must not leak into the next
     * order exported through the same, shared tax manager. Removed by
     * construction, because the accumulator is keyed by the SalesOrder object
     * that every getNetsuiteFormat() call creates. Fails on the pre-refactor
     * code, where the accumulator is a property of the shared plugin/manager
     * instance.
     *
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/customer.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/order_tax_isolation_pair.php
     * phpcs:disable
     * @magentoConfigFixture default/mageos_netsuite/payment_methods/netsuite_mapping [{"payment_method":"checkmo","payment_cc":"","internal_netsuite_id":"1"}]
     * @magentoConfigFixture default/mageos_netsuite/shipping_methods/netsuite_default_shipping_id 2
     * @magentoConfigFixture default/mageos_netsuite/shipping_methods/netsuite_mapping {"_1598626367813_813":{"shipping_method":"flatrate_flatrate","shipping_description":"","internal_netsuite_id":"2"}}
     * phpcs:enable
     * @magentoConfigFixture default/mageos_netsuite/orders/order_skip_discount 1
     * @magentoConfigFixture default/mageos_netsuite/tax/tax_item_internal_netsuite_id 9
     * @magentoAppIsolation enabled
     */
    public function testTaxDoesNotLeakBetweenOrders()
    {
        \Magento\TestFramework\Helper\Bootstrap::getInstance()
            ->loadArea(\Magento\Framework\App\Area::AREA_FRONTEND);

        $orderRepository = $this->objectManager->get(OrderRepositoryInterface::class);
        $orderA = $this->objectManager->create(\Magento\Sales\Model\Order::class);
        $orderA->loadByIncrementId('100000010');
        $orderB = $this->objectManager->create(\Magento\Sales\Model\Order::class);
        $orderB->loadByIncrementId('100000011');

        $orderMapper = $this->objectManager->create(\MageOS\NetSuiteConnector\Order\Model\Mapper\Order::class);

        $threw = false;
        try {
            $orderMapper->getNetsuiteFormat($orderA);
        } catch (DataIntegrityException $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'order A must throw because its second item has no netsuite_internal_id');

        $netsuiteOrderB = $orderMapper->getNetsuiteFormat($orderB);

        $taxLines = array_values(array_filter(
            $netsuiteOrderB->itemList->item,
            static fn ($item) => $item->description === 'Sales tax'
        ));
        $this->assertCount(1, $taxLines);
        $this->assertEqualsWithDelta(
            5.00,
            reset($taxLines)->amount,
            0.001,
            'B\'s own tax only, not leaked A + B'
        );
    }

    private function getMessage(): MessageInterface
    {
        /** @var \MageOS\NetSuiteConnector\Core\Api\MessageManagementInterface $messageManagement */
        $messageManagement = $this->objectManager->create(\MageOS\NetSuiteConnector\Core\Api\MessageManagementInterface::class);
        $messages = $messageManagement->receive(\MageOS\NetSuiteConnector\Core\Enum\Message\Queue::EXPORT(), 50);
        $this->assertCount(1, $messages);

        foreach ($messages as $originalMessage) {
            $message = $originalMessage;
        }

        return $message;
    }

    private function setNetSuiteFaker()
    {
        self::$nsHelper->method('get')
            ->willReturn(self::$netsuiteServiceFaker);
    }

    private function getSnapshotFile(string $fileName): string
    {
        return __DIR__ . "/../../../_files_ns_request/" . $fileName;
    }

    private function cleanUpQueue(): void
    {
        /** @var \MageOS\NetSuiteConnector\Core\Api\MessageManagementInterface $messageManagement */
        $messageManagement = $this->objectManager->get(\MageOS\NetSuiteConnector\Core\Api\MessageManagementInterface::class);
        $messages = $messageManagement->receive(Queue::EXPORT(), 50);
        foreach ($messages as $message) {
            $messageManagement->deleteById($message->getId());
        }
    }
}
