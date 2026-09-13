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

namespace MageOS\NetSuiteConnector\Test\Integration\Invoice\Model\Process\Export;

use Magento\TestFramework\Helper\Bootstrap;
use NetSuite\Classes\AddRequest;
use NetSuite\Classes\InitializeRequest;
use MageOS\NetSuiteConnector\Core\Api\Data\MessageInterface;
use MageOS\NetSuiteConnector\Core\Api\MessageManagementInterface;
use MageOS\NetSuiteConnector\Core\Enum\Message\Queue;
use MageOS\NetSuiteConnector\Core\Exception\DataIntegrityException;
use MageOS\NetSuiteConnector\Test\Integration\Core\Fixtures\Locator;

/**
 * Class InvoiceSaveTest -
 * @magentoDbIsolation enabled
 */
class InvoiceSaveTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Magento\TestFramework\ObjectManager
     */
    protected $objectManager;

    /**
     * @var \MageOS\NetSuiteConnector\Core\Helper\Data|\PHPUnit\Framework\MockObject\MockObject
     */
    private static $nsHelper;

    /**
     * @var \MageOS\NetSuiteConnector\Test\Integration\Core\Helper\NetSuiteServiceFaker
     */
    private static $netsuiteServiceFaker;

    /**
     * Path to _files/_files_ns/... folders
     */
    private const RELATIVE_PATH_TO_FIXTURES = '../../../';

    public static function setUpBeforeClass():void
    {
        $fixturesUsed = [
            '_files/address_data.php',
            '_files/customer.php',
            '_files/customer_rollback.php',
            '_files/default_rollback.php',
            '_files/product_simple.php',
            '_files/product_simple_rollback.php',
            '_files/order.php',
            '_files/order_set_netsuite_id.php',
            '_files/order_rollback.php',
            '_files/invoice.php',
            '_files/invoice_rollback.php',
            '_files/invoice_with_discount_and_tax.php'
        ];

        $path = realpath(__DIR__ . "/" . self::RELATIVE_PATH_TO_FIXTURES) . "/";

        Locator::copy($path, $fixturesUsed);
    }

    /**
     * $netusiteServicerFaker is a replacement class for WSDL Netsuite class
     * $nsHelper is a mock because we use getNetsuiteService() call to get access to WSDL Netsuite class
     *
     * Because how Magento & phpunit works, we need to have them as static values. Main reason - the Mock is
     * cached but on the second test we create a new instance of the Mock but the old one is actually still active
     * in Magento code.
     */
    protected function setUp():void
    {
        $objectManager = Bootstrap::getObjectManager();
        $this->objectManager = $objectManager;

        if (!self::$netsuiteServiceFaker) {
            $path = realpath(__DIR__ . "/" . self::RELATIVE_PATH_TO_FIXTURES) . "/";
            self::$netsuiteServiceFaker = new \MageOS\NetSuiteConnector\Test\Integration\Core\Helper\NetSuiteServiceFaker($path);
        }

        if (!self::$nsHelper) {
            self::$nsHelper = $this->getMockBuilder(\MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management::class)
                ->onlyMethods(['get'])
                ->disableOriginalConstructor()
                ->getMock();
        }

        $this->objectManager->configure([
            \MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management::class => ['shared' => true]
        ]);
        $this->objectManager->addSharedInstance(
            self::$nsHelper,
            \MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management::class
        );
    }

    /**
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/customer.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/order.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/order_set_netsuite_id.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/invoice.php
     * @magentoConfigFixture default/mageos_netsuite/orders/logic_switch line
     * @magentoConfigFixture default/mageos_netsuite/orders/discount_item_id 123
     * @magentoConfigFixture default/mageos_netsuite/tax/tax_item_internal_netsuite_id 124
     * @magentoAppIsolation enabled
     */
    public function testInvoiceExport()
    {
        $this->unsetSkipInvoiceExport();
        $parameters = [
            'netsuite_internal_id' => 11,
            'initialize_success' => 1,
            'add_success' => 1
        ];
        self::$netsuiteServiceFaker->setParameters($parameters);
        $this->setNetSuiteServiceFaker();

        $message = $this->getMessage();

        $invoiceSaveProcess = $this->objectManager->create(
            \MageOS\NetSuiteConnector\Invoice\Model\Process\Export\InvoiceSave::class
        );
        $invoiceSaveProcess->process($message);

        // load the invoice again, it should have a different internal_id
        $invoice = $this->loadInvoice();

        $this->assertEquals($parameters['netsuite_internal_id'], $invoice->getData('netsuite_internal_id'));

        /** @var InitializeRequest $initializeRequest */
        $initializeRequest = self::$netsuiteServiceFaker->getInitializeRequest();
        $expectedInitializeRequest = $this->getRequest('Invoice-export-initialize');

        $this->assertEquals($expectedInitializeRequest, $initializeRequest);

        /** @var AddRequest $addRequest */
        $addRequest = self::$netsuiteServiceFaker->getAddRequest();
        $expectedAddRequest = $this->getRequest('Invoice-export-add');
        // copy existing logic from original class: remove "shipGroupList" property
        $cashSale = $expectedAddRequest->record;
        $actualRecord = $addRequest->record;
        unset($cashSale->shipGroupList, $actualRecord->shipGroupList);
        foreach ($cashSale->itemList->item as $item) {
            unset($item->taxRate1);
        }
        foreach ($actualRecord->itemList->item as $item) {
            unset($item->taxRate1);
        }
        $this->assertEquals($expectedAddRequest, $addRequest);
    }

    /**
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/customer.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/order.php
     * phpcs:ignore
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/order_set_netsuite_id.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/invoice.php
     * @magentoConfigFixture default/mageos_netsuite/orders/logic_switch line
     * @magentoConfigFixture default/mageos_netsuite/orders/discount_item_id 123
     * @magentoConfigFixture default/mageos_netsuite/tax/tax_item_internal_netsuite_id 124
     * @magentoAppIsolation enabled
     */
    public function testInvoiceExportFailedInitializationRequest()
    {
        $this->expectException(DataIntegrityException::class);
        $this->unsetSkipInvoiceExport();
        $parameters = [
            'netsuite_internal_id' => 11,
            'initialize_success' => 0
        ];
        self::$netsuiteServiceFaker->setParameters($parameters);
        $this->setNetSuiteServiceFaker();

        $message = $this->getMessage();

        $invoiceSaveProcess = $this->objectManager->create(
            \MageOS\NetSuiteConnector\Invoice\Model\Process\Export\InvoiceSave::class
        );
        $invoiceSaveProcess->process($message);
    }

    /**

     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/customer.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/order.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/order_set_netsuite_id.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/invoice.php
     * @magentoConfigFixture default/mageos_netsuite/orders/logic_switch line
     * @magentoConfigFixture default/mageos_netsuite/orders/discount_item_id 123
     * @magentoConfigFixture default/mageos_netsuite/tax/tax_item_internal_netsuite_id 124
     * @magentoAppIsolation enabled
     */
    public function testInvoiceExportFailedAddRequest()
    {
        $this->expectException(\MageOS\NetSuiteConnector\Core\Exception\DataIntegrityException::class);
        $this->unsetSkipInvoiceExport();
        $parameters = [
            'netsuite_internal_id' => 11,
            'initialize_success' => 1,
            'add_success' => 0
        ];
        self::$netsuiteServiceFaker->setParameters($parameters);
        $this->setNetSuiteServiceFaker();

        $message = $this->getMessage();

        $invoiceSaveProcess = $this->objectManager->create(
            \MageOS\NetSuiteConnector\Invoice\Model\Process\Export\InvoiceSave::class
        );
        $invoiceSaveProcess->process($message);
    }

    /**
     * Unset flag to make it possible to run logic in observer
     *
     * This flag is set inside invoice.php fixture to skip observer when saving invoice from fixture
     */
    private function unsetSkipInvoiceExport()
    {
        $registry = $this->objectManager->get(\MageOS\NetSuiteConnector\Core\Registry\ModuleRegistry::class);
        $registry->unregister('netsuite_skip_invoice_export');
    }

    /**
     * Get invoice created inside fixtures
     *
     * @return \Magento\Sales\Model\Order\Invoice
     */
    private function loadInvoice()
    {
        /** @var \Magento\Sales\Model\Order $order */
        $order = $this->objectManager->create(\Magento\Sales\Model\Order::class);
        $order->loadByIncrementId('100000001');
        $invoice = $order->getInvoiceCollection()->getFirstItem();
        return $invoice;
    }

    /*
     * Helper methods for tests
     */
    private function setNetSuiteServiceFaker()
    {
        self::$nsHelper->method('get')
            ->willReturn(self::$netsuiteServiceFaker);
    }

    /**
     * Create Message which we process
     *
     * @return MessageInterface
     */
    private function getMessage(): MessageInterface
    {
        /** @var MessageManagementInterface $messageManagement */
        $messageManagement = $this->objectManager->get(MessageManagementInterface::class);
        $message = $messageManagement->createMessage(
            \MageOS\NetSuiteConnector\Invoice\Model\Process\Export\InvoiceSave::MESSAGE_ACTION,
            (int)$this->loadInvoice()->getId(),
            Queue::EXPORT()
        );

        return $message;
    }

    /**
     * Fetch expected request from file for comparison
     *
     * @param $fileName
     * @return mixed
     */
    private function getRequest(string $fileName)
    {
        $file = __DIR__ . "/../../../_files_ns_request/" . $fileName;
        $content = file_get_contents($file);
        $serialized = str_replace("\r", "", $content);
        return unserialize($serialized);// phpcs:ignore
    }

    /**
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/customer.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/order.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/order_set_netsuite_id.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/invoice_with_discount_and_tax.php
     * @magentoConfigFixture default/mageos_netsuite/orders/logic_switch line
     * @magentoConfigFixture default/mageos_netsuite/orders/discount_item_id 123
     * @magentoConfigFixture default/mageos_netsuite/tax/tax_item_internal_netsuite_id 124
     * @magentoConfigFixture default/mageos_netsuite/tax/skip_tax 0
     * @magentoAppIsolation enabled
     */
    public function testInvoiceExportDiscountLinePresentInInitializeResponse(): void
    {
        $this->unsetSkipInvoiceExport();
        $faker = $this->createScopedFaker('discount_and_tax_present');

        $message = $this->getMessage();

        $invoiceSaveProcess = $this->objectManager->create(
            \MageOS\NetSuiteConnector\Invoice\Model\Process\Export\InvoiceSave::class
        );
        $invoiceSaveProcess->process($message);

        /** @var \NetSuite\Classes\AddRequest $addRequest */
        $addRequest = $faker->getAddRequest();
        $items = array_values($addRequest->record->itemList->item);

        $this->assertCount(3, $items, 'Expected the product line, the rewritten discount line and the appended tax line');

        $productLine = $items[0];
        $this->assertSame('1', (string)$productLine->item->internalId);
        $this->assertFalse(isset($productLine->taxRate1), 'Product line must not carry taxRate1');

        $discountLine = $items[1];
        $this->assertSame('123', (string)$discountLine->item->internalId);
        $this->assertEquals(-15.0, $discountLine->rate);
        $this->assertEquals(-15.0, $discountLine->amount);
        $this->assertSame('Order discount', $discountLine->description);

        $taxLine = end($addRequest->record->itemList->item);
        $this->assertSame('124', (string)$taxLine->item->internalId);
        $this->assertEquals(6.75, $taxLine->amount);

        $this->restoreUnsetTaxRate1($addRequest);
        $snapshot = $this->objectManager->create(
            \MageOS\NetSuiteConnector\Test\Integration\Core\Helper\RequestSnapshot::class
        );
        $snapshot->assertRecordMatches(
            __DIR__ . '/../../../_files_ns_request/Invoice-export-add-discount-and-tax',
            $addRequest,
            []
        );
    }

    /**
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/customer.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/order.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/order_set_netsuite_id.php
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Invoice/_files/invoice_with_discount_and_tax.php
     * @magentoConfigFixture default/mageos_netsuite/orders/logic_switch line
     * @magentoConfigFixture default/mageos_netsuite/orders/discount_item_id 123
     * @magentoConfigFixture default/mageos_netsuite/tax/tax_item_internal_netsuite_id 124
     * @magentoConfigFixture default/mageos_netsuite/tax/skip_tax 0
     * @magentoAppIsolation enabled
     */
    public function testInvoiceExportDiscountLineAbsentFromInitializeResponse(): void
    {
        $this->unsetSkipInvoiceExport();
        $faker = $this->createScopedFaker('discount_and_tax_absent');

        $message = $this->getMessage();

        $invoiceSaveProcess = $this->objectManager->create(
            \MageOS\NetSuiteConnector\Invoice\Model\Process\Export\InvoiceSave::class
        );
        $invoiceSaveProcess->process($message);

        /** @var \NetSuite\Classes\AddRequest $addRequest */
        $addRequest = $faker->getAddRequest();
        $items = array_values($addRequest->record->itemList->item);

        $this->assertCount(3, $items, 'The appended discount line and tax line are both expected');
        $this->assertSame('123', (string)$items[1]->item->internalId, 'The 123 line must be appended before 124');
        $this->assertSame('124', (string)$items[2]->item->internalId, 'The 123 line must be appended before 124');

        $this->restoreUnsetTaxRate1($addRequest);
        $snapshot = $this->objectManager->create(
            \MageOS\NetSuiteConnector\Test\Integration\Core\Helper\RequestSnapshot::class
        );
        $snapshot->assertRecordMatches(
            __DIR__ . '/../../../_files_ns_request/Invoice-export-add-appended-discount',
            $addRequest,
            []
        );
    }

    /**
     * TaxItemLine::process() unsets taxRate1 on every line. A live object with an
     * unset declared property serializes without that key, but the snapshot file
     * round-trips through unserialize(), which reconstructs every declared
     * property at its class default. Re-assign the property where it is unset so
     * the live object matches that round trip before comparing.
     *
     * @param \NetSuite\Classes\AddRequest $addRequest
     */
    private function restoreUnsetTaxRate1(\NetSuite\Classes\AddRequest $addRequest): void
    {
        foreach ($addRequest->record->itemList->item as $item) {
            if (!isset($item->taxRate1)) {
                $item->taxRate1 = null;
            }
        }
    }

    /**
     * Point a fresh NetSuiteServiceFaker at a scenario-scoped fixtures directory and bind it as the
     * shared NetSuite service instance, without disturbing the class-wide static faker the other
     * tests in this class use.
     *
     * @param string $scenario
     * @return \MageOS\NetSuiteConnector\Test\Integration\Core\Helper\NetSuiteServiceFaker
     */
    private function createScopedFaker(
        string $scenario
    ): \MageOS\NetSuiteConnector\Test\Integration\Core\Helper\NetSuiteServiceFaker {
        $path = __DIR__ . "/../../../_files/" . $scenario . "/";
        $faker = new \MageOS\NetSuiteConnector\Test\Integration\Core\Helper\NetSuiteServiceFaker($path);
        $faker->setParameters([
            'netsuite_internal_id' => 11,
            'initialize_success' => 1,
            'add_success' => 1
        ]);

        $management = $this->getMockBuilder(\MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management::class)
            ->onlyMethods(['get'])
            ->disableOriginalConstructor()
            ->getMock();
        $management->method('get')->willReturn($faker);

        $this->objectManager->addSharedInstance(
            $management,
            \MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management::class
        );

        return $faker;
    }
}
