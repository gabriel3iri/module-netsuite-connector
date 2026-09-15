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

namespace MageOS\NetSuiteConnector\Test\Unit\Customer\Model\Mapper;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterfaceFactory;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\CustomerInterfaceFactory;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\ResourceModel\AddressRepository as MagentoAddressRepository;
use Magento\Directory\Api\CountryInformationAcquirerInterfaceFactory;
use Magento\Directory\Model\ResourceModel\Country\CollectionFactory;
use Magento\Framework\Api\AttributeValue;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Model\Context;
use Magento\Sales\Model\Order\AddressRepository as OrderAddressRepository;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\NetSuiteConnector\Core\Helper\Transform;
use MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management;
use MageOS\NetSuiteConnector\Customer\Model\Mapper\Address;
use MageOS\NetSuiteConnector\Customer\Model\Mapper\Customer;
use MageOS\NetSuiteConnector\Customer\Model\Mapper\PriceLevel;
use MageOS\NetSuiteConnector\CustomerImport\Model\Customer\Export\IsImportableFlag;
use NetSuite\Classes\GetResponse;
use NetSuite\Classes\ReadResponse;
use NetSuite\Classes\RecordList;
use NetSuite\Classes\SearchRequest;
use NetSuite\Classes\SearchResponse;
use NetSuite\Classes\SearchResult;
use NetSuite\Classes\Status;
use NetSuite\NetSuiteService;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

/**
 * resolveNetsuiteInternalId() replaced a lookup that searched NetSuite only by the externalId built
 * from the current email and store. That search misses an existing NetSuite customer after an email
 * change or a first order from a second store, so export added a duplicate record instead of updating
 * the one already on file.
 */
class CustomerTest extends TestCase
{
    public function testUsesStoredInternalIdWhenRecordStillExists(): void
    {
        $magentoCustomer = $this->createStub(CustomerInterface::class);
        $magentoCustomer->method('getCustomAttribute')
            ->willReturn((new AttributeValue())->setValue('42'));

        $netsuiteService = $this->createMock(NetSuiteService::class);
        $netsuiteService->expects($this->once())
            ->method('get')
            ->with($this->callback(function ($request) {
                return $request->baseRef->internalId === '42'
                    && $request->baseRef->type === \NetSuite\Classes\RecordType::customer;
            }))
            ->willReturn($this->buildGetResponse(new \NetSuite\Classes\Customer()));
        $netsuiteService->expects($this->never())->method('search');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $mapper = $this->createMapper($netsuiteService, $logger);

        $this->assertSame('42', $mapper->resolveNetsuiteInternalId($magentoCustomer));
    }

    public function testFallsThroughToExternalIdSearchWhenStoredInternalIdLookupThrows(): void
    {
        $magentoCustomer = $this->createStub(CustomerInterface::class);
        $magentoCustomer->method('getCustomAttribute')
            ->willReturn((new AttributeValue())->setValue('42'));
        $magentoCustomer->method('getEmail')->willReturn('new@example.com');
        $magentoCustomer->method('getStoreId')->willReturn(1);

        $netsuiteService = $this->createMock(NetSuiteService::class);
        $netsuiteService->expects($this->once())
            ->method('get')
            ->willThrowException(new \SoapFault('Server', 'record no longer exists'));
        $netsuiteService->expects($this->once())
            ->method('search')
            ->with($this->callback(function (SearchRequest $request) {
                return $request->searchRecord->externalIdString->searchValue === 'new@example.com_1';
            }))
            ->willReturn($this->buildSearchResponse('77'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('warning');

        $mapper = $this->createMapper($netsuiteService, $logger);

        $this->assertSame('77', $mapper->resolveNetsuiteInternalId($magentoCustomer));
    }

    public function testFallsThroughWhenStoredInternalIdRecordTypeIsWrong(): void
    {
        $magentoCustomer = $this->createStub(CustomerInterface::class);
        $magentoCustomer->method('getCustomAttribute')
            ->willReturn((new AttributeValue())->setValue('42'));
        $magentoCustomer->method('getEmail')->willReturn('new@example.com');
        $magentoCustomer->method('getStoreId')->willReturn(1);

        $netsuiteService = $this->createMock(NetSuiteService::class);
        $netsuiteService->expects($this->once())
            ->method('get')
            ->willReturn($this->buildGetResponse(new \NetSuite\Classes\Vendor()));
        $netsuiteService->expects($this->once())
            ->method('search')
            ->willReturn($this->buildSearchResponse('77'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->atLeastOnce())->method('warning');

        $mapper = $this->createMapper($netsuiteService, $logger);

        $this->assertSame('77', $mapper->resolveNetsuiteInternalId($magentoCustomer));
    }

    public function testFallsThroughToEmailSearchWhenExternalIdNotFound(): void
    {
        $magentoCustomer = $this->createStub(CustomerInterface::class);
        $magentoCustomer->method('getCustomAttribute')->willReturn(null);
        $magentoCustomer->method('getEmail')->willReturn('customer@example.com');
        $magentoCustomer->method('getStoreId')->willReturn(1);

        $netsuiteService = $this->createMock(NetSuiteService::class);
        $netsuiteService->expects($this->never())->method('get');
        $netsuiteService->expects($this->exactly(2))
            ->method('search')
            ->willReturnCallback(function (SearchRequest $request) {
                if (isset($request->searchRecord->externalIdString)) {
                    return $this->buildSearchResponse(null);
                }
                $this->assertSame('customer@example.com', $request->searchRecord->email->searchValue);
                return $this->buildSearchResponse('88');
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $mapper = $this->createMapper($netsuiteService, $logger);

        $this->assertSame('88', $mapper->resolveNetsuiteInternalId($magentoCustomer));
    }

    public function testReturnsNullWhenNothingMatches(): void
    {
        $magentoCustomer = $this->createStub(CustomerInterface::class);
        $magentoCustomer->method('getCustomAttribute')->willReturn(null);
        $magentoCustomer->method('getEmail')->willReturn('nobody@example.com');
        $magentoCustomer->method('getStoreId')->willReturn(1);

        $netsuiteService = $this->createMock(NetSuiteService::class);
        $netsuiteService->expects($this->never())->method('get');
        $netsuiteService->expects($this->exactly(2))
            ->method('search')
            ->willReturn($this->buildSearchResponse(null));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $mapper = $this->createMapper($netsuiteService, $logger);

        $this->assertNull($mapper->resolveNetsuiteInternalId($magentoCustomer));
    }

    private function buildGetResponse(object $record): GetResponse
    {
        $response = new GetResponse();
        $response->readResponse = new ReadResponse();
        $response->readResponse->status = new Status();
        $response->readResponse->status->isSuccess = true;
        $response->readResponse->record = $record;

        return $response;
    }

    private function buildSearchResponse(?string $internalId): SearchResponse
    {
        $response = new SearchResponse();
        $response->searchResult = new SearchResult();

        if ($internalId === null) {
            $response->searchResult->totalRecords = 0;
            return $response;
        }

        $response->searchResult->totalRecords = 1;
        $response->searchResult->recordList = new RecordList();
        $response->searchResult->recordList->record = [new \NetSuite\Classes\Customer()];
        $response->searchResult->recordList->record[0]->internalId = $internalId;

        return $response;
    }

    private function createMapper(NetSuiteService $netsuiteService, LoggerInterface $logger): Customer
    {
        $serviceManagement = $this->createStub(Management::class);
        $serviceManagement->method('get')->willReturn($netsuiteService);

        return new Customer(
            $serviceManagement,
            $this->createStub(AddressRepositoryInterface::class),
            $this->createStub(CountryInformationAcquirerInterfaceFactory::class),
            $this->createStub(Transform::class),
            $this->createStub(Address::class),
            $this->createStub(Context::class),
            $this->createStub(CustomerInterfaceFactory::class),
            $this->createStub(CustomerRepositoryInterface::class),
            $this->createStub(FilterBuilder::class),
            $this->createStub(StoreManagerInterface::class),
            $this->createStub(CustomerInterfaceFactory::class),
            $this->createStub(AddressInterfaceFactory::class),
            $this->createStub(MagentoAddressRepository::class),
            $this->createStub(OrderAddressRepository::class),
            $this->createStub(ScopeConfigInterface::class),
            $this->createStub(SearchCriteriaBuilder::class),
            $this->createStub(CollectionFactory::class),
            $this->createStub(PriceLevel::class),
            $this->createStub(IsImportableFlag::class),
            $logger
        );
    }
}
