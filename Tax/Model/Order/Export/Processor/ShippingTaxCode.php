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

namespace MageOS\NetSuiteConnector\Tax\Model\Order\Export\Processor;

use Magento\Sales\Api\Data\OrderInterface;
use NetSuite\Classes\RecordRef;
use NetSuite\Classes\SalesOrder;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderProcessorInterface;
use MageOS\NetSuiteConnector\Tax\Model\Config\Source\Tax as TaxLogic;

/**
 * Runs right after the shipping step, so that a custom field mapping of shippingTaxCode still wins.
 */
class ShippingTaxCode implements OrderProcessorInterface
{
    public function __construct(
        private readonly \MageOS\NetSuiteConnector\Tax\Model\Config\Tax $taxConfig
    ) {
    }

    public function process(SalesOrder $netsuiteOrder, OrderInterface $magentoOrder): void
    {
        if (!$netsuiteOrder->shipMethod
            || !$this->taxConfig->isTaxLogicActive(TaxLogic::TAX_HANDLING_TAX_ITEM, 'order_export')
        ) {
            return;
        }
        $netsuiteOrder->shippingTaxCode = new RecordRef();
        $netsuiteOrder->shippingTaxCode->internalId = $this->taxConfig->getNotTaxableInternalNetsuiteId();
    }
}
