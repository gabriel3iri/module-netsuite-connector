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

namespace MageOS\NetSuiteConnector\Test\Unit\Core\Model\Pipeline;

use MageOS\NetSuiteConnector\Core\Model\Pipeline\ProcessorSorter;
use PHPUnit\Framework\TestCase;

/**
 * Marker interface implemented by processors that belong in the pool under test.
 */
interface ProcessorSorterTestSampleInterface
{
}

/**
 * A second, unrelated interface used to prove the sorter checks the interface it was given.
 */
interface ProcessorSorterTestOtherInterface
{
}

class ProcessorSorterTestSampleProcessor implements ProcessorSorterTestSampleInterface
{
}

class ProcessorSorterTestOtherProcessor implements ProcessorSorterTestOtherInterface
{
}

class ProcessorSorterTest extends TestCase
{
    private ProcessorSorter $sorter;

    protected function setUp(): void
    {
        $this->sorter = new ProcessorSorter();
    }

    /**
     * Items sort by sortOrder ascending, whatever their declaration order.
     */
    public function testItSortsBySortOrderAscending(): void
    {
        $first = new ProcessorSorterTestSampleProcessor();
        $second = new ProcessorSorterTestSampleProcessor();
        $third = new ProcessorSorterTestSampleProcessor();

        $result = $this->sorter->sort(
            [
                'c' => ['processor' => $third, 'sortOrder' => 300],
                'a' => ['processor' => $first, 'sortOrder' => 100],
                'b' => ['processor' => $second, 'sortOrder' => 200],
            ],
            ProcessorSorterTestSampleInterface::class
        );

        $this->assertSame([$first, $second, $third], $result);
    }

    /**
     * Equal sortOrder values keep declaration order, because PHP's sort is stable.
     */
    public function testItKeepsDeclarationOrderForEqualSortOrder(): void
    {
        $first = new ProcessorSorterTestSampleProcessor();
        $second = new ProcessorSorterTestSampleProcessor();
        $third = new ProcessorSorterTestSampleProcessor();

        $result = $this->sorter->sort(
            [
                'first' => ['processor' => $first, 'sortOrder' => 10],
                'second' => ['processor' => $second, 'sortOrder' => 10],
                'third' => ['processor' => $third, 'sortOrder' => 10],
            ],
            ProcessorSorterTestSampleInterface::class
        );

        $this->assertSame([$first, $second, $third], $result);
    }

    /**
     * An item marked disabled is dropped, even without a processor or sortOrder.
     */
    public function testItDropsDisabledItems(): void
    {
        $kept = new ProcessorSorterTestSampleProcessor();

        $result = $this->sorter->sort(
            [
                'kept' => ['processor' => $kept, 'sortOrder' => 100],
                'removed' => ['disabled' => true],
            ],
            ProcessorSorterTestSampleInterface::class
        );

        $this->assertSame([$kept], $result);
    }

    /**
     * A missing sortOrder must throw, never fall back to a default order.
     */
    public function testItThrowsOnMissingSortOrder(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->sorter->sort(
            [
                'broken' => ['processor' => new ProcessorSorterTestSampleProcessor()],
            ],
            ProcessorSorterTestSampleInterface::class
        );
    }

    public function testItThrowsOnMissingProcessor(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->sorter->sort(
            [
                'broken' => ['sortOrder' => 100],
            ],
            ProcessorSorterTestSampleInterface::class
        );
    }

    public function testItThrowsOnWrongInterface(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->sorter->sort(
            [
                'broken' => ['processor' => new ProcessorSorterTestOtherProcessor(), 'sortOrder' => 100],
            ],
            ProcessorSorterTestSampleInterface::class
        );
    }
}
