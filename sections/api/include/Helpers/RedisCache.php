<?php
namespace Helpers;

use Predis\Client;

class RedisCache
{
    private static $instance = null;
    private $redis;
    
    private function __construct()
    {
        try {
            $this->redis = new Client([
                'scheme' => 'tcp',
                'host'   => '127.0.0.1',
                'port'   => 6379,
            ]);
            $this->redis->ping();
        } catch (\Exception $e) {
            $this->redis = null;
            error_log("Redis unavailable: " . $e->getMessage());
        }
    }
    
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function get($key)
    {
        if (!$this->redis) return null;
        
        try {
            $value = $this->redis->get($key);
            return $value ? json_decode($value, true) : null;
        } catch (\Exception $e) {
            error_log("Redis get error: " . $e->getMessage());
            return null;
        }
    }
    
    public function set($key, $value, $ttl = 300)
    {
        if (!$this->redis) return false;
        
        try {
            $this->redis->setex($key, $ttl, json_encode($value));
            return true;
        } catch (\Exception $e) {
            error_log("Redis set error: " . $e->getMessage());
            return false;
        }
    }
    
    public function delete($key)
    {
        if (!$this->redis) return false;
        
        try {
            $this->redis->del($key);
            return true;
        } catch (\Exception $e) {
            error_log("Redis delete error: " . $e->getMessage());
            return false;
        }
    }
    
    public function flush()
    {
        if (!$this->redis) return false;
        
        try {
            $this->redis->flushdb();
            return true;
        } catch (\Exception $e) {
            error_log("Redis flush error: " . $e->getMessage());
            return false;
        }
    }
    
    public function isAvailable()
    {
        return $this->redis !== null;
    }
}
