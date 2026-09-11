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

namespace MageOS\NetSuiteConnector\Shipment\MultiSource\Model\Mapper\ShipmentMultiSource;

use Magento\Framework\Exception\NoSuchEntityException;
use NetSuite\Classes\Record;
use MageOS\NetSuiteConnector\Core\Exception\ConnectorRuntimeException;
use MageOS\NetSuiteConnector\Core\Exception\SkipRecordException;

/**
 * This class creates an array of cloned object with items from same location/source
 * not mapped locations is ignored
 */
class LocationGrouper
{
    private \MageOS\NetSuiteConnector\Inventory\Multi\Model\MagentoSourceRepository $magentoSourceRepository;

    /**
     * LocationGrouper constructor.
     * @param \MageOS\NetSuiteConnector\Inventory\Multi\Model\MagentoSourceRepository $magentoSourceRepository
     */
    public function __construct(
        \MageOS\NetSuiteConnector\Inventory\Multi\Model\MagentoSourceRepository $magentoSourceRepository
    ) {
        $this->magentoSourceRepository = $magentoSourceRepository;
    }

    /**
     * returns an array of the cloned fulfillment each of the fulfillment contains all items from same location,
     * keyed by the matched Magento source code
     * @param Record $baseNetsuiteShipment
     * @return \Generator
     * @throws ConnectorRuntimeException
     */
    public function group(Record $baseNetsuiteShipment): \Generator
    {
        $itemsGroups = [];
        $sourceCodes = [];
        foreach ($baseNetsuiteShipment->itemList->item as $netsuiteShipmentItem) {
            $magentoSource = $this->magentoSourceRepository
                ->getSourceByNetSuiteData((int)$netsuiteShipmentItem->location->internalId, null);
            //we ignore items with locations that is not mapped
            if (null === $magentoSource) {
                continue;
            }
            $locationId = $netsuiteShipmentItem->location->internalId;
            $itemsGroups[$locationId][] = $netsuiteShipmentItem;
            $sourceCodes[$locationId] = $magentoSource->getSourceCode();
        }
        if (empty($itemsGroups)) {
            throw new ConnectorRuntimeException(
                "Fulfillment #{$baseNetsuiteShipment->entity->internalId} have items with not mapped locations!"
            );
        }
        //for each item group we create separate fulfillment that will contain only items with same location
        foreach ($itemsGroups as $locationId => $itemsGroup) {
            $groupedFulfillment = clone $baseNetsuiteShipment;
            $groupedFulfillment->itemList = clone $baseNetsuiteShipment->itemList;
            $groupedFulfillment->itemList->item = $itemsGroup;
            yield $sourceCodes[$locationId] => $groupedFulfillment;
        }
    }
}
