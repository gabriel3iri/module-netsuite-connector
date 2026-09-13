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

namespace MageOS\NetSuiteConnector\Invoice\Model\Export\LineMatcher;

use Magento\Sales\Api\Data\InvoiceItemInterface;
use Magento\Sales\Api\Data\OrderInterface;
use NetSuite\Classes\CashSaleItem;
use MageOS\NetSuiteConnector\Invoice\Model\Export\CashSaleLineMatcherInterface;

class ProductLine implements CashSaleLineMatcherInterface
{
    public function match(
        CashSaleItem $netsuiteItem,
        InvoiceItemInterface $magentoItem,
        mixed $netsuiteInternalId,
        OrderInterface $magentoOrder,
        ?float &$pendingLineDiscount
    ): bool {
        if ($netsuiteInternalId != $netsuiteItem->item->internalId) {
            return false;
        }

        $netsuiteItem->quantity = $magentoItem->getQty();
        $netsuiteItem->amount = $magentoItem->getRowTotal();
        $netsuiteItem->isTaxable = false;
        return true;
    }
}
