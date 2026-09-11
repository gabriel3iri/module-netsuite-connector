<?php

use Magento\TestFramework\Helper\Bootstrap;
use MageOS\NetSuiteConnector\Test\Integration\Core\Helper\NetSuiteFixtureCreator;

$objectManager = Bootstrap::getObjectManager();
$fixtureCreator = $objectManager->get(NetSuiteFixtureCreator::class)->createAll();
//die('444444444444444');
