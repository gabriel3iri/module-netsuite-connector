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

namespace MageOS\NetSuiteConnector\Core\Model\Pipeline;

class ProcessorSorter
{
    /**
     * @param array<string, array{processor?: object, sortOrder?: int, disabled?: bool}> $items
     * @return object[]
     */
    public function sort(array $items, string $processorInterface): array
    {
        $enabled = [];
        foreach ($items as $name => $item) {
            if (!empty($item['disabled'])) {
                continue;
            }
            if (!array_key_exists('processor', $item)) {
                throw new \InvalidArgumentException("Processor list item \"$name\" is missing a processor.");
            }
            if (!($item['processor'] instanceof $processorInterface)) {
                throw new \InvalidArgumentException(
                    "Processor list item \"$name\" does not implement $processorInterface."
                );
            }
            if (!array_key_exists('sortOrder', $item)) {
                throw new \InvalidArgumentException("Processor list item \"$name\" is missing a sortOrder.");
            }
            $enabled[] = $item;
        }

        usort($enabled, static fn (array $a, array $b): int => (int)$a['sortOrder'] <=> (int)$b['sortOrder']);

        return array_column($enabled, 'processor');
    }
}
