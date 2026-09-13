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

namespace MageOS\NetSuiteConnector\Test\Unit\CustomerImport\Model\Customer\Export;

use MageOS\NetSuiteConnector\CustomerImport\Model\Config\CustomerImportConfig;
use MageOS\NetSuiteConnector\CustomerImport\Model\Customer\Export\IsImportableFlag;
use NetSuite\Classes\BooleanCustomFieldRef;
use NetSuite\Classes\CustomFieldList;
use NetSuite\Classes\Customer as NetsuiteCustomer;
use PHPUnit\Framework\TestCase;

/**
 * CustomerImportConfig resolves every getter through AbstractConfig::__call(), so PHPUnit cannot mock
 * one method of it in isolation. This double overrides only the one getter the class reads.
 */
class IsImportableFlagTestConfig extends CustomerImportConfig
{
    public function __construct(private readonly ?string $isImportableFieldId)
    {
    }

    public function getIsImportableFieldId()
    {
        return $this->isImportableFieldId;
    }
}

class IsImportableFlagTest extends TestCase
{
    public function testItAppendsTheFieldWhenConfigured(): void
    {
        $netsuiteCustomer = new NetsuiteCustomer();

        (new IsImportableFlag(new IsImportableFlagTestConfig('custentity_importable')))->execute($netsuiteCustomer);

        $this->assertCount(1, $netsuiteCustomer->customFieldList->customField);
        $customField = $netsuiteCustomer->customFieldList->customField[0];
        $this->assertInstanceOf(BooleanCustomFieldRef::class, $customField);
        $this->assertSame('custentity_importable', $customField->scriptId);
        $this->assertTrue($customField->value);
    }

    public function testItAppendsToAnExistingCustomFieldList(): void
    {
        $existingField = new BooleanCustomFieldRef();
        $existingField->scriptId = 'custentity_other';

        $netsuiteCustomer = new NetsuiteCustomer();
        $netsuiteCustomer->customFieldList = new CustomFieldList();
        $netsuiteCustomer->customFieldList->customField = [$existingField];

        (new IsImportableFlag(new IsImportableFlagTestConfig('custentity_importable')))->execute($netsuiteCustomer);

        $this->assertCount(2, $netsuiteCustomer->customFieldList->customField);
        $this->assertSame($existingField, $netsuiteCustomer->customFieldList->customField[0]);
    }

    public function testItDoesNothingWhenNotConfigured(): void
    {
        $netsuiteCustomer = new NetsuiteCustomer();

        (new IsImportableFlag(new IsImportableFlagTestConfig(null)))->execute($netsuiteCustomer);

        $this->assertNull($netsuiteCustomer->customFieldList);
    }
}
