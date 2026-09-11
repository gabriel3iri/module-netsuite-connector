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

namespace MageOS\NetSuiteConnector\Test\Integration\Core\Controller\Adminhtml\System\Config\Connection;

use Magento\Config\Model\Config as AdminConfig;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractBackendController;
use MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management as ServiceManagement;
use MageOS\NetSuiteConnector\Test\Integration\Core\Helper\NetSuiteServiceFaker;

/**
 * Validate::execute() builds the NetSuite client straight from request params. Every dispatch
 * here that expects a network attempt supplies a host that fails fast (a closed local port) so
 * the failure happens inside the try/catch, where the controller already handles it.
 *
 * The controller is gated by its own ADMIN_RESOURCE, MageOS_NetSuiteConnector::config_netsuite,
 * the same resource the section carries in system.xml.
 *
 * @magentoAppArea adminhtml
 */
class ValidateTest extends AbstractBackendController
{
    /**
     * @var string
     */
    protected $resource = 'MageOS_NetSuiteConnector::config_netsuite';

    /**
     * @var string
     */
    protected $uri = 'backend/netsuite/system_config_connection/validate';

    /**
     * @var string
     */
    protected $httpMethod = HttpRequest::METHOD_POST;

    public function testAclHasAccess(): void
    {
        $this->setSafeConnectionParams();

        parent::testAclHasAccess();
    }

    public function testAclNoAccess(): void
    {
        $this->setSafeConnectionParams();

        parent::testAclNoAccess();
    }

    public function testValidateReturnsJson(): void
    {
        $this->setSafeConnectionParams();

        $this->dispatch($this->uri);

        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
        $data = json_decode((string)$this->getResponse()->getBody(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
    }

    /**
     * Before Fix 6, the NetSuite client was built outside the try/catch. With no connection
     * fields at all, the SDK's own required-field check throws before the controller's try/catch
     * is reached, and the dispatch never produces a JSON response.
     */
    public function testValidateWithEmptyFieldsReturnsAJsonErrorInsteadOfFailingTheRequest(): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setPostValue([
            'host' => '',
            'account_id' => '',
            'consumer_key' => '',
            'consumer_secret' => '',
            'token_id' => '',
            'token_secret' => '',
        ]);

        $this->dispatch($this->uri);

        $this->assertEquals(200, $this->getResponse()->getHttpResponseCode());
        $data = json_decode((string)$this->getResponse()->getBody(), true);
        $this->assertIsArray($data);
        $this->assertSame('error', $data['status'] ?? null);
    }

    /**
     * When a group's own consumer secret and token secret were never changed, the admin form
     * posts back the obscured placeholder rather than the real value. Validate must resolve the
     * stored, decrypted secret of the group the Test Connection button belongs to, not the
     * literal placeholder text.
     *
     * @magentoDbIsolation enabled
     */
    public function testValidateReplacesAnObscuredPlaceholderWithTheStoredGeneralSecret(): void
    {
        $this->saveObscuredValue('general', [
            'consumer_secret' => 'general-consumer-secret',
            'token_secret' => 'general-token-secret',
        ]);

        $captured = $this->dispatchWithCapturedConnectionData([
            'group' => 'general',
            'consumer_secret' => '******',
            'token_secret' => '******',
        ]);

        $this->assertSame('general-consumer-secret', $captured['consumer_secret']);
        $this->assertSame('general-token-secret', $captured['token_secret']);
    }

    /**
     * A dedicated import connection has its own stored secret, distinct from the general one.
     * Validate must resolve the import group's own secret, not fall back to the general group.
     *
     * @magentoDbIsolation enabled
     */
    public function testValidateReplacesAnObscuredPlaceholderWithTheStoredImportGroupSecret(): void
    {
        $this->saveObscuredValue('general', [
            'consumer_secret' => 'general-consumer-secret',
            'token_secret' => 'general-token-secret',
        ]);
        $this->saveObscuredValue('connection_import', [
            'same' => '0',
            'consumer_secret' => 'import-consumer-secret',
            'token_secret' => 'import-token-secret',
        ]);

        $captured = $this->dispatchWithCapturedConnectionData([
            'group' => 'connection_import',
            'consumer_secret' => '******',
            'token_secret' => '******',
        ]);

        $this->assertSame('import-consumer-secret', $captured['consumer_secret']);
        $this->assertSame('import-token-secret', $captured['token_secret']);
    }

    /**
     * A secret field that was actually changed in the form must reach the client verbatim, not
     * be replaced with the stored value.
     *
     * @magentoDbIsolation enabled
     */
    public function testValidateKeepsAChangedSecretAsSubmitted(): void
    {
        $this->saveObscuredValue('general', [
            'consumer_secret' => 'general-consumer-secret',
            'token_secret' => 'general-token-secret',
        ]);

        $captured = $this->dispatchWithCapturedConnectionData([
            'group' => 'general',
            'consumer_secret' => 'freshly-typed-secret',
            'token_secret' => '******',
        ]);

        $this->assertSame('freshly-typed-secret', $captured['consumer_secret']);
        $this->assertSame('general-token-secret', $captured['token_secret']);
    }

    private function setSafeConnectionParams(): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setPostValue([
            'host' => 'http://127.0.0.1:1',
            'account_id' => 'TSTACC',
            'consumer_key' => 'consumer-key',
            'consumer_secret' => 'consumer-secret',
            'token_id' => 'token-id',
            'token_secret' => 'token-secret',
        ]);
    }

    /**
     * Save one or more field values into the mageos_netsuite/<group> group through the real
     * admin config save path, so obscured fields are encrypted exactly like a merchant save.
     *
     * @param string $group
     * @param array<string, string> $fields
     */
    private function saveObscuredValue(string $group, array $fields): void
    {
        $fieldsData = [];
        foreach ($fields as $fieldId => $value) {
            $fieldsData[$fieldId] = ['value' => $value];
        }

        $objectManager = Bootstrap::getObjectManager();
        /** @var AdminConfig $config */
        $config = $objectManager->create(AdminConfig::class);
        $config->setSection('mageos_netsuite');
        $config->setGroups([
            $group => ['fields' => $fieldsData],
        ]);
        $config->save();
    }

    /**
     * Dispatch the controller with a mocked Management so that the NetSuite SDK is never
     * touched, and return the connection data array that reached Management::get().
     *
     * @param array<string, string> $postValues
     * @return array<string, mixed>
     */
    private function dispatchWithCapturedConnectionData(array $postValues): array
    {
        $objectManager = Bootstrap::getObjectManager();

        $faker = new NetSuiteServiceFaker(__DIR__);

        $captured = null;
        $serviceManagement = $this->getMockBuilder(ServiceManagement::class)
            ->onlyMethods(['get'])
            ->disableOriginalConstructor()
            ->getMock();
        $serviceManagement->method('get')->willReturnCallback(
            function (?array $connectionData = null) use (&$captured, $faker) {
                $captured = $connectionData;
                return $faker;
            }
        );

        $objectManager->configure([ServiceManagement::class => ['shared' => true]]);
        $objectManager->addSharedInstance($serviceManagement, ServiceManagement::class);

        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $this->getRequest()->setPostValue($postValues + [
            'host' => 'http://127.0.0.1:1',
            'account_id' => 'TSTACC',
            'consumer_key' => 'consumer-key',
            'token_id' => 'token-id',
        ]);

        $this->dispatch($this->uri);

        $this->assertIsArray($captured, 'Management::get() was never called.');

        return $captured;
    }
}
