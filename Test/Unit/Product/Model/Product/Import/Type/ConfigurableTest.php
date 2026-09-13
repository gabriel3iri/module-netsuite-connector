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

use Magento\CatalogImportExport\Model\Import\Product as ImportEntityModel;
use MageOS\NetSuiteConnector\Product\Model\Product\Import\Type\Configurable;
use MageOS\NetSuiteConnector\Product\Model\Product\Import\Type\Configurable\RedundantLinkCleaner;
use PHPUnit\Framework\TestCase;

class ConfigurableTest extends TestCase
{
    /**
     * saveData() calls the parent save, then feeds its own super attribute
     * data to the cleaner.
     */
    public function testSaveDataCallsTheCleaner(): void
    {
        $cleaner = $this->createMock(RedundantLinkCleaner::class);
        $cleaner->expects($this->once())->method('clean')->with(null);

        $entityModel = $this->createStub(ImportEntityModel::class);
        $entityModel->method('getNewSku')->willReturn([]);
        $entityModel->method('getNextBunch')->willReturn(false);

        $configurable = $this->getStubBuilder(Configurable::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSuperAttributeData'])
            ->getStub();
        $configurable->method('getSuperAttributeData')->willReturn(null);

        $this->setProtectedProperty($configurable, '_entityModel', $entityModel);
        $this->setPrivateProperty($configurable, Configurable::class, 'redundantLinkCleaner', $cleaner);

        $result = $configurable->saveData();

        $this->assertSame($configurable, $result);
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
