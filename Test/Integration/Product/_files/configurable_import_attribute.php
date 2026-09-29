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
 */

declare(strict_types=1);

use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Catalog\Setup\CategorySetup;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();
$installer = $objectManager->create(CategorySetup::class);
$attribute = $objectManager->create(Attribute::class);

if (!$attribute->loadByCode('catalog_product', 'nsc_cfg_color')->getId()) {
    $attribute->setData(
        [
            'attribute_code' => 'nsc_cfg_color',
            'entity_type_id' => $installer->getEntityTypeId('catalog_product'),
            'is_user_defined' => 1,
            'frontend_input' => 'select',
            'is_unique' => 0,
            'is_required' => 0,
            'is_global' => Attribute::SCOPE_GLOBAL,
            'frontend_label' => ['NSC Cfg Color'],
            'backend_type' => 'int',
            'option' => [
                'value' => [
                    'red' => ['Red'],
                    'blue' => ['Blue'],
                    'green' => ['Green'],
                    'black' => ['Black'],
                ],
                'order' => ['red' => 1, 'blue' => 2, 'green' => 3, 'black' => 4],
            ],
        ]
    );
    $attribute->save();
}

$installer->addAttributeToGroup('catalog_product', 'Default', 'General', $attribute->getId());
