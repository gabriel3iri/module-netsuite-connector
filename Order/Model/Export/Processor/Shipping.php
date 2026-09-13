<?php
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

declare(strict_types=1);

namespace MageOS\NetSuiteConnector\Order\Model\Export\Processor;

use Magento\Sales\Api\Data\OrderInterface;
use NetSuite\Classes\RecordRef;
use NetSuite\Classes\SalesOrder;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderProcessorInterface;

class Shipping implements OrderProcessorInterface
{
    public function __construct(
        private readonly \MageOS\NetSuiteConnector\Shipment\Model\Config\ShippingConfig $shippingConfig
    ) {
    }

    public function process(SalesOrder $netsuiteOrder, OrderInterface $magentoOrder): void
    {
        $netsuiteShippingInternalId = $this->getNetsuiteShippingMethodInternalId(
            $magentoOrder->getShippingMethod()
        );
        if ($netsuiteShippingInternalId !== 0) {
            $netsuiteShippingMethod = new RecordRef();
            $netsuiteShippingMethod->internalId = $netsuiteShippingInternalId;
            $netsuiteOrder->shipMethod = $netsuiteShippingMethod;
        }
        $netsuiteOrder->shippingCost = $magentoOrder->getShippingAmount();
    }

    private function getNetsuiteShippingMethodInternalId($magentoShippingMethodCode): int
    {
        $shippingMapping = $this->shippingConfig->getNetsuiteMapping();
        foreach ($shippingMapping as $shippingMappingElement) {
            if ($shippingMappingElement['shipping_method'] == $magentoShippingMethodCode) {
                return (int)$shippingMappingElement['internal_netsuite_id'];
            }
        }
        return (int)$this->shippingConfig->getNetsuiteDefaultShippingId();
    }
}
