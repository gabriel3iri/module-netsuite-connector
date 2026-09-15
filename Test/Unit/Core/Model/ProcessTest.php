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

namespace MageOS\NetSuiteConnector\Test\Unit\Core\Model;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\Context;
use MageOS\NetSuiteConnector\Core\Api\Data\MessageInterface;
use MageOS\NetSuiteConnector\Core\Api\MessageManagementInterface;
use MageOS\NetSuiteConnector\Core\Api\MonitorManagementInterface;
use MageOS\NetSuiteConnector\Core\Model\Config\ConnectorConfig;
use MageOS\NetSuiteConnector\Core\Model\Config\QueueConfig;
use MageOS\NetSuiteConnector\Core\Model\ImportQueueManager;
use MageOS\NetSuiteConnector\Core\Model\Logger\Logger;
use MageOS\NetSuiteConnector\Core\Model\NetSuite\LastUpdateManager;
use MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Repository;
use MageOS\NetSuiteConnector\Core\Model\Process;
use MageOS\NetSuiteConnector\Core\Model\Process\ExportProcessor;
use MageOS\NetSuiteConnector\Core\Model\Process\Import\AbstractImportProcessor;
use MageOS\NetSuiteConnector\Core\Model\Process\Import\BatchPrefetchInterface;
use MageOS\NetSuiteConnector\Core\Model\Process\ImportProcessor;
use MageOS\NetSuiteConnector\Core\Model\ProcessManagement;
use NetSuite\Classes\Customer;
use NetSuite\Classes\InventoryItem;
use NetSuite\Classes\Record;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ProcessTestPrefetchingProcessor extends AbstractImportProcessor implements BatchPrefetchInterface
{
    /**
     * @var Record[]
     */
    public array $receivedRecords = [];

    public function __construct()
    {
    }

    public function prefetch(array $records): void
    {
        $this->receivedRecords = $records;
    }

    public function isMagentoImportable(Record $record)
    {
        return true;
    }

    public function getMessageType()
    {
        return 'inventoryitem';
    }

    public function process(Record $record)
    {
        return null;
    }

    public function getRecordType()
    {
        return 'inventoryitem';
    }

    public function isActive()
    {
        return true;
    }

    public function getPermissionName()
    {
        return '';
    }
}

/**
 * Has a method named prefetch() but does not implement BatchPrefetchInterface, so it must never be called.
 */
class ProcessTestNonPrefetchingProcessor extends AbstractImportProcessor
{
    public bool $prefetchWasCalled = false;

    public function __construct()
    {
    }

    public function prefetch(array $records): void
    {
        $this->prefetchWasCalled = true;
    }

    public function isMagentoImportable(Record $record)
    {
        return true;
    }

    public function getMessageType()
    {
        return 'customer';
    }

    public function process(Record $record)
    {
        return null;
    }

    public function getRecordType()
    {
        return 'customer';
    }

    public function isActive()
    {
        return true;
    }

    public function getPermissionName()
    {
        return '';
    }
}

class ProcessTestThrowingImportProcessor extends AbstractImportProcessor
{
    public function __construct(private readonly \Throwable $failure)
    {
    }

    public function queryNetsuite($startDateTime, $fromBeginning = true)
    {
        throw $this->failure;
    }

    public function isMagentoImportable(Record $record)
    {
        return true;
    }

    public function getMessageType()
    {
        return 'invoice';
    }

    public function process(Record $record)
    {
        return null;
    }

    public function getRecordType()
    {
        return 'invoice';
    }

    public function isActive()
    {
        return true;
    }

    public function getPermissionName()
    {
        return '';
    }
}

class ProcessTestSucceedingImportProcessor extends AbstractImportProcessor
{
    private bool $alreadyQueried = false;
    public ?string $receivedStartDateTime = null;

    public function __construct(private readonly Record $record)
    {
    }

    public function queryNetsuite($startDateTime, $fromBeginning = true)
    {
        $this->receivedStartDateTime = $startDateTime;
        if ($this->alreadyQueried) {
            return false;
        }

        $this->alreadyQueried = true;
        return [$this->record];
    }

    public function isMagentoImportable(Record $record)
    {
        return true;
    }

    public function getMessageType()
    {
        return 'customer';
    }

    public function process(Record $record)
    {
        return null;
    }

    public function getRecordType()
    {
        return 'customer';
    }

    public function isActive()
    {
        return true;
    }

    public function getPermissionName()
    {
        return '';
    }
}

class ProcessTest extends TestCase
{
    public function testProcessImportContinuesToTheNextEntityTypeWhenOneEntityTypeFails(): void
    {
        $throwingProcessor = new ProcessTestThrowingImportProcessor(new \RuntimeException('search failed'));

        $record = new Customer();
        $succeedingProcessor = new ProcessTestSucceedingImportProcessor($record);

        $importProcessor = $this->createStub(ImportProcessor::class);
        $importProcessor->method('getImportableEntities')->willReturn(
            ['invoice' => $throwingProcessor, 'customer' => $succeedingProcessor]
        );

        $connectorConfig = $this->createStub(ConnectorConfig::class);
        $connectorConfig->method('isEnabled')->willReturn(true);

        $repository = $this->createStub(Repository::class);
        $repository->method('getServerTime')->willReturn('2026-09-15T00:00:00+0000');

        $lastUpdateManager = $this->createMock(LastUpdateManager::class);
        $lastUpdateManager->method('getLastUpdateDate')->willReturn(null);
        $lastUpdateManager->expects($this->once())
            ->method('setLastUpdateDate')
            ->with(LastUpdateManager::IMPORT_FLAG . '_customer', '2026-09-15T00:00:00+0000');

        $queueConfig = $this->createStub(QueueConfig::class);
        $queueConfig->method('__call')->willReturnCallback(
            static fn (string $name) => $name === 'getUpdatedFromMinutes' ? 60 : null
        );

        $processManagement = $this->createMock(ProcessManagement::class);
        $processManagement->expects($this->once())
            ->method('processRecords')
            ->with($succeedingProcessor, [$record]);

        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('addError');

        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));

        $process = new Process(
            $this->createStub(ImportQueueManager::class),
            $queueConfig,
            $importProcessor,
            $this->createStub(ExportProcessor::class),
            $lastUpdateManager,
            $context,
            $processManagement,
            $connectorConfig,
            $repository,
            $logger,
            $this->createStub(MessageManagementInterface::class),
            $this->createStub(MonitorManagementInterface::class)
        );

        $process->processImport(false);
    }

    #[DataProvider('watermarkCases')]
    public function testAnEntityStartsFromItsOwnWatermarkAndFallsBackToTheSharedOne(
        ?string $entityDate,
        string $sharedDate,
        string $expectedStart
    ): void {
        $succeedingProcessor = new ProcessTestSucceedingImportProcessor(new Customer());

        $importProcessor = $this->createStub(ImportProcessor::class);
        $importProcessor->method('getImportableEntities')->willReturn(['customer' => $succeedingProcessor]);

        $connectorConfig = $this->createStub(ConnectorConfig::class);
        $connectorConfig->method('isEnabled')->willReturn(true);

        $repository = $this->createStub(Repository::class);
        $repository->method('getServerTime')->willReturn('2026-09-15T00:00:00+0000');

        $lastUpdateManager = $this->createStub(LastUpdateManager::class);
        $lastUpdateManager->method('getLastUpdateDate')->willReturnCallback(
            static fn (string $flagCode) => $flagCode === LastUpdateManager::IMPORT_FLAG ? $sharedDate : $entityDate
        );

        $queueConfig = $this->createStub(QueueConfig::class);
        $queueConfig->method('__call')->willReturnCallback(
            static fn (string $name) => $name === 'getUpdatedFromMinutes' ? 60 : null
        );

        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));

        $process = new Process(
            $this->createStub(ImportQueueManager::class),
            $queueConfig,
            $importProcessor,
            $this->createStub(ExportProcessor::class),
            $lastUpdateManager,
            $context,
            $this->createStub(ProcessManagement::class),
            $connectorConfig,
            $repository,
            $this->createStub(Logger::class),
            $this->createStub(MessageManagementInterface::class),
            $this->createStub(MonitorManagementInterface::class)
        );

        $process->processImport(false);

        $this->assertSame(
            (new \DateTime($expectedStart))->format(\DateTime::ISO8601),
            $succeedingProcessor->receivedStartDateTime
        );
    }

    public static function watermarkCases(): array
    {
        return [
            'own watermark wins' => ['2020-06-12 00:00:00', '2020-06-01 00:00:00', '2020-06-12 00:00:00'],
            'no own watermark uses the shared one' => [null, '2020-06-01 00:00:00', '2020-06-01 00:00:00'],
        ];
    }

    public function testTheImportQueuePrefetchesOnlyThroughProcessorsThatImplementTheInterface(): void
    {
        $inventoryRecordOne = new InventoryItem();
        $inventoryRecordTwo = new InventoryItem();
        $customerRecord = new Customer();

        $messages = [
            $this->message('inventoryitem', $inventoryRecordOne),
            $this->message('inventoryitem', $inventoryRecordTwo),
            $this->message('customer', $customerRecord),
        ];

        $inventoryProcessor = new ProcessTestPrefetchingProcessor();
        $customerProcessor = new ProcessTestNonPrefetchingProcessor();

        $importProcessor = $this->createStub(ImportProcessor::class);
        $importProcessor->method('getEntityProcessor')->willReturnMap(
            [
                ['inventoryitem', $inventoryProcessor],
                ['customer', $customerProcessor],
            ]
        );

        $connectorConfig = $this->createStub(ConnectorConfig::class);
        $connectorConfig->method('isEnabled')->willReturn(true);

        $messageManagement = $this->createStub(MessageManagementInterface::class);
        $messageManagement->method('receive')->willReturn($messages);

        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));

        $process = new Process(
            $this->createStub(ImportQueueManager::class),
            $this->createStub(QueueConfig::class),
            $importProcessor,
            $this->createStub(ExportProcessor::class),
            $this->createStub(LastUpdateManager::class),
            $context,
            $this->createStub(ProcessManagement::class),
            $connectorConfig,
            $this->createStub(Repository::class),
            $this->createStub(Logger::class),
            $messageManagement,
            $this->createStub(MonitorManagementInterface::class)
        );
        $process->processImportQueue();

        $this->assertSame([$inventoryRecordOne, $inventoryRecordTwo], $inventoryProcessor->receivedRecords);
        $this->assertFalse($customerProcessor->prefetchWasCalled);
    }

    private function message(string $action, Record $record): MessageInterface
    {
        $message = $this->createStub(MessageInterface::class);
        $message->method('getAction')->willReturn($action);
        $message->method('getObject')->willReturn($record);

        return $message;
    }
}
