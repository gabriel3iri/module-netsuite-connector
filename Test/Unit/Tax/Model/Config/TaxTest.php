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

namespace MageOS\NetSuiteConnector\Test\Unit\Tax\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use MageOS\NetSuiteConnector\Core\Exception\ConfigurationException;
use MageOS\NetSuiteConnector\Core\Model\Config\ConfigurationResolver;
use MageOS\NetSuiteConnector\Core\Model\Config\ConfigurationResolverFactory;
use MageOS\NetSuiteConnector\Tax\Model\Config\Source\Tax as TaxLogic;
use MageOS\NetSuiteConnector\Tax\Model\Config\Tax;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TaxTest extends TestCase
{
    public function testOnlyTheConfiguredTaxLogicIsActive(): void
    {
        $config = $this->createConfig(['mageos_netsuite/tax/tax_logic' => TaxLogic::TAX_HANDLING_TAX_ITEM]);

        $this->assertTrue($config->isTaxLogicActive(TaxLogic::TAX_HANDLING_TAX_ITEM, 'order_export'));
        $this->assertFalse($config->isTaxLogicActive(TaxLogic::TAX_HANDLING_NETSUITE_SIDE, 'order_export'));
    }

    public function testNoTaxLogicIsActiveWhenTaxIsSkipped(): void
    {
        $config = $this->createConfig([
            'mageos_netsuite/tax/tax_logic' => 'unknown',
            'mageos_netsuite/tax/skip_tax' => '1',
        ]);

        $this->assertFalse($config->isTaxLogicActive(TaxLogic::TAX_HANDLING_TAX_ITEM, 'order_export'));
    }

    #[DataProvider('taxManagerProvider')]
    public function testAnUnknownTaxLogicThrowsTheMessageOfTheTaxManager(string $taxManager): void
    {
        $config = $this->createConfig(['mageos_netsuite/tax/tax_logic' => 'unknown']);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage(
            "There is no Tax Logic set for $taxManager tax manager. Please check NSC configuration."
        );

        $config->isTaxLogicActive(TaxLogic::TAX_HANDLING_TAX_ITEM, $taxManager);
    }

    public static function taxManagerProvider(): array
    {
        return [
            'order export' => ['order_export'],
            'invoice export' => ['invoice_export'],
        ];
    }

    private function createConfig(array $values): Tax
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(static fn ($path) => $values[$path] ?? null);

        $serializer = $this->createStub(SerializerInterface::class);
        $factory = $this->createStub(ConfigurationResolverFactory::class);
        $factory->method('create')->willReturnCallback(
            static fn (array $data): ConfigurationResolver => new ConfigurationResolver(
                $scopeConfig,
                $serializer,
                $data['optionsMap'],
                $data['cacheEnabled']
            )
        );

        return new Tax($factory, new TaxLogic());
    }
}
