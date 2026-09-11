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

namespace MageOS\NetSuiteConnector\Test\Unit\Core\Model\Process\Import;

use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\Context;
use MageOS\NetSuiteConnector\Core\Api\MessageManagementInterface;
use MageOS\NetSuiteConnector\Core\Model\Config\DeveloperConfig;
use MageOS\NetSuiteConnector\Core\Model\Config\PermissionsConfigInterface;
use MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management;
use MageOS\NetSuiteConnector\Core\Model\Process\Import\AbstractImportProcessor;
use NetSuite\Classes\Record;
use NetSuite\Classes\RecordList;
use NetSuite\Classes\SearchMoreWithIdResponse;
use NetSuite\Classes\SearchResponse;
use NetSuite\Classes\SearchResult;
use NetSuite\Classes\Status;
use NetSuite\NetSuiteService;
use PHPUnit\Framework\TestCase;

/**
 * queryNetsuite() used to keep its response and page counter in method statics. Since PHP 8.1, an
 * inherited, non-overridden method shares its statics across every subclass that calls it, and a
 * fresh full search never reset the page counter, so pagination silently stopped after page one
 * for every record type after the first, and for every later cron run on the same instance.
 */
class AbstractImportProcessorTest extends TestCase
{
    /**
     * Two independent processor subclasses that both inherit queryNetsuite() without overriding it.
     * The first processor runs its full pagination cycle, then the second processor starts its own
     * search. The second processor's own page two must still be reachable.
     */
    public function testTheSecondProcessorsSecondPageIsNotSkippedAfterTheFirstProcessorFinishes(): void
    {
        $recordA1 = new Record();
        $recordA2 = new Record();
        $recordB1 = new Record();
        $recordB2 = new Record();

        $processorA = $this->createProcessorOne(
            $this->buildManagement($this->buildNetsuiteService(
                $this->buildSearchResponse(2, 'search-a', [$recordA1]),
                $this->buildSearchMoreResponse([$recordA2])
            ))
        );
        $processorB = $this->createProcessorTwo(
            $this->buildManagement($this->buildNetsuiteService(
                $this->buildSearchResponse(2, 'search-b', [$recordB1]),
                $this->buildSearchMoreResponse([$recordB2])
            ))
        );

        $this->assertSame([$recordA1], $processorA->queryNetsuite('2026-01-01 00:00:00', true));
        $this->assertSame([$recordA2], $processorA->queryNetsuite('2026-01-01 00:00:00', false));

        $this->assertSame([$recordB1], $processorB->queryNetsuite('2026-01-01 00:00:00', true));
        $this->assertSame(
            [$recordB2],
            $processorB->queryNetsuite('2026-01-01 00:00:00', false),
            'The second processor must fetch its own page two, not inherit the first processor\'s exhausted page counter.'
        );
    }

    /**
     * A single processor instance runs a full pagination cycle to exhaustion, then starts a second,
     * unrelated search. The second search's own page two must be reachable, not skipped because the
     * page counter was left over from the first search.
     */
    public function testASecondSearchOnTheSameProcessorInstanceRestartsAtPageTwo(): void
    {
        $recordRun1Page1 = new Record();
        $recordRun1Page2 = new Record();
        $recordRun2Page1 = new Record();
        $recordRun2Page2 = new Record();

        $netsuiteService = $this->createStub(NetSuiteService::class);
        $netsuiteService->method('setSearchPreferences');
        $netsuiteService->method('search')->willReturnOnConsecutiveCalls(
            $this->buildSearchResponse(2, 'search-run-1', [$recordRun1Page1]),
            $this->buildSearchResponse(2, 'search-run-2', [$recordRun2Page1])
        );
        $netsuiteService->method('searchMoreWithId')->willReturnOnConsecutiveCalls(
            $this->buildSearchMoreResponse([$recordRun1Page2]),
            $this->buildSearchMoreResponse([$recordRun2Page2])
        );

        $processor = $this->createProcessorOne($this->buildManagement($netsuiteService));

        $this->assertSame([$recordRun1Page1], $processor->queryNetsuite('2026-01-01 00:00:00', true));
        $this->assertSame([$recordRun1Page2], $processor->queryNetsuite('2026-01-01 00:00:00', false));
        $this->assertFalse($processor->queryNetsuite('2026-01-01 00:00:00', false));

        $this->assertSame([$recordRun2Page1], $processor->queryNetsuite('2026-01-01 00:00:00', true));
        $this->assertSame(
            [$recordRun2Page2],
            $processor->queryNetsuite('2026-01-01 00:00:00', false),
            'A fresh search on the same instance must restart pagination at page two.'
        );
    }

    /**
     * Builds the first of two distinct anonymous subclasses of AbstractImportProcessor. It must be
     * a separate class literal from createProcessorTwo() so the two instances are genuinely
     * different subclasses, the condition the shared-statics bug depends on.
     */
    private function createProcessorOne(Management $serviceManagement): AbstractImportProcessor
    {
        return new class(
            $this->createStub(PermissionsConfigInterface::class),
            $this->buildContext(),
            $serviceManagement,
            $this->buildDeveloperConfig(),
            $this->createStub(MessageManagementInterface::class)
        ) extends AbstractImportProcessor {
            public function isMagentoImportable(Record $record)
            {
                return true;
            }

            public function getMessageType()
            {
                return 'processor_one';
            }

            public function process(Record $record)
            {
                return null;
            }

            public function getRecordType()
            {
                return 'ProcessorOneRecord';
            }

            public function isActive()
            {
                return true;
            }

            public function getPermissionName()
            {
                return '';
            }
        };
    }

    /**
     * Builds the second of two distinct anonymous subclasses of AbstractImportProcessor.
     */
    private function createProcessorTwo(Management $serviceManagement): AbstractImportProcessor
    {
        return new class(
            $this->createStub(PermissionsConfigInterface::class),
            $this->buildContext(),
            $serviceManagement,
            $this->buildDeveloperConfig(),
            $this->createStub(MessageManagementInterface::class)
        ) extends AbstractImportProcessor {
            public function isMagentoImportable(Record $record)
            {
                return true;
            }

            public function getMessageType()
            {
                return 'processor_two';
            }

            public function process(Record $record)
            {
                return null;
            }

            public function getRecordType()
            {
                return 'ProcessorTwoRecord';
            }

            public function isActive()
            {
                return true;
            }

            public function getPermissionName()
            {
                return '';
            }
        };
    }

    /**
     * A context stub that only wires the event dispatcher the constructor and
     * getNetsuiteRequest() need.
     */
    private function buildContext(): Context
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));

        return $context;
    }

    /**
     * A developer config stub whose magic getters all resolve through __call().
     */
    private function buildDeveloperConfig(): DeveloperConfig
    {
        $developerConfig = $this->createStub(DeveloperConfig::class);
        $developerConfig->method('__call')->willReturn(10);

        return $developerConfig;
    }

    /**
     * A service management stub that hands back a fixed NetSuite connection and server time.
     */
    private function buildManagement(NetSuiteService $netsuiteService): Management
    {
        $management = $this->createStub(Management::class);
        $management->method('get')->willReturn($netsuiteService);
        $management->method('getServerTime')->willReturn('2026-01-01 00:00:00');

        return $management;
    }

    /**
     * A NetSuite connection stub returning one fixed search and one fixed search-more response.
     */
    private function buildNetsuiteService(
        SearchResponse $searchResponse,
        SearchMoreWithIdResponse $searchMoreResponse
    ): NetSuiteService {
        $netsuiteService = $this->createStub(NetSuiteService::class);
        $netsuiteService->method('setSearchPreferences');
        $netsuiteService->method('search')->willReturn($searchResponse);
        $netsuiteService->method('searchMoreWithId')->willReturn($searchMoreResponse);

        return $netsuiteService;
    }

    /**
     * @param Record[] $records
     */
    private function buildSearchResponse(int $totalPages, string $searchId, array $records): SearchResponse
    {
        $status = new Status();
        $status->isSuccess = true;

        $recordList = new RecordList();
        $recordList->record = $records;

        $searchResult = new SearchResult();
        $searchResult->status = $status;
        $searchResult->totalPages = $totalPages;
        $searchResult->searchId = $searchId;
        $searchResult->recordList = $recordList;

        $response = new SearchResponse();
        $response->searchResult = $searchResult;

        return $response;
    }

    /**
     * @param Record[] $records
     */
    private function buildSearchMoreResponse(array $records): SearchMoreWithIdResponse
    {
        $status = new Status();
        $status->isSuccess = true;

        $recordList = new RecordList();
        $recordList->record = $records;

        $searchResult = new SearchResult();
        $searchResult->status = $status;
        $searchResult->recordList = $recordList;

        $response = new SearchMoreWithIdResponse();
        $response->searchResult = $searchResult;

        return $response;
    }
}
