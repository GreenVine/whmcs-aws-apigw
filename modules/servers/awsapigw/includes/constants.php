<?php

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

define('DEFAULT_USAGE_TABLE', 'mod_awsapigw');

define('DEFAULT_REDIS_HOST', '127.0.0.1');
define('DEFAULT_REDIS_PORT', 6379);
define('DEFAULT_REDIS_DB_INDEX', 1);
define('DEFAULT_REDIS_AUTH', null);
define('DEFAULT_REDIS_PREFIX', 'awsapigw:');
define('DEFAULT_REDIS_PERSIST_CONN', false);
define('DEFAULT_REDIS_TIMEOUT', 3);

define('CFG_OPTION_AWS_KEY', 'configoption1');
define('CFG_OPTION_AWS_SECRET', 'configoption2');
define('CFG_OPTION_API_KEY_NAME_PREFIX', 'configoption3');
define('CFG_OPTION_API_REGION', 'configoption4');
define('CFG_OPTION_API_USAGE_PLANS', 'configoption5');
define('CFG_OPTION_API_ENDPOINT_URL', 'configoption6');
define('CFG_OPTION_CACHE_ENABLE', 'configoption7');
define('CFG_OPTION_CACHE_PERSIST_CONN', 'configoption8');
define('CFG_OPTION_CACHE_REDIS_HOST', 'configoption9');
define('CFG_OPTION_CACHE_REDIS_PORT', 'configoption10');
define('CFG_OPTION_CACHE_REDIS_DBINDEX', 'configoption11');
define('CFG_OPTION_CACHE_REDIS_KEY_PREFIX', 'configoption12');
define('CFG_OPTION_CACHE_REDIS_AUTH', 'configoption13');
define('CFG_OPTION_CACHE_REDIS_TIMEOUT', 'configoption14');
