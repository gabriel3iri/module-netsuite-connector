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

namespace MageOS\NetSuiteConnector\Test\Unit\Inventory\Multi\Model\Process\Import;

use MageOS\NetSuiteConnector\Core\Model\Config\DeveloperConfig;
use MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management;
use MageOS\NetSuiteConnector\Core\Model\ProcessManagement;
use MageOS\NetSuiteConnector\Inventory\Model\Config\InventoryMode;
use MageOS\NetSuiteConnector\Inventory\Multi\Model\Process\Import\Location;
use MageOS\NetSuiteConnector\Inventory\Multi\Model\Process\Import\LocationImportToQueue;
use NetSuite\Classes\Location as NetSuiteLocation;
use NetSuite\Classes\RecordList;
use NetSuite\Classes\SearchRequest;
use NetSuite\Classes\SearchResponse;
use NetSuite\Classes\SearchResult;
use NetSuite\Classes\Status;
use NetSuite\NetSuiteService;
use PHPUnit\Framework\TestCase;

/**
 * Covers the isMulti() guard that used to live on the "after" plugin, MageOS\NetSuiteConnector\Inventory\Multi\
 * Plugin\NetSuiteCron, which ran for every cron run mode and checked the mode and the inventory mode itself.
 * The guard is now the whole reason this class does nothing outside multi source inventory mode.
 */
class LocationImportToQueueTest extends TestCase
{
    /**
     * Outside multi source inventory mode, execute() does nothing: it never reaches NetSuite, the location
     * processor or the process management collaborator.
     */
    public function testItDoesNothingWhenTheInventoryModeIsNotMulti(): void
    {
        $inventoryMode = $this->createStub(InventoryMode::class);
        $inventoryMode->method('isMulti')->willReturn(false);
        $location = $this->createMock(Location::class);
        $location->expects($this->never())->method('getNetsuiteRequest');
        $processManagement = $this->createStub(ProcessManagement::class);
        $serviceManagement = $this->createMock(Management::class);
        $serviceManagement->expects($this->never())->method('get');
        $developerConfig = $this->createMock(DeveloperConfig::class);
        $developerConfig->expects($this->never())->method('__call');

        $locationImportToQueue = new LocationImportToQueue(
            $inventoryMode,
            $location,
            $processManagement,
            $serviceManagement,
            $developerConfig
        );

        $locationImportToQueue->execute();
    }

    /**
     * In multi source inventory mode, the locations that NetSuite returns go to the import queue through
     * ProcessManagement::processRecords(), with the location import processor
     */
    public function testItQueuesTheLocationsFoundInNetSuiteInMultiMode(): void
    {
        $records = [new NetSuiteLocation(), new NetSuiteLocation()];
        $response = new SearchResponse();
        $response->searchResult = new SearchResult();
        $response->searchResult->status = new Status();
        $response->searchResult->status->isSuccess = true;
        $response->searchResult->recordList = new RecordList();
        $response->searchResult->recordList->record = $records;

        $searchRequest = new SearchRequest();
        $inventoryMode = $this->createStub(InventoryMode::class);
        $inventoryMode->method('isMulti')->willReturn(true);
        $location = $this->createMock(Location::class);
        $location->expects($this->once())
            ->method('getNetsuiteRequest')
            ->with('location', '')
            ->willReturn($searchRequest);
        $netsuiteService = $this->createMock(NetSuiteService::class);
        $netsuiteService->expects($this->once())->method('setSearchPreferences')->with(false, 25);
        $netsuiteService->expects($this->once())->method('search')->with($searchRequest)->willReturn($response);
        $serviceManagement = $this->createStub(Management::class);
        $serviceManagement->method('get')->willReturn($netsuiteService);
        $developerConfig = $this->createStub(DeveloperConfig::class);
        $developerConfig->method('__call')->willReturn(25);
        $processManagement = $this->createMock(ProcessManagement::class);
        $processManagement->expects($this->once())->method('processRecords')->with($location, $records);

        $locationImportToQueue = new LocationImportToQueue(
            $inventoryMode,
            $location,
            $processManagement,
            $serviceManagement,
            $developerConfig
        );

        $locationImportToQueue->execute();
    }
}
