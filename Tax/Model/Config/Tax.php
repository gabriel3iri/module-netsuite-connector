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
 *
 */
declare(strict_types=1);

namespace MageOS\NetSuiteConnector\Tax\Model\Config;

use MageOS\NetSuiteConnector\Core\Exception\ConfigurationException;
use MageOS\NetSuiteConnector\Core\Model\Config\AbstractConfig;

/**
 * @method int getTaxItemInternalNetsuiteId
 * @method int getNotTaxableInternalNetsuiteId
 * @method string getTaxLogic
 * @method string getSalesOrderTaxAmountId
 * @method string getSalesOrderTotalAmountId
 * @method int getNetSuiteShippingTaxId
 * @method bool getSkipTax
 */
class Tax extends AbstractConfig
{
    private const TAX_LOGIC = 'mageos_netsuite/tax/tax_logic';
    private const TAX_ITEM_INTERNAL_ID = 'mageos_netsuite/tax/tax_item_internal_netsuite_id';
    private const NOT_TAXABLE_INTERNAL_NETSUITE_ID = 'mageos_netsuite/tax/not_taxable_internal_netsuite_id';
    private const SALES_ORDER_TAX_AMOUNT_ID = 'mageos_netsuite/tax/sales_order_tax_amount_id';
    private const SALES_ORDER_TOTAL_AMOUNT_ID = 'mageos_netsuite/tax/sales_order_total_amount_id';
    private const NETSUITE_SHIPPING_TAX_ID = 'mageos_netsuite/tax/netsuite_shipping_tax_id';
    private const SKIP_TAX = 'mageos_netsuite/tax/skip_tax';

    public function __construct(
        \MageOS\NetSuiteConnector\Core\Model\Config\ConfigurationResolverFactory $configFactory,
        private readonly \MageOS\NetSuiteConnector\Tax\Model\Config\Source\Tax $taxLogicSource
    ) {
        parent::__construct($configFactory);
    }

    /**
     * @throws ConfigurationException when tax is not skipped and tax_logic is not an option of the admin source model
     */
    public function isTaxLogicActive(string $taxLogic, string $taxManager): bool
    {
        if ($this->getSkipTax()) {
            return false;
        }
        $configuredTaxLogic = (string)$this->getTaxLogic();
        if (!array_key_exists($configuredTaxLogic, $this->taxLogicSource->toArray())) {
            throw new ConfigurationException(
                "There is no Tax Logic set for $taxManager tax manager. Please check NSC configuration."
            );
        }
        return $configuredTaxLogic === $taxLogic;
    }

    public function getOptionsMap(): array
    {
        return [
            self::TAX_ITEM_INTERNAL_ID => 'int',
            self::NOT_TAXABLE_INTERNAL_NETSUITE_ID => 'int',
            self::TAX_LOGIC => 'string',
            self::SALES_ORDER_TAX_AMOUNT_ID => 'string',
            self::SALES_ORDER_TOTAL_AMOUNT_ID => 'string',
            self::NETSUITE_SHIPPING_TAX_ID => 'int',
            self::SKIP_TAX => 'bool',
        ];
    }
}
