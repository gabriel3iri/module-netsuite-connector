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

namespace MageOS\NetSuiteConnector\Test\Integration\Core\Helper;

use PHPUnit\Framework\Assert;

/**
 * Compares a live NetSuite request record against a recorded snapshot.
 *
 * A snapshot is the serialize() output of the SDK request object, one
 * file per case, under a domain's Test/Integration/<Domain>/_files_ns_request/
 * directory. When the snapshot file is missing, the actual payload is
 * written to a directory under the system temp directory so it can be
 * reviewed and installed at the snapshot path.
 */
class RequestSnapshot
{
    private const OUTPUT_DIRECTORY_NAME = 'mageos-netsuite-request-snapshots';

    /**
     * Directory that receives the actual payload of a missing snapshot
     */
    public function getOutputDirectory(): string
    {
        return rtrim(sys_get_temp_dir(), '/') . '/' . self::OUTPUT_DIRECTORY_NAME;
    }

    /**
     * @param string[] $unsetFields
     */
    public function assertRecordMatches(string $snapshotFile, object $actualRequest, array $unsetFields): void
    {
        if (!file_exists($snapshotFile)) {
            $outputDirectory = $this->getOutputDirectory();
            if (!is_dir($outputDirectory)) {
                mkdir($outputDirectory, 0777, true);
            }
            $writtenFile = $outputDirectory . '/' . basename($snapshotFile);
            file_put_contents($writtenFile, serialize($actualRequest));
            Assert::fail(
                "Snapshot \"$snapshotFile\" is missing. Wrote the actual payload to \"$writtenFile\". "
                . 'Review it against the test assertions, then install it at the snapshot path.'
            );
        }

        $expectedRequest = unserialize((string)file_get_contents($snapshotFile));
        foreach ($unsetFields as $field) {
            unset($expectedRequest->record->$field, $actualRequest->record->$field);
        }

        Assert::assertEquals($expectedRequest->record, $actualRequest->record);
    }
}
