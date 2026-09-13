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

namespace MageOS\NetSuiteConnector\Order\Model\Export\Processor;

use Magento\Sales\Api\Data\OrderInterface;
use NetSuite\Classes\CustomFieldList;
use NetSuite\Classes\GetRequest;
use NetSuite\Classes\ListOrRecordRef;
use NetSuite\Classes\Record;
use NetSuite\Classes\RecordRef;
use NetSuite\Classes\RecordType;
use NetSuite\Classes\SalesOrder;
use NetSuite\Classes\SelectCustomFieldRef;
use NetSuite\Classes\StringCustomFieldRef;
use MageOS\NetSuiteConnector\Order\Model\CustomFields as CustomFieldTypes;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderProcessorInterface;

class CustomFields implements OrderProcessorInterface
{
    public function __construct(
        private readonly \MageOS\NetSuiteConnector\Core\Model\NetSuite\Service\Management $serviceManagement,
        private readonly \MageOS\NetSuiteConnector\Order\Model\Config\SalesConfig $salesConfig
    ) {
    }

    public function process(SalesOrder $netsuiteOrder, OrderInterface $magentoOrder): void
    {
        $this->addTypeStandard($netsuiteOrder, $magentoOrder);
        $this->addTypeCustom($netsuiteOrder, $magentoOrder);
    }

    private function canAddCustomFields(): bool
    {
        $customFieldsConfig = $this->getCustomFieldsConfig();
        if (is_array($customFieldsConfig) && count($customFieldsConfig)) {
            return true;
        }
        return false;
    }

    private function addTypeStandard(SalesOrder $netsuiteOrder, OrderInterface $magentoOrder)
    {
        if (!$this->canAddCustomFields()) {
            return;
        }
        $customFieldsConfig = $this->getCustomFieldsConfig();
        foreach ($customFieldsConfig as $customFieldsConfigItem) {
            switch ($customFieldsConfigItem['netsuite_field_type']) {
                case CustomFieldTypes::TYPE_STANDARD:
                    $netsuiteOrder->{$customFieldsConfigItem['netsuite_field_name']} =
                        $this->getCustomFieldValueFromMagentoData(
                            $magentoOrder,
                            $customFieldsConfigItem['value_type'],
                            $customFieldsConfigItem['value']
                        );
                    break;
                case CustomFieldTypes::TYPE_STANDARD_RECORD_REF:
                    $this->addStandardRecordRef(
                        $netsuiteOrder,
                        $magentoOrder,
                        $customFieldsConfigItem['netsuite_field_name'],
                        $customFieldsConfigItem['value_type'],
                        $customFieldsConfigItem['value']
                    );
                    break;
                default:
                    break;
            }
        }
    }

    private function addTypeCustom(SalesOrder $netsuiteOrder, OrderInterface $magentoOrder)
    {
        if (!$this->canAddCustomFields()) {
            return;
        }
        $customFields = [];
        $customFieldsConfig = $this->getCustomFieldsConfig();
        foreach ($customFieldsConfig as $customFieldsConfigItem) {
            switch ($customFieldsConfigItem['netsuite_field_type']) {
                case CustomFieldTypes::TYPE_LIST:
                    $customFields[] = $this->createListCustomField(
                        $magentoOrder,
                        $customFieldsConfigItem['netsuite_field_name'],
                        $customFieldsConfigItem['netsuite_list_internal_id'],
                        $customFieldsConfigItem['value_type'],
                        $customFieldsConfigItem['value']
                    );
                    break;
                case CustomFieldTypes::TYPE_SIMPLE:
                    $customFields[] = $this->createSimpleCustomField(
                        $magentoOrder,
                        $customFieldsConfigItem['netsuite_field_name'],
                        $customFieldsConfigItem['value_type'],
                        $customFieldsConfigItem['value']
                    );
                    break;
                default:
                    break;
            }
        }
        if ($netsuiteOrder->customFieldList === null) {
            $netsuiteOrder->customFieldList = new CustomFieldList();
            $netsuiteOrder->customFieldList->customField = [];
        }
        $netsuiteOrder->customFieldList->customField = array_merge(
            $netsuiteOrder->customFieldList->customField,
            $customFields
        );
    }

    private function addStandardRecordRef(
        SalesOrder $netsuiteOrder,
        OrderInterface $magentoOrder,
        $customFieldName,
        $customFieldValueType,
        $customFieldValue
    ) {
        $recordRefField = new RecordRef();
        $recordRefField->internalId = $this->getCustomFieldValueFromMagentoData(
            $magentoOrder,
            $customFieldValueType,
            $customFieldValue
        );
        $netsuiteOrder->{$customFieldName} = $recordRefField;
    }

    private function getCustomFieldsConfig()
    {
        return $this->salesConfig->getCustomFieldsMapping();
    }

    private function getCustomFieldValueFromMagentoData(
        OrderInterface $magentoOrder,
        $customFieldValueType,
        $customFieldValue
    ) {
        switch ($customFieldValueType) {
            case CustomFieldTypes::VALUE_TYPE_ORDER_ATTRIBUTE:
                return $magentoOrder->getData($customFieldValue);
            case CustomFieldTypes::VALUE_TYPE_FIXED:
            default:
                return $customFieldValue;
        }
    }

    private function createListCustomField(
        OrderInterface $magentoOrder,
        $customFieldName,
        $customFieldInternalId,
        $customFieldValueType,
        $customFieldValue
    ): SelectCustomFieldRef {
        $customField = new SelectCustomFieldRef();
        $customField->scriptId = $customFieldName;

        $customList = $this->loadCustomList($customFieldInternalId);

        $recordRef = new ListOrRecordRef();
        $recordRef->typeId = $customList->internalId;
        foreach ($customList->customValueList->customValue as $customListCustomValue) {
            $value = $this->getCustomFieldValueFromMagentoData($magentoOrder, $customFieldValueType, $customFieldValue);
            if (strtolower($value) == strtolower($customListCustomValue->value)) {
                $recordRef->internalId = $customListCustomValue->valueId;
                break;
            }
        }

        $customField->value = $recordRef;

        return $customField;
    }

    private function createSimpleCustomField(
        OrderInterface $magentoOrder,
        $customFieldName,
        $customFieldValueType,
        $customFieldValue
    ): StringCustomFieldRef {
        $customField = new StringCustomFieldRef();
        $customField->scriptId = $customFieldName;
        $customField->value = $this->getCustomFieldValueFromMagentoData(
            $magentoOrder,
            $customFieldValueType,
            $customFieldValue
        );

        return $customField;
    }

    private function loadCustomList($internalId): Record
    {
        $request = new GetRequest();
        $request->baseRef = new RecordRef();
        $request->baseRef->internalId = $internalId;
        $request->baseRef->type = RecordType::customList;

        $getResponse = $this->serviceManagement->get()->get($request);
        if (!$getResponse->readResponse->status->isSuccess) {
            throw new \RuntimeException(var_export($getResponse->readResponse->status->statusDetail, true));
        } else {
            return $getResponse->readResponse->record;
        }
    }
}
