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

use Magento\TestFramework\Helper\Bootstrap;
use MageOS\NetSuiteConnector\Core\Model\Monitor\Data\Process;
use MageOS\NetSuiteConnector\Core\Model\Monitor\Data\ProcessOutput;
use MageOS\NetSuiteConnector\Core\Model\Monitor\Data\Status;
use MageOS\NetSuiteConnector\Queue\Model\Monitor\MonitorRepository;

$objectManager = Bootstrap::getObjectManager();

/** @var MonitorRepository $monitorRepository */
$monitorRepository = $objectManager->create(MonitorRepository::class);

$monitorItem = $monitorRepository->create();
$monitorItem->setMessageId(9999001);
$monitorItem->setProcess(Process::IMPORT());
$monitorItem->setEntity('customer');
$monitorItem->setItemId(1);
$monitorItem->setStatus(Status::DONE());
$monitorItem->setHasPayload(true);
$monitorItem->setPayloadInstance('NetSuite\Classes\Customer');
$monitorItem->setPayloadString('{"email":"acl-secret-payload@example.com"}');
$monitorItem->addProcessOutput(new ProcessOutput('acl-secret-process-output'));

$monitorRepository->save($monitorItem);
