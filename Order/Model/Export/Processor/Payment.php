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
use Magento\Sales\Api\Data\OrderPaymentInterface;
use NetSuite\Classes\RecordRef;
use NetSuite\Classes\RecordType;
use NetSuite\Classes\SalesOrder;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderProcessorInterface;

class Payment implements OrderProcessorInterface
{
    public function __construct(
        private readonly \Magento\Store\Api\StoreRepositoryInterface $storeRepository,
        private readonly \MageOS\NetSuiteConnector\Order\Model\Config\SalesConfig $salesConfig,
        private readonly \MageOS\NetSuiteConnector\Order\Model\PaymentProcessorFactory $paymentProcessorFactory
    ) {
    }

    public function process(SalesOrder $netsuiteOrder, OrderInterface $magentoOrder): void
    {
        $this->addPaymentMethodData($netsuiteOrder, $magentoOrder);
        $this->addPaymentProcessorData($netsuiteOrder, $magentoOrder);
    }

    private function addPaymentMethodData($netsuiteOrder, OrderInterface $magentoOrder)
    {
        $paymentMethodNetsuiteId = $this->getNetsuitePaymentMethodInternalId($magentoOrder->getPayment());
        if (null !== $paymentMethodNetsuiteId) {
            $netsuitePaymentMethod = new RecordRef();
            $netsuitePaymentMethod->type = RecordType::paymentMethod;
            $netsuitePaymentMethod->internalId = $paymentMethodNetsuiteId;
            $netsuiteOrder->paymentMethod = $netsuitePaymentMethod;
        }
    }

    private function addPaymentProcessorData($netsuiteOrder, OrderInterface $magentoOrder)
    {
        $configItem = $this->getPaymentProcessorConfigItem($magentoOrder);
        if (null !== $configItem) {
            $netsuiteOrder->creditCardProcessor = new RecordRef();
            $netsuiteOrder->creditCardProcessor->internalId = $configItem['internal_netsuite_id'];

            $paymentProcessor = $this->paymentProcessorFactory->create($configItem['payment_processor']);
            $paymentProcessor->addProcessorSpecificInformationToNetSuiteOrder($netsuiteOrder, $magentoOrder);
        }
    }

    private function getPaymentProcessorConfigItem(OrderInterface $magentoOrder)
    {
        $paymentProcessorConfig = $this->salesConfig->getProcessorMapping();
        $websiteId = $this->getWebsiteIdFromStoreId($magentoOrder->getStoreId());

        if (!is_array($paymentProcessorConfig) || count($paymentProcessorConfig) == 0) {
            return null;
        }
        foreach ($paymentProcessorConfig as $configItem) {
            if ($configItem['payment_method'] == $magentoOrder->getPayment()->getMethod()
                && ($configItem['website'] == 0 || ($websiteId == $configItem['website']))
            ) {
                return $configItem;
            }
        }
        return null;
    }

    private function getNetsuitePaymentMethodInternalId(OrderPaymentInterface $magentoPaymentObject)
    {
        $paymentMapping = $this->salesConfig->getNetsuiteMapping();
        foreach ($paymentMapping as $paymentMappingElement) {
            $paymentMethod = $paymentMappingElement['payment_method'] ?? null;
            if ($paymentMethod === $magentoPaymentObject->getMethod()) {
                if ($paymentMappingElement['payment_cc'] === '') {
                    return $paymentMappingElement['internal_netsuite_id'];
                }
                if ($magentoPaymentObject->getCcType() == $paymentMappingElement['payment_cc']) {
                    return $paymentMappingElement['internal_netsuite_id'];
                }
            }
        }

        return null;
    }

    private function getWebsiteIdFromStoreId($storeId)
    {
        return $this->storeRepository->getById($storeId)->getWebsiteId();
    }
}
