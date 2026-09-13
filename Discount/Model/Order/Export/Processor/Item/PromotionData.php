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

namespace MageOS\NetSuiteConnector\Discount\Model\Order\Export\Processor\Item;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\SalesRule\Api\Data\RuleInterface;
use NetSuite\Classes\CustomFieldList;
use NetSuite\Classes\DoubleCustomFieldRef;
use NetSuite\Classes\SalesOrder;
use NetSuite\Classes\SalesOrderItem;
use NetSuite\Classes\StringCustomFieldRef;
use MageOS\NetSuiteConnector\Core\Exception\MessageProcessor;
use MageOS\NetSuiteConnector\Order\Model\Export\OrderItemProcessorInterface;

/**
 * A failure here is logged and never fails the order export.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class PromotionData implements OrderItemProcessorInterface
{
    private const PROMOTION_AMOUNT = 'custcol_rw_cc_promotion_amount';
    private const PROMOTION_RULES = 'custcol_rw_cc_promotion_rules';

    private array $rulesCache = [];

    public function __construct(
        private readonly \Magento\SalesRule\Api\RuleRepositoryInterface $ruleRepository,
        private readonly \MageOS\NetSuiteConnector\Discount\Model\Config\DiscountConfig $discountConfig,
        private readonly \MageOS\NetSuiteConnector\Core\Model\Logger\Logger $logger
    ) {
    }

    public function processItem(
        SalesOrder $netsuiteOrder,
        SalesOrderItem $netsuiteItem,
        OrderItemInterface $magentoItem,
        ProductInterface $product,
        OrderInterface $magentoOrder
    ): void {
        if (!$this->discountConfig->getAddPromotionData()) {
            return;
        }

        try {
            $promotionData = $this->getPromotionData($magentoItem);
            if (!$promotionData) {
                return;
            }

            [$discount, $rules] = $promotionData;
            if ($discount) {
                $this->setPromotionAmount($netsuiteItem, (float)$discount);
                if ($rules) {
                    $this->setPromotionRules($netsuiteItem, explode(',', $rules));
                }
            }
        } catch (\Throwable $e) {
            $this->logger->error(MessageProcessor::getMessagesAsString($e));
        }
    }

    /**
     * @return array{0: float|string, 1: string|null}|null discount amount and applied rule ids of the item or its parent
     */
    private function getPromotionData(OrderItemInterface $magentoOrderItem): ?array
    {
        if ($magentoOrderItem->getDiscountAmount() > 0.001) {
            return [
                $magentoOrderItem->getDiscountAmount(),
                $magentoOrderItem->getAppliedRuleIds()
            ];
        }
        if ($magentoOrderItem->getParentItem() && $magentoOrderItem->getParentItem()->getDiscountAmount() > 0.001) {
            return [
                $magentoOrderItem->getParentItem()->getDiscountAmount(),
                $magentoOrderItem->getParentItem()->getAppliedRuleIds()
            ];
        }

        return null;
    }

    private function setPromotionRules(SalesOrderItem $netSuiteOrderItem, array $ruleIds)
    {
        $ruleNames = [];
        foreach ($ruleIds as $ruleId) {
            $rule = $this->getRule((int)$ruleId);
            if ($rule) {
                $ruleNames[] = $rule->getName();
            }
        }

        if (empty($ruleNames)) {
            return;
        }

        $customFieldList = $netSuiteOrderItem->customFieldList ?? new CustomFieldList();
        $customFieldList->customField = $customFieldList->customField ?? [];

        $customField = new StringCustomFieldRef();
        $customField->value = implode(',', $ruleNames);
        $customField->scriptId = self::PROMOTION_RULES;
        $customFieldList->customField[] = $customField;

        $netSuiteOrderItem->customFieldList = $customFieldList;
    }

    private function getRule(int $ruleId): ?RuleInterface
    {
        if (isset($this->rulesCache[$ruleId])) {
            return $this->rulesCache[$ruleId];
        }
        try {
            $this->rulesCache[$ruleId] = $this->ruleRepository->getById($ruleId);
        } catch (NoSuchEntityException) {
            return null;
        }

        return $this->rulesCache[$ruleId];
    }

    private function setPromotionAmount(SalesOrderItem $netSuiteOrderItem, float $amount)
    {
        $customFieldList = $netSuiteOrderItem->customFieldList ?? new CustomFieldList();
        $customFieldList->customField = $customFieldList->customField ?? [];

        $customField = new DoubleCustomFieldRef();
        $customField->value = -abs($amount);
        $customField->scriptId = self::PROMOTION_AMOUNT;
        $customFieldList->customField[] = $customField;

        $netSuiteOrderItem->customFieldList = $customFieldList;
    }
}
