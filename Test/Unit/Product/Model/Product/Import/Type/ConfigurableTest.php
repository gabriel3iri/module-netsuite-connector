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

namespace MageOS\NetSuiteConnector\Test\Unit\Product\Model\Product\Import\Type;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use MageOS\NetSuiteConnector\Core\Model\Plugin\ImportExport\PluginState;
use MageOS\NetSuiteConnector\Product\Model\Product\Import\Type\Configurable;
use MageOS\NetSuiteConnector\Product\Model\Product\Import\Type\Configurable\RedundantLinkCleaner;
use PHPUnit\Framework\TestCase;

class ConfigurableTest extends TestCase
{
    /**
     * _insertData() runs the parent insert, then feeds the super attribute
     * data of the current bunch to the cleaner while the connector import runs.
     */
    public function testInsertDataCallsTheCleanerWhileTheConnectorImportRuns(): void
    {
        $superAttributesData = $this->emptySuperAttributesData();

        $cleaner = $this->createMock(RedundantLinkCleaner::class);
        $cleaner->expects($this->once())->method('clean')->with($superAttributesData);

        $configurable = $this->buildConfigurable($cleaner, true, $superAttributesData);

        $this->invokeInsertData($configurable);
    }

    /**
     * Outside the connector import, for example an admin CSV import, the
     * cleaner must not run so core keeps the children the file does not list.
     */
    public function testInsertDataSkipsTheCleanerOutsideTheConnectorImport(): void
    {
        $cleaner = $this->createMock(RedundantLinkCleaner::class);
        $cleaner->expects($this->never())->method('clean');

        $configurable = $this->buildConfigurable($cleaner, false, $this->emptySuperAttributesData());

        $this->invokeInsertData($configurable);
    }

    private function emptySuperAttributesData(): array
    {
        return [
            'attributes' => [],
            'labels' => [],
            'super_link' => [],
            'relation' => [],
        ];
    }

    private function buildConfigurable(
        RedundantLinkCleaner $cleaner,
        bool $isRunning,
        array $superAttributesData
    ): Configurable {
        $state = $this->createStub(PluginState::class);
        $state->method('isRunning')->willReturn($isRunning);

        $resource = $this->createStub(ResourceConnection::class);
        $resource->method('getTableName')->willReturnArgument(0);

        $configurable = (new \ReflectionClass(Configurable::class))->newInstanceWithoutConstructor();

        $this->setProtectedProperty($configurable, '_resource', $resource);
        $this->setProtectedProperty($configurable, 'connection', $this->createStub(AdapterInterface::class));
        $this->setProtectedProperty($configurable, '_superAttributesData', $superAttributesData);
        $this->setPrivateProperty($configurable, Configurable::class, 'redundantLinkCleaner', $cleaner);
        $this->setPrivateProperty($configurable, Configurable::class, 'state', $state);

        return $configurable;
    }

    private function invokeInsertData(Configurable $configurable): void
    {
        $method = new \ReflectionMethod($configurable, '_insertData');
        $method->invoke($configurable);
    }

    private function setProtectedProperty(object $object, string $property, $value): void
    {
        $reflectionProperty = new \ReflectionProperty($object, $property);
        $reflectionProperty->setValue($object, $value);
    }

    /**
     * A private property declared on a parent class is name-mangled, so it
     * must be reflected against the class that declares it, not the mock
     * subclass instance.
     */
    private function setPrivateProperty(object $object, string $declaringClass, string $property, $value): void
    {
        $reflectionProperty = new \ReflectionProperty($declaringClass, $property);
        $reflectionProperty->setValue($object, $value);
    }
}
