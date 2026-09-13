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
 *
 */
declare(strict_types=1);

namespace MageOS\NetSuiteConnector\Tax\Model\Order\Export\TaxManager;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use NetSuite\Classes\CustomFieldList;
use NetSuite\Classes\SalesOrder;
use NetSuite\Classes\SalesOrderItem;
use NetSuite\Classes\StringCustomFieldRef;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderItemProcessorInterface;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderProcessorInterface;
use MageOS\NetSuiteConnector\Tax\Model\Config\Source\Tax as TaxLogic;

/**
 * NetSuite calculates the tax. The order carries the Magento tax and grand total in custom fields.
 */
class NetSuiteTaxProcessor implements OrderProcessorInterface, OrderItemProcessorInterface
{
    /** @var \WeakMap<SalesOrder, float> */
    private readonly \WeakMap $collectedItemTax;

    public function __construct(
        private readonly \MageOS\NetSuiteConnector\Tax\Model\Config\Tax $taxConfig,
        private readonly \MageOS\NetSuiteConnector\Tax\Model\Order\Export\TaxManager\TaxCalculation\TaxPerItem $taxPerItem,
        private readonly \MageOS\NetSuiteConnector\Core\Model\Logger\Logger $logger
    ) {
        $this->collectedItemTax = new \WeakMap();
    }

    public function processItem(
        SalesOrder $netsuiteOrder,
        SalesOrderItem $netsuiteItem,
        OrderItemInterface $magentoItem,
        ProductInterface $product,
        OrderInterface $magentoOrder
    ): void {
        if (!$this->taxConfig->isTaxLogicActive(TaxLogic::TAX_HANDLING_NETSUITE_SIDE, 'order_export')) {
            return;
        }
        if (!empty($product->getData('tax_class_id'))) {
            $netsuiteItem->isTaxable = true;
        }
        $this->collectedItemTax[$netsuiteOrder] = ($this->collectedItemTax[$netsuiteOrder] ?? 0.0)
            + $this->taxPerItem->getTaxAmount($magentoItem, $product);
    }

    public function process(SalesOrder $netsuiteOrder, OrderInterface $magentoOrder): void
    {
        if (!$this->taxConfig->isTaxLogicActive(TaxLogic::TAX_HANDLING_NETSUITE_SIDE, 'order_export')) {
            return;
        }
        $collectedItemTax = $this->collectedItemTax[$netsuiteOrder] ?? 0.0;
        unset($this->collectedItemTax[$netsuiteOrder]);
        $taxAmount = round($collectedItemTax + $magentoOrder->getShippingTaxAmount(), 2);
        if ($taxAmount > 0) {
            $netsuiteOrder->isTaxable = true;
        }
        $this->addCustomFields($netsuiteOrder, (float)$magentoOrder->getGrandTotal(), $taxAmount);
    }

    private function addCustomFields(SalesOrder $netsuiteOrder, float $orderTotal, float $taxAmount): void
    {
        if (empty($this->taxConfig->getSalesOrderTotalAmountId())) {
            $this->logger->debug(sprintf(
                'There was no Custom Field for Total Amount set! ' .
                'Order have been sent without this field. Total Amount was %s.',
                $orderTotal
            ));
        }
        if (empty($this->taxConfig->getSalesOrderTaxAmountId())) {
            $this->logger->debug(sprintf(
                'There was no Custom Field for Tax Amount set! ' .
                'Order have been sent without this field. Tax Amount was %s.',
                $taxAmount
            ));
        }
        if (empty($this->taxConfig->getSalesOrderTotalAmountId()) &&
            empty($this->taxConfig->getSalesOrderTaxAmountId())) {
            return;
        }
        $customFields = [];
        if (!empty($this->taxConfig->getSalesOrderTaxAmountId())) {
            $customFieldTax = new StringCustomFieldRef();
            $customFieldTax->scriptId = $this->taxConfig->getSalesOrderTaxAmountId();
            $customFieldTax->value = $taxAmount;
            $customFields[] = $customFieldTax;
        }
        if (!empty($this->taxConfig->getSalesOrderTotalAmountId())) {
            $customFieldTotal = new StringCustomFieldRef();
            $customFieldTotal->scriptId = $this->taxConfig->getSalesOrderTotalAmountId();
            $customFieldTotal->value = $orderTotal;
            $customFields[] = $customFieldTotal;
        }
        if ($netsuiteOrder->customFieldList === null) {
            $netsuiteOrder->customFieldList = new CustomFieldList();
            $netsuiteOrder->customFieldList->customField = [];
        }
        $netsuiteOrder->customFieldList->customField = array_merge(
            $netsuiteOrder->customFieldList->customField,
            $customFields
        );
    }
}
