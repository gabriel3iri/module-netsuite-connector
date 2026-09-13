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

namespace MageOS\NetSuiteConnector\Discount\Model\Invoice\Import;

use MageOS\NetSuiteConnector\Core\Exception\DataIntegrityException;
use MageOS\NetSuiteConnector\Discount\Model\Config\Source\LogicSwitcher;
use NetSuite\Classes\Record;

class CashSaleDiscount
{
    public function __construct(
        private readonly \MageOS\NetSuiteConnector\Discount\Model\Config\DiscountConfig $discountConfig
    ) {
    }

    public function getAmount(Record $cashSale): float|int
    {
        if ($this->discountConfig->getLogicSwitch() === LogicSwitcher::BODY) {
            $result = $cashSale->discountRate;
            if (strpos((string)$result, '%') !== false) {
                throw new DataIntegrityException(
                    'The CashSale Discount Rate is a percent value which is not supported'
                );
            }
            return (float)$result;
        }

        $discountItemId = $this->discountConfig->getDiscountItemId();
        foreach ($cashSale->itemList->item as $item) {
            if ($item->item->internalId == $discountItemId) {
                return abs((float)$item->amount);
            }
        }

        return 0;
    }
}
