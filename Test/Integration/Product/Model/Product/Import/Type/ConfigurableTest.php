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
 */


namespace MageOS\NetSuiteConnector\Test\Integration\Product\Model\Product\Import\Type;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Filesystem;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\Source\Csv;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\ObjectManager;
use MageOS\NetSuiteConnector\Core\Model\Plugin\ImportExport\PluginState;
use PHPUnit\Framework\TestCase;

/**
 * Drives real catalog product imports and checks which children the
 * configurable link tables keep.
 */
class ConfigurableTest extends TestCase
{
    private const CSV_FILE = 'nsc_configurable_link_cleaner.csv';

    private const COLUMNS = [
        'sku',
        'attribute_set_code',
        'product_type',
        'product_websites',
        'name',
        'price',
        'url_key',
        'nsc_cfg_color',
        'configurable_variations',
    ];

    private ObjectManager $objectManager;

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
    }

    protected function tearDown(): void
    {
        $this->objectManager->get(PluginState::class)->setRunning(false);
        $directory = $this->objectManager->get(Filesystem::class)
            ->getDirectoryWrite(DirectoryList::VAR_IMPORT_EXPORT);
        if ($directory->isExist(self::CSV_FILE)) {
            $directory->delete(self::CSV_FILE);
        }
    }

    /**
     * A plain import (admin CSV) that lists 2 of the 3 children keeps the third
     * child in both link tables.
     *
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Product/_files/configurable_import_attribute.php
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testPlainImportKeepsChildrenTheFileDoesNotList(): void
    {
        $this->seedConfigurables();

        $this->import([
            $this->configurableRow('nsc-cfg-1', [['nsc-cfg-1-red', 'Red'], ['nsc-cfg-1-blue', 'Blue']]),
        ]);

        $expected = ['nsc-cfg-1-black', 'nsc-cfg-1-blue', 'nsc-cfg-1-red'];
        $this->assertSame($expected, $this->linkedSkus('catalog_product_super_link', 'nsc-cfg-1'));
        $this->assertSame($expected, $this->linkedSkus('catalog_product_relation', 'nsc-cfg-1'));
    }

    /**
     * A connector import with one configurable per bunch keeps only the listed
     * children of every configurable, the first bunch included, in both link tables.
     *
     * @magentoConfigFixture current_store general/file/bunch_size 1
     * @magentoDataFixture MageOS_NetSuiteConnector::Test/Integration/Product/_files/configurable_import_attribute.php
     * @magentoAppIsolation enabled
     * @magentoDbIsolation enabled
     */
    public function testConnectorImportCleansEveryBunch(): void
    {
        $this->seedConfigurables();
        $this->objectManager->get(PluginState::class)->setRunning(true);

        $this->import([
            $this->configurableRow('nsc-cfg-1', [['nsc-cfg-1-red', 'Red'], ['nsc-cfg-1-blue', 'Blue']]),
            $this->configurableRow('nsc-cfg-2', [['nsc-cfg-2-red', 'Red']]),
            $this->configurableRow('nsc-cfg-3', [['nsc-cfg-3-red', 'Red']]),
        ]);

        $expected = [
            'nsc-cfg-1' => ['nsc-cfg-1-blue', 'nsc-cfg-1-red'],
            'nsc-cfg-2' => ['nsc-cfg-2-red'],
            'nsc-cfg-3' => ['nsc-cfg-3-red'],
        ];
        foreach ($expected as $parentSku => $childSkus) {
            $this->assertSame($childSkus, $this->linkedSkus('catalog_product_super_link', $parentSku), $parentSku);
            $this->assertSame($childSkus, $this->linkedSkus('catalog_product_relation', $parentSku), $parentSku);
        }
    }

    private function seedConfigurables(): void
    {
        $rows = [];
        $colors = ['Red', 'Blue', 'Black'];
        foreach ([1, 2, 3] as $index) {
            $variations = [];
            foreach ($colors as $color) {
                $childSku = 'nsc-cfg-' . $index . '-' . strtolower($color);
                $rows[] = [
                    $childSku,
                    'Default',
                    'simple',
                    'base',
                    $childSku,
                    '10',
                    $childSku,
                    $color,
                    '',
                ];
                $variations[] = [$childSku, $color];
            }
            $rows[] = $this->configurableRow('nsc-cfg-' . $index, $variations);
        }

        $this->import($rows);

        $this->assertCount(3, $this->linkedSkus('catalog_product_super_link', 'nsc-cfg-1'));
        $this->assertCount(3, $this->linkedSkus('catalog_product_relation', 'nsc-cfg-1'));
    }

    private function configurableRow(string $sku, array $children): array
    {
        $variations = [];
        foreach ($children as [$childSku, $color]) {
            $variations[] = 'sku=' . $childSku . ',nsc_cfg_color=' . $color;
        }

        return [
            $sku,
            'Default',
            'configurable',
            'base',
            $sku,
            '12',
            $sku,
            '',
            implode('|', $variations),
        ];
    }

    private function import(array $rows): void
    {
        $directory = $this->objectManager->get(Filesystem::class)
            ->getDirectoryWrite(DirectoryList::VAR_IMPORT_EXPORT);
        $directory->create();
        $stream = $directory->openFile(self::CSV_FILE, 'w');
        $stream->writeCsv(self::COLUMNS);
        foreach ($rows as $row) {
            $stream->writeCsv($row);
        }
        $stream->close();

        $import = $this->objectManager->create(Import::class);
        $import->setData([
            'entity' => 'catalog_product',
            'behavior' => Import::BEHAVIOR_APPEND,
            'validation_strategy' => 'validation-stop-on-errors',
            'allowed_error_count' => 0,
            '_import_field_separator' => ',',
            '_import_multiple_value_separator' => ',',
        ]);
        $source = $this->objectManager->create(
            Csv::class,
            ['file' => self::CSV_FILE, 'directory' => $directory]
        );

        $valid = $import->validateSource($source);
        $messages = [];
        foreach ($import->getErrorAggregator()->getAllErrors() as $error) {
            $messages[] = 'row ' . $error->getRowNumber() . ': ' . $error->getErrorMessage();
        }
        $this->assertTrue($valid, implode("\n", $messages));

        $this->assertTrue($import->importSource());
    }

    private function linkedSkus(string $table, string $parentSku): array
    {
        $resource = $this->objectManager->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $childColumn = $table === 'catalog_product_relation' ? 'child_id' : 'product_id';
        $select = $connection->select()
            ->from(['link' => $resource->getTableName($table)], [])
            ->join(
                ['parent' => $resource->getTableName('catalog_product_entity')],
                'parent.entity_id = link.parent_id',
                []
            )
            ->join(
                ['child' => $resource->getTableName('catalog_product_entity')],
                'child.entity_id = link.' . $childColumn,
                ['sku']
            )
            ->where('parent.sku = ?', $parentSku)
            ->order('child.sku ASC');

        return $connection->fetchCol($select);
    }
}
