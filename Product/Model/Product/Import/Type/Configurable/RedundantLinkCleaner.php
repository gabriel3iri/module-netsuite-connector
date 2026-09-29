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

declare(strict_types=1);

namespace MageOS\NetSuiteConnector\Product\Model\Product\Import\Type\Configurable;

use Magento\Framework\DB\Adapter\AdapterInterface;

/**
 * Deletes the super attributes, super links and relations that the latest configurable product import no longer lists.
 */
class RedundantLinkCleaner
{
    private ?array $superAttributesData = null;
    private ?AdapterInterface $connection = null;

    public function __construct(
        private readonly \Magento\Framework\App\ResourceConnection $resource
    ) {
    }

    public function clean(?array $superAttributesData): void
    {
        if (null === $superAttributesData) {
            return;
        }

        $this->superAttributesData = $superAttributesData;
        $this->connection = $this->resource->getConnection();
        $this->removeAttributes();
        $this->removeLinks();
    }

    private function removeAttributes()
    {
        $removeAttributes = [];
        $mainTable = $this->resource->getTableName('catalog_product_super_attribute');
        foreach ($this->superAttributesData['attributes'] as $productId => $attributesData) {
            foreach ($attributesData as $attrId => $row) {
                $row['product_id'] = $productId;
                $row['attribute_id'] = $attrId;
                $mainData[] = $row;
            }
            $removeAttributes[] = $this->connection->quoteInto('(product_id=?', $productId) . ' AND ' .
                $this->connection->quoteInto('attribute_id NOT IN(?))', array_keys($attributesData));
        }
        if ($removeAttributes) {
            $this->connection->delete($mainTable, implode(' OR ', $removeAttributes));
        }
    }

    private function removeLinks()
    {
        $linkTable = $this->resource->getTableName('catalog_product_super_link');
        if ($this->superAttributesData['super_link']) {
            $superLinks = $this->superAttributesData['super_link'];

            $links = [];
            foreach ($superLinks as $entry) {
                $links[$entry['parent_id']][] = $entry['product_id'];
            }

            $toDelete = [];
            foreach ($links as $parent_id => $products) {
                $toDelete[] = $this->connection->quoteInto('(parent_id=?', $parent_id) . ' AND ' .
                    $this->connection->quoteInto('product_id NOT IN(?))', $products);
            }

            if ($toDelete) {
                $this->connection->delete($linkTable, implode(' OR ', $toDelete));
            }
        }

        if (!empty($this->superAttributesData['relation'])) {
            $relationTable = $this->resource->getTableName('catalog_product_relation');
            $relations = [];
            foreach ($this->superAttributesData['relation'] as $entry) {
                $relations[$entry['parent_id']][] = $entry['child_id'];
            }

            $toDeleteRelations = [];
            foreach ($relations as $parent_id => $children) {
                $toDeleteRelations[] = $this->connection->quoteInto('(parent_id=?', $parent_id) . ' AND ' .
                    $this->connection->quoteInto('child_id NOT IN(?))', $children);
            }

            $this->connection->delete($relationTable, implode(' OR ', $toDeleteRelations));
        }
    }
}
