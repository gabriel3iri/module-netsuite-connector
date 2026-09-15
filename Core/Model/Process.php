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

namespace MageOS\NetSuiteConnector\Core\Model;

use NetSuite\Classes\RecordType;
use MageOS\NetSuiteConnector\Core\Enum\Message\Queue;
use MageOS\NetSuiteConnector\Core\Enum\Message\Status;
use MageOS\NetSuiteConnector\Core\Exception\MessageProcessor;
use MageOS\NetSuiteConnector\Core\Exception\SkipRecordException;
use MageOS\NetSuiteConnector\Core\Model\Config\QueueConfig;

use MageOS\NetSuiteConnector\Core\Model\NetSuite\LastUpdateManager;
use MageOS\NetSuiteConnector\Core\Model\Process\ExportProcessor;
use MageOS\NetSuiteConnector\Core\Model\Process\ImportProcessor;
use MageOS\NetSuiteConnector\Core\Model\Process\Import\BatchPrefetchInterface;

/**
 * Refactor #2. Not much more to Decouple without major rewrite. Ignoring it for now.
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Process
{
    private const MAX_MESSAGES_PER_BATCH = 50;

    /**
     * @var \Magento\Framework\Event\ManagerInterface
     */
    protected $eventManager;
    /**
     * @var ImportProcessor
     */
    protected $importProcessor;
    /**
     * @var ExportProcessor
     */
    protected $exportProcessor;
    /**
     * @var array
     */
    protected $_processedOperatios = [];
    /**
     * @var ImportQueueManager
     */
    private $importManager;
    /**
     * @var QueueConfig
     */
    private $queueConfig;
    /**
     * @var NetSuite\LastUpdateManager
     */
    private $lastUpdateManager;
    /**
     * @var Config\ConnectorConfig
     */
    private $connectorConfig;
    /**
     * @var NetSuite\ServiceRepository
     */
    private $serviceRepository;

    /**
     * @var ProcessManagement
     */
    private $processManagement;
    /**
     * @var \MageOS\NetSuiteConnector\Core\Model\Logger\Logger
     */
    private $logger;
    private \MageOS\NetSuiteConnector\Core\Api\MonitorManagementInterface $monitorManagement;
    private \MageOS\NetSuiteConnector\Core\Api\MessageManagementInterface $messageManagement;

    /**
     * Refactor number #2. I've cut down to half but less then that and whole file needs to be rewritten.
     * Ignoring the parameter list size for now.
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \MageOS\NetSuiteConnector\Core\Model\ImportQueueManager $importManager,
        \MageOS\NetSuiteConnector\Core\Model\Config\QueueConfig $queueConfig,
        \MageOS\NetSuiteConnector\Core\Model\Process\ImportProcessor $importProcessor,
        \MageOS\NetSuiteConnector\Core\Model\Process\ExportProcessor $exportProcessor,
        LastUpdateManager $lastUpdateManager,
        \Magento\Framework\Model\Context $context,
        \MageOS\NetSuiteConnector\Core\Model\ProcessManagement $processManagement,
        \MageOS\NetSuiteConnector\Core\Model\Config\ConnectorConfig $connectorConfig,
        \MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Repository $serviceRepository,
        \MageOS\NetSuiteConnector\Core\Model\Logger\Logger $logger,
        \MageOS\NetSuiteConnector\Core\Api\MessageManagementInterface $messageManagement,
        \MageOS\NetSuiteConnector\Core\Api\MonitorManagementInterface $monitorManagement
    ) {
        $this->importProcessor = $importProcessor;
        $this->exportProcessor = $exportProcessor;
        $this->importManager = $importManager;
        $this->queueConfig = $queueConfig;
        $this->lastUpdateManager = $lastUpdateManager;
        $this->eventManager = $context->getEventDispatcher();
        $this->connectorConfig = $connectorConfig;
        $this->serviceRepository = $serviceRepository;
        $this->processManagement = $processManagement;
        $this->logger = $logger;
        $this->monitorManagement = $monitorManagement;
        $this->messageManagement = $messageManagement;
    }

    /**
     * @param bool $processQueue
     * @throws \Exception
     */
    public function processImport(bool $processQueue = true)
    {
        if (!$this->connectorConfig->isEnabled()) {
            return;
        }

        if ($processQueue) {
            $this->processImportQueue();
        }

        $time = $this->serviceRepository->getServerTime();

        $importableEntities = $this->importProcessor->getImportableEntities();

        $allEntitiesSucceeded = true;

        foreach ($importableEntities as $entityCode => $importableEntityModel) {
            /** @var \MageOS\NetSuiteConnector\Product\Model\Process\Import\Item|\MageOS\NetSuiteConnector\Core\Model\Process\Import\AbstractImportProcessor $importableEntityModel */
            if (!$importableEntityModel->isActive()) {
                continue;
            }

            try {
                $entityFlag = LastUpdateManager::IMPORT_FLAG . '_' . $entityCode;
                $updatedFrom = $this->getUpdatedFromDateInNetsuiteFormat($entityFlag);
                $fromBeginning = true;
                while ($records = $importableEntityModel->queryNetsuite($updatedFrom, $fromBeginning)) {
                    $fromBeginning = false;

                    if (!is_array($records)) {
                        continue;
                    }

                    $this->processManagement->processRecords($importableEntityModel, $records);
                }

                $this->lastUpdateManager->setLastUpdateDate($entityFlag, $time);
            } catch (\Throwable $e) {
                $allEntitiesSucceeded = false;
                $this->logger->addError(
                    'Error importing entity type ' . $importableEntityModel->getRecordType() . ': ' .
                    implode(' | ', MessageProcessor::getMessages($e))
                );
            }
        }

        if ($allEntitiesSucceeded) {
            $this->lastUpdateManager->setLastUpdateDate(LastUpdateManager::IMPORT_FLAG, $time);
        }
    }

    public function processImportQueue()
    {
        if (!$this->connectorConfig->isEnabled()) {
            return;
        }

        $maxMessages = $this->queueConfig->getImportBatchSize();
        $maxMessages = $maxMessages < 1 ? self::MAX_MESSAGES_PER_BATCH : $maxMessages;

        $perBatch = $maxMessages >= self::MAX_MESSAGES_PER_BATCH ?
            self::MAX_MESSAGES_PER_BATCH : $maxMessages;
        $totalRounds = (int)ceil($maxMessages / $perBatch);

        for ($i = 0; $i <= $totalRounds; $i++) {
            $messages = $this->messageManagement->receive(Queue::IMPORT(), $perBatch);
            if (count($messages) == 0) {
                $this->logger->addInfo('import queue empty, nothing to import.');
                break;
            }

            /**
             * If number of messages is smaller then the batch, then we are at the end of the batch so it can be
             * terminated at the end of the cycle
             */
            if (count($messages) < $perBatch) {
                $i = $totalRounds;
            }

            try {
                $this->prefetch($messages);
                $this->logger->addInfo('Processing import queue. Size: ' . count($messages));

                $messagesToDelete = $this->importMessages($messages);
                if (empty($messagesToDelete)) {
                    continue;
                }

                // Now batch import what's left
                try {
                    $this->importManager->commit();
                } catch (\Throwable $e) {
                    $errorMessage = implode(
                        "\n",
                        MessageProcessor::getMessages($e)
                    );
                    $messageIds =  $this->messageManagement->transformToIds($messagesToDelete);
                    $this->messageManagement->reject($messageIds, $errorMessage);
                    $this->logger->addError($errorMessage);

                    continue;
                }

                $importErrors = $this->importManager->getFailedNetsuiteIds();
                $this->deleteMessages($messagesToDelete, $importErrors);

            } catch (\Throwable $e) {
                $this->logger->addError(
                    'Error processing queue messages: ' .
                    implode(' | ', MessageProcessor::getMessages($e))
                );
                $this->messageManagement->changeStatus(
                    $this->messageManagement->transformToIds($messages),
                    Status::IN_QUEUE(),
                    'Server error, please check the connector log'
                );
            }

        }
    }

    private function prefetch(array $messages): void
    {
        $recordsByAction = [];
        foreach ($messages as $message) {
            $recordsByAction[$message->getAction()][] = $message->getObject();
        }

        foreach ($recordsByAction as $action => $records) {
            $processor = $this->importProcessor->getEntityProcessor($action);
            if ($processor instanceof BatchPrefetchInterface) {
                $processor->prefetch($records);
            }
        }
    }

    protected function getUpdatedFromDateInNetsuiteFormat(string $flagCode)
    {
        $lastUpdateDate = $this->lastUpdateManager->getLastUpdateDate($flagCode)
            ?: $this->lastUpdateManager->getLastUpdateDate(LastUpdateManager::IMPORT_FLAG);
        $lastUpdateDate = $lastUpdateDate ? new \DateTime($lastUpdateDate) : null;

        $updatedFromDefault = $this->queueConfig->getUpdatedFromMinutes();
        $updatedFromDate = new \DateTime();
        $updatedFromDate->setTimezone(new \DateTimeZone('GMT'));
        $updatedFromDate->sub(new \DateInterval('PT' . $updatedFromDefault . 'M'));

        if ($lastUpdateDate === null) {
            return $updatedFromDate->format(\DateTime::ISO8601);
        }

        return $lastUpdateDate->getTimestamp() < $updatedFromDate->getTimestamp()
            ? $lastUpdateDate->format(\DateTime::ISO8601)
            : $updatedFromDate->format(\DateTime::ISO8601);
    }

    public function processExport()
    {
        if (!$this->connectorConfig->isEnabled()) {
            return;
        }

        $maxMessages = $this->queueConfig->getExportBatchSize();
        if (!$maxMessages) {
            $maxMessages = 50;
        }

        $time = $this->serviceRepository->getServerTime();
        $messages = $this->messageManagement->receive(Queue::EXPORT(), $maxMessages);

        foreach ($messages as $message) {
            $processModel = $this->exportProcessor->getEntityProcessor($message->getAction());

            if (!$processModel) {
                $errorMessage = "Model for action '{$message->getAction()}' was not found!";
                $this->messageManagement->reject(
                    [$message->getId()],
                    $errorMessage,
                    true
                );
                $this->logger->addInfo($errorMessage);
                continue;
            }

            try {
                //if more of the same operation are on the list, process just one.
                if (!isset($this->_processedOperatios[$message->getAction()][$message->getItemId()])) {
                    $processModel->process($message);
                    $this->_processedOperatios[$message->getAction()][$message->getItemId()] = 1;
                }

                if (!$message->getSkipProcessing()) {
                    $this->messageManagement->changeStatus(
                        [$message->getId()],
                        Status::DONE()
                    );
                    $this->messageManagement->deleteById($message->getId());
                }
            } catch (\Throwable $ex) {
                $errorMessage = $this->processException($message, $ex);
                $this->messageManagement->reject(
                    [$message->getId()],
                    $errorMessage
                );
            }
        }

        $this->lastUpdateManager->setLastUpdateDate(LastUpdateManager::EXPORT_FLAG, $time);
    }

    public function resetProcessedOperations()
    {
        $this->_processedOperatios = [];
    }

    private function processException($queueMessage, \Throwable $ex): string
    {
        $message = [];
        $message[] = $queueMessage->getAction() . '#' . $queueMessage->getItemId() . ': ';
        $message = array_merge($message, MessageProcessor::getMessages($ex));
        $message = implode("\n", $message);

        $this->logger->addError($message);
        return $message;
    }

    private function importMessages(array $messages): array
    {
        /**
         * Simple anon. function to get Identifier for logger
         */
        $identifier = function ($message) {
            return $message->getAction() . '#' . $message->getObject()->internalId;
        };

        $messagesToDelete = [];
        foreach ($messages as $message) {
            $processModel = $this->importProcessor->getEntityProcessor($message->getAction());

            if (!$processModel) {
                $errorMessage = "Model for action '{$message->getAction()}' was not found!";
                $this->messageManagement->reject([$message->getId()], $errorMessage, true);
                $this->logger->addInfo($errorMessage);
                continue;
            }

            try {
                $this->eventManager->dispatch(
                    'netsuite_import_item_process_before',
                    ['item' => $message->getObject()]
                );

                if (!$message->getObject()) {
                    $errorMessage = 'deleting import message with empty body: ' . $identifier($message);
                    $this->logger->addInfo($errorMessage);
                    $this->messageManagement->reject([$message->getId()], $errorMessage, true);
                    $this->messageManagement->deleteById($message->getId());
                    continue;
                }

                $object = $this->monitorManagement->getModifiedObject($message)
                    ?? $message->getObject();

                $result = $processModel->process($object);
                if (!($result instanceof ImportRowList)) {
                    $this->logger->addInfo('imported message:' . $identifier($message));
                    $this->messageManagement->changeStatus(
                        [$message->getId()],
                        Status::DONE(),
                        'Processed successfully'
                    );
                    $this->messageManagement->deleteById($message->getId());
                } else {
                    $this->importManager->import($result);
                    $messagesToDelete[$message->getObject()->internalId] = $message;
                }
            } catch (SkipRecordException $ex) {
                $errorMessage = 'skipped, deleting:' . $identifier($message);
                $this->logger->addInfo($errorMessage);
                $this->messageManagement->changeStatus(
                    [$message->getId()],
                    Status::DONE(),
                    $errorMessage
                );
                $this->messageManagement->deleteById($message->getId());
            } catch (\Throwable $ex) {
                $errorMessage = $this->processException($message, $ex);
                $this->messageManagement->reject([$message->getId()], $errorMessage);
            }
        }

        return $messagesToDelete;
    }

    private function deleteMessages(array $messagesToDelete, array $importErrors): void
    {
        foreach ($messagesToDelete as $internalId => $message) {
            if (!isset($importErrors[$internalId])) {
                $this->logger->addInfo('deleted message: #' . $message->getId());
                $this->messageManagement->changeStatus(
                    [$message->getId()],
                    Status::DONE(),
                    'Processed successfully'
                );
                $this->messageManagement->deleteById($message->getId());
            } else {
                $errorMessage = 'Error importing internalId#' . $internalId . ': ' . $importErrors[$internalId];
                $this->logger->addInfo($errorMessage);
                $this->messageManagement->reject(
                    [$message->getId()],
                    $errorMessage
                );
            }
        }
    }
}
