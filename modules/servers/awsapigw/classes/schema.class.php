<?php

namespace WHMCS\Module\AwsApiGateway;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__ . '/../includes/constants.php';

use WHMCS\Database\Capsule;

class SchemaManager {
    public static function createTable(string $tableName = DEFAULT_USAGE_TABLE) {
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
}
