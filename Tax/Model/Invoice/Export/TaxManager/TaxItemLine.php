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

namespace MageOS\NetSuiteConnector\Tax\Model\Invoice\Export\TaxManager;

use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use NetSuite\Classes\CashSale;
use NetSuite\Classes\CashSaleItem;
use NetSuite\Classes\RecordRef;
use MageOS\NetSuiteConnector\Invoice\Model\Export\CashSaleProcessorInterface;
use MageOS\NetSuiteConnector\Tax\Model\Config\Source\Tax as TaxLogic;

/**
 * The netsuite_processor tax logic adds nothing to a CashSale, so it has no invoice step.
 */
class TaxItemLine implements CashSaleProcessorInterface
{
    private const ITEM_DESCRIPTION = 'Sales tax';

    public function __construct(
        private readonly \MageOS\NetSuiteConnector\Tax\Model\Config\Tax $taxConfig
    ) {
    }

    public function process(CashSale $cashSale, InvoiceInterface $magentoInvoice, OrderInterface $magentoOrder): void
    {
        if (!$this->taxConfig->isTaxLogicActive(TaxLogic::TAX_HANDLING_TAX_ITEM, 'invoice_export')) {
            return;
        }
        foreach ($cashSale->itemList->item as $item) {
            unset($item->taxRate1);
        }
        $taxAmount = (float)$magentoInvoice->getTaxAmount();
        if ($taxAmount) {
            $cashSale->itemList->item[] = $this->createNSTaxItem($taxAmount);
            $cashSale->itemList->item = array_values($cashSale->itemList->item);
        }
    }

    private function createNSTaxItem(float $taxAmount): CashSaleItem
    {
        $taxItem = new CashSaleItem();
        $taxItem->quantity = 1;
        $taxItem->item = new RecordRef();
        $taxItem->item->internalId = $this->taxConfig->getTaxItemInternalNetsuiteId();
        $taxItem->amount = $taxAmount;
        $taxItem->rate = $taxAmount;
        $taxItem->price = new RecordRef();
        $taxItem->price->internalId = -1;
        $taxItem->description = self::ITEM_DESCRIPTION;
        $taxItem->isTaxable = false;
        $taxItem->itemIsFulfilled = true;
        return $taxItem;
    }
}
