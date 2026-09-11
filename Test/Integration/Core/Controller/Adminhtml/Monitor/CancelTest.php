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

namespace MageOS\NetSuiteConnector\Test\Integration\Core\Controller\Adminhtml\Monitor;

use Magento\Framework\Message\MessageInterface;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;
use MageOS\NetSuiteConnector\Core\Api\MonitorRegistryInterface;
use MageOS\NetSuiteConnector\Core\Enum\Message\Queue;
use MageOS\NetSuiteConnector\Core\Enum\Message\Status as MessageStatus;
use MageOS\NetSuiteConnector\Core\Model\Monitor\Data\Process;
use MageOS\NetSuiteConnector\Core\Model\Monitor\Data\Status as MonitorStatus;
use MageOS\NetSuiteConnector\Queue\Model\Monitor\MonitorRepository;
use MageOS\NetSuiteConnector\Queue\Model\Queue\MessageManagement;
use MageOS\NetSuiteConnector\Queue\Model\ResourceModel\Queue\Message as MessageResource;

/**
 * Cancel redirects on every outcome, allowed or denied, so a plain HTTP status code cannot
 * tell the two apart (both are 302); the inherited testAclHasAccess/testAclNoAccess assume it
 * can. These overrides check the redirect target instead.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class CancelTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'MageOS_NetSuiteConnector::netsuite';

    /**
     * @var string
     */
    protected $uri = 'backend/netsuite/monitor/cancel';

    public function testAclHasAccess(): void
    {
        $this->dispatch($this->uri);

        $this->assertNotSame(404, $this->getResponse()->getHttpResponseCode());
        $this->assertRedirect($this->logicalNot($this->stringContains('admin/denied')));
    }

    public function testAclNoAccess(): void
    {
        $acl = $this->_objectManager->get(\Magento\Framework\Acl\Builder::class)->getAcl();
        $acl->deny($this->_auth->getUser()->getRoles(), $this->resource);

        $this->dispatch($this->uri);

        $this->assertRedirect($this->stringContains('admin/denied'));
    }

    public function testCancelQueuedMessageCancelsTheQueueMessageToo(): void
    {
        $messageId = $this->createMessage(MessageStatus::IN_QUEUE());
        $monitorId = $this->createMonitorItem($messageId, MonitorStatus::IN_QUEUE());

        $this->getRequest()->setParam('id', $monitorId);
        $this->dispatch($this->uri);

        $this->assertSessionMessages(
            $this->containsEqual('Item Cancelled!'),
            MessageInterface::TYPE_SUCCESS
        );
        $this->assertSame((string)MessageStatus::CANCELLED(), $this->getMessageStatus($messageId));
        $this->assertSame((string)MonitorStatus::CANCELLED(), (string)$this->getMonitorStatus($monitorId));

        $messageManagement = $this->getMessageManagement();
        $received = $messageManagement->receive(Queue::EXPORT(), 100);
        $receivedIds = array_map(static fn ($message) => $message->getId(), $received);
        $this->assertNotContains($messageId, $receivedIds);
    }

    public function testCancelInProgressMessageIsRefused(): void
    {
        $messageId = $this->createMessage(MessageStatus::IN_PROGRESS());
        $monitorId = $this->createMonitorItem($messageId, MonitorStatus::IN_PROGRESS());

        $this->getRequest()->setParam('id', $monitorId);
        $this->dispatch($this->uri);

        $this->assertSessionMessages(
            $this->containsEqual('This job is currently running and cannot be cancelled.'),
            MessageInterface::TYPE_ERROR
        );
        $this->assertSame((string)MessageStatus::IN_PROGRESS(), $this->getMessageStatus($messageId));
        $this->assertSame((string)MonitorStatus::IN_PROGRESS(), (string)$this->getMonitorStatus($monitorId));
    }

    public function testCancelWithNoQueueMessageStillCancelsTheMonitor(): void
    {
        $missingMessageId = 987654321;
        $monitorId = $this->createMonitorItem($missingMessageId, MonitorStatus::IN_QUEUE());

        $this->getRequest()->setParam('id', $monitorId);
        $this->dispatch($this->uri);

        $this->assertSessionMessages(
            $this->containsEqual('Item Cancelled!'),
            MessageInterface::TYPE_SUCCESS
        );
        $this->assertSame((string)MonitorStatus::CANCELLED(), (string)$this->getMonitorStatus($monitorId));
    }

    private function createMessage(MessageStatus $status): int
    {
        /** @var MessageResource $messageResource */
        $messageResource = Bootstrap::getObjectManager()->get(MessageResource::class);

        return $messageResource->saveMessage([
            'queue' => (string)Queue::EXPORT(),
            'status' => (string)$status,
            'action' => 'order_place',
            'item_id' => (string)random_int(10000, 99999),
            'number_of_trials' => 0
        ]);
    }

    private function createMonitorItem(int $messageId, MonitorStatus $status): int
    {
        /** @var MonitorRepository $monitorRepository */
        $monitorRepository = Bootstrap::getObjectManager()->get(MonitorRepository::class);

        $monitorItem = $monitorRepository->create();
        $monitorItem->setMessageId($messageId);
        $monitorItem->setProcess(Process::EXPORT());
        $monitorItem->setEntity('order');
        $monitorItem->setItemId(1);
        $monitorItem->setStatus($status);
        $monitorItem->setHasPayload(false);

        $monitorRepository->save($monitorItem);

        return (int)$monitorItem->getId();
    }

    private function getMessageStatus(int $messageId): ?string
    {
        /** @var MessageResource $messageResource */
        $messageResource = Bootstrap::getObjectManager()->get(MessageResource::class);
        $data = $messageResource->getMessage(['message_id' => $messageId]);

        return $data === null ? null : (string)$data['status'];
    }

    private function getMonitorStatus(int $monitorId): MonitorStatus
    {
        /** @var MonitorRegistryInterface $monitorRegistry */
        $monitorRegistry = Bootstrap::getObjectManager()->get(MonitorRegistryInterface::class);
        $monitorItem = $monitorRegistry->getById($monitorId);

        return $monitorItem->getStatus();
    }

    private function getMessageManagement(): MessageManagement
    {
        return Bootstrap::getObjectManager()->get(MessageManagement::class);
    }
}
