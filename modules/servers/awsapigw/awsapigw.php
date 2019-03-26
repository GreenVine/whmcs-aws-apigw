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
require_once __DIR__ . '/classes/apigw.class.php';

use Aws\Result;
use WHMCS\Module\AwsApiGateway;

AwsApiGateway\DatabaseMgr::createTable();

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
function awsapigw_MetaData()
{
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
function awsapigw_ConfigOptions()
{
    return [
        'aws_key_id'          => [
            'FriendlyName' => 'AWS Key ID',
            'Type'         => 'text',
            'Size'         => '25',
            'Default'      => '',
            'Description'  => 'AWS Access Key ID',
        ],
        'aws_key_secret'      => [
            'FriendlyName' => 'AWS Key Secret',
            'Type'         => 'password',
            'Size'         => '25',
            'Default'      => '',
            'Description'  => 'AWS Secret Access Key',
        ],
        'api_name_pfx' => [
            'FriendlyName'  => 'API Key Name Prefix',
            'Type'        => 'text',
            'Size'        => '25',
            'Default'     => 'whmcs_',
            'Description' => 'Prefix added to the name of API key',
        ],
        'api_region'  => [
            'FriendlyName'  => 'API Gateway Region',
            'Type'        => 'text',
            'Size'        => '25',
            'Default'     => 'us-east-1',
            'Description' => 'Deployed region of API Gateway',
        ],
        'usage_plan_ids'  => [
            'FriendlyName'  => 'API Usage Plan IDs',
            'Type' => 'textarea',
            'Rows'  => '3',
            'Cols'  => '50',
            'Default' => '',
            'Description' => 'List of usage plan IDs (separated by line breaks or comma)'
        ],
    ];
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
function awsapigw_CreateAccount(array $params)
{
    try {
        $serviceId  = $params['model']['id'];
        $awsKey     = $params['configoption1'];
        $awsSecret  = $params['configoption2'];
        $namePrefix = $params['configoption3'];
        $apiRegion  = $params['configoption4'];
        $usagePlans = formatUsagePlan($params['configoption5']);

        $apigwClient = new AwsApiGateway\AwsApiGatewayClient($awsKey, $awsSecret, $apiRegion);

        if (AwsApiGateway\DatabaseMgr::hasServiceConfig($serviceId)) {
            return 'The API key already exists. Use reset function to generate a new one. In case the key is deleted externally, please invoke termination command to remove the old record stored in the database.';
        }

        if (is_int($serviceId) && $serviceId > 0 && !empty($apiRegion)) {
            $createdKey = $apigwClient->createKeyWithUsagePlans("{$namePrefix}_serviceid_{$serviceId}", $usagePlans);

            if ($createdKey) {
                $ret = AwsApiGateway\DatabaseMgr::addServiceConfig([
                    'id'                => $serviceId,
                    'apigw_key_id'      => $createdKey->keyId,
                    'apigw_key_value'   => $createdKey->keyVal,
                    'apigw_region'      => $apiRegion,
                    'usage_plans'       => empty($createdKey->assocUsagePlans) ? null : implode(',', $createdKey->assocUsagePlans),
                    'created_at'        => $createdKey->createdAt,
                    'updated_at'        => $createdKey->updatedAt
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

    return 'Invalid request parameters';
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
function awsapigw_SuspendAccount(array $params)
{
    try {
        $serviceId      = $params['model']['id'];
        $awsKey         = $params['configoption1'];
        $awsSecret      = $params['configoption2'];
        $apiRegion      = $params['configoption4'];

        $serviceConfig = AwsApiGateway\DatabaseMgr::getServiceConfig($serviceId);

        if (!empty($serviceConfig)) {
            $keyId = $serviceConfig->apigw_key_id;
            $apigwClient = new AwsApiGateway\AwsApiGatewayClient($awsKey, $awsSecret, $apiRegion);

            if ($apigwClient->disableKey($keyId)) {
                return 'success';
            } else {
                return 'Failed to suspend the API key.';
            }
        } else {
            return 'The API key does not exist any more in the database. It may be deleted externally and you must re-create the key to continue.';
        }
    } catch (\Exception $e) {
        logModuleCall('awsapigw', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        return $e->getMessage();
    }

    return 'Invalid request parameters';
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
function awsapigw_UnsuspendAccount(array $params)
{
    try {
        $serviceId      = $params['model']['id'];
        $awsKey         = $params['configoption1'];
        $awsSecret      = $params['configoption2'];
        $apiRegion      = $params['configoption4'];

        $serviceConfig = AwsApiGateway\DatabaseMgr::getServiceConfig($serviceId);

        if (!empty($serviceConfig)) {
            $keyId = $serviceConfig->apigw_key_id;
            $apigwClient = new AwsApiGateway\AwsApiGatewayClient($awsKey, $awsSecret, $apiRegion);

            if ($apigwClient->enableKey($keyId)) {
                return 'success';
            } else {
                return 'Failed to unsuspend the API key.';
            }
        } else {
            return 'The API key does not exist any more in the database. It may be deleted externally and you must re-create the key to continue.';
        }
    } catch (\Exception $e) {
        logModuleCall('awsapigw', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        return $e->getMessage();
    }

    return 'Invalid request parameters';
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
function awsapigw_TerminateAccount(array $params)
{
    try {
        // Call the service's terminate function, using the values provided by
        // WHMCS in `$params`.
    } catch (\Exception $e) {
        logModuleCall('awsapigw', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        return $e->getMessage();
    }

    return 'success';
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
function awsapigw_AdminCustomButtonArray()
{
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
// function awsapigw_ClientAreaCustomButtonArray()
// {
//     return array(
//         "Action 1 Display Value" => "actionOneFunction",
//         "Action 2 Display Value" => "actionTwoFunction",
//     );
// }

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
function awsapigw_ResetApiKey(array $params)
{
    try {
        // Call the service's function, using the values provided by WHMCS in
        // `$params`.
    } catch (\Exception $e) {
        logModuleCall('awsapigw', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        return $e->getMessage();
    }

    return 'success';
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
function awsapigw_AdminServicesTabFields(array $params)
{
    try {
        $serviceId = $params['model']['id'];
        $awsKey     = $params['configoption1'];
        $awsSecret  = $params['configoption2'];
        $apiRegion  = $params['configoption4'];

        $serviceConfig = AwsApiGateway\DatabaseMgr::getServiceConfig($serviceId);

        if (!empty($serviceConfig)) {
            $keyId = $serviceConfig->apigw_key_id;
            $retFields = [
                'Deployed Region' => $serviceConfig->apigw_region,
                'API Key'    => $serviceConfig->apigw_key_value
            ];

            $apigwClient    = new AwsApiGateway\AwsApiGatewayClient($awsKey, $awsSecret, $apiRegion);
            $apiKeyStatus   = $apigwClient->getKey($keyId);

            if ($apiKeyStatus instanceof Result) {
                $retFields['Key Name'] = "{$apiKeyStatus->get('name')} (ID: {$keyId})";

                if (!empty($apiDesc = $apiKeyStatus->get('description'))) {
                    $retFields['Key Description'] = $apiDesc;
                }

                $createdAt = $apiKeyStatus->get('createdDate');
                $updatedAt = $apiKeyStatus->get('lastUpdatedDate');

                if (!empty($tz = @date_default_timezone_get())) {
                    $createdAt->setTimezone(new DateTimeZone($tz));
                    $updatedAt->setTimezone(new DateTimeZone($tz));
                }

                $retFields['Usage Plans'] = str_replace(',', ' ,', $serviceConfig->usage_plans);
                $retFields['Key Status'] = $apiKeyStatus->get('enabled') ? 'Enabled' : 'Disabled';
                $retFields['Key Created'] = fromMySQLDate($createdAt, true);
                $retFields['Key Last Updated'] = fromMySQLDate($updatedAt, true);

                refreshServiceConfig($serviceId, $apiKeyStatus); // sync external config changes when page loads
            }

            return $retFields;
        }
    } catch (\Exception $e) {
        logModuleCall('awsapigw', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        return ['Key Status' => 'Unknown / Error'];
    }

    return ['Key Status' => 'Not Registered'];
}

/**
 * Execute actions upon save of an instance of a product/service.
 *
 * Use to perform any required actions upon the submission of the admin area
 * product management form.
 *
 * It can also be used in conjunction with the AdminServicesTabFields function
 * to handle values submitted in any custom fields which is demonstrated here.
 *
 * @param array $params common module parameters
 *
 * @see https://developers.whmcs.com/provisioning-modules/module-parameters/
 * @see awsapigw_AdminServicesTabFields()
 */
function awsapigw_AdminServicesTabFieldsSave(array $params)
{
    // Fetch form submission variables.
    $originalFieldValue = isset($_REQUEST['awsapigw_original_uniquefieldname'])
        ? $_REQUEST['awsapigw_original_uniquefieldname']
        : '';

    $newFieldValue = isset($_REQUEST['awsapigw_uniquefieldname'])
        ? $_REQUEST['awsapigw_uniquefieldname']
        : '';

    // Look for a change in value to avoid making unnecessary service calls.
    if ($originalFieldValue != $newFieldValue) {
        try {
            // Call the service's function, using the values provided by WHMCS
            // in `$params`.
        } catch (\Exception $e) {
            logModuleCall('awsapigw', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());
        }
    }
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
function awsapigw_ClientArea(array $params)
{
    // Determine the requested action and set service call parameters based on
    // the action.
    $requestedAction = isset($_REQUEST['customAction']) ? $_REQUEST['customAction'] : '';

    if ($requestedAction == 'manage') {
        $serviceAction = 'get_usage';
        $templateFile  = 'templates/manage.tpl';
    } else {
        $serviceAction = 'get_stats';
        $templateFile  = 'templates/overview.tpl';
    }

    try {
        // Call the service's function based on the request action, using the
        // values provided by WHMCS in `$params`.
        $response = [];

        $extraVariable1 = 'abc';
        $extraVariable2 = '123';

        return [
            'tabOverviewReplacementTemplate' => $templateFile,
            'templateVariables'              => [
                'extraVariable1' => $extraVariable1,
                'extraVariable2' => $extraVariable2,
            ],
        ];
    } catch (\Exception $e) {
        logModuleCall('awsapigw', __FUNCTION__, $params, $e->getMessage(), $e->getTraceAsString());

        // In an error condition, display an error page.
        return [
            'tabOverviewReplacementTemplate' => 'error.tpl',
            'templateVariables'              => [
                'usefulErrorHelper' => $e->getMessage(),
            ],
        ];
    }
}

function refreshServiceConfig($serviceId, $apiKeyStatus)
{
    try {
        AwsApiGateway\DatabaseMgr::updateServiceConfig($serviceId, [
            'created_at'    => $apiKeyStatus->get('createdDate'),
            'updated_at'    => $apiKeyStatus->get('lastUpdatedDate')
        ]);
    } catch (\Exception $e) {
        logModuleCall('awsapigw', __FUNCTION__, $serviceId, $e->getMessage(), $e->getTraceAsString());
    }
}

function formatUsagePlan(string $plans)
{
    $plans = str_replace(['\r\n', '\r', '\n'], ',', strtolower(trim($plans)));
    $plansArr = explode(',', $plans);

    return array_unique(array_map(function ($plan) {
        return trim($plan); // trim redundant characters
    }, $plansArr));
}
