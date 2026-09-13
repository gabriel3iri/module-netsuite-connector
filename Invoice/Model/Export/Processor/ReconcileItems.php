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

namespace MageOS\NetSuiteConnector\Invoice\Model\Export\Processor;

use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use NetSuite\Classes\CashSale;
use NetSuite\Classes\CashSaleItem;
use MageOS\NetSuiteConnector\Invoice\Model\Export\CashSaleLineMatcherInterface;
use MageOS\NetSuiteConnector\Invoice\Model\Export\CashSaleProcessorInterface;

/**
 * Drops every NetSuite line that matches no invoice item, because an invoice may not carry every order line.
 */
class ReconcileItems implements CashSaleProcessorInterface
{
    public function __construct(
        private readonly \Magento\Catalog\Model\ResourceModel\Product $productResource,
        \MageOS\NetSuiteConnector\Core\Model\Pipeline\ProcessorSorter $sorter,
        private array $matchers = []
    ) {
        $this->matchers = $sorter->sort($this->matchers, CashSaleLineMatcherInterface::class);
    }

    public function process(CashSale $cashSale, InvoiceInterface $magentoInvoice, OrderInterface $magentoOrder): void
    {
        $pendingLineDiscount = null;
        foreach ($cashSale->itemList->item as $key => $netsuiteItem) {
            if (!$this->isMatched($netsuiteItem, $magentoInvoice, $magentoOrder, $pendingLineDiscount)) {
                unset($cashSale->itemList->item[$key]);
            }
        }

        $cashSale->itemList->item = array_values($cashSale->itemList->item);
    }

    private function isMatched(
        CashSaleItem $netsuiteItem,
        InvoiceInterface $magentoInvoice,
        OrderInterface $magentoOrder,
        ?float &$pendingLineDiscount
    ): bool {
        foreach ($magentoInvoice->getItems() as $magentoItem) {
            $netsuiteInternalId = $this->productResource
                ->getAttributeRawValue($magentoItem->getProductId(), 'netsuite_internal_id', 0);

            $matched = false;
            foreach ($this->matchers as $matcher) {
                /** @var CashSaleLineMatcherInterface $matcher */
                $matched = $matcher->match(
                    $netsuiteItem,
                    $magentoItem,
                    $netsuiteInternalId,
                    $magentoOrder,
                    $pendingLineDiscount
                ) || $matched;
            }
            if ($matched) {
                return true;
            }
        }
        return false;
    }
}
