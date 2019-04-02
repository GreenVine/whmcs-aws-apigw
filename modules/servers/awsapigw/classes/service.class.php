<?php

namespace WHMCS\Module\AwsApiGateway;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/../includes/constants.php';

use WHMCS\Database\Capsule;

class ServiceManager {
    protected $cache = null;

    public function __construct(array &$params) {
        if (class_exists('\Redis')) {
            require_once __DIR__ . '/cache.class.php';

            $this->cache = new CacheManager();
            $this->cache
                ->setHost($params[CFG_OPTION_CACHE_REDIS_HOST])
                ->setPort($params[CFG_OPTION_CACHE_REDIS_PORT])
                ->setPersistConn(strtolower($params[CFG_OPTION_CACHE_PERSIST_CONN]) === 'on')
                ->setDbIndex($params[CFG_OPTION_CACHE_REDIS_DBINDEX])
                ->setAuth($params[CFG_OPTION_CACHE_REDIS_AUTH])
                ->setTimeout($params[CFG_OPTION_CACHE_REDIS_TIMEOUT])
                ->setPrefix($params[CFG_OPTION_API_KEY_NAME_PREFIX])
                ->connect();
        }
    }

    public function getServiceConfig(int $serviceId, string $tableName = DEFAULT_USAGE_TABLE) {
        try {
            return Capsule::table($tableName)
                ->where('id', $serviceId)
                ->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    public function hasServiceConfig(int $serviceId, string $tableName = DEFAULT_USAGE_TABLE) {
        try {
            return Capsule::table($tableName)
                ->where('id', $serviceId)
                ->first() !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function updateServiceConfig(int $serviceId, array $newConfig, string $tableName = DEFAULT_USAGE_TABLE) {
        try {
            $affectedRows = Capsule::table($tableName)
                ->where('id', $serviceId)
                ->update($newConfig);

            return $affectedRows === 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function addServiceConfig(array $newConfig, string $tableName = DEFAULT_USAGE_TABLE) {
        Capsule::transaction(function () use (&$newConfig, &$tableName) {
            $id = $newConfig['id'];

            if (!self::hasServiceConfig($id, $tableName)) {
                return Capsule::table($tableName)
                    ->insert($newConfig);
            } else {
                return 0;
            }
        });
    }

    public function deleteServiceConfig(int $serviceId, string $tableName = DEFAULT_USAGE_TABLE) {
        try {
            Capsule::table($tableName)
                ->where('id', $serviceId)
                ->delete();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
