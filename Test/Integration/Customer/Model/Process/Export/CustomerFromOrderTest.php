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
 *
 */

namespace MageOS\NetSuiteConnector\Test\Integration\Customer\Model\Process\Export;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use NetSuite\Classes\BooleanCustomFieldRef;
use MageOS\NetSuiteConnector\Customer\Model\Mapper\Customer;
use MageOS\NetSuiteConnector\Test\Integration\Core\Fixtures\Locator;

/**
 * Covers Customer::createNetsuiteCustomerFromOrder(): the guest-checkout path that builds and adds a
 * NetSuite customer directly from an order, instead of going through Customer\Model\Process\Export\CustomerSave.
 *
 * @SuppressWarnings(PHPMD)
 */
class CustomerFromOrderTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Magento\TestFramework\ObjectManager
     */
    private $objectManager;

    /**
     * @var \MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management|\PHPUnit\Framework\MockObject\MockObject
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

    public static function setUpBeforeClass(): void
    {
        $fixturesUsed = [
            '_files/default_rollback.php',
            '_files/product_simple.php',
            '_files/address_data.php',
            '_files/order_guest_customer.php',
        ];

        $path = realpath(__DIR__ . "/" . self::RELATIVE_PATH_TO_FIXTURES) . "/";

        Locator::copy($path, $fixturesUsed);
    }

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
                ->disableOriginalConstructor()
                ->getMock();
        }

        $this->objectManager->configure([\MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management::class => ['shared' => true]]);
        $this->objectManager->addSharedInstance(self::$nsHelper, \MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management::class);
    }

    /**
     * A guest order whose email has no matching NetSuite customer builds a new one. When an is_importable
     * scriptId is configured, the add request carries it.
     *
     * CustomerImportConfig caches a resolved value for the lifetime of its shared instance, so
     * setCacheEnabled(false) is required here to make this test observe the config fixture below
     * instead of a value an earlier test already cached for the same config path.
     *
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_final/_files/order_guest_customer.php
     * @magentoConfigFixture default/mageos_netsuite/customers_import/is_importable_field_id custentity_importable
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     */
    public function testGuestOrderCustomerAddRequestCarriesTheImportableField()
    {
        $parameters = [
            'search_success' => 0,
            'add_success' => 1,
            'netsuite_internal_id' => 77,
        ];
        self::$netsuiteServiceFaker->setParameters($parameters);
        self::$nsHelper->method('get')->willReturn(self::$netsuiteServiceFaker);

        $this->objectManager->get(\MageOS\NetSuiteConnector\CustomerImport\Model\Config\CustomerImportConfig::class)
            ->setCacheEnabled(false);

        $order = $this->getOrder();

        /** @var Customer $customerMapper */
        $customerMapper = $this->objectManager->create(Customer::class);
        $internalId = $customerMapper->createNetsuiteCustomerFromOrder($order);

        $this->assertEquals($parameters['netsuite_internal_id'], $internalId);

        $addRequests = self::$netsuiteServiceFaker->getAddRequests();
        $addRequest = end($addRequests);
        $this->assertNotFalse($addRequest, 'Expected createNetsuiteCustomerFromOrder() to add a customer.');

        $importableField = null;
        foreach ($addRequest->record->customFieldList->customField as $customField) {
            if ($customField instanceof BooleanCustomFieldRef
                && $customField->scriptId === 'custentity_importable'
            ) {
                $importableField = $customField;
            }
        }
        $this->assertNotNull($importableField, 'Expected a BooleanCustomFieldRef for the is_importable field.');
        $this->assertTrue($importableField->value);
    }

    /**
     * Loads the order created by the order_guest_customer.php fixture.
     *
     * @return \Magento\Sales\Api\Data\OrderInterface
     */
    private function getOrder()
    {
        /** @var SearchCriteriaBuilder $searchCriteriaBuilder */
        $searchCriteriaBuilder = $this->objectManager->create(SearchCriteriaBuilder::class);
        $searchCriteria = $searchCriteriaBuilder
            ->addFilter('increment_id', '100000099')
            ->create();

        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = $this->objectManager->get(OrderRepositoryInterface::class);
        $orders = $orderRepository->getList($searchCriteria)->getItems();

        return reset($orders);
    }
}
