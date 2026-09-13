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

namespace MageOS\NetSuiteConnector\CustomerImport\Model\Customer\Export;

use NetSuite\Classes\BooleanCustomFieldRef;
use NetSuite\Classes\Customer;
use NetSuite\Classes\CustomFieldList;

class IsImportableFlag
{
    public function __construct(
        private readonly \MageOS\NetSuiteConnector\CustomerImport\Model\Config\CustomerImportConfig $customerImportConfig
    ) {
    }

    public function execute(Customer $netsuiteCustomer): void
    {
        $customFieldScriptId = $this->customerImportConfig->getIsImportableFieldId();
        if (empty($customFieldScriptId)) {
            return;
        }

        $customField = new BooleanCustomFieldRef();
        $customField->scriptId = $customFieldScriptId;
        $customField->value = true;
        if ($netsuiteCustomer->customFieldList === null) {
            $netsuiteCustomer->customFieldList = new CustomFieldList();
            $netsuiteCustomer->customFieldList->customField = [];
        }
        $netsuiteCustomer->customFieldList->customField[] = $customField;
    }
}
