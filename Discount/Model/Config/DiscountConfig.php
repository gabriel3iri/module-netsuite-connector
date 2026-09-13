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

namespace MageOS\NetSuiteConnector\Discount\Model\Config;

use MageOS\NetSuiteConnector\Core\Exception\ConnectorRuntimeException;
use MageOS\NetSuiteConnector\Core\Model\Config\AbstractConfig;

/**
 * @method int getDiscountItemId
 * @method bool getDisableOrderLevelDiscount
 * @method string getLogicSwitch
 * @method bool getAddPromotionData
 * @method bool getOrderSkipDiscount
 */
class DiscountConfig extends AbstractConfig
{
    private const DISCOUNT_ITEM_ID = 'mageos_netsuite/orders/discount_item_id';
    private const DISABLE_ORDER_LEVEL_DISCOUNT = 'mageos_netsuite/orders/disable_order_level_discount';
    private const LOGIC_SWITCH = 'mageos_netsuite/orders/logic_switch';
    private const ADD_PROMOTION_DATA = 'mageos_netsuite/orders/add_promotion_data';
    private const ORDER_SKIP_DISCOUNT = 'mageos_netsuite/orders/order_skip_discount';

    public function __construct(
        \MageOS\NetSuiteConnector\Core\Model\Config\ConfigurationResolverFactory $configFactory,
        private readonly \MageOS\NetSuiteConnector\Discount\Model\Config\Source\LogicSwitcher $logicSwitcher
    ) {
        parent::__construct($configFactory);
    }

    /**
     * @throws ConnectorRuntimeException when discounts are not skipped and logic_switch is not an option of the
     *     admin source model
     */
    public function isLogicSwitchActive(string $logicSwitch): bool
    {
        if ($this->getOrderSkipDiscount()) {
            return false;
        }
        $configuredLogicSwitch = $this->getLogicSwitch();
        if (!in_array($configuredLogicSwitch, array_column($this->logicSwitcher->toOptionArray(), 'value'), true)) {
            throw new ConnectorRuntimeException('Discount Provider mismatch with Interface!');
        }
        return $configuredLogicSwitch === $logicSwitch;
    }

    public function getOptionsMap(): array
    {
        return [
            self::DISCOUNT_ITEM_ID => 'int',
            self::DISABLE_ORDER_LEVEL_DISCOUNT => 'bool',
            self::LOGIC_SWITCH => 'string',
            self::ADD_PROMOTION_DATA => 'bool',
            self::ORDER_SKIP_DISCOUNT => 'bool',
        ];
    }
}
