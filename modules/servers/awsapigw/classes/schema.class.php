<?php

namespace WHMCS\Module\AwsApiGateway;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/../includes/constants.php';

use WHMCS\Database\Capsule;

class DatabaseMgr
{
    public static function createTable(string $tableName = DEFAULT_USAGE_TABLE)
    {
        try {
            Capsule::transaction(function () use (&$tableName) {
                if (!Capsule::schema()->hasTable($tableName)) {
                    Capsule::schema()->create($tableName, function ($table) {
                        $table->engine = 'InnoDB'; // only InnoDB supports foreign key constraints

                        $table->integer('id')->primary();
                        $table->foreign('id')->references('id')->on('tblhosting')->onDelete('cascade');

                        $table->string('apigw_key_id', 100)->nullable();
                        $table->string('apigw_key_value', 100)->nullable();
                        $table->string('apigw_region', 20)->default('us-east-1');
                        $table->longText('usage_plans')->nullable();

                        $table->timestamps();
                    });
                }
            });
        } catch (\Exception $e) {
            throw new \Exception("Failed to create {$tableName}: {$e->getMessage()}");
        }
    }

    public static function getServiceConfig(int $serviceId, string $tableName = DEFAULT_USAGE_TABLE)
    {
        try {
            return Capsule::table($tableName)
                ->where('id', $serviceId)
                ->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function hasServiceConfig(int $serviceId, string $tableName = DEFAULT_USAGE_TABLE)
    {
        try {
            return Capsule::table($tableName)
                ->where('id', $serviceId)
                ->first() !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function updateServiceConfig(int $serviceId, array $newConfig, string $tableName = DEFAULT_USAGE_TABLE)
    {
        try {
            $affectedRows = Capsule::table($tableName)
                ->where('id', $serviceId)
                ->update($newConfig);

            return $affectedRows === 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function addServiceConfig(array $newConfig, string $tableName = DEFAULT_USAGE_TABLE)
    {
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

    public static function deleteServiceConfig(int $serviceId, string $tableName = DEFAULT_USAGE_TABLE) {
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
