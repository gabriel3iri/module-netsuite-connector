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
 *
 */

namespace MageOS\NetSuiteConnector\Inventory\Multi\Model\Process\Import;

use MageOS\NetSuiteConnector\Core\Exception\DataIntegrityException;
use MageOS\NetSuiteConnector\Core\Exception\NetSuiteRuntimeException;
use MageOS\NetSuiteConnector\Core\Model\NetSuite\ResponseValidator;

class LocationImportToQueue
{
    public function __construct(
        private readonly \MageOS\NetSuiteConnector\Inventory\Model\Config\InventoryMode $inventoryMode,
        private readonly \MageOS\NetSuiteConnector\Inventory\Multi\Model\Process\Import\Location $location,
        private readonly \MageOS\NetSuiteConnector\Core\Model\ProcessManagement $processManagement,
        private readonly \MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management $serviceManagement,
        private readonly \MageOS\NetSuiteConnector\Core\Model\Config\DeveloperConfig $developerConfig
    ) {
    }

    /**
     * @throws DataIntegrityException
     * @throws NetSuiteRuntimeException
     */
    public function execute(): void
    {
        if (!$this->inventoryMode->isMulti()) {
            return;
        }

        $netsuiteService = $this->serviceManagement->get();
        $netsuiteService->setSearchPreferences(false, (int)$this->developerConfig->getImportRecordLimit());
        $response = $netsuiteService->search($this->location->getNetsuiteRequest('location', ''));
        ResponseValidator::validate($response);
        $this->processManagement->processRecords($this->location, $response->searchResult->recordList->record);
    }
}
