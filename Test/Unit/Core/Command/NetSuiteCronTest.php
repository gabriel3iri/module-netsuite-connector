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

namespace MageOS\NetSuiteConnector\Test\Unit\Core\Command;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\State;
use Magento\Framework\ObjectManagerInterface;
use MageOS\NetSuiteConnector\Core\Command\NetSuiteCron;
use MageOS\NetSuiteConnector\Core\Model\Config\ConnectorConfig;
use MageOS\NetSuiteConnector\Core\Model\Logger\Logger;
use MageOS\NetSuiteConnector\Core\Model\MutexFactory;
use MageOS\NetSuiteConnector\Core\Model\Process\Proxy as ProcessProxy;
use MageOS\NetSuiteConnector\Core\Registry\ModuleRegistry;
use MageOS\NetSuiteConnector\Inventory\Model\Process\Import\Stock;
use MageOS\NetSuiteConnector\Inventory\Multi\Model\Process\Import\LocationImportToQueue;
use PHPUnit\Framework\TestCase;

/**
 * Covers the run mode switch that decides which import, export, stock and location steps run.
 *
 * The stock and location steps used to run through two "after" plugins on processMode() that fired for every
 * mode and checked the mode themselves. They are now cases in the same switch as the pre-existing modes.
 */
class NetSuiteCronTest extends TestCase
{
    /**
     * Labels appended by each collaborator stub, in the order they were called.
     *
     * @var string[]
     */
    private array $callOrder = [];

    protected function setUp(): void
    {
        $this->callOrder = [];

        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturnMap([
            [Logger::class, $this->createStub(Logger::class)],
            [State::class, $this->createStub(State::class)],
        ]);
        ObjectManager::setInstance($objectManager);
    }

    /**
     * Mode "stock" runs only the stock import.
     */
    public function testStockModeRunsOnlyTheStockImport(): void
    {
        $process = $this->createMock(ProcessProxy::class);
        $process->expects($this->never())->method('processImportQueue');
        $process->expects($this->never())->method('processImport');
        $process->expects($this->never())->method('processExport');
        $stock = $this->createMock(Stock::class);
        $stock->expects($this->once())->method('process');
        $locationImportToQueue = $this->createMock(LocationImportToQueue::class);
        $locationImportToQueue->expects($this->never())->method('execute');

        $cron = $this->createCron($process, $stock, $locationImportToQueue);
        $cron->processMode('stock');
    }

    /**
     * Mode "all" runs the import steps, then export, then stock, in that order.
     */
    public function testAllModeRunsImportThenExportThenStock(): void
    {
        $process = $this->createStub(ProcessProxy::class);
        $process->method('processImportQueue')->willReturnCallback(function (): void {
            $this->callOrder[] = 'processImportQueue';
        });
        $process->method('processImport')->willReturnCallback(function (): void {
            $this->callOrder[] = 'processImport';
        });
        $process->method('processExport')->willReturnCallback(function (): void {
            $this->callOrder[] = 'processExport';
        });
        $stock = $this->createStub(Stock::class);
        $stock->method('process')->willReturnCallback(function (): void {
            $this->callOrder[] = 'stock';
        });
        $locationImportToQueue = $this->createMock(LocationImportToQueue::class);
        $locationImportToQueue->expects($this->never())->method('execute');

        $cron = $this->createCron($process, $stock, $locationImportToQueue);
        $cron->processMode('all');

        $this->assertSame(
            ['processImportQueue', 'processImport', 'processExport', 'stock'],
            $this->callOrder
        );
    }

    /**
     * Mode "location" runs only the location import to queue.
     */
    public function testLocationModeRunsOnlyTheLocationImportToQueue(): void
    {
        $process = $this->createMock(ProcessProxy::class);
        $process->expects($this->never())->method('processImportQueue');
        $process->expects($this->never())->method('processImport');
        $process->expects($this->never())->method('processExport');
        $stock = $this->createMock(Stock::class);
        $stock->expects($this->never())->method('process');
        $locationImportToQueue = $this->createMock(LocationImportToQueue::class);
        $locationImportToQueue->expects($this->once())->method('execute');

        $cron = $this->createCron($process, $stock, $locationImportToQueue);
        $cron->processMode('location');
    }

    /**
     * Mode "import" still runs neither the stock import nor the location import to queue.
     */
    public function testImportModeRunsNeitherStockNorLocation(): void
    {
        $process = $this->createMock(ProcessProxy::class);
        $process->expects($this->once())->method('processImportQueue');
        $process->expects($this->once())->method('processImport')->with(false);
        $process->expects($this->never())->method('processExport');
        $stock = $this->createMock(Stock::class);
        $stock->expects($this->never())->method('process');
        $locationImportToQueue = $this->createMock(LocationImportToQueue::class);
        $locationImportToQueue->expects($this->never())->method('execute');

        $cron = $this->createCron($process, $stock, $locationImportToQueue);
        $cron->processMode('import');
    }

    /**
     * Builds the command with the given collaborators and stubs for every dependency processMode() does not use.
     */
    private function createCron(
        ProcessProxy $process,
        Stock $stock,
        LocationImportToQueue $locationImportToQueue
    ): NetSuiteCron {
        return new NetSuiteCron(
            $process,
            $this->createStub(ModuleRegistry::class),
            $this->createStub(ConnectorConfig::class),
            $this->createStub(MutexFactory::class),
            $stock,
            $locationImportToQueue,
            []
        );
    }
}
