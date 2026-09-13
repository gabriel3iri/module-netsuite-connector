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

namespace MageOS\NetSuiteConnector\Order\Model\Mapper;

use Magento\Sales\Api\Data\OrderInterface;
use NetSuite\Classes\SalesOrder;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderProcessorInterface;

class Order
{
    public function __construct(
        \MageOS\NetSuiteConnector\Core\Model\Pipeline\ProcessorSorter $sorter,
        private array $processors = []
    ) {
        $this->processors = $sorter->sort($this->processors, OrderProcessorInterface::class);
    }

    public function getNetsuiteFormat(OrderInterface $magentoOrder): SalesOrder
    {
        $netsuiteOrder = new SalesOrder();
        foreach ($this->processors as $processor) {
            /** @var OrderProcessorInterface $processor */
            $processor->process($netsuiteOrder, $magentoOrder);
        }

        return $netsuiteOrder;
    }
}
