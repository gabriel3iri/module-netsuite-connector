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

namespace MageOS\NetSuiteConnector\Invoice\Model\Export;

interface CashSaleLineMatcherInterface
{
    /**
     * Every matcher runs for every pair. The NetSuite line is kept when at least one matcher returns true.
     * $pendingLineDiscount is null at the start of each CashSale and is shared by all matchers and pairs.
     */
    public function match(
        \NetSuite\Classes\CashSaleItem $netsuiteItem,
        \Magento\Sales\Api\Data\InvoiceItemInterface $magentoItem,
        mixed $netsuiteInternalId,
        \Magento\Sales\Api\Data\OrderInterface $magentoOrder,
        ?float &$pendingLineDiscount
    ): bool;
}
