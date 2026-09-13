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

namespace MageOS\NetSuiteConnector\Invoice\Model\Mapper;

use Magento\Sales\Api\Data\InvoiceInterface;
use Magento\Sales\Api\Data\OrderInterface;
use NetSuite\Classes\CashSale;
use NetSuite\Classes\RecordRef;
use NetSuite\Classes\RecordType;
use MageOS\NetSuiteConnector\Invoice\Model\Export\CashSaleProcessorInterface;

class ExportInvoice
{
    public function __construct(
        \MageOS\NetSuiteConnector\Core\Model\Pipeline\ProcessorSorter $sorter,
        private array $processors = []
    ) {
        $this->processors = $sorter->sort($this->processors, CashSaleProcessorInterface::class);
    }

    public function cleanupNetsuiteCashSale(
        CashSale $cashSale,
        InvoiceInterface $magentoInvoice,
        OrderInterface $magentoOrder
    ): CashSale {
        $cashSale->createdFrom = new RecordRef();
        $cashSale->createdFrom->internalId = $magentoOrder->getData('netsuite_internal_id');
        $cashSale->createdFrom->type = RecordType::salesOrder;
        $cashSale->ccApproved = true;
        $cashSale->chargeIt = false;

        foreach ($this->processors as $processor) {
            /** @var CashSaleProcessorInterface $processor */
            $processor->process($cashSale, $magentoInvoice, $magentoOrder);
        }

        return $cashSale;
    }
}
