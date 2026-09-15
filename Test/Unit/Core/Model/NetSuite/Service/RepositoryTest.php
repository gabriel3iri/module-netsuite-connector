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

namespace MageOS\NetSuiteConnector\Test\Unit\Core\Model\NetSuite\Service;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use MageOS\NetSuiteConnector\Core\Model\Config\CacheConfig;
use MageOS\NetSuiteConnector\Core\Model\Config\ConfigurationResolver;
use MageOS\NetSuiteConnector\Core\Model\Config\ConfigurationResolverFactory;
use MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management;
use MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Repository;
use NetSuite\Classes\CustomList;
use NetSuite\Classes\CustomListCustomValue;
use NetSuite\Classes\CustomListCustomValueList;
use NetSuite\Classes\GetListResponse;
use NetSuite\Classes\ReadResponse;
use NetSuite\Classes\ReadResponseList;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * A new NetSuite custom list value is invisible to the hour-long persistent cache until it
 * expires. getListValue() read the cached list with no existence check on the item id, so a
 * newly added value raised an undefined-array-key error on every call instead of refetching.
 */
class RepositoryTest extends TestCase
{
    public function testValueMissingFromCachedListIsReturnedAfterRefetch(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn(json_encode(['100' => 'Red', '200' => 'Blue']));

        $savedPayload = null;
        $cache->method('save')->willReturnCallback(
            function ($data) use (&$savedPayload): bool {
                $savedPayload = json_decode($data, true);
                return true;
            }
        );

        $management = $this->createMock(Management::class);
        $management->expects($this->once())
            ->method('retryNetSuiteQuery')
            ->willReturn($this->buildGetListResponse(['100' => 'Red', '200' => 'Blue', '501' => 'New Color']));

        $repository = $this->buildRepository($cache, $management, $this->createStub(LoggerInterface::class));

        $this->assertSame('Red', $repository->getListValue('7', 100), 'the first read fills the in-memory list');
        $this->assertSame(
            'New Color',
            $repository->getListValue('7', 501),
            'a value missing from the in-memory list triggers a refetch'
        );
        $this->assertSame('New Color', $savedPayload['501'] ?? null);
    }

    public function testValueStillMissingAfterRefetchReturnsNullWithoutError(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn(json_encode(['100' => 'Red', '200' => 'Blue']));
        $cache->method('save')->willReturn(true);

        $management = $this->createMock(Management::class);
        $management->expects($this->once())
            ->method('retryNetSuiteQuery')
            ->willReturn($this->buildGetListResponse(['100' => 'Red', '200' => 'Blue']));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $repository = $this->buildRepository($cache, $management, $logger);

        $this->assertNull($repository->getListValue('7', 999));
    }

    public function testSecondMissOnSameListInSameRunDoesNotRefetchAgain(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturn(json_encode(['100' => 'Red', '200' => 'Blue']));
        $cache->method('save')->willReturn(true);

        $management = $this->createMock(Management::class);
        $management->expects($this->once())
            ->method('retryNetSuiteQuery')
            ->willReturn($this->buildGetListResponse(['100' => 'Red', '200' => 'Blue']));

        $repository = $this->buildRepository($cache, $management, $this->createStub(LoggerInterface::class));

        $this->assertNull($repository->getListValue('7', 999));
        $this->assertNull($repository->getListValue('7', 999));
    }

    private function buildRepository(
        CacheInterface $cache,
        Management $management,
        LoggerInterface $logger
    ): Repository {
        return new Repository($cache, $this->buildCacheConfig(), $management, $logger);
    }

    private function buildCacheConfig(): CacheConfig
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('3600');

        $serializer = $this->createStub(SerializerInterface::class);
        $factory = $this->createStub(ConfigurationResolverFactory::class);
        $factory->method('create')->willReturnCallback(
            static fn (array $data): ConfigurationResolver => new ConfigurationResolver(
                $scopeConfig,
                $serializer,
                $data['optionsMap'],
                $data['cacheEnabled']
            )
        );

        return new CacheConfig($factory);
    }

    /**
     * @param array<int|string, string> $values Map of valueId to value as NetSuite would return it
     */
    private function buildGetListResponse(array $values): GetListResponse
    {
        $customValueList = new CustomListCustomValueList();
        foreach ($values as $valueId => $value) {
            $customValue = new CustomListCustomValue();
            $customValue->valueId = (int)$valueId;
            $customValue->value = $value;
            $customValueList->customValue[] = $customValue;
        }

        $customList = new CustomList();
        $customList->customValueList = $customValueList;

        $readResponse = new ReadResponse();
        $readResponse->record = $customList;

        $readResponseList = new ReadResponseList();
        $readResponseList->readResponse = [$readResponse];

        $response = new GetListResponse();
        $response->readResponseList = $readResponseList;

        return $response;
    }
}
