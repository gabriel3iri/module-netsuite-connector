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

namespace MageOS\NetSuiteConnector\Order\Model\Export;

use Magento\Bundle\Model\Product\Type as BundleProduct;
use Magento\Catalog\Model\Product\Type;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableProduct;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;

class OrderItemSkipRule
{
    public function __construct(
        private readonly \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        private readonly \MageOS\NetSuiteConnector\Product\Model\Config\ProductConfig $productConfig
    ) {
    }

    public function shouldSkip(OrderItemInterface $magentoOrderItem, OrderInterface $magentoOrder): bool
    {
        if (in_array($magentoOrderItem->getProductType(), [ConfigurableProduct::TYPE_CODE], true)) {
            return true;
        }
        $parentItem = $magentoOrderItem->getParentItem();
        if ($parentItem && $parentItem->getProductType() === BundleProduct::TYPE_CODE) {
            return true;
        }
        if ($this->productIsPartOfFixedPriceBundle($magentoOrderItem, $magentoOrder->getItems())
            && !$this->productConfig->getPushLineItemsWithBundles()
        ) {
            return true;
        }
        return false;
    }

    /**
     * A fixed price bundle's children are simple products with a zero row total.
     */
    private function productIsPartOfFixedPriceBundle(OrderItemInterface $orderItem, $allOrderItems): bool
    {
        if ($orderItem->getProductType() != Type::TYPE_SIMPLE) {
            return false;
        }

        if ($orderItem->getRowTotal() != 0) {
            return false;
        }

        foreach ($allOrderItems as $currentOrderItem) {
            if ($currentOrderItem->getProductType() == BundleProduct::TYPE_CODE) {
                $bundleProduct = $this->productRepository->getById($currentOrderItem->getProductId());

                $selection = $bundleProduct->getTypeInstance()->getSelectionsCollection(
                    $bundleProduct->getTypeInstance()->getOptionsIds($bundleProduct),
                    $bundleProduct
                );
                foreach ($selection as $child) {
                    if ($child->getId() == $orderItem->getProductId()) {
                        return true;
                    }
                }
            }
            return false;
        }
        return false;
    }
}
