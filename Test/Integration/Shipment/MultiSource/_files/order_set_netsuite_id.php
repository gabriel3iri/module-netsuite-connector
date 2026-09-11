<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

$order = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
    ->create('Magento\Sales\Model\Order');
$order->loadByIncrementId('100000001');
$order->setNetsuiteInternalId('1');
$order->save();
