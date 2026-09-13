<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

use Magento\Sales\Model\Order\Payment;

// @codingStandardsIgnoreFile

require 'default_rollback.php';
require 'product_simple_taxable.php';
/** @var \Magento\Catalog\Model\Product $product */

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

/** @var \Magento\Catalog\Model\Product $secondProduct */
$secondProduct = $objectManager->create(\Magento\Catalog\Model\Product::class);
$secondProduct->isObjectNew(true);
$secondProduct->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
    ->setId(2)
    ->setAttributeSetId(4)
    ->setWebsiteIds([1])
    ->setName('Simple Product Without NetSuite Id')
    ->setSku('simple-no-ns-id')
    ->setPrice(10)
    ->setWeight(1)
    ->setTaxClassId(0)
    ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
    ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
    ->setStockData(
        [
            'use_config_manage_stock' => 1,
            'qty' => 100,
            'is_qty_decimal' => 0,
            'is_in_stock' => 1,
        ]
    );

$productRepository = $objectManager->create(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$productRepository->save($secondProduct);

$addressData = include __DIR__ . '/address_data.php';

$billingAddress = $objectManager->create('Magento\Sales\Model\Order\Address', ['data' => $addressData]);
$billingAddress->setAddressType('billing');

$shippingAddress = clone $billingAddress;
$shippingAddress->setId(null)->setAddressType('shipping');

/** @var Payment $payment */
$payment = $objectManager->create(Payment::class);
$payment->setMethod('checkmo')
    ->setAdditionalInformation([
        'token_metadata' => [
            'token' => 'f34vjw',
            'customer_id' => 1
        ]
    ]);

$storeId = $objectManager->get('Magento\Store\Model\StoreManagerInterface')->getStore()->getId();

/** @var \Magento\Sales\Model\Order\Item $orderAItem1 */
$orderAItem1 = $objectManager->create('Magento\Sales\Model\Order\Item');
$orderAItem1->setProductId($product->getId())->setQtyOrdered(2);
$orderAItem1->setBasePrice($product->getPrice());
$orderAItem1->setPrice($product->getPrice());
$orderAItem1->setRowTotal($product->getPrice());
$orderAItem1->setSku($product->getSku());
$orderAItem1->setWeight($product->getWeight());
$orderAItem1->setProductType('simple');
$orderAItem1->setTaxAmount(2.25);

/** @var \Magento\Sales\Model\Order\Item $orderAItem2 */
$orderAItem2 = $objectManager->create('Magento\Sales\Model\Order\Item');
$orderAItem2->setProductId($secondProduct->getId())->setQtyOrdered(1);
$orderAItem2->setBasePrice($secondProduct->getPrice());
$orderAItem2->setPrice($secondProduct->getPrice());
$orderAItem2->setRowTotal($secondProduct->getPrice());
$orderAItem2->setSku($secondProduct->getSku());
$orderAItem2->setWeight($secondProduct->getWeight());
$orderAItem2->setProductType('simple');

/** @var \Magento\Sales\Model\Order $orderA */
$orderA = $objectManager->create('Magento\Sales\Model\Order');
$orderA->setIncrementId(
    '100000010'
)->setState(
    \Magento\Sales\Model\Order::STATE_PROCESSING
)->setStatus(
    $orderA->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)
)->setSubtotal(
    100
)->setGrandTotal(
    100
)->setBaseSubtotal(
    100
)->setBaseGrandTotal(
    100
)->setCustomerIsGuest(
    true
)->setCustomerEmail(
    'customer@null.com'
)->setCustomerId(
    1
)->setBillingAddress(
    $billingAddress
)->setShippingAddress(
    $shippingAddress
)->setStoreId(
    $storeId
)->addItem(
    $orderAItem1
)->addItem(
    $orderAItem2
)->setPayment(
    $payment
);
$orderA->save();

$billingAddressB = clone $billingAddress;
$billingAddressB->setId(null)->setAddressType('billing');
$shippingAddressB = clone $billingAddress;
$shippingAddressB->setId(null)->setAddressType('shipping');

$paymentB = $objectManager->create(Payment::class);
$paymentB->setMethod('checkmo')
    ->setAdditionalInformation([
        'token_metadata' => [
            'token' => 'f34vjw',
            'customer_id' => 1
        ]
    ]);

/** @var \Magento\Sales\Model\Order\Item $orderBItem */
$orderBItem = $objectManager->create('Magento\Sales\Model\Order\Item');
$orderBItem->setProductId($product->getId())->setQtyOrdered(1);
$orderBItem->setBasePrice($product->getPrice());
$orderBItem->setPrice($product->getPrice());
$orderBItem->setRowTotal($product->getPrice());
$orderBItem->setSku($product->getSku());
$orderBItem->setWeight($product->getWeight());
$orderBItem->setProductType('simple');
$orderBItem->setTaxAmount(5.00);

/** @var \Magento\Sales\Model\Order $orderB */
$orderB = $objectManager->create('Magento\Sales\Model\Order');
$orderB->setIncrementId(
    '100000011'
)->setState(
    \Magento\Sales\Model\Order::STATE_PROCESSING
)->setStatus(
    $orderB->getConfig()->getStateDefaultStatus(\Magento\Sales\Model\Order::STATE_PROCESSING)
)->setSubtotal(
    50
)->setGrandTotal(
    50
)->setBaseSubtotal(
    50
)->setBaseGrandTotal(
    50
)->setCustomerIsGuest(
    true
)->setCustomerEmail(
    'customer@null.com'
)->setCustomerId(
    1
)->setBillingAddress(
    $billingAddressB
)->setShippingAddress(
    $shippingAddressB
)->setStoreId(
    $storeId
)->addItem(
    $orderBItem
)->setPayment(
    $paymentB
);
$orderB->save();
