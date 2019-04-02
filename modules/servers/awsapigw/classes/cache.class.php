<?php

namespace WHMCS\Module\AwsApiGateway;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

if (!class_exists('\Redis')) {
    throw new \Exception("Required dependency: phpredis does not loaded");
}

require_once __DIR__ . '/../includes/constants.php';

use Redis;

class CacheManager {
    protected $host    = DEFAULT_REDIS_HOST;
    protected $port    = DEFAULT_REDIS_PORT;
    protected $db      = DEFAULT_REDIS_DB_INDEX;
    protected $auth    = DEFAULT_REDIS_AUTH;
    protected $prefix  = DEFAULT_REDIS_PREFIX;
    protected $persist = DEFAULT_REDIS_PERSIST_CONN;
    protected $timeout = DEFAULT_REDIS_TIMEOUT;

    protected $redis = null;

    public function __construct($connect = false) {
        if ($connect) {
            $this->connect();
        }
    }

    public function connect() {
        if ($this->isConnected()) {
            $this->redis->close();
        } else {
            $this->redis = new Redis();
        }

        $connFunc = $this->getConnFunctionName();
        $this->redis->$connFunc();
    }

    public function set(string $key, $value = null, array $options = []) {
        return $this->redis->set($this->genKey($key), $value, $options);
    }

    public function get(string $key) {
        return $this->redis->get($this->genKey($key));
    }

    public function setHost($host) {
        $host = trim($host);

        if (!filter_var($host, FILTER_VALIDATE_IP) && !filter_var($host, FILTER_VALIDATE_DOMAIN)) {
            throw new \Exception('Invalid Redis Host');
        } else {
            $this->host = strtolower($host);
        }

        if ($this->isConnected()) {
            $this->connect();
        }

        return $this;
    }

    public function setPort($port) {
        if (\is_string($port) && \is_numeric($port)) {
            $port = (int) $port;
        } else if (!\is_integer($port)) {
            throw new \Exception('Invalid Redis Port');
        }

        if ($port >= 0 && $port <= 65535) {
            $this->port = $port;
        } else {
            throw new \Exception('Invalid Redis Port Range');
        }

        if ($this->isConnected()) {
            $this->connect();
        }

        return $this;
    }

    public function setPersistConn($persist = true) {
        $this->persist = !!$persist;

        if ($this->isConnected()) {
            $this->connect();
        }

        return $this;
    }

    public function setDbIndex($db = 0) {
        if (\is_string($db) && \is_numeric($db)) {
            $db = (int) $db;
        } else if (!\is_integer($db)) {
            throw new \Exception('Invalid Redis Database Index');
        }

        if ($db >= 0) {
            $this->db = $db;
        } else {
            throw new \Exception('Invalid Redis Database Index: must be a positive integer');
        }

        if ($this->isConnected()) {
            $this->redis->select($db);
        }

        return $this;
    }

    public function setAuth($auth = null) {
        $this->auth = $auth ?? '';

        if ($this->isConnected()) {
            $this->connect();
        }

        return $this;
    }

    public function setTimeout($timeout = 3) {
        if (\is_string($timeout) && \is_numeric($timeout)) {
            $timeout = (float) $timeout;
        } else if (!\is_integer($timeout) && !\is_float($timeout)) {
            throw new \Exception('Invalid Redis Connection Timeout');
        }

        if ($timeout > 0) {
            $this->timeout = $timeout;
        } else {
            throw new \Exception('Invalid Redis Connection Timeout: must be a positive integer');
        }

        return $this;
    }

    public function setPrefix($prefix = null) {
        $this->prefix = $prefix ?? '';

        if ($this->isConnected()) {
            $this->connect();
        }

        return $this;
    }

    private function genKey($key = null) {
        return $prefix . trim($key ?? '');
    }

    private function getConnFunctionName() {
        if ($this->persist) {
            return 'pconnect';
        } else {
            return 'connect';
        }
    }

    private function isConnected() {
        return $this->redis instanceof Redis && $this->redis->isConnected();
    }

    public function __deconstruct() {
        if ($this->isConnected()) {
            $this->redis->close();
        }

    }
}
