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

namespace MageOS\NetSuiteConnector\Test\Integration\Core\Controller\Adminhtml;

use Magento\Framework\App\Router\ActionList;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Magento reads controllers only from <module root>/Controller. This resolves every admin
 * action of the module through the same ActionList lookup the front controller uses, so a
 * controller left under the wrong directory (or namespace) is caught here instead of in
 * production, where it surfaces as a silent 404.
 *
 * @magentoAppArea adminhtml
 */
class ActionListResolutionTest extends TestCase
{
    /**
     * @param string $namespace
     * @param string $action
     * @param string $expectedClass
     */
    #[DataProvider('actionProvider')]
    public function testActionResolves(string $namespace, string $action, string $expectedClass): void
    {
        $actionList = Bootstrap::getObjectManager()->get(ActionList::class);

        $resolvedClass = $actionList->get('MageOS_NetSuiteConnector', 'Adminhtml', $namespace, $action);

        $this->assertSame($expectedClass, $resolvedClass);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function actionProvider(): array
    {
        return [
            'monitor index' => [
                'monitor',
                'index',
                'MageOS\NetSuiteConnector\Controller\Adminhtml\Monitor\Index',
            ],
            'monitor view' => [
                'monitor',
                'view',
                'MageOS\NetSuiteConnector\Controller\Adminhtml\Monitor\View',
            ],
            'monitor cancel' => [
                'monitor',
                'cancel',
                'MageOS\NetSuiteConnector\Controller\Adminhtml\Monitor\Cancel',
            ],
            'monitor execute' => [
                'monitor',
                'execute',
                'MageOS\NetSuiteConnector\Controller\Adminhtml\Monitor\Execute',
            ],
            'monitor process' => [
                'monitor',
                'process',
                'MageOS\NetSuiteConnector\Controller\Adminhtml\Monitor\Process',
            ],
            'monitor save' => [
                'monitor',
                'save',
                'MageOS\NetSuiteConnector\Controller\Adminhtml\Monitor\Save',
            ],
            'system config connection validate' => [
                'system_config_connection',
                'validate',
                'MageOS\NetSuiteConnector\Controller\Adminhtml\System\Config\Connection\Validate',
            ],
        ];
    }
}
