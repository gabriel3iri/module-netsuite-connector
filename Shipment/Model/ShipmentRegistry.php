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
 *
 */


namespace MageOS\NetSuiteConnector\Shipment\Model;

use Magento\Sales\Api\Data\ShipmentInterface;

/**
 * This class is responsible for loading shipment by NS internal ID. Loaded shipments are cached inside the class
 * variable.
 */
class ShipmentRegistry
{
    /**
     * @var array
     */
    private $shipmentCache = [];

    /**
     * @param \Magento\Sales\Api\ShipmentRepositoryInterface $shipmentRepository
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
     * @param \Magento\InventoryShipping\Model\ResourceModel\ShipmentSource\GetSourceCodeByShipmentId $getSourceCodeByShipmentId
     */
    public function __construct(
        private readonly \Magento\Sales\Api\ShipmentRepositoryInterface $shipmentRepository,
        private readonly \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly \Magento\InventoryShipping\Model\ResourceModel\ShipmentSource\GetSourceCodeByShipmentId $getSourceCodeByShipmentId
    ) {
    }

    /**
     * Load magento shipment based on NS internal ID, and, in multi source mode, on the Magento source code that
     * the shipment was created for. With no source code the lookup matches on the NetSuite ID alone, exactly as
     * single source mode has always done.
     *
     * @param int $internalNetSuiteId
     * @param string|null $sourceCode
     * @return ShipmentInterface|null
     */
    public function getShipmentByNetsuiteId($internalNetSuiteId, ?string $sourceCode = null)
    {
        $cacheKey = $sourceCode === null ? $internalNetSuiteId : $internalNetSuiteId . ':' . $sourceCode;
        if (isset($this->shipmentCache[$cacheKey])) {
            return $this->shipmentCache[$cacheKey];
        }

        $this->searchCriteriaBuilder->addFilter('netsuite_internal_id', $internalNetSuiteId);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $shipments = $this->shipmentRepository->getList($searchCriteria)->getItems();

        if ($sourceCode === null) {
            if (\count($shipments)) {
                $this->shipmentCache[$cacheKey] = array_pop($shipments);
                return $this->shipmentCache[$cacheKey];
            }
            return null;
        }

        foreach ($shipments as $shipment) {
            if ($this->getSourceCodeByShipmentId->execute((int)$shipment->getEntityId()) === $sourceCode) {
                $this->shipmentCache[$cacheKey] = $shipment;
                return $this->shipmentCache[$cacheKey];
            }
        }

        return null;
    }
}
