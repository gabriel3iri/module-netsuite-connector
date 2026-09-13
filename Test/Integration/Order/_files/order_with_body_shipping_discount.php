<?php
require __DIR__ . '/order.php';

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

$order = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
    ->create('Magento\Sales\Model\Order');
$order->loadByIncrementId('100000001');
$order->setDiscountAmount(-11);
$order->setShippingDiscountAmount(1);
$order->save();
