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
use NetSuite\Classes\RecordRef;
use NetSuite\Classes\RecordType;
use NetSuite\Classes\SalesOrder;
use MageOS\NetSuiteConnector\Core\Exception\DataIntegrityException;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderProcessorInterface;

class Customer implements OrderProcessorInterface
{
    public function __construct(
        private readonly \MageOS\NetSuiteConnector\Customer\Model\Mapper\Customer $customerMapperHelper
    ) {
    }

    public function process(SalesOrder $netsuiteOrder, OrderInterface $magentoOrder): void
    {
        $netsuiteOrder->entity = $this->createCustomer($this->getNetsuiteCustomerId($magentoOrder));
    }

    private function getNetsuiteCustomerId($magentoOrder)
    {
        $netsuiteCustomerId = $this->customerMapperHelper->createNetsuiteCustomerFromOrder($magentoOrder);
        if (!$netsuiteCustomerId) {
            throw new DataIntegrityException("Could not find / create the netsuite customer externalIdString="
                . $magentoOrder->getCustomerId());
        }
        return $netsuiteCustomerId;
    }

    private function createCustomer($netsuiteCustomerId): RecordRef
    {
        $netsuiteCustomer = new RecordRef();
        $netsuiteCustomer->type = RecordType::customer;
        $netsuiteCustomer->internalId = $netsuiteCustomerId;
        return $netsuiteCustomer;
    }
}
