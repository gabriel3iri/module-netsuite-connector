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

namespace MageOS\NetSuiteConnector\Test\Integration\Core\Cron;

use Magento\Framework\App\ObjectManager;
use Magento\TestFramework\Mail\Template\TransportBuilderMock;
use MageOS\NetSuiteConnector\Core\Cron\SendQueueWarnings;
use MageOS\NetSuiteConnector\Core\Enum\Message\Queue;
use MageOS\NetSuiteConnector\Core\Enum\Message\Status;
use MageOS\NetSuiteConnector\Core\Model\Config\DeveloperConfig;
use MageOS\NetSuiteConnector\Queue\Model\ResourceModel\Queue\Message;

class SendQueueWarningsTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        /** @var DeveloperConfig $developerConfig */
        $developerConfig = ObjectManager::getInstance()->get(DeveloperConfig::class);
        $developerConfig->setCacheEnabled(false);

        /** @var TransportBuilderMock $transportBuilder */
        $transportBuilder = ObjectManager::getInstance()->get(TransportBuilderMock::class);
        $transportBuilder->clean();
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppArea adminhtml
     * @magentoConfigFixture default/mageos_netsuite/developer/email dev@example.test
     * @magentoConfigFixture default/mageos_netsuite/developer/export_queue_threshold 1
     */
    public function testExportQueueOverThresholdSendsEmail(): void
    {
        $objectManager = ObjectManager::getInstance();
        /** @var Message $messageResource */
        $messageResource = $objectManager->get(Message::class);
        $this->addMessage($messageResource, Queue::EXPORT(), Status::IN_QUEUE());
        $this->addMessage($messageResource, Queue::EXPORT(), Status::RETRY());

        /** @var SendQueueWarnings $cron */
        $cron = $objectManager->create(SendQueueWarnings::class);
        $cron->execute();

        /** @var TransportBuilderMock $transportBuilder */
        $transportBuilder = $objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilder->getSentMessage();
        $this->assertNotNull($sentMessage);
        $this->assertStringContainsString(
            'Netsuite export queue size threshold reached',
            (string)$sentMessage->getSubject()
        );
        $this->assertStringContainsString(
            '2 elements in the queue',
            quoted_printable_decode($sentMessage->getBody()->bodyToString())
        );
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppArea adminhtml
     * @magentoConfigFixture default/mageos_netsuite/developer/email dev@example.test
     */
    public function testImportQueueUnderThresholdSendsNoEmail(): void
    {
        $objectManager = ObjectManager::getInstance();
        /** @var Message $messageResource */
        $messageResource = $objectManager->get(Message::class);
        $this->addMessage($messageResource, Queue::IMPORT(), Status::IN_QUEUE());

        /** @var SendQueueWarnings $cron */
        $cron = $objectManager->create(SendQueueWarnings::class);
        $cron->execute();

        /** @var TransportBuilderMock $transportBuilder */
        $transportBuilder = $objectManager->get(TransportBuilderMock::class);
        $this->assertNull($transportBuilder->getSentMessage());
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppArea adminhtml
     * @magentoConfigFixture default/mageos_netsuite/developer/email dev@example.test
     * @magentoConfigFixture default/mageos_netsuite/developer/export_queue_threshold 1
     * @magentoConfigFixture default/mageos_netsuite/developer/import_queue_threshold 5
     */
    public function testCountsArePerQueue(): void
    {
        $objectManager = ObjectManager::getInstance();
        /** @var Message $messageResource */
        $messageResource = $objectManager->get(Message::class);
        $this->addMessage($messageResource, Queue::EXPORT(), Status::IN_QUEUE());
        $this->addMessage($messageResource, Queue::EXPORT(), Status::IN_QUEUE());
        $this->addMessage($messageResource, Queue::IMPORT(), Status::IN_QUEUE());

        /** @var SendQueueWarnings $cron */
        $cron = $objectManager->create(SendQueueWarnings::class);
        $cron->execute();

        /** @var TransportBuilderMock $transportBuilder */
        $transportBuilder = $objectManager->get(TransportBuilderMock::class);
        $sentMessage = $transportBuilder->getSentMessage();
        $this->assertNotNull($sentMessage);
        $this->assertStringContainsString(
            'Netsuite export queue size threshold reached',
            (string)$sentMessage->getSubject()
        );
        $this->assertStringContainsString(
            '2 elements in the queue',
            quoted_printable_decode($sentMessage->getBody()->bodyToString())
        );
    }

    /**
     * @param Message $messageResource
     * @param Queue $queue
     * @param Status $status
     * @return void
     */
    private function addMessage(Message $messageResource, Queue $queue, Status $status): void
    {
        $messageResource->saveMessage([
            'queue' => (string)$queue,
            'status' => (string)$status,
            'action' => 'test_action',
            'number_of_trials' => 0
        ]);
    }
}
