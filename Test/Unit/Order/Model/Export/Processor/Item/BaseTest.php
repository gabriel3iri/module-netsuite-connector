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
 */

namespace MageOS\NetSuiteConnector\Test\Unit\Order\Model\Export\Processor\Item;

use Magento\Bundle\Model\Product\Type as BundleProduct;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Framework\Api\AttributeValue;
use Magento\Sales\Api\Data\OrderItemInterface;
use NetSuite\Classes\SalesOrder;
use NetSuite\Classes\SalesOrderItem;
use PHPUnit\Framework\TestCase;
use MageOS\NetSuiteConnector\Core\Helper\EavHelper;
use MageOS\NetSuiteConnector\Order\Model\Export\Processor\Item\Base;
use MageOS\NetSuiteConnector\Order\Model\Mapper\OrderExport\Location;

/**
 * Covers MageOS\NetSuiteConnector\Order\Model\Export\Processor\Item\Base,
 * in particular that a fixed price bundle's simple children are zeroed
 * through the state kept per NetSuite order, the port of OrderItem.php:252-275
 * before the refactor.
 */
class BaseTest extends TestCase
{
    private Base $processor;

    protected function setUp(): void
    {
        $eavHelper = $this->createStub(EavHelper::class);
        $eavHelper->method('mapBundleOptionToInternalId')->willReturn(null);

        $this->processor = new Base($eavHelper, $this->createStub(Location::class));
    }

    private function process(OrderItemInterface $item, ProductInterface $product, SalesOrder $netsuiteOrder): SalesOrderItem
    {
        $netsuiteItem = new SalesOrderItem();
        $this->processor->processItem(
            $netsuiteOrder,
            $netsuiteItem,
            $item,
            $product,
            $this->createStub(\Magento\Sales\Api\Data\OrderInterface::class)
        );

        return $netsuiteItem;
    }

    private function netsuiteInternalIdAttribute(string $value): AttributeValue
    {
        $attribute = $this->createStub(AttributeValue::class);
        $attribute->method('getValue')->willReturn($value);

        return $attribute;
    }

    public function testZeroesPriceOfFixedPriceBundleChild(): void
    {
        $netsuiteOrder = new SalesOrder();

        $bundleItem = $this->createStub(\Magento\Sales\Model\Order\Item::class);
        $bundleItem->method('getId')->willReturn(10);
        $bundleItem->method('getProductId')->willReturn(100);
        $bundleItem->method('getProductType')->willReturn(BundleProduct::TYPE_CODE);
        $bundleItem->method('getRowTotal')->willReturn('50');
        $bundleItem->method('getParentItemId')->willReturn(null);
        $bundleItem->method('getPrice')->willReturn(50.0);
        $bundleItem->method('getQtyOrdered')->willReturn(1);
        $bundleItem->method('getName')->willReturn('Fixed Price Bundle');
        $bundleItem->method('getProductOptions')->willReturn(null);

        $bundleProduct = $this->createStub(ProductInterface::class);
        $bundleProduct->method('getPrice')->willReturn(50.0);
        $bundleProduct->method('getCustomAttribute')->willReturn($this->netsuiteInternalIdAttribute('5'));

        $bundleNetsuiteItem = $this->process($bundleItem, $bundleProduct, $netsuiteOrder);
        $this->assertSame(50.0, $bundleNetsuiteItem->rate, 'the bundle line itself keeps its own price');

        $childItem = $this->createStub(\Magento\Sales\Model\Order\Item::class);
        $childItem->method('getId')->willReturn(11);
        $childItem->method('getProductId')->willReturn(101);
        $childItem->method('getProductType')->willReturn(Type::TYPE_SIMPLE);
        $childItem->method('getRowTotal')->willReturn('20');
        $childItem->method('getParentItemId')->willReturn(10);
        $childItem->method('getPrice')->willReturn(20.0);
        $childItem->method('getQtyOrdered')->willReturn(1);
        $childItem->method('getName')->willReturn('Bundle Child');
        $childItem->method('getProductOptions')->willReturn(null);

        $childProduct = $this->createStub(ProductInterface::class);
        $childProduct->method('getPrice')->willReturn(20.0);
        $childProduct->method('getCustomAttribute')->willReturn($this->netsuiteInternalIdAttribute('6'));

        $childNetsuiteItem = $this->process($childItem, $childProduct, $netsuiteOrder);

        $this->assertSame(0, $childNetsuiteItem->rate, 'the fixed price bundle child must be zeroed');
    }

    public function testDoesNotZeroASimpleChildOfAnUnrelatedParent(): void
    {
        $netsuiteOrder = new SalesOrder();

        $unrelatedChild = $this->createStub(\Magento\Sales\Model\Order\Item::class);
        $unrelatedChild->method('getId')->willReturn(21);
        $unrelatedChild->method('getProductId')->willReturn(201);
        $unrelatedChild->method('getProductType')->willReturn(Type::TYPE_SIMPLE);
        $unrelatedChild->method('getRowTotal')->willReturn('30');
        $unrelatedChild->method('getParentItemId')->willReturn(999);
        $unrelatedChild->method('getPrice')->willReturn(30.0);
        $unrelatedChild->method('getQtyOrdered')->willReturn(1);
        $unrelatedChild->method('getName')->willReturn('Unrelated Simple');
        $unrelatedChild->method('getProductOptions')->willReturn(null);

        $unrelatedProduct = $this->createStub(ProductInterface::class);
        $unrelatedProduct->method('getPrice')->willReturn(30.0);
        $unrelatedProduct->method('getCustomAttribute')->willReturn($this->netsuiteInternalIdAttribute('7'));

        $netsuiteItem = $this->process($unrelatedChild, $unrelatedProduct, $netsuiteOrder);

        $this->assertSame(30.0, $netsuiteItem->rate);
    }
}
