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

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventorySales\Model\PlaceReservationsForSalesEvent;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use MageOS\NetSuiteConnector\Core\Model\Config\ConnectorConfig;
use MageOS\NetSuiteConnector\Inventory\Plugin\DisableReservation;
use MageOS\NetSuiteConnector\Order\Model\ConfigProvider\Permissions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Covers MageOS\NetSuiteConnector\Inventory\Plugin\DisableReservation: with order export off the whitelist
 * must not apply at all, and with order export on a cancel or credit memo must still release stock that
 * NetSuite never took ownership of.
 */
class DisableReservationTest extends TestCase
{
    private const ORDER_ID = '7';

    public function testItProceedsForACancelEventWhenTheConnectorIsDisabled(): void
    {
        $plugin = $this->buildPlugin(connectorEnabled: false, sendOrdersEnabled: true);

        $result = $this->invoke($plugin, SalesEventInterface::EVENT_ORDER_CANCELED);

        $this->assertSame('proceeded', $result);
    }

    public function testItProceedsForACancelEventWhenOrderExportIsDisabled(): void
    {
        $plugin = $this->buildPlugin(connectorEnabled: true, sendOrdersEnabled: false);

        $result = $this->invoke($plugin, SalesEventInterface::EVENT_ORDER_CANCELED);

        $this->assertSame('proceeded', $result);
    }

    #[DataProvider('alwaysAllowedEventProvider')]
    public function testItProceedsForTheWhitelistedEventsWhileTheConnectorIsEnabled(string $eventType): void
    {
        $plugin = $this->buildPlugin(connectorEnabled: true, sendOrdersEnabled: true);

        $result = $this->invoke($plugin, $eventType);

        $this->assertSame('proceeded', $result);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function alwaysAllowedEventProvider(): array
    {
        return [
            'order placed' => [SalesEventInterface::EVENT_ORDER_PLACED],
            'order place failed' => [SalesEventInterface::EVENT_ORDER_PLACE_FAILED],
            'shipment created' => [SalesEventInterface::EVENT_SHIPMENT_CREATED],
        ];
    }

    public function testItDropsACancelEventWhileTheConnectorIsEnabledAndNetSuiteOwnsTheOrder(): void
    {
        $plugin = $this->buildPlugin(connectorEnabled: true, sendOrdersEnabled: true, netsuiteInternalId: '4501');

        $result = $this->invoke($plugin, SalesEventInterface::EVENT_ORDER_CANCELED);

        $this->assertNull($result);
    }

    public function testItDropsACreditMemoEventWhileTheConnectorIsEnabledAndNetSuiteOwnsTheOrder(): void
    {
        $plugin = $this->buildPlugin(connectorEnabled: true, sendOrdersEnabled: true, netsuiteInternalId: '4501');

        $result = $this->invoke($plugin, SalesEventInterface::EVENT_CREDITMEMO_CREATED);

        $this->assertNull($result);
    }

    public function testItProceedsForACancelEventWhileTheConnectorIsEnabledButNetSuiteNeverOwnedTheOrder(): void
    {
        $plugin = $this->buildPlugin(connectorEnabled: true, sendOrdersEnabled: true, netsuiteInternalId: null);

        $result = $this->invoke($plugin, SalesEventInterface::EVENT_ORDER_CANCELED);

        $this->assertSame('proceeded', $result);
    }

    public function testItProceedsForACreditMemoEventWhileTheConnectorIsEnabledButNetSuiteNeverOwnedTheOrder(): void
    {
        $plugin = $this->buildPlugin(connectorEnabled: true, sendOrdersEnabled: true, netsuiteInternalId: null);

        $result = $this->invoke($plugin, SalesEventInterface::EVENT_CREDITMEMO_CREATED);

        $this->assertSame('proceeded', $result);
    }

    public function testItDropsACancelEventWhenTheOrderCannotBeLoaded(): void
    {
        $connectorConfig = $this->createStub(ConnectorConfig::class);
        $connectorConfig->method('isEnabled')->willReturn(true);

        $permissions = $this->createStub(Permissions::class);
        $permissions->method('isFeatureEnabled')->willReturn(true);

        $orderRepository = $this->createStub(OrderRepositoryInterface::class);
        $orderRepository->method('get')->willThrowException(new NoSuchEntityException(__('No such entity.')));

        $plugin = new DisableReservation($connectorConfig, $permissions, $orderRepository);

        $result = $this->invoke($plugin, SalesEventInterface::EVENT_ORDER_CANCELED);

        $this->assertNull($result, 'an unknown owner must keep the drop, because a double release oversells');
    }

    private function invoke(DisableReservation $plugin, string $eventType)
    {
        $salesEvent = $this->createStub(SalesEventInterface::class);
        $salesEvent->method('getType')->willReturn($eventType);
        $salesEvent->method('getObjectType')->willReturn(SalesEventInterface::OBJECT_TYPE_ORDER);
        $salesEvent->method('getObjectId')->willReturn(self::ORDER_ID);

        $proceed = static fn (array $items, SalesChannelInterface $salesChannel, SalesEventInterface $event) => 'proceeded';

        return $plugin->aroundExecute(
            $this->createStub(PlaceReservationsForSalesEvent::class),
            $proceed,
            [],
            $this->createStub(SalesChannelInterface::class),
            $salesEvent
        );
    }

    private function buildPlugin(
        bool $connectorEnabled,
        bool $sendOrdersEnabled,
        ?string $netsuiteInternalId = null
    ): DisableReservation {
        $connectorConfig = $this->createStub(ConnectorConfig::class);
        $connectorConfig->method('isEnabled')->willReturn($connectorEnabled);

        $permissions = $this->createStub(Permissions::class);
        $permissions->method('isFeatureEnabled')->willReturn($sendOrdersEnabled);

        $order = $this->createStub(Order::class);
        $order->method('getData')->willReturn($netsuiteInternalId);

        $orderRepository = $this->createStub(OrderRepositoryInterface::class);
        $orderRepository->method('get')->willReturn($order);

        return new DisableReservation($connectorConfig, $permissions, $orderRepository);
    }
}
