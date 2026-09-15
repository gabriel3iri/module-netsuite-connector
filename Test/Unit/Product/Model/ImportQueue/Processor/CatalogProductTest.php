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
namespace MageOS\NetSuiteConnector\Test\Unit\Product\Model\ImportQueue\Processor;

use Magento\Framework\App\Cache\StateInterface;
use Magento\Framework\App\Cache\Type\FrontendPool;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Event\Manager;
use Magento\Framework\Indexer\CacheContext;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use MageOS\NetSuiteConnector\Core\Model\FlatIndexState;
use MageOS\NetSuiteConnector\Core\Model\ImportRowList;
use MageOS\NetSuiteConnector\Core\Model\Importer;
use MageOS\NetSuiteConnector\Core\Model\Logger\Logger;
use MageOS\NetSuiteConnector\Product\Model\ImportQueue\Processor\CatalogProduct;
use MageOS\NetSuiteConnector\Product\Model\Product\Import\UrlCollisionValidator;
use MageOS\NetSuiteConnector\Product\Model\ResourceModel\Repository;
use PHPUnit\Framework\TestCase;

/**
 * reindexAndCleanCache() consumes $affectedIds once per cron batch. Without a reset, the
 * accumulator kept every id ever seen in the run, so the second batch reindexed the first
 * batch's ids again on top of its own.
 */
class CatalogProductTest extends TestCase
{
    public function testSecondBatchReindexesOnlyItsOwnIds(): void
    {
        $capturedIds = [];
        $catalogProduct = $this->createCatalogProduct($capturedIds);

        $this->setAffectedIds($catalogProduct, [10, 20]);
        $catalogProduct->reindexAndCleanCache();

        $this->assertSame(
            [],
            $this->getAffectedIds($catalogProduct),
            'The accumulator must be empty once a batch has been reindexed.'
        );

        $capturedIds = [];
        $this->setAffectedIds($catalogProduct, [30]);
        $catalogProduct->reindexAndCleanCache();

        $idsSeenInSecondBatch = [];
        foreach ($capturedIds as $ids) {
            foreach ($ids as $id) {
                $idsSeenInSecondBatch[$id] = true;
            }
        }
        $idsSeenInSecondBatch = array_keys($idsSeenInSecondBatch);
        sort($idsSeenInSecondBatch);

        $this->assertSame(
            [30],
            $idsSeenInSecondBatch,
            'The second batch must reindex only its own product ids, not the ids the first batch already reindexed.'
        );
    }

    /**
     * @param array $capturedIds Filled with the $ids argument of every reindexList() call.
     */
    private function createCatalogProduct(array &$capturedIds): CatalogProduct
    {
        $select = $this->createStub(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('joinInner')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('getTableName')->willReturnArgument(0);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturn([]);

        $resourceConnection = $this->createStub(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($connection);

        $indexer = $this->createStub(IndexerInterface::class);
        $indexer->method('reindexList')->willReturnCallback(
            function (array $ids) use (&$capturedIds): void {
                $capturedIds[] = $ids;
            }
        );

        $indexerRegistry = $this->createStub(IndexerRegistry::class);
        $indexerRegistry->method('get')->willReturn($indexer);

        $cacheState = $this->createStub(StateInterface::class);
        $cacheState->method('isEnabled')->willReturn(false);

        return new CatalogProduct(
            $this->createStub(UrlCollisionValidator::class),
            $this->createStub(Repository::class),
            $resourceConnection,
            $this->createStub(CacheInterface::class),
            $cacheState,
            $this->createStub(FrontendPool::class),
            $this->createStub(CacheContext::class),
            $indexerRegistry,
            $this->createStub(Manager::class),
            $this->createStub(FlatIndexState::class),
            $this->createStub(ImportRowList::class),
            $this->createStub(Importer::class),
            $this->createStub(Logger::class)
        );
    }

    private function setAffectedIds(CatalogProduct $catalogProduct, array $ids): void
    {
        $property = new \ReflectionProperty(CatalogProduct::class, 'affectedIds');
        $property->setValue($catalogProduct, $ids);
    }

    private function getAffectedIds(CatalogProduct $catalogProduct): array
    {
        $property = new \ReflectionProperty(CatalogProduct::class, 'affectedIds');
        return $property->getValue($catalogProduct);
    }
}
