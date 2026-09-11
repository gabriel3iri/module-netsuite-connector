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

namespace MageOS\NetSuiteConnector\Test\Integration\Core\Ui\Component\Monitor;

use Magento\TestFramework\TestCase\AbstractBackendController;

/**
 * mui/index/render prepares the monitor_item_view form outside of the View controller, so the
 * button providers under Core/Ui/Component/Monitor/Button never see a registered
 * current_monitor_item. An allowed admin must still get a rendered component back, not a fatal
 * error.
 *
 * @magentoAppArea adminhtml
 */
class FormRenderTest extends AbstractBackendController
{
    public function testAllowedAdminRendersFormWithoutFatalError(): void
    {
        $this->getRequest()->getHeaders()->addHeaderLine('Accept', 'application/json');

        $this->dispatch('backend/mui/index/render/?namespace=monitor_item_view&isAjax=1');

        $response = $this->getResponse();

        self::assertSame(200, $response->getHttpResponseCode());
        self::assertNotSame('', (string)$response->getBody());
    }
}
