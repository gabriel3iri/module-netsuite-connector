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

namespace MageOS\NetSuiteConnector\Test\Integration\Core\Model\Config;

use Magento\Config\Model\Config as AdminConfig;
use Magento\Framework\App\ResourceConnection;
use Magento\TestFramework\Helper\Bootstrap;
use MageOS\NetSuiteConnector\Core\Model\Config\ConnectorConfig;
use PHPUnit\Framework\TestCase;

/**
 * consumer_secret and token_secret are obscured fields with the Encrypted backend model. A save
 * through the real admin config model must store ciphertext, and ConnectorConfig must hand back
 * the original plain text, never the stored ciphertext.
 *
 * The admin config structure (obscure type, backend model) only merges in when the request runs
 * in the adminhtml area, exactly like a real merchant save.
 *
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class ConnectorConfigTest extends TestCase
{
    public function testASaveThroughTheAdminConfigModelStoresCiphertextAndConnectorConfigReturnsThePlainText(): void
    {
        $objectManager = Bootstrap::getObjectManager();

        /** @var AdminConfig $config */
        $config = $objectManager->create(AdminConfig::class);
        $config->setSection('mageos_netsuite');
        $config->setGroups([
            'general' => [
                'fields' => [
                    'consumer_secret' => ['value' => 'plain-text-consumer-secret'],
                    'token_secret' => ['value' => 'plain-text-token-secret'],
                ],
            ],
        ]);
        $config->save();

        $storedConsumerSecret = $this->readStoredValue('mageos_netsuite/general/consumer_secret');
        $storedTokenSecret = $this->readStoredValue('mageos_netsuite/general/token_secret');

        $this->assertNotSame('plain-text-consumer-secret', $storedConsumerSecret);
        $this->assertNotSame('plain-text-token-secret', $storedTokenSecret);
        $this->assertNotEmpty($storedConsumerSecret);
        $this->assertNotEmpty($storedTokenSecret);

        /** @var ConnectorConfig $connectorConfig */
        $connectorConfig = $objectManager->create(ConnectorConfig::class);

        $this->assertSame('plain-text-consumer-secret', $connectorConfig->getConsumerSecret('default'));
        $this->assertSame('plain-text-token-secret', $connectorConfig->getTokenSecret('default'));
    }

    /**
     * Read the raw stored value for a config path, bypassing any backend model or scope config
     * cache, so the assertion sees exactly what landed in the database.
     *
     * @param string $path
     * @return string|null
     */
    private function readStoredValue(string $path): ?string
    {
        $resource = Bootstrap::getObjectManager()->get(ResourceConnection::class);
        $connection = $resource->getConnection();
        $select = $connection->select()
            ->from($resource->getTableName('core_config_data'), 'value')
            ->where('path = ?', $path)
            ->where('scope = ?', 'default');

        $value = $connection->fetchOne($select);

        return $value === false ? null : (string)$value;
    }
}
