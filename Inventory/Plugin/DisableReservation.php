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

namespace MageOS\NetSuiteConnector\Inventory\Plugin;

use Magento\InventorySales\Model\PlaceReservationsForSalesEvent;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\Data\SalesEventInterface;
use MageOS\NetSuiteConnector\Order\Model\ConfigProvider\Permissions;

/**
 * This class disables reservation for events not connected with direct order placement, but only while the
 * connector owns order export. With order export off, every event proceeds so core inventory behaves unmodified.
 */
class DisableReservation
{
    private const ALWAYS_ALLOWED_EVENTS = [
        SalesEventInterface::EVENT_ORDER_PLACED,
        SalesEventInterface::EVENT_ORDER_PLACE_FAILED,
        SalesEventInterface::EVENT_SHIPMENT_CREATED,
    ];

    private const STOCK_RELEASE_EVENTS = [
        SalesEventInterface::EVENT_ORDER_CANCELED,
        SalesEventInterface::EVENT_CREDITMEMO_CREATED,
    ];

    public function __construct(
        private readonly \MageOS\NetSuiteConnector\Core\Model\Config\ConnectorConfig $connectorConfig,
        private readonly \MageOS\NetSuiteConnector\Order\Model\ConfigProvider\Permissions $orderPermissions,
        private readonly \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
    ) {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        PlaceReservationsForSalesEvent $subject,
        \Closure $proceed,
        array $items,
        SalesChannelInterface $salesChannel,
        SalesEventInterface $salesEvent
    ) {
        if (!$this->connectorConfig->isEnabled()
            || !$this->orderPermissions->isFeatureEnabled(Permissions::SEND_ORDERS)
        ) {
            return $proceed($items, $salesChannel, $salesEvent);
        }

        if (in_array($salesEvent->getType(), self::ALWAYS_ALLOWED_EVENTS, true)) {
            return $proceed($items, $salesChannel, $salesEvent);
        }

        if (in_array($salesEvent->getType(), self::STOCK_RELEASE_EVENTS, true)
            && $this->netsuiteNeverOwnedOrderStock($salesEvent)
        ) {
            return $proceed($items, $salesChannel, $salesEvent);
        }
    }

    /**
     * An order that was never exported to NetSuite has no netsuite_internal_id, so Magento still owns its stock
     * and a cancel or credit memo must release the reservation itself.
     */
    private function netsuiteNeverOwnedOrderStock(SalesEventInterface $salesEvent): bool
    {
        if ($salesEvent->getObjectType() !== SalesEventInterface::OBJECT_TYPE_ORDER) {
            return false;
        }

        try {
            $order = $this->orderRepository->get((int)$salesEvent->getObjectId());
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            return false;
        }

        return empty($order->getData('netsuite_internal_id'));
    }
}
