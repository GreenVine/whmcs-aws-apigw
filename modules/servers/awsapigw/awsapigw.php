<?php
/**
 * WHMCS SDK Sample Provisioning Module
 *
 * Provisioning Modules, also referred to as Product or Server Modules, allow
 * you to create modules that allow for the provisioning and management of
 * products and services in WHMCS.
 *
 * This sample file demonstrates how a provisioning module for WHMCS should be
 * structured and exercises all supported functionality.
 *
 * Provisioning Modules are stored in the /modules/servers/ directory. The
 * module name you choose must be unique, and should be all lowercase,
 * containing only letters & numbers, always starting with a letter.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "awsapigw" and therefore all
 * functions begin "awsapigw_".
 *
 * If your module or third party API does not support a given function, you
 * should not define that function within your module. Only the _ConfigOptions
 * function is required.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/provisioning-modules/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license https://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/classes/schema.class.php';
require_once __DIR__ . '/classes/service.class.php';
require_once __DIR__ . '/classes/apigw.class.php';

require_once __DIR__ . '/includes/service.php';

use Aws\Result;
use WHMCS\Module\AwsApiGateway;

/**
 * Define module related meta data.
 *
 * Values returned here are used to determine module related abilities and
 * settings.
 *
 * @see https://developers.whmcs.com/provisioning-modules/meta-data-params/
 *
 * @return array
 */
function awsapigw_MetaData() {
    return [
        'DisplayName'    => 'AWS API Gateway',
        'APIVersion'     => '1.1', // Use API Version 1.1
        'RequiresServer' => false, // Set true if module requires a server to work
    ];
}

/**
 * Define product configuration options.
 *
 * The values you return here define the configuration options that are
 * presented to a user when configuring a product for use with the module. These
 * values are then made available in all module function calls with the key name
 * configoptionX - with X being the index number of the field from 1 to 24.
 *
 * You can specify up to 24 parameters, with field types:
 * * text
 * * password
 * * yesno
 * * dropdown
 * * radio
 * * textarea
 *
 * Examples of each and their possible configuration parameters are provided in
 * this sample function.
 *
 * @see https://developers.whmcs.com/provisioning-modules/config-options/
 *
 * @return array
 */
function awsapigw_ConfigOptions() {
    $basicFields = [
        'aws_key_id'     => [
            'FriendlyName' => 'AWS Key ID',
            'Type'         => 'text',
            'Size'         => '25',
            'Default'      => '',
            'Description'  => 'AWS Access Key ID',
            'SimpleMode'   => true,
        ],
        'aws_key_secret' => [
            'FriendlyName' => 'AWS Key Secret',
            'Type'         => 'password',
            'Size'         => '25',
            'Default'      => '',
            'Description'  => 'AWS Secret Access Key',
            'SimpleMode'   => true,
        ],
        'api_name_pfx'   => [
            'FriendlyName' => 'API Key Name Prefix',
            'Type'         => 'text',
            'Size'         => '25',
            'Default'      => 'whmcs_',
            'Description'  => 'Prefix added to the name of API key',
            'SimpleMode'   => true,
        ],
        'api_region'     => [
            'FriendlyName' => 'API Gateway Region',
            'Type'         => 'text',
            'Size'         => '25',
            'Default'      => 'us-east-1',
            'Description'  => 'Deployed region of API Gateway',
            'SimpleMode'   => true,
        ],
        'usage_plan_ids' => [
            'FriendlyName' => 'API Usage Plan IDs',
            'Type'         => 'textarea',
            'Rows'         => '2',
            'Cols'         => '15',
            'Default'      => '',
            'Description'  => 'List of usage plan IDs (separated by line breaks or comma)',
            'SimpleMode'   => true,
        ],
        'api_endpoint'   => [
            'FriendlyName' => 'API Endpoint',
            'Type'         => 'text',
            'Size'         => '25',
            'Default'      => '',
            'Description'  => 'API endpoint displayed to clients',
            'SimpleMode'   => true,
        ],
    ];

    $redisFields = [
        'api_key_cache'              => [
            'FriendlyName' => 'Cache API Key with Redis',
            'Type'         => 'yesno',
            'Size'         => '25',
            'Default'      => 'off',
            'Description'  => 'Use Redis for API key reverse lookup (optional)',
        ],
        'api_key_cache_persist_conn' => [
            'FriendlyName' => 'Redis Persistent Connection',
            'Type'         => 'yesno',
            'Size'         => '25',
            'Default'      => 'off',
            'Description'  => 'Prefer persistent connection when possible',
        ],
        'api_key_cache_host'         => [
            'FriendlyName' => 'Redis Host',
            'Type'         => 'text',
            'Size'         => '25',
            'Default'      => '127.0.0.1',
            'Description'  => 'Redis server IP or hostname',
        ],
        'api_key_cache_port'         => [
            'FriendlyName' => 'Redis Port',
            'Type'         => 'text',
            'Size'         => '25',
            'Default'      => '6379',
            'Description'  => 'Redis server port',
        ],
        'api_key_cache_dbindex'      => [
            'FriendlyName' => 'Redis Database',
            'Type'         => 'text',
            'Size'         => '25',
            'Default'      => '1',
            'Description'  => 'Redis database index',
        ],
        'api_key_cache_key_prefix'   => [
            'FriendlyName' => 'Redis Key Prefix',
            'Type'         => 'text',
            'Size'         => '25',
            'Default'      => 'awsapigw:',
            'Description'  => 'Prefix attached to each key',
        ],
        'api_key_cache_auth'         => [
            'FriendlyName' => 'Redis Password',
            'Type'         => 'text',
            'Size'         => '25',
            'Default'      => '',
            'Description'  => 'Redis AUTH password',
        ],
        'api_key_cache_timeout'      => [
            'FriendlyName' => 'Redis Timeout',
            'Type'         => 'text',
            'Size'         => '25',
            'Default'      => '3',
            'Description'  => 'Redis timeout (in sec)',
        ],
    ];

    if (class_exists('\Redis', true)) {
        $basicFields = array_merge($basicFields, $redisFields); // show Redis options to Redis-enabled PHP installation
    } else {
        $basicFields['api_key_cache_not_available'] = [
            'Type'         => 'label',
            'FriendlyName' => 'API Key Caching',
            'Description'  => 'Options are disabled as <strong><a href="https://github.com/phpredis/phpredis" target="_blank">phpredis</a></strong> extension is not installed.',
        ];
    }

    return $basicFields;
}

/**
 * Provision a new instance of a product/service.
 *
 * Attempt to provision a new instance of a given product/service. This is
 * called any time provisioning is requested inside of WHMCS. Depending upon the
 * configuration, this can be any of:
 * * When a new order is placed
 * * When an invoice for a new order is paid
 * * Upon manual request by an admin user
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return string "success" or an error message
 */
function awsapigw_CreateAccount(array $params) {
    try {
        $serviceId  = $params['model']['id'];
        $awsKey     = $params[CFG_OPTION_AWS_KEY];
        $awsSecret  = $params[CFG_OPTION_AWS_SECRET];
        $namePrefix = $params[CFG_OPTION_API_KEY_NAME_PREFIX];
        $apiRegion  = $params[CFG_OPTION_API_REGION];
        $usagePlans = formatUsagePlan($params[CFG_OPTION_API_USAGE_PLANS]);

        $svcManager = getServiceManager($params);
        $apigwClient = new AwsApiGateway\AwsApiGatewayClient($awsKey, $awsSecret, $apiRegion);

        if ($svcManager->hasServiceConfig($serviceId)) {
            throw new \Exception('The API key already exists. Use reset function to generate a new one. In case the key is deleted externally, please invoke termination command to remove the old record stored in the database.');
        }

        if (is_int($serviceId) && $serviceId > 0 && !empty($apiRegion)) {
            $createdKey = $apigwClient->createKeyWithUsagePlans("{$namePrefix}_serviceid_{$serviceId}", $usagePlans);

            if ($createdKey) {
                $ret = $svcManager->addServiceConfig([
                    'id'              => $serviceId,
                    'apigw_key_id'    => $createdKey->keyId,
                    'apigw_key_value' => $createdKey->keyVal,
                    'apigw_region'    => $apiRegion,
                    'usage_plans'     => empty($createdKey->assocUsagePlans) ? null : implode(',', $createdKey->assocUsagePlans),
                    'created_at'      => $createdKey->createdAt,
                    'updated_at'      => $createdKey->updatedAt,
                ]);

                return $ret >= 0 ? 'success' : "Failed to insert record for Service #{$serviceId}";
            } else {
                return "Failed to create API key and/or associate usage plans for Service #{$serviceId}";
            }
        }
    } catch (\Exception $e) {
        logModuleCall('awsapigw', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        return $e->getMessage();
    }

    return 'Unknown error occurred, please contact the support team.';
}

/**
 * Suspend an instance of a product/service.
 *
 * Called when a suspension is requested. This is invoked automatically by WHMCS
 * when a product becomes overdue on payment or can be called manually by admin
 * user.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return string "success" or an error message
 */
function awsapigw_SuspendAccount(array $params) {
    try {
        $serviceId = $params['model']['id'];
        $awsKey    = $params[CFG_OPTION_AWS_KEY];
        $awsSecret = $params[CFG_OPTION_AWS_SECRET];
        $apiRegion = $params[CFG_OPTION_API_REGION];

        $serviceConfig = getServiceManager($params)->getServiceConfig($serviceId);

        if (!empty($serviceConfig)) {
            $keyId       = $serviceConfig->apigw_key_id;
            $apigwClient = new AwsApiGateway\AwsApiGatewayClient($awsKey, $awsSecret, $apiRegion);

            if ($apigwClient->disableKey($keyId)) {
                return 'success';
            } else {
                throw new \Exception('Failed to suspend the API key.');
            }
        } else {
            throw new \Exception('The API key does not exist any more in the database. It may be deleted externally.');
        }
    } catch (\Exception $e) {
        logModuleCall('awsapigw', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        return $e->getMessage();
    }

    return 'Unknown error occurred, please contact the support team.';
}

/**
 * Un-suspend instance of a product/service.
 *
 * Called when an un-suspension is requested. This is invoked
 * automatically upon payment of an overdue invoice for a product, or
 * can be called manually by admin user.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return string "success" or an error message
 */
function awsapigw_UnsuspendAccount(array $params) {
    try {
        $serviceId = $params['model']['id'];
        $awsKey    = $params[CFG_OPTION_AWS_KEY];
        $awsSecret = $params[CFG_OPTION_AWS_SECRET];
        $apiRegion = $params[CFG_OPTION_API_REGION];

        $serviceConfig = getServiceManager($params)->getServiceConfig($serviceId);

        if (!empty($serviceConfig)) {
            $keyId       = $serviceConfig->apigw_key_id;
            $apigwClient = new AwsApiGateway\AwsApiGatewayClient($awsKey, $awsSecret, $apiRegion);

            if ($apigwClient->enableKey($keyId)) {
                return 'success';
            } else {
                throw new \Exception('Failed to unsuspend the API key.');
            }
        } else {
            throw new \Exception('The API key does not exist any more in the database. It may be deleted externally.');
        }
    } catch (\Exception $e) {
        logModuleCall('awsapigw', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        return $e->getMessage();
    }

    return 'Unknown error occurred, please contact the support team.';
}

/**
 * Terminate instance of a product/service.
 *
 * Called when a termination is requested. This can be invoked automatically for
 * overdue products if enabled, or requested manually by an admin user.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return string "success" or an error message
 */
function awsapigw_TerminateAccount(array $params) {
    try {
        $serviceId = $params['model']['id'];
        $awsKey    = $params[CFG_OPTION_AWS_KEY];
        $awsSecret = $params[CFG_OPTION_AWS_SECRET];
        $apiRegion = $params[CFG_OPTION_API_REGION];

        $svcManager = getServiceManager($params);
        $serviceConfig = $svcManager->getServiceConfig($serviceId);

        if (!empty($serviceConfig)) {
            $keyId       = $serviceConfig->apigw_key_id;
            $apigwClient = new AwsApiGateway\AwsApiGatewayClient($awsKey, $awsSecret, $apiRegion);

            if ($apigwClient->deleteKey($keyId)) {
                if ($svcManager->deleteServiceConfig($serviceId)) {
                    return 'success';
                } else {
                    throw new \Exception('The API key is successfully deleted on AWS but not in WHMCS database.');
                }
            } else {
                throw new \Exception('Failed to terminate the API key on AWS.');
            }
        } else {
            throw new \Exception('The API key does not exist any more in the database. It may be deleted externally.');
        }
    } catch (\Exception $e) {
        logModuleCall('awsapigw', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        return $e->getMessage();
    }

    return 'Unknown error occurred, please contact the support team.';
}

/**
 * Additional actions an admin user can invoke.
 *
 * Define additional actions that an admin user can perform for an
 * instance of a product/service.
 *
 * @see awsapigw_buttonOneFunction()
 *
 * @return array
 */
function awsapigw_AdminCustomButtonArray() {
    return [
        "Reset API Key" => "ResetApiKey",
    ];
}

/**
 * Additional actions a client user can invoke.
 *
 * Define additional actions a client user can perform for an instance of a
 * product/service.
 *
 * Any actions you define here will be automatically displayed in the available
 * list of actions within the client area.
 *
 * @return array
 */
function awsapigw_ClientAreaAllowedFunctions() {
    return [
        "ResetApiKey" => "ResetApiKey",
    ];
}

/**
 * Custom function for performing an additional action.
 *
 * You can define an unlimited number of custom functions in this way.
 *
 * Similar to all other module call functions, they should either return
 * 'success' or an error message to be displayed.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 * @see awsapigw_AdminCustomButtonArray()
 *
 * @return string "success" or an error message
 */
function awsapigw_ResetApiKey(array $params) {
    if (isset($params['status']) && $params['status'] == 'Active') {
        awsapigw_TerminateAccount($params);
        awsapigw_CreateAccount($params);

        return 'success';
    } else {
        return 'Service must be in Active status to reset the API key.';
    }
}

/**
 * Admin services tab additional fields.
 *
 * Define additional rows and fields to be displayed in the admin area service
 * information and management page within the clients profile.
 *
 * Supports an unlimited number of additional field labels and content of any
 * type to output.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 * @see awsapigw_AdminServicesTabFieldsSave()
 *
 * @return array
 */
function awsapigw_AdminServicesTabFields(array $params) {
    try {
        $serviceId = $params['model']['id'];
        $awsKey    = $params[CFG_OPTION_AWS_KEY];
        $awsSecret = $params[CFG_OPTION_AWS_SECRET];
        $apiRegion = $params[CFG_OPTION_API_REGION];

        $serviceConfig = getServiceManager($params)->getServiceConfig($serviceId);

        if (!empty($serviceConfig)) {
            $apigwClient          = new AwsApiGateway\AwsApiGatewayClient($awsKey, $awsSecret, $apiRegion);
            $serviceConfigDetails = getServiceConfigDetails($params);
            $retFields            = [
                'Deployed Region' => $serviceConfigDetails->deploy_region,
                'API Key'         => "{$serviceConfigDetails->api_key} (ID: {$serviceConfigDetails->api_key_id})",
                'API Endpoint'    => $serviceConfigDetails->api_endpoint,
                'Key Name'        => $serviceConfigDetails->api_key_name,
                'Key Description' => $serviceConfigDetails->api_key_desc,
                'Key Status'      => $serviceConfigDetails->api_key_stat ? 'Active' : 'Inactive',
                'Usage Plans'     => str_replace(',', ' ,', $serviceConfigDetails->usage_plans),
                'Creation Date'   => !empty($serviceConfigDetails->created_at) ? fromMySQLDate($serviceConfigDetails->created_at, true) : null,
                'Updated Date'    => !empty($serviceConfigDetails->updated_at) ? fromMySQLDate($serviceConfigDetails->updated_at, true) : null,
            ];

            return array_filter($retFields, function ($el) {
                return isset($el) && $el !== null && trim($el) !== '';
            });
        }
    } catch (\Exception $e) {
        $msg = $e->getMessage();

        logModuleCall('awsapigw', __FUNCTION__, $params, $msg, $e->getTraceAsString());

        return ['Key Status' => 'Unknown / Error' . (!empty($msg) ? ": $msg" : null)];
    }

    return ['Key Status' => 'Not Registered'];
}

/**
 * Client area output logic handling.
 *
 * This function is used to define module specific client area output. It should
 * return an array consisting of a template file and optional additional
 * template variables to make available to that template.
 *
 * The template file you return can be one of two types:
 *
 * * tabOverviewModuleOutputTemplate - The output of the template provided here
 *   will be displayed as part of the default product/service client area
 *   product overview page.
 *
 * * tabOverviewReplacementTemplate - Alternatively using this option allows you
 *   to entirely take control of the product/service overview page within the
 *   client area.
 *
 * Whichever option you choose, extra template variables are defined in the same
 * way. This demonstrates the use of the full replacement.
 *
 * Please Note: Using tabOverviewReplacementTemplate means you should display
 * the standard information such as pricing and billing details in your custom
 * template or they will not be visible to the end user.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 *
 * @return array
 */
function awsapigw_ClientArea(array $params) {
    try {
        $serviceId = $params['model']['id'];

        $templateFile         = 'templates/api.tpl';
        $serviceConfig        = getServiceManager($params)->getServiceConfig($serviceId);
        $serviceConfigDetails = array_filter((array) getServiceConfigDetails($params, false), function ($el) {
            return isset($el) && $el !== null && trim($el) !== '';
        });

        return [
            'tabOverviewModuleOutputTemplate' => $templateFile,
            'templateVariables'               => $serviceConfigDetails,
        ];
    } catch (\Exception $e) {
        logModuleCall('awsapigw', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
    }

    return [];
}

function getServiceConfigDetails(&$params, $onlineCheck = true) {
    $data = [];

    try {
        $serviceId     = $params['model']['id'];
        $awsKey        = $params[CFG_OPTION_AWS_KEY];
        $awsSecret     = $params[CFG_OPTION_AWS_SECRET];
        $apiRegion     = $params[CFG_OPTION_API_REGION];
        $apiEndpoint   = $params[CFG_OPTION_API_ENDPOINT_URL];

        $serviceConfig = getServiceManager($params)->getServiceConfig($serviceId);

        $data = [
            'deploy_region' => $serviceConfig->apigw_region,
            'api_key'       => $serviceConfig->apigw_key_value,
            'api_key_name'  => null,
            'api_key_id'    => $serviceConfig->apigw_key_id,
            'api_key_desc'  => null,
            'api_key_stat'  => null,
            'api_endpoint'  => $apiEndpoint ?? null,
            'usage_plans'   => $serviceConfig->usage_plans,
            'created_at'    => null,
            'updated_at'    => null,
        ];

        if (!empty($serviceConfig)) {
            $apigwClient = new AwsApiGateway\AwsApiGatewayClient($awsKey, $awsSecret, $apiRegion);

            if ($onlineCheck) { // pass false to disables online check and provides database-cached info
                $apiKeyStatus = $apigwClient->getKey($data['api_key_id']);
                if ($apiKeyStatus instanceof Result) {
                    $data['api_key_name'] = $apiKeyStatus->get('name');

                    if (!empty($apiDesc = $apiKeyStatus->get('description'))) {
                        $data['api_key_desc'] = $apiDesc;
                    }

                    $createdAt = $apiKeyStatus->get('createdDate');
                    $updatedAt = $apiKeyStatus->get('lastUpdatedDate');

                    if (!empty($tz = @date_default_timezone_get())) {
                        $createdAt->setTimezone(new DateTimeZone($tz));
                        $updatedAt->setTimezone(new DateTimeZone($tz));
                    }

                    $data['api_key_stat'] = $apiKeyStatus->get('enabled');
                    $data['created_at']   = $createdAt;
                    $data['updated_at']   = $updatedAt;

                    refreshServiceConfig($params, $apiKeyStatus); // sync external config changes for each call
                }
            }
        }
    } catch (\Exception $e) {
        logModuleCall('awsapigw', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
    }

    return (object) $data;
}

function refreshServiceConfig(&$params, &$apiKeyStatus) {
    try {
        $serviceId     = $params['model']['id'];

        getServiceManager($params)
            ->updateServiceConfig($serviceId, [
                'created_at' => $apiKeyStatus->get('createdDate'),
                'updated_at' => $apiKeyStatus->get('lastUpdatedDate'),
            ]);
    } catch (\Exception $e) {
        logModuleCall('awsapigw', __FUNCTION__, $serviceId, $e->getMessage(), $e->getTraceAsString());
    }
}

function formatUsagePlan(string $plans) {
    $plans    = str_replace(['\r\n', '\r', '\n'], ',', strtolower(trim($plans)));
    $plansArr = explode(',', $plans);

    return array_unique(array_map(function ($plan) {
        return trim($plan); // trim redundant characters
    }, $plansArr));
}
