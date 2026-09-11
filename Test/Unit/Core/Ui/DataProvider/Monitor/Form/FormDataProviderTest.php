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

namespace MageOS\NetSuiteConnector\Test\Unit\Core\Ui\DataProvider\Monitor\Form;

use Magento\Framework\Escaper;
use Magento\Framework\Stdlib\DateTime\Timezone;
use MageOS\NetSuiteConnector\Core\Api\MonitorItemCollectionInterface;
use MageOS\NetSuiteConnector\Core\Api\MonitorItemCollectionInterfaceFactory;
use MageOS\NetSuiteConnector\Core\Model\Config\MonitorConfig;
use MageOS\NetSuiteConnector\Core\Model\Monitor\Data\Source\Entity;
use MageOS\NetSuiteConnector\Core\Ui\DataProvider\Monitor\Form\FormDataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class FormDataProviderTest extends TestCase
{
    /**
     * A process output message that carries a script payload must render escaped, and the bold
     * timestamp markup around it must stay intact.
     */
    public function testItEscapesProcessOutputMessagesAndKeepsTheTimestampMarkup(): void
    {
        $rendered = $this->renderProcessOutput([
            [1735732800, '<img src=x onerror=alert(1)>', 'standard'],
        ]);

        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $rendered);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $rendered);
        $this->assertStringContainsString('<b>Jan 1, 2025, 12:00:00 PM</b>: ', $rendered);
    }

    /**
     * A newline already present in a message must still render as a line break after the message
     * is escaped.
     */
    public function testItConvertsNewlinesInAnEscapedMessageToLineBreaks(): void
    {
        $rendered = $this->renderProcessOutput([
            [1735732800, "first line\nsecond line", 'standard'],
        ]);

        $this->assertStringContainsString("first line<br />\nsecond line", $rendered);
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: string}> $data
     */
    private function renderProcessOutput(array $data): string
    {
        $method = new ReflectionMethod(FormDataProvider::class, 'renderProcessOutput');

        return $method->invoke($this->createProvider(), $data);
    }

    private function createProvider(): FormDataProvider
    {
        $timezone = $this->createStub(Timezone::class);
        $timezone->method('formatDateTime')->willReturn('Jan 1, 2025, 12:00:00 PM');

        $collection = $this->createStub(MonitorItemCollectionInterface::class);
        $collectionFactory = $this->createStub(MonitorItemCollectionInterfaceFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        return new FormDataProvider(
            $timezone,
            $this->createStub(MonitorConfig::class),
            $this->createStub(Entity::class),
            $collectionFactory,
            new Escaper(),
            'monitor_item_view',
            'monitor_id',
            'id'
        );
    }
}
