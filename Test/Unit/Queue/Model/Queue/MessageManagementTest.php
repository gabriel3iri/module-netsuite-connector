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

namespace MageOS\NetSuiteConnector\Test\Unit\Queue\Model\Queue;

use MageOS\NetSuiteConnector\Core\Enum\Message\Status;
use MageOS\NetSuiteConnector\Queue\Model\Queue\Message;
use MageOS\NetSuiteConnector\Queue\Model\Queue\MessageManagement;
use NetSuite\Classes\Invoice;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class MessageManagementTest extends TestCase
{
    private const ERROR_REASON = 'Retried 10 times or force rejecting, changing status to Error. Last error: still failing';

    public function testAPriorityMessageAtTheLimitGoesToError(): void
    {
        $sut = $this->sutWithMessage(7, new Invoice(), 30);

        $sut->expects($this->once())
            ->method('changeStatus')
            ->with([7], $this->equalTo(Status::ERROR()), self::ERROR_REASON);

        $sut->reject([7], 'still failing');
    }

    public function testAPriorityMessageBelowTheLimitGoesToRetry(): void
    {
        $sut = $this->sutWithMessage(7, new Invoice(), 29);

        $sut->expects($this->once())
            ->method('changeStatus')
            ->with([7], $this->equalTo(Status::RETRY()), 'still failing');

        $sut->reject([7], 'still failing');
    }

    public function testAPriorityZeroMessageKeepsTheTenTrialLimit(): void
    {
        $sut = $this->sutWithMessage(9, null, 10);

        $sut->expects($this->once())
            ->method('changeStatus')
            ->with([9], $this->equalTo(Status::ERROR()), self::ERROR_REASON);

        $sut->reject([9], 'still failing');
    }

    /**
     * @return MessageManagement&MockObject
     */
    private function sutWithMessage(int $messageId, $body, int $numberOfTrials): MessageManagement
    {
        $message = new Message([
            'message_id' => $messageId,
            'body' => $body,
            'number_of_trials' => $numberOfTrials,
        ]);

        $sut = $this->getMockBuilder(MessageManagement::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['changeStatus'])
            ->getMock();

        $property = new \ReflectionProperty(MessageManagement::class, 'messages');
        $property->setValue($sut, [$messageId => $message]);

        return $sut;
    }
}
