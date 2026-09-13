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

namespace MageOS\NetSuiteConnector\Inventory\Model\Process\Import;

use MageOS\NetSuiteConnector\Core\Exception\MessageProcessor;
use MageOS\NetSuiteConnector\Core\Model\NetSuite\ConvertDate;
use MageOS\NetSuiteConnector\Inventory\Model\Config\StockConfig;

class Stock
{
    private \MageOS\NetSuiteConnector\Inventory\Model\ConfigProvider\Permissions $stockUpdatePermissions;
    private \MageOS\NetSuiteConnector\Inventory\Model\NetSuiteInventoryRepository $netSuiteInventoryRepository;
    private \MageOS\NetSuiteConnector\Core\Model\NetSuite\LastUpdateManager $lastUpdateManager;
    private \MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Repository $serviceRepository;
    private \MageOS\NetSuiteConnector\Core\Model\Logger\Logger $logger;
    private \MageOS\NetSuiteConnector\Inventory\Model\Config\StockConfig $stockConfig;

    public function __construct(
        \MageOS\NetSuiteConnector\Core\Model\NetSuite\LastUpdateManager $lastUpdateManager,
        \MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Repository $serviceRepository,
        \MageOS\NetSuiteConnector\Inventory\Model\ConfigProvider\Permissions $stockUpdatePermissions,
        \MageOS\NetSuiteConnector\Inventory\Model\NetSuiteInventoryRepository $netSuiteInventoryRepository,
        \MageOS\NetSuiteConnector\Core\Model\Logger\Logger $logger,
        \MageOS\NetSuiteConnector\Inventory\Model\Config\StockConfig $stockConfig
    ) {

        $this->stockUpdatePermissions = $stockUpdatePermissions;
        $this->netSuiteInventoryRepository = $netSuiteInventoryRepository;
        $this->lastUpdateManager = $lastUpdateManager;
        $this->serviceRepository = $serviceRepository;
        $this->logger = $logger;
        $this->stockConfig = $stockConfig;
    }

    public function process(): void
    {
        try {
            if (!$this->stockUpdatePermissions->isFeatureEnabled()) {
                return;
            }
            $currentDate = $this->serviceRepository->getServerTime();
            if ($this->stockConfig->shouldRun(ConvertDate::fromNetSuiteToSql($currentDate))) {
                $this->netSuiteInventoryRepository->processInventoryUpdates();
                $this->lastUpdateManager->setLastUpdateDate(StockConfig::FLAG_CODE, $currentDate);
            }
        } catch (\Throwable $exception) {
            $messageErrors = MessageProcessor::getMessagesAsString($exception);
            $this->logger->addError($messageErrors);
        }
    }
}
