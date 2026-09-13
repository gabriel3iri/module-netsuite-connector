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

use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Address as OrderAddress;
use NetSuite\Classes\Address;
use NetSuite\Classes\SalesOrder;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderProcessorInterface;

class Addresses implements OrderProcessorInterface
{
    public function __construct(
        private readonly \MageOS\NetSuiteConnector\Core\Helper\Transform $transformHelper,
        private readonly \Magento\Framework\Event\ManagerInterface $eventManager,
        private readonly \Magento\Sales\Api\OrderAddressRepositoryInterface $orderAddressRepository,
        private readonly \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
    }

    /**
     * A virtual order sends its billing address as the shipping address, so that NetSuite calculates the tax.
     */
    public function process(SalesOrder $netsuiteOrder, OrderInterface $magentoOrder): void
    {
        $netsuiteOrder->billingAddress = $this->createAddress($magentoOrder->getBillingAddress());

        if (!$magentoOrder->getIsVirtual()) {
            $addresses = $this->getOrderAddresses($magentoOrder);
            foreach ($addresses as $address) {
                if ($address->getAddressType() == OrderAddress::TYPE_SHIPPING) {
                    $netsuiteOrder->shippingAddress = $this->createAddress($address);
                }
            }
        } else {
            $netsuiteOrder->shippingAddress = $this->createAddress($magentoOrder->getBillingAddress());
        }
    }

    public function createAddress(OrderAddressInterface $address): Address
    {
        $netsuiteAddress = new Address();
        $netsuiteAddress->addr1 = isset($address->getStreet()[0]) ? $address->getStreet()[0] : '';
        $netsuiteAddress->addr2 = isset($address->getStreet()[1]) ? $address->getStreet()[1] : '';
        $netsuiteAddress->city = $address->getCity();
        $netsuiteAddress->country = $this->transformHelper->transformCountryCode($address->getCountryId());
        $netsuiteAddress->addressee = $address->getFirstname() . ' ' . $address->getLastname();
        $netsuiteAddress->addrPhone = $this->sanitizePhoneNumber($address->getTelephone());
        $netsuiteAddress->state = $address->getRegionCode();
        $netsuiteAddress->zip = $address->getPostcode();

        $this->eventManager->dispatch('netsuite_address_create_before', ['netsuite_address' => $netsuiteAddress]);
        return $netsuiteAddress;
    }

    private function getOrderAddresses(OrderInterface $magentoOrder): array
    {
        $this->searchCriteriaBuilder->addFilter('parent_id', $magentoOrder->getEntityId());
        $searchCriteria = $this->searchCriteriaBuilder->create();

        return $this->orderAddressRepository->getList($searchCriteria)->getItems();
    }

    /**
     * Keeps digits only and drops a number shorter than the NetSuite minimum of 7 digits.
     * Public so that plugins can change it.
     */
    public function sanitizePhoneNumber($phoneNumber): string
    {
        $phoneNumber = preg_replace('/[^\d]/', '', trim($phoneNumber??''));
        if (strlen($phoneNumber) < 7) {
            $phoneNumber = '';
        }
        return $phoneNumber;
    }
}
