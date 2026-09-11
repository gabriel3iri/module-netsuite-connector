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

namespace MageOS\NetSuiteConnector\Test\Integration\Core\Ui\DataProvider\Monitor;

use Magento\Framework\Acl\Builder as AclBuilder;
use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Verifies that the monitor grid and monitor item form honor the module ACL resource
 *
 * @magentoAppArea adminhtml
 * @magentoCache all disabled
 */
class ListingAclTest extends AbstractBackendController
{
    private const ACL_RESOURCE = 'MageOS_NetSuiteConnector::netsuite';
    private const FIXTURE_MESSAGE_ID = 9999001;
    private const FIXTURE_SECRET_PAYLOAD = 'acl-secret-payload@example.com';
    private const FIXTURE_SECRET_PROCESS_OUTPUT = 'acl-secret-process-output';

    protected function tearDown(): void
    {
        $this->_objectManager->get(AclBuilder::class)->resetRuntimeAcl();
        parent::tearDown();
    }

    /**
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_files/monitor_item_with_payload.php
     */
    public function testListingDeniedAclReturnsNoData(): void
    {
        $this->denyAclResource();
        $this->acceptJson();

        $this->dispatch('backend/mui/index/render/?namespace=monitor_item_listing&isAjax=1');

        $response = $this->getResponse();
        $data = json_decode((string)$response->getBody(), true);

        self::assertSame(403, $response->getHttpResponseCode());
        self::assertSame(['error' => 'Forbidden', 'errorcode' => 403], $data);
    }

    /**
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_files/monitor_item_with_payload.php
     */
    public function testFormDeniedAclReturnsNoData(): void
    {
        $this->denyAclResource();
        $this->acceptJson();

        $this->dispatch('backend/mui/index/render/?namespace=monitor_item_view&isAjax=1');

        $response = $this->getResponse();
        $data = json_decode((string)$response->getBody(), true);

        self::assertSame(403, $response->getHttpResponseCode());
        self::assertSame(['error' => 'Forbidden', 'errorcode' => 403], $data);
    }

    /**
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_files/monitor_item_with_payload.php
     */
    public function testAllowedAdminGetsRowsWithoutPayload(): void
    {
        $this->acceptJson();

        $this->dispatch('backend/mui/index/render/?namespace=monitor_item_listing&isAjax=1');

        $response = $this->getResponse();
        $data = json_decode((string)$response->getBody(), true);

        self::assertSame(200, $response->getHttpResponseCode());
        self::assertGreaterThan(0, $data['totalRecords']);

        $item = $this->findFixtureItem($data['items']);

        self::assertNotNull($item, 'Fixture monitor item was not found in the listing response.');
        self::assertArrayNotHasKey('payload', $item);
        self::assertArrayNotHasKey('payload_original', $item);
        self::assertArrayNotHasKey('process_output', $item);
        self::assertArrayNotHasKey('process_output_original', $item);
        self::assertStringNotContainsString(self::FIXTURE_SECRET_PAYLOAD, (string)$response->getBody());
        self::assertStringNotContainsString(self::FIXTURE_SECRET_PROCESS_OUTPUT, (string)$response->getBody());
    }

    private function denyAclResource(): void
    {
        $acl = $this->_objectManager->get(AclBuilder::class)->getAcl();
        $acl->deny($this->_auth->getUser()->getRoles(), self::ACL_RESOURCE);
    }

    private function acceptJson(): void
    {
        $this->getRequest()->getHeaders()->addHeaderLine('Accept', 'application/json');
    }

    private function findFixtureItem(array $items): ?array
    {
        foreach ($items as $row) {
            if ((int)$row['message_id'] === self::FIXTURE_MESSAGE_ID) {
                return $row;
            }
        }

        return null;
    }
}
