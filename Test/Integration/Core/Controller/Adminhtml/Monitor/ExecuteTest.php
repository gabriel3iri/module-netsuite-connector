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

use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * Execute redirects on every outcome, allowed or denied, so a plain HTTP status code cannot
 * tell the two apart (both are 302); the inherited testAclHasAccess/testAclNoAccess assume it
 * can. These overrides check the redirect target instead.
 *
 * @magentoAppArea adminhtml
 */
class ExecuteTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'MageOS_NetSuiteConnector::netsuite';

    /**
     * @var string
     */
    protected $uri = 'backend/netsuite/monitor/execute';

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
}
