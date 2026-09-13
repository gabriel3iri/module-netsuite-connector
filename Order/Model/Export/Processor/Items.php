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
use NetSuite\Classes\SalesOrder;
use NetSuite\Classes\SalesOrderItem;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderItemProcessorInterface;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderProcessorInterface;

class Items implements OrderProcessorInterface
{
    public function __construct(
        private readonly \MageOS\NetSuiteConnector\Order\Model\Export\OrderItemSkipRule $skipRule,
        private readonly \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        private readonly \MageOS\NetSuiteConnector\Order\Model\Mapper\OrderExport\OrderItemList $nsOrderItemList,
        \MageOS\NetSuiteConnector\Core\Model\Pipeline\ProcessorSorter $sorter,
        private array $processors = []
    ) {
        $this->processors = $sorter->sort($this->processors, OrderItemProcessorInterface::class);
    }

    public function process(SalesOrder $netsuiteOrder, OrderInterface $magentoOrder): void
    {
        $this->nsOrderItemList->initOrderItemList($netsuiteOrder);
        foreach ($magentoOrder->getItems() as $item) {
            if ($this->skipRule->shouldSkip($item, $magentoOrder)) {
                continue;
            }
            $product = $this->productRepository->getById($item->getProductId());
            $netsuiteItem = new SalesOrderItem();
            foreach ($this->processors as $processor) {
                /** @var OrderItemProcessorInterface $processor */
                $processor->processItem($netsuiteOrder, $netsuiteItem, $item, $product, $magentoOrder);
            }
        }
    }
}
