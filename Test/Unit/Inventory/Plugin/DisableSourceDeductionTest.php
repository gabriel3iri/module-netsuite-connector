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

namespace MageOS\NetSuiteConnector\Test\Unit\Inventory\Plugin;

use Magento\Framework\Event\Observer;
use Magento\InventoryShipping\Observer\SourceDeductionProcessor;
use MageOS\NetSuiteConnector\Core\Model\Config\ConnectorConfig;
use MageOS\NetSuiteConnector\Inventory\Plugin\DisableSourceDeduction;
use MageOS\NetSuiteConnector\Order\Model\ConfigProvider\Permissions;
use PHPUnit\Framework\TestCase;

/**
 * Covers MageOS\NetSuiteConnector\Inventory\Plugin\DisableSourceDeduction: with order export off, core must
 * deduct source stock and place its own compensating reservation on shipment creation, same as without this
 * module installed.
 */
class DisableSourceDeductionTest extends TestCase
{
    public function testItProceedsWhenTheConnectorIsDisabled(): void
    {
        $plugin = $this->buildPlugin(connectorEnabled: false, sendOrdersEnabled: true);

        $result = $this->invoke($plugin);

        $this->assertSame('proceeded', $result);
    }

    public function testItProceedsWhenOrderExportIsDisabled(): void
    {
        $plugin = $this->buildPlugin(connectorEnabled: true, sendOrdersEnabled: false);

        $result = $this->invoke($plugin);

        $this->assertSame('proceeded', $result);
    }

    public function testItSkipsCoreDeductionWhileTheConnectorOwnsOrderExport(): void
    {
        $plugin = $this->buildPlugin(connectorEnabled: true, sendOrdersEnabled: true);

        $result = $this->invoke($plugin);

        $this->assertNull($result);
    }

    private function invoke(DisableSourceDeduction $plugin)
    {
        $proceed = static fn (Observer $observer) => 'proceeded';

        return $plugin->aroundExecute(
            $this->createStub(SourceDeductionProcessor::class),
            $proceed,
            $this->createStub(Observer::class)
        );
    }

    private function buildPlugin(bool $connectorEnabled, bool $sendOrdersEnabled): DisableSourceDeduction
    {
        $connectorConfig = $this->createStub(ConnectorConfig::class);
        $connectorConfig->method('isEnabled')->willReturn($connectorEnabled);

        $permissions = $this->createStub(Permissions::class);
        $permissions->method('isFeatureEnabled')->willReturn($sendOrdersEnabled);

        return new DisableSourceDeduction($connectorConfig, $permissions);
    }
}
