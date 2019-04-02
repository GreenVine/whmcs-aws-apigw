<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Module\AwsApiGateway;

$serviceManager = null;

function getServiceManager(array &$params) {
    if (!$serviceManager instanceof AwsApiGateway\ServiceManager) {
        AwsApiGateway\SchemaManager::createTable(); // create schema if does not exist
        $serviceManager = new AwsApiGateway\ServiceManager($params);
    }

    return $serviceManager;
}
