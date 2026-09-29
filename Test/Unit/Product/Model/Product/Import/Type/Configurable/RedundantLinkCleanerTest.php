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
 */

namespace MageOS\NetSuiteConnector\Test\Unit\Product\Model\Product\Import\Type\Configurable;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use MageOS\NetSuiteConnector\Product\Model\Product\Import\Type\Configurable\RedundantLinkCleaner;
use PHPUnit\Framework\TestCase;

class RedundantLinkCleanerTest extends TestCase
{
    /**
     * Given super attribute data, delete() runs on both tables with the
     * conditions built from that data.
     */
    public function testItDeletesRedundantAttributesAndLinks(): void
    {
        $resource = $this->createStub(ResourceConnection::class);
        $connection = $this->createMock(AdapterInterface::class);

        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $connection->method('quoteInto')->willReturnCallback(
            static function (string $text, $value): string {
                $formatted = is_array($value) ? implode(',', $value) : (string)$value;
                return str_replace('?', $formatted, $text);
            }
        );

        $calls = [];
        $connection->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(function (string $table, string $where) use (&$calls): int {
                $calls[] = [$table, $where];
                return 1;
            });

        $superAttributesData = [
            'attributes' => [
                10 => [
                    1 => ['product_super_attribute_id' => 1],
                    2 => ['product_super_attribute_id' => 2],
                ],
            ],
            'super_link' => [
                ['parent_id' => 10, 'product_id' => 20],
                ['parent_id' => 10, 'product_id' => 21],
            ],
        ];

        $cleaner = new RedundantLinkCleaner($resource);
        $cleaner->clean($superAttributesData);

        $this->assertSame('catalog_product_super_attribute', $calls[0][0]);
        $this->assertSame('(product_id=10 AND attribute_id NOT IN(1,2))', $calls[0][1]);
        $this->assertSame('catalog_product_super_link', $calls[1][0]);
        $this->assertSame('(parent_id=10 AND product_id NOT IN(20,21))', $calls[1][1]);
    }

    /**
     * Relation entries delete the rows of catalog_product_relation that the
     * parent no longer lists, matching the super link delete.
     */
    public function testItDeletesRedundantRelations(): void
    {
        $resource = $this->createStub(ResourceConnection::class);
        $connection = $this->createMock(AdapterInterface::class);

        $resource->method('getConnection')->willReturn($connection);
        $resource->method('getTableName')->willReturnArgument(0);
        $connection->method('quoteInto')->willReturnCallback(
            static function (string $text, $value): string {
                $formatted = is_array($value) ? implode(',', $value) : (string)$value;
                return str_replace('?', $formatted, $text);
            }
        );

        $calls = [];
        $connection->expects($this->exactly(3))
            ->method('delete')
            ->willReturnCallback(function (string $table, string $where) use (&$calls): int {
                $calls[$table] = $where;
                return 1;
            });

        $superAttributesData = [
            'attributes' => [
                10 => [1 => ['product_super_attribute_id' => 1]],
            ],
            'super_link' => [
                ['parent_id' => 10, 'product_id' => 20],
                ['parent_id' => 10, 'product_id' => 21],
            ],
            'relation' => [
                ['parent_id' => 10, 'child_id' => 20],
                ['parent_id' => 10, 'child_id' => 21],
                ['parent_id' => 11, 'child_id' => 30],
            ],
        ];

        $cleaner = new RedundantLinkCleaner($resource);
        $cleaner->clean($superAttributesData);

        $this->assertSame(
            '(parent_id=10 AND child_id NOT IN(20,21)) OR (parent_id=11 AND child_id NOT IN(30))',
            $calls['catalog_product_relation']
        );
    }

    /**
     * Null data, the shape saveData() passes when no configurable was in the
     * bunch, calls nothing.
     */
    public function testNullDataCallsNothing(): void
    {
        $resource = $this->createMock(ResourceConnection::class);
        $resource->expects($this->never())->method('getConnection');

        $cleaner = new RedundantLinkCleaner($resource);
        $cleaner->clean(null);
    }
}
