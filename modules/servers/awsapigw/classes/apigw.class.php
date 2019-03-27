<?php

namespace WHMCS\Module\AwsApiGateway;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

if (!class_exists('\Aws\ApiGateway\ApiGatewayClient')) {
    throw new Exception("Required dependency: Aws\ApiGateway does not loaded");
}

use Aws\Result;
use Aws\Credentials\Credentials;
use Aws\ApiGateway\ApiGatewayClient;
use Aws\ApiGateway\Exception\ApiGatewayException;

class AwsApiGatewayClient
{
    protected $apigwClient;

    public function __construct($apiKey, $apiSecret, $region = 'us-east-1')
    {
        $credentials = new Credentials($apiKey, $apiSecret);

        $this->apigwClient = new ApiGatewayClient([
            'version'       => 'latest',
            'region'        => $region,
            'credentials'   => $credentials
        ]);
    }

    public function createKeyWithUsagePlans($name, array $usagePlans)
    {
        $createdApiKey = $this->createKey($name);

        if ($createdApiKey instanceof Result) {
            $keyId = $createdApiKey->get('id');
            $keyVal = $createdApiKey->get('value');
            $assocUsagePlans = [];

            if (!empty($keyId) && !empty($keyVal)) {
                if (!empty($usagePlans)) {

                    foreach ($usagePlans as $usagePlan) {
                        try {
                            $createdUsagePlanKey = $this->apigwClient->createUsagePlanKey([
                                'keyId'         => $keyId,
                                'usagePlanId'   => $usagePlan,
                                'keyType'       => 'API_KEY'
                            ]);

                            if ($createdUsagePlanKey instanceof Result) {
                                $assocUsagePlans[] = $usagePlan; // treat as successful request
                            }
                        } catch (ApiGatewayException $e) {
                            logModuleCall('awsapigw', __FUNCTION__, $usagePlan, $e->getMessage(), $e->getTraceAsString());
                        }
                    }
                }

                return (object)[
                    'keyId'             => $keyId,
                    'keyVal'            => $keyVal,
                    'assocUsagePlans'   => $assocUsagePlans,
                    'createdAt'         => $createdApiKey->get('createdDate'),
                    'updatedAt'         => $createdApiKey->get('lastUpdatedDate')
                ];
            }
        }

        throw new \Exception("Failed to create API key");
    }

    public function createKey($name)
    {
        return $this->apigwClient->createApiKey([
            'name'                  => $name,
            'enabled'               => true,
            'generateDistinctId'    => true
        ]);
    }

    public function getKey($id)
    {
        return $this->apigwClient->getApiKey([
            'apiKey'        => $id,
            'includeValue'  => true
        ]);
    }

    public function deleteKey($id)
    {
        try {
            $this->apigwClient->deleteApiKey([
                'apiKey'    => $id
            ]);

            return true;
        } catch (ApiGatewayException $e) {
            if (!empty($e)) {
                if ($e->getAwsErrorCode() === 'NotFoundException') {
                    return true; // suppress this error as key may be deleted externally
                }
            }
        }

        return false;
    }

    public function enableKey($id)
    {
        $status = $this->updateKey($id, [
            'enabled'   => 'true'
        ]);

        if ($status instanceof Result) {
            return $status->get('enabled') === true;
        }

        return false;
    }

    public function disableKey($id)
    {
        $status = $this->updateKey($id, [
            'enabled'   => 'false'
        ]);

        if ($status instanceof Result) {
            return $status->get('enabled') === false;
        }

        return false;
    }

    private function updateKey($id, $kv)
    {
        $patches = [];

        foreach ($kv as $k => $v) {
            $patches[] = [
                'op'    => 'replace',
                'path'  => "/{$k}",
                'value' => $v
            ];
        }

        try {
            return $this->apigwClient->updateApiKey([
                'apiKey'            => $id,
                'patchOperations'   => $patches
            ]);
        } catch (ApiGatewayException $e) {
            logModuleCall('awsapigw', __FUNCTION__, $id, $e->getMessage(), $e->getTraceAsString());
            return false;
        }
    }
}
