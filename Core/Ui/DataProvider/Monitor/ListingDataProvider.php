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

namespace MageOS\NetSuiteConnector\Core\Ui\DataProvider\Monitor;

class ListingDataProvider extends \Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider
{
    private const ORIGINAL_VALUE_FIELDS = ['process', 'entity', 'item_id', 'status'];
    private const EXCLUDED_FIELDS = ['payload', 'process_output'];

    /**
     * Adding an "_original" copy of the fields the grid columns need, and dropping fields the grid does not show
     */
    public function getData()
    {
        $data = parent::getData();
        foreach ($data['items'] as &$item) {
            foreach (self::ORIGINAL_VALUE_FIELDS as $field) {
                if (array_key_exists($field, $item)) {
                    $item[$field . '_original'] = $item[$field];
                }
            }

            foreach (self::EXCLUDED_FIELDS as $field) {
                unset($item[$field]);
            }
        }

        return $data;
    }
}
