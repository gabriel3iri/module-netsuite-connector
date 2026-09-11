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
 * On denial the controller redirects to admin/denied rather than answering with a bare 403,
 * so the expected no-access response is a redirect.
 *
 * @magentoAppArea adminhtml
 */
class IndexTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'MageOS_NetSuiteConnector::netsuite';

    /**
     * @var string
     */
    protected $uri = 'backend/netsuite/monitor/index';

    /**
     * @var int
     */
    protected $expectedNoAccessResponseCode = 302;

    public function testMonitorIndexRendersSuccessfully(): void
    {
        $this->dispatch($this->uri);

        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
    }
}
