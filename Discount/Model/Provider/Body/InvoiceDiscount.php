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

namespace MageOS\NetSuiteConnector\Discount\Model\Provider\Body;

use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use NetSuite\Classes\CashSale;
use NetSuite\Classes\RecordRef;
use MageOS\NetSuiteConnector\Discount\Model\Config\Source\LogicSwitcher;
use MageOS\NetSuiteConnector\Invoice\Model\Export\CashSaleProcessorInterface;

class InvoiceDiscount implements CashSaleProcessorInterface
{
    public function __construct(
        private readonly \MageOS\NetSuiteConnector\Discount\Model\Config\DiscountConfig $discountConfig
    ) {
    }

    public function process(CashSale $cashSale, InvoiceInterface $magentoInvoice, OrderInterface $magentoOrder): void
    {
        if (!$this->discountConfig->isLogicSwitchActive(LogicSwitcher::BODY)) {
            return;
        }
        $discountAmount = (float) $magentoInvoice->getDiscountAmount();

        if (abs($discountAmount) > 0.001 && $this->discountConfig->getDiscountItemId()) {
            if (!$cashSale->discountItem || $cashSale->discountItem->internalId) {
                $cashSale->discountItem = new RecordRef();
                $cashSale->discountItem->internalId = $this->discountConfig->getDiscountItemId();
            }

            $cashSale->discountRate = abs($discountAmount);
        }
    }
}
