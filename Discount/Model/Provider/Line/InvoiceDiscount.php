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

namespace MageOS\NetSuiteConnector\Discount\Model\Provider\Line;

use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\InvoiceItemInterface;
use Magento\Sales\Api\Data\OrderInterface;
use NetSuite\Classes\CashSale;
use NetSuite\Classes\CashSaleItem;
use NetSuite\Classes\RecordRef;
use NetSuite\Classes\RecordType;
use MageOS\NetSuiteConnector\Discount\Model\Config\Source\LogicSwitcher;
use MageOS\NetSuiteConnector\Invoice\Model\Export\CashSaleLineMatcherInterface;
use MageOS\NetSuiteConnector\Invoice\Model\Export\CashSaleProcessorInterface;

class InvoiceDiscount implements CashSaleProcessorInterface, CashSaleLineMatcherInterface
{
    private const ITEM_DESCRIPTION = 'Discount';

    public function __construct(
        private readonly \MageOS\NetSuiteConnector\Discount\Model\Config\DiscountConfig $discountConfig
    ) {
    }

    /**
     * NetSuite adds the discount line only when it invoices a sales order for the first time.
     * A later invoice of the same order gets its discount line here.
     */
    public function process(CashSale $cashSale, InvoiceInterface $magentoInvoice, OrderInterface $magentoOrder): void
    {
        if (!$this->discountConfig->isLogicSwitchActive(LogicSwitcher::LINE)) {
            return;
        }
        $discountItemId = $this->discountConfig->getDiscountItemId();
        $discountAmount = (float) $magentoInvoice->getDiscountAmount();
        if ($discountAmount) {
            $found = false;
            foreach ($cashSale->itemList->item as $item) {
                if ($item->item->internalId == $discountItemId) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $cashSale->itemList->item[] = $this->createNSDiscountItem($discountAmount);
                $cashSale->itemList->item = array_values($cashSale->itemList->item);
            }
        }
    }

    /**
     * A product line records its invoice item's discount. The discount line after it takes that discount.
     */
    public function match(
        CashSaleItem $netsuiteItem,
        InvoiceItemInterface $magentoItem,
        mixed $netsuiteInternalId,
        OrderInterface $magentoOrder,
        ?float &$pendingLineDiscount
    ): bool {
        if (!$this->discountConfig->isLogicSwitchActive(LogicSwitcher::LINE)) {
            return false;
        }
        if ($netsuiteInternalId == $netsuiteItem->item->internalId) {
            $pendingLineDiscount = $this->getInvoiceItemDiscount($magentoItem);
            return false;
        }

        if ($pendingLineDiscount === null
            || $pendingLineDiscount <= 0.001
            || $netsuiteItem->item->internalId != $this->discountConfig->getDiscountItemId()
        ) {
            return false;
        }

        $this->updateNSDiscountItem(
            $netsuiteItem,
            $pendingLineDiscount,
            $magentoOrder->getDiscountDescription()
        );
        $pendingLineDiscount = null;
        return true;
    }

    private function getInvoiceItemDiscount($magentoItem): ?float
    {
        $discountValue = $magentoItem->getDiscountAmount();
        $orderItem = $magentoItem->getOrderItem();
        if (!$discountValue && $orderItem->getParentItemId()) {
            $discountValue = $orderItem->getParentItem()->getDiscountAmount();
        }
        return $discountValue ? (float)$discountValue : null;
    }

    private function updateNSDiscountItem($netsuiteItem, $discountValue, $discountDescription)
    {
        $discountValue = -(abs($discountValue));
        $netsuiteItem->item->type = RecordType::discountItem;
        $netsuiteItem->amount = $discountValue;
        $netsuiteItem->rate =  $discountValue;
        $netsuiteItem->price = new RecordRef();
        $netsuiteItem->price->internalId = -1;
        $netsuiteItem->description = $discountDescription ?? self::ITEM_DESCRIPTION;
        $netsuiteItem->isTaxable = false;
    }

    private function createNSDiscountItem($discountAmount)
    {
        $discountItem = new CashSaleItem();
        $discountItem->quantity = 1;
        $discountItem->item = new RecordRef();
        $discountItem->item->internalId = $this->discountConfig->getDiscountItemId();
        $discountItem->amount = $discountAmount;
        $discountItem->rate = $discountAmount;
        $discountItem->price = new RecordRef();
        $discountItem->price->internalId = -1;
        $discountItem->description = self::ITEM_DESCRIPTION;
        $discountItem->isTaxable = false;
        $discountItem->itemIsFulfilled = true;
        return $discountItem;
    }
}
