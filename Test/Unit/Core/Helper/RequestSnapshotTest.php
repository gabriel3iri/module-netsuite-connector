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

namespace MageOS\NetSuiteConnector\Test\Unit\Core\Helper;

use MageOS\NetSuiteConnector\Test\Integration\Core\Helper\RequestSnapshot;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

/**
 * A minimal stand-in for an SDK request object: a container with one
 * public "record" property, the shape RequestSnapshot compares.
 */
class RequestSnapshotTestFixtureRequest
{
    public $record;
}

class RequestSnapshotTest extends TestCase
{
    private RequestSnapshot $snapshot;

    /**
     * @var string[]
     */
    private $fixtureFilesToClean = [];

    protected function setUp(): void
    {
        $this->snapshot = new RequestSnapshot();
    }

    protected function tearDown(): void
    {
        foreach ($this->fixtureFilesToClean as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        $this->fixtureFilesToClean = [];
    }

    /**
     * A missing snapshot writes the actual payload to the output directory and
     * fails with that path, so the payload can be reviewed and installed.
     */
    public function testItWritesAMissingSnapshotToTheOutputDirectoryAndFailsWithThatPath(): void
    {
        $actual = $this->makeRequest(['foo' => 'bar']);
        $missingSnapshotFile = sys_get_temp_dir() . '/mageos-netsuite-connector-tests/does-not-exist-fixture';
        $expectedWrittenFile = $this->snapshot->getOutputDirectory() . '/does-not-exist-fixture';
        $this->fixtureFilesToClean[] = $expectedWrittenFile;

        try {
            $this->snapshot->assertRecordMatches($missingSnapshotFile, $actual, []);
            $this->fail('Expected an assertion failure for a missing snapshot.');
        } catch (AssertionFailedError $failure) {
            $this->assertStringContainsString($expectedWrittenFile, $failure->getMessage());
        }

        $this->assertFileExists($expectedWrittenFile);
        $writtenRequest = unserialize((string)file_get_contents($expectedWrittenFile));
        $this->assertEquals($actual, $writtenRequest);
    }

    public function testItPassesWhenTheSnapshotMatches(): void
    {
        $actual = $this->makeRequest(['foo' => 'bar', 'count' => 3]);
        $expected = $this->makeRequest(['foo' => 'bar', 'count' => 3]);
        $snapshotFile = $this->writeFixtureSnapshot($expected);

        $this->snapshot->assertRecordMatches($snapshotFile, $actual, []);

        $this->assertTrue(true);
    }

    public function testItFailsWhenTheRecordDiffers(): void
    {
        $actual = $this->makeRequest(['foo' => 'bar']);
        $expected = $this->makeRequest(['foo' => 'different']);
        $snapshotFile = $this->writeFixtureSnapshot($expected);

        $this->expectException(AssertionFailedError::class);

        $this->snapshot->assertRecordMatches($snapshotFile, $actual, []);
    }

    /**
     * Fields named in $unsetFields are ignored on both the snapshot and
     * the actual request before the comparison runs.
     */
    public function testItIgnoresListedUnsetFieldsOnBothSides(): void
    {
        $actual = $this->makeRequest(['foo' => 'bar', 'volatile' => 'actual-value']);
        $expected = $this->makeRequest(['foo' => 'bar', 'volatile' => 'expected-value']);
        $snapshotFile = $this->writeFixtureSnapshot($expected);

        $this->snapshot->assertRecordMatches($snapshotFile, $actual, ['volatile']);

        $this->assertTrue(true);
    }

    private function makeRequest(array $fields): RequestSnapshotTestFixtureRequest
    {
        $request = new RequestSnapshotTestFixtureRequest();
        $request->record = new \stdClass();
        foreach ($fields as $name => $value) {
            $request->record->$name = $value;
        }
        return $request;
    }

    private function writeFixtureSnapshot(RequestSnapshotTestFixtureRequest $request): string
    {
        $file = tempnam(sys_get_temp_dir(), 'mageos_netsuite_request_snapshot_');
        file_put_contents($file, serialize($request));
        $this->fixtureFilesToClean[] = $file;
        return $file;
    }
}
