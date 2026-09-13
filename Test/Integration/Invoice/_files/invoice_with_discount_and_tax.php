<?php
//phpcs:ignoreFile
require __DIR__ . '/order.php';

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

/** @var \Magento\Sales\Model\Order $order */
$order = $objectManager->create(\Magento\Sales\Model\Order::class);
$order->loadByIncrementId('100000001');
$order->setDiscountDescription('Order discount');
$order->save();

$registry = $objectManager->get(\MageOS\NetSuiteConnector\Core\Registry\ModuleRegistry::class);
$registry->register('netsuite_skip_invoice_export', true);

$orderService = $objectManager->create(\Magento\Sales\Api\InvoiceManagementInterface::class);
$invoice = $orderService->prepareInvoice($order);
$invoice->register();

/** @var \Magento\Sales\Model\Order\Invoice\Item $invoiceItem */
foreach ($invoice->getAllItems() as $invoiceItem) {
    $invoiceItem->setDiscountAmount(15.00);
    $invoiceItem->setBaseDiscountAmount(15.00);
}
$invoice->setDiscountAmount(15.00);
$invoice->setBaseDiscountAmount(15.00);
$invoice->setTaxAmount(6.75);
$invoice->setBaseTaxAmount(6.75);

$order = $invoice->getOrder();
$order->setIsInProcess(true);
$transactionSave = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
    ->create(\Magento\Framework\DB\Transaction::class);
$transactionSave->addObject($invoice)->addObject($order)->save();
