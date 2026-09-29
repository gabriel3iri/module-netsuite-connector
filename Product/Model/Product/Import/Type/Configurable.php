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
 *
 */

// @codingStandardsIgnoreFile
namespace MageOS\NetSuiteConnector\Product\Model\Product\Import\Type;

/**
 * The constructor copies the signature of the Magento parent and breaks when core changes it.
 *
 * @SuppressWarnings(PHPMD)
 */
class Configurable extends \Magento\ConfigurableImportExport\Model\Import\Product\Type\Configurable
{
    public function __construct(
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $attrSetColFac,
        \Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory $prodAttrColFac,
        \Magento\Framework\App\ResourceConnection $resource,
        array $params,
        \Magento\Catalog\Model\ProductTypes\ConfigInterface $productTypesConfig,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $_productColFac,
        private readonly \MageOS\NetSuiteConnector\Product\Model\Product\Import\Type\Configurable\RedundantLinkCleaner $redundantLinkCleaner,
        private readonly \MageOS\NetSuiteConnector\Core\Model\Plugin\ImportExport\PluginState $state,
        ?\Magento\Framework\EntityManager\MetadataPool $metadataPool = null,
        ?\Magento\CatalogImportExport\Model\Import\Product\SkuStorage $skuStorage = null,
        ?\Magento\Eav\Model\ResourceModel\Entity\Attribute\Option\CollectionFactory $attributeOptionCollectionFactory = null
    ) {
        parent::__construct(
            $attrSetColFac,
            $prodAttrColFac,
            $resource,
            $params,
            $productTypesConfig,
            $resourceHelper,
            $_productColFac,
            $metadataPool,
            $skuStorage,
            $attributeOptionCollectionFactory
        );
    }

    protected function _insertData()
    {
        parent::_insertData();

        if (!$this->state->isRunning()) {
            return;
        }

        $this->redundantLinkCleaner->clean($this->_superAttributesData);
    }
}
