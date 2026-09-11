<?php
namespace MageOS\NetSuiteConnector\Controller\Adminhtml\System\Config\Connection;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use NetSuite\Classes\GetServerTimeRequest;
use MageOS\NetSuiteConnector\Core\Model\Config\ConnectorConfig;

class Validate extends \Magento\Backend\App\Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'MageOS_NetSuiteConnector::config_netsuite';

    /**
     * Magento's own obscured field renderer masks a set value with one or more asterisks
     * (Magento\Framework\Data\Form\Element\Obscure) and its backend model (Encrypted::beforeSave())
     * treats the same pattern as "unchanged". A submitted secret matching it is not a real secret.
     */
    private const OBSCURED_VALUE_PATTERN = '/^\*+$/';

    /**
     * Maps the "group" the Test Connection button was rendered in (the last segment of the field's
     * system.xml path) to the run mode ConnectorConfig::getConsumerSecret()/getTokenSecret() expect.
     */
    private const GROUP_RUN_MODES = [
        'general' => 'default',
        'connection_import' => 'import',
        'connection_export' => 'export',
        'connection_stock' => 'stock',
    ];

    /** @var \Magento\Framework\Controller\Result\JsonFactory */
    protected $_jsonFactory;

    /** @var ScopeConfigInterface  */
    protected $scopeConfig;
    /**
     * @var \MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management
     */
    private $serviceManagement;

    /**
     * Validate constructor.
     * @param \Magento\Backend\App\Action\Context $context
     * @param \MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management $serviceManagement
     * @param JsonFactory $jsonFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param ConnectorConfig $connectorConfig
     */
    public function __construct(
        \Magento\Backend\App\Action\Context  $context,
        \MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management $serviceManagement,
        \Magento\Framework\Controller\Result\JsonFactory $jsonFactory,
        ScopeConfigInterface $scopeConfig,
        private readonly ConnectorConfig $connectorConfig
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->_jsonFactory = $jsonFactory;
        $this->serviceManagement = $serviceManagement;
        parent::__construct($context);
    }

    public function execute()
    {
        $connectionData = [];
        $connectionData['host'] = $this->getRequest()->getParam('host');
        $connectionData['endpoint'] = $this->scopeConfig->getValue(ConnectorConfig::PATH_ENDPOINT);
        $connectionData['account_id'] = $this->getRequest()->getParam('account_id');
        $connectionData['consumer_key'] = $this->getRequest()->getParam('consumer_key');
        $connectionData['consumer_secret'] = $this->getRequest()->getParam('consumer_secret');
        $connectionData['token_id'] = $this->getRequest()->getParam('token_id');
        $connectionData['token_secret'] = $this->getRequest()->getParam('token_secret');

        $runMode = self::GROUP_RUN_MODES[(string)$this->getRequest()->getParam('group')] ?? 'default';
        if (preg_match(self::OBSCURED_VALUE_PATTERN, (string)$connectionData['consumer_secret'])) {
            $connectionData['consumer_secret'] = $this->connectorConfig->getConsumerSecret($runMode);
        }
        if (preg_match(self::OBSCURED_VALUE_PATTERN, (string)$connectionData['token_secret'])) {
            $connectionData['token_secret'] = $this->connectorConfig->getTokenSecret($runMode);
        }

        $result = ['status' => 'success'];
        try {
            $netsuiteService = $this->serviceManagement->get($connectionData);
            $netsuiteService->setSearchPreferences(false, 1);
            $getServerTimeRequest = new GetServerTimeRequest();

            $response = $netsuiteService->getServerTime($getServerTimeRequest);
            if ($response === null) {
                $result['status'] = 'error';
                $result['message'] = 'Cannot connect';
            }
        } catch (\Exception $ex) {
            $result['status'] = 'error';
            $result['message'] = $ex->getMessage();
        }

        $jsonResult = $this->_jsonFactory->create();
        return $jsonResult->setData($result);
    }
}
