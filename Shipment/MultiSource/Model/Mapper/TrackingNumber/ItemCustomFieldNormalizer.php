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

namespace MageOS\NetSuiteConnector\Shipment\MultiSource\Model\Mapper\TrackingNumber;

use NetSuite\Classes\Record;
use MageOS\NetSuiteConnector\Core\Model\NetSuite\CustomFieldAccess;

class ItemCustomFieldNormalizer
{
    const CUST_FIELD_FOR_TRACKING_NUMBER = 'custcol_rw_cf_msi_tracking_number';

    /**
     * Returns the package tracking numbers unchanged when no shipment item carries the custom field. When only
     * some items carry it, the package tracking numbers that no custom field names are appended.
     *
     * @param array<int, array{number: string, description: string}> $originalTrackingNumbers from the package lists
     * @return array<int, array{number: string, description: string}>
     */
    public function normalize(Record $netsuiteShipment, array $originalTrackingNumbers): array
    {
        $result = [];
        $trackingNumbers = [];
        $needToMerge = false;
        foreach ($netsuiteShipment->itemList->item as $netsuiteShipmentItem) {
            $trackingNumber = CustomFieldAccess::get(
                $netsuiteShipmentItem,
                self::CUST_FIELD_FOR_TRACKING_NUMBER
            );
            if (null !== $trackingNumber) {
                $trackingNumbers[] = $trackingNumber;
            } else {
                $needToMerge = true;
            }
        }
        $trackingNumbers = array_unique($trackingNumbers);
        if (empty($trackingNumbers)) {
            return $originalTrackingNumbers;
        }
        foreach ($originalTrackingNumbers as $key => $originalTrackingNumber) {
            foreach ($trackingNumbers as $trackingNumber) {
                if ($originalTrackingNumber['number'] == $trackingNumber) {
                    $result[] = $originalTrackingNumber;
                    unset($originalTrackingNumbers[$key]);
                } else {
                    $result[] = ['number' => $trackingNumber, 'description' => ''];
                }
            }
        }
        if ($needToMerge) {
            $result = array_merge($result, $originalTrackingNumbers);
        }
        return $result;
    }
}
