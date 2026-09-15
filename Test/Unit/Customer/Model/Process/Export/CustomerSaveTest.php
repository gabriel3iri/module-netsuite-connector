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

namespace MageOS\NetSuiteConnector\Test\Unit\Customer\Model\Process\Export;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\Context;
use MageOS\NetSuiteConnector\Core\Api\Data\MessageInterface;
use MageOS\NetSuiteConnector\Core\Api\MonitorManagementInterface;
use MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management;
use MageOS\NetSuiteConnector\Core\Registry\ModuleRegistry;
use MageOS\NetSuiteConnector\Customer\Model\Mapper\Customer;
use MageOS\NetSuiteConnector\Customer\Model\Process\Export\CustomerSave;
use NetSuite\Classes\AddResponse;
use NetSuite\Classes\RecordRef;
use NetSuite\Classes\Status;
use NetSuite\Classes\UpdateResponse;
use NetSuite\Classes\WriteResponse;
use NetSuite\NetSuiteService;
use PHPUnit\Framework\TestCase;

/**
 * Before the fix, CustomerSave looked the NetSuite customer up only by the externalId built from the
 * current email and store, so an email change or a stored internal id that the externalId search could
 * not reach fell through to add(), creating a duplicate NetSuite record. These pin the delegation to
 * Customer::resolveNetsuiteInternalId() and the resulting add/update choice.
 */
class CustomerSaveTest extends TestCase
{
    public function testEmailChangeWithStoredInternalIdUpdatesAndSendsNoAdd(): void
    {
        $this->assertUpdatesExistingRecord('42');
    }

    public function testNoInternalIdButMatchingExternalIdUpdates(): void
    {
        $this->assertUpdatesExistingRecord('77');
    }

    public function testFoundOnlyByEmailUpdatesAndStoresId(): void
    {
        $this->assertUpdatesExistingRecord('88');
    }

    public function testGenuinelyNewCustomerIsAddedOnce(): void
    {
        $magentoCustomer = $this->createMock(CustomerInterface::class);
        $magentoCustomer->method('getId')->willReturn(1);
        $magentoCustomer->expects($this->once())
            ->method('setCustomAttribute')
            ->with('netsuite_internal_id', '99')
            ->willReturnSelf();

        $customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepository->method('getById')->with(1)->willReturn($magentoCustomer);
        $customerRepository->expects($this->once())->method('save')->with($magentoCustomer);

        $customerMapper = $this->createMock(Customer::class);
        $customerMapper->expects($this->once())
            ->method('resolveNetsuiteInternalId')
            ->with($magentoCustomer)
            ->willReturn(null);
        $customerMapper->method('getNetsuiteFormat')->willReturn(new \NetSuite\Classes\Customer());

        $netsuiteService = $this->createMock(NetSuiteService::class);
        $netsuiteService->expects($this->once())->method('add')->willReturn($this->buildWriteResponse(AddResponse::class, '99'));
        $netsuiteService->expects($this->never())->method('update');

        $customerSave = $this->createCustomerSave($customerRepository, $customerMapper, $netsuiteService);
        $customerSave->process($this->createMessage());
    }

    private function assertUpdatesExistingRecord(string $existingInternalId): void
    {
        $magentoCustomer = $this->createMock(CustomerInterface::class);
        $magentoCustomer->method('getId')->willReturn(1);
        $magentoCustomer->expects($this->once())
            ->method('setCustomAttribute')
            ->with('netsuite_internal_id', $existingInternalId)
            ->willReturnSelf();

        $customerRepository = $this->createMock(CustomerRepositoryInterface::class);
        $customerRepository->method('getById')->with(1)->willReturn($magentoCustomer);
        $customerRepository->expects($this->once())->method('save')->with($magentoCustomer);

        $customerMapper = $this->createMock(Customer::class);
        $customerMapper->expects($this->once())
            ->method('resolveNetsuiteInternalId')
            ->with($magentoCustomer)
            ->willReturn($existingInternalId);
        $customerMapper->method('getNetsuiteFormat')->willReturn(new \NetSuite\Classes\Customer());

        $netsuiteService = $this->createMock(NetSuiteService::class);
        $netsuiteService->expects($this->never())->method('add');
        $netsuiteService->expects($this->once())
            ->method('update')
            ->with($this->callback(function ($request) use ($existingInternalId) {
                return $request->record->internalId === $existingInternalId;
            }))
            ->willReturn($this->buildWriteResponse(UpdateResponse::class, $existingInternalId));

        $customerSave = $this->createCustomerSave($customerRepository, $customerMapper, $netsuiteService);
        $customerSave->process($this->createMessage());
    }

    private function buildWriteResponse(string $responseClass, string $internalId): object
    {
        $response = new $responseClass();
        $response->writeResponse = new WriteResponse();
        $response->writeResponse->status = new Status();
        $response->writeResponse->status->isSuccess = true;
        $response->writeResponse->baseRef = new RecordRef();
        $response->writeResponse->baseRef->internalId = $internalId;

        return $response;
    }

    private function createMessage(): MessageInterface
    {
        $message = $this->createStub(MessageInterface::class);
        $message->method('getItemId')->willReturn(1);

        return $message;
    }

    private function createCustomerSave(
        CustomerRepositoryInterface $customerRepository,
        Customer $customerMapper,
        NetSuiteService $netsuiteService
    ): CustomerSave {
        $serviceManagement = $this->createStub(Management::class);
        $serviceManagement->method('get')->willReturn($netsuiteService);

        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));

        return new CustomerSave(
            $customerRepository,
            $serviceManagement,
            $customerMapper,
            $context,
            $this->createStub(ModuleRegistry::class),
            $this->createStub(MonitorManagementInterface::class)
        );
    }
}
