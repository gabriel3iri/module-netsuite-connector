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

namespace MageOS\NetSuiteConnector\Test\Unit\Product\Model\Process\Import;

use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\ObjectManagerInterface;
use MageOS\NetSuiteConnector\Core\Api\MessageManagementInterface;
use MageOS\NetSuiteConnector\Core\Model\Config\DeveloperConfig;
use MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management;
use MageOS\NetSuiteConnector\Product\Model\ConfigProvider\Permissions;
use MageOS\NetSuiteConnector\Product\Model\Import\Item\Mapper;
use MageOS\NetSuiteConnector\Product\Model\Prefetch\ProcessingItem;
use MageOS\NetSuiteConnector\Product\Model\Process\Import\Item;
use MageOS\NetSuiteConnector\Product\Model\ResourceModel\Repository;
use NetSuite\Classes\Record;
use NetSuite\Classes\RecordList;
use NetSuite\Classes\SearchMoreWithIdResponse;
use NetSuite\Classes\SearchResponse;
use NetSuite\Classes\SearchResult;
use NetSuite\Classes\Status;
use NetSuite\NetSuiteService;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Item::queryNetsuite() overrides the abstract implementation with its own method statics for the
 * response, search id, total pages and page counter. A fresh full search never reset the page
 * counter, so a second run on the same instance stopped pagination one page early.
 */
class ItemTest extends TestCase
{
    /**
     * Item's constructor forwards only three arguments to the parent constructor, so
     * developerConfig and messageManagement always resolve through ObjectManager::getInstance().
     * The instance is torn down after every test so it never leaks into another test file.
     */
    protected function tearDown(): void
    {
        $property = new \ReflectionProperty(ObjectManager::class, '_instance');
        $property->setValue(null, null);
    }

    /**
     * A single Item instance runs a full pagination cycle to exhaustion, then starts a second,
     * unrelated search with no resume file present. The second search's own page two must be
     * reachable, not skipped because the page counter was left over from the first search.
     */
    public function testASecondSearchOnTheSameInstanceRestartsAtPageTwo(): void
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

        $item = $this->createItem($netsuiteService);

        $this->assertSame([$recordRun1Page1], $item->queryNetsuite('2026-01-01 00:00:00', true));
        $this->assertSame([$recordRun1Page2], $item->queryNetsuite('2026-01-01 00:00:00', false));
        $this->assertFalse($item->queryNetsuite('2026-01-01 00:00:00', false));

        $this->assertSame([$recordRun2Page1], $item->queryNetsuite('2026-01-01 00:00:00', true));
        $this->assertSame(
            [$recordRun2Page2],
            $item->queryNetsuite('2026-01-01 00:00:00', false),
            'A fresh search on the same instance must restart pagination at page two.'
        );
    }

    /**
     * Builds a real Item processor with every collaborator stubbed. No resume file is present, so
     * every $fromBeginning search runs as a fresh search rather than resuming a prior one.
     */
    private function createItem(NetSuiteService $netsuiteService): Item
    {
        $permissionHelper = $this->createStub(Permissions::class);
        $permissionHelper->method('isFeatureEnabled')->willReturn(true);

        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));
        $context->method('getLogger')->willReturn($this->createStub(LoggerInterface::class));

        $management = $this->createStub(Management::class);
        $management->method('get')->willReturn($netsuiteService);
        $management->method('getServerTime')->willReturn('2026-01-01 00:00:00');

        $readDirectory = $this->createStub(ReadInterface::class);
        $readDirectory->method('isFile')->willReturn(false);

        $writeDirectory = $this->createStub(WriteInterface::class);
        $writeDirectory->method('isFile')->willReturn(false);

        $filesystem = $this->createStub(Filesystem::class);
        $filesystem->method('getDirectoryRead')->willReturn($readDirectory);
        $filesystem->method('getDirectoryWrite')->willReturn($writeDirectory);

        $developerConfig = $this->createStub(DeveloperConfig::class);
        $developerConfig->method('__call')->willReturn(10);

        $objectManager = $this->createStub(ObjectManagerInterface::class);
        $objectManager->method('get')->willReturnMap([
            [DeveloperConfig::class, $developerConfig],
            [MessageManagementInterface::class, $this->createStub(MessageManagementInterface::class)],
        ]);
        ObjectManager::setInstance($objectManager);

        return new Item(
            $this->createStub(Mapper::class),
            $permissionHelper,
            $this->createStub(ProcessingItem::class),
            $management,
            $context,
            $this->createStub(CollectionFactory::class),
            $this->createStub(Repository::class),
            $filesystem
        );
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
