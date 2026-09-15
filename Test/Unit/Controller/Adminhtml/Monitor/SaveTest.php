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

namespace MageOS\NetSuiteConnector\Test\Unit\Controller\Adminhtml\Monitor;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Framework\App\Request\Http;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager as ObjectManagerHelper;
use MageOS\NetSuiteConnector\Controller\Adminhtml\Monitor\Save;
use MageOS\NetSuiteConnector\Core\Api\Data\MonitorItemInterface;
use MageOS\NetSuiteConnector\Core\Api\MonitorRegistryInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SaveTest extends TestCase
{
    private Http $request;
    private Redirect $redirect;
    private MonitorItemInterface|MockObject $monitorItem;
    private Save $controller;

    protected function setUp(): void
    {
        $objectManagerHelper = new ObjectManagerHelper($this);

        $context = $this->createStub(Context::class);

        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturn(true);
        $context->method('getAuthorization')->willReturn($authorization);

        $this->redirect = $this->createStub(Redirect::class);
        $this->redirect->method('setPath')->willReturnSelf();

        $resultFactory = $this->createStub(ResultFactory::class);
        $resultFactory->method('create')->willReturn($this->redirect);
        $context->method('getResultFactory')->willReturn($resultFactory);

        $this->request = $this->createStub(Http::class);
        $this->request->method('getParam')->willReturn(false);
        $context->method('getRequest')->willReturn($this->request);

        $context->method('getMessageManager')->willReturn($this->createStub(ManagerInterface::class));

        $this->monitorItem = $this->createMock(MonitorItemInterface::class);
        $this->monitorItem->method('getHasPayload')->willReturn(true);
        $this->monitorItem->method('getPayload')->willReturn(['foo' => 'bar']);

        $monitorRegistry = $this->createStub(MonitorRegistryInterface::class);
        $monitorRegistry->method('getById')->willReturn($this->monitorItem);

        $this->controller = $objectManagerHelper->getObject(
            Save::class,
            [
                'context' => $context,
                'monitorRegistry' => $monitorRegistry,
            ]
        );
    }

    #[DataProvider('overwritePayloadDataProvider')]
    public function testExecuteResolvesOverwritePayloadFromPostedValue(array $postData, bool $expectedOverwrite): void
    {
        $this->request->method('getPostValue')->willReturn($postData);

        $this->monitorItem->expects($this->once())
            ->method('setOverwritePayload')
            ->with($expectedOverwrite);

        $this->assertSame($this->redirect, $this->controller->execute());
    }

    public static function overwritePayloadDataProvider(): array
    {
        $base = ['monitor_id' => 1, 'payload' => '{"foo":"bar"}'];

        return [
            'posted string false stays off' => [$base + ['overwrite_payload' => 'false'], false],
            'posted string true turns on' => [$base + ['overwrite_payload' => 'true'], true],
            'posted string 1 turns on' => [$base + ['overwrite_payload' => '1'], true],
            'posted string 0 stays off' => [$base + ['overwrite_payload' => '0'], false],
            'missing param stays off' => [$base, false],
        ];
    }
}
