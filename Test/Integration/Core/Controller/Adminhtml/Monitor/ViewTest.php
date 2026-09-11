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

namespace MageOS\NetSuiteConnector\Test\Integration\Core\Controller\Adminhtml\Monitor;

use Magento\Framework\Message\MessageInterface;
use Magento\TestFramework\TestCase\AbstractBackendController;
use MageOS\NetSuiteConnector\Core\Api\MonitorRegistryInterface;

/**
 * View redirects on every outcome, allowed or denied, so a plain HTTP status code cannot tell
 * the two apart (both are 302); the inherited testAclHasAccess/testAclNoAccess assume it can.
 * These overrides check the redirect target instead.
 *
 * @magentoAppArea adminhtml
 */
class ViewTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'MageOS_NetSuiteConnector::netsuite';

    /**
     * @var string
     */
    protected $uri = 'backend/netsuite/monitor/view';

    public function testAclHasAccess(): void
    {
        $this->dispatch($this->uri);

        $this->assertNotSame(404, $this->getResponse()->getHttpResponseCode());
        $this->assertRedirect($this->logicalNot($this->stringContains('admin/denied')));
    }

    public function testAclNoAccess(): void
    {
        $acl = $this->_objectManager->get(\Magento\Framework\Acl\Builder::class)->getAcl();
        $acl->deny($this->_auth->getUser()->getRoles(), $this->resource);

        $this->dispatch($this->uri);

        $this->assertRedirect($this->stringContains('admin/denied'));
    }

    public function testUnknownIdRedirectsWithAnError(): void
    {
        $this->getRequest()->setParam('id', 999999999);

        $this->dispatch($this->uri);

        $this->assertEquals(302, $this->getResponse()->getHttpResponseCode());
        $this->assertSessionMessages(
            $this->containsEqual('Something went wrong, please try again: The ID 999999999 does not exists!'),
            MessageInterface::TYPE_ERROR
        );
    }

    /**
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Core/_files/monitor_item_with_payload.php
     */
    public function testRealIdRendersFormWithoutFatalError(): void
    {
        $monitorItem = $this->_objectManager->get(MonitorRegistryInterface::class)->getByMessageId(9999001);
        $this->getRequest()->setParam('id', $monitorItem->getId());

        $this->dispatch($this->uri);

        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
        $this->assertStringContainsString('Monitor Item', $this->getResponse()->getBody());
    }
}
