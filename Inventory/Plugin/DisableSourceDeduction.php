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

use Magento\Framework\Event\Observer;
use Magento\InventoryShipping\Observer\SourceDeductionProcessor;
use MageOS\NetSuiteConnector\Order\Model\ConfigProvider\Permissions;

/**
 * Core deducts source stock and places its own compensating reservation on shipment creation. NetSuite order
 * export already compensates the reservation for an exported order, so this skips the core work only while the
 * connector owns order export. With order export off, core runs unmodified.
 */
class DisableSourceDeduction
{
    public function __construct(
        private readonly \MageOS\NetSuiteConnector\Core\Model\Config\ConnectorConfig $connectorConfig,
        private readonly \MageOS\NetSuiteConnector\Order\Model\ConfigProvider\Permissions $orderPermissions,
    ) {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(
        SourceDeductionProcessor $subject,
        \Closure $proceed,
        Observer $observer
    ) {
        if (!$this->connectorConfig->isEnabled()
            || !$this->orderPermissions->isFeatureEnabled(Permissions::SEND_ORDERS)
        ) {
            return $proceed($observer);
        }
    }
}
