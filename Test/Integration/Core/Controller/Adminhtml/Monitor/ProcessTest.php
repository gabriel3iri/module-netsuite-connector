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
 * Process runs the real import/export pipeline, so the connector is disabled through config to
 * keep it from reaching NetSuite; processImport()/processExport() both return immediately once
 * disabled.
 *
 * Process has no manual ACL check of its own; it relies on the ADMIN_RESOURCE gate that
 * Magento\Backend\App\AbstractAction::dispatch() enforces before execute() runs. A denied admin
 * gets a plain HTTP 403 with the "denied" layout, not a redirect, so the inherited
 * testAclNoAccess (which asserts 403) is correct as-is and is not overridden. testAclHasAccess
 * is overridden only to add the config fixture that keeps the pipeline from reaching NetSuite.
 *
 * @magentoAppArea adminhtml
 */
class ProcessTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'MageOS_NetSuiteConnector::netsuite';

    /**
     * @var string
     */
    protected $uri = 'backend/netsuite/monitor/process';

    /**
     * @magentoConfigFixture current_store mageos_netsuite/general/enabled 0
     */
    public function testAclHasAccess(): void
    {
        $this->dispatch($this->uri);

        $this->assertNotSame(404, $this->getResponse()->getHttpResponseCode());
        $this->assertNotSame($this->expectedNoAccessResponseCode, $this->getResponse()->getHttpResponseCode());
    }
}
