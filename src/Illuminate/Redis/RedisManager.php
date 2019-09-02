<?php

namespace Illuminate\Redis;

use InvalidArgumentException;
use Illuminate\Contracts\Redis\Factory;

/**
 * @mixin \Illuminate\Redis\Connections\Connection
 */
class RedisManager implements Factory
{
    /**
     * The name of the default driver.
     *
     * @var string
     */
    protected $driver;

    /**
     * The Redis server configurations.
     *
     * @var array
     */
    protected $config;

    /**
     * The Redis connections.
     *
     * @var mixed
     */
    protected $connections;

    /**
     * Create a new Redis manager instance.
     *
     * @param  string  $driver
     * @param  array  $config
     * @return void
     */
    public function __construct($driver, array $config)
    {
        $this->driver = $driver;
        $this->config = $config;
    }

    /**
     * Get a Redis connection by name.
     *
     * @param  string|null  $name
     * @return \Illuminate\Redis\Connections\Connection
     */
    public function connection($name = null)
    {
        $name = $name ?: 'default';

        if (isset($this->connections[$name])) {
            return $this->connections[$name];
        }

        return $this->connections[$name] = $this->resolve($name);
    }

    /**
     * Resolve the given connection by name.
     *
     * @param  string|null  $name
     * @return \Illuminate\Redis\Connections\Connection
     *
     * @throws \InvalidArgumentException
     */
    public function resolve($name = null)
    {
        $name = $name ?: 'default';

        $options = $this->config['options'] ?? [];

        $clusterConfig = $this->getClusterConfig($name);
        if ($clusterConfig) {
            return $this->resolveClusterOption($name);
        }

        if (isset($this->config[$name])) {
            return $this->connector()->connect($this->config[$name], $options);
        }

        if (isset($this->config['clusters'][$name])) {
            return $this->resolveCluster($name);
        }

        throw new InvalidArgumentException(
            "Redis connection [{$name}] not configured."
        );
    }

    /**
     * Get the cluster options if it exists 
     *
     * @param  string $connection the connection given by name  
     * @return mixed 
     *
     */
    private function getClusterConfig($connection)
    {
        if (isset($this->config[$connection]['clusters'])) {
            return $this->config[$connection]['clusters']; 
        } else {
            return null; 
        }
    } 

    /**
     * Resolve the cluster option
     * note: this basically does the same job as resolveCluster
     * but targetig a config where a cluster connection is treated
     * the same way as the default connection, ie as a first class citizen
     *
     * @param  string  $name
     * @return \Illuminate\Redis\Connections\Connection
     */
    protected function resolveClusterOption($name)
    {
        // this works if you put the cluster options at the top level
        $clusterOptions = $this->config[$name]['options'] ?? [];

        return $this->connector()->connectToCluster(
            $this->config[$name]['clusters'], $clusterOptions, $this->config['options'] ?? []
        );
    }

    /**
     * Resolve the given cluster connection by name.
     *
     * @param  string  $name
     * @return \Illuminate\Redis\Connections\Connection
     */
    protected function resolveCluster($name)
    {
        $clusterOptions = $this->config['clusters']['options'] ?? [];

        return $this->connector()->connectToCluster(
            $this->config['clusters'][$name], $clusterOptions, $this->config['options'] ?? []
        );
    }

    /**
     * Get the connector instance for the current driver.
     *
     * @return \Illuminate\Redis\Connectors\PhpRedisConnector|\Illuminate\Redis\Connectors\PredisConnector
     */
    protected function connector()
    {
        switch ($this->driver) {
            case 'predis':
                return new Connectors\PredisConnector;
            case 'phpredis':
                return new Connectors\PhpRedisConnector;
        }
    }

    /**
     * Return all of the created connections.
     *
     * @return array
     */
    public function connections()
    {
        return $this->connections;
    }

    /**
     * Pass methods onto the default Redis connection.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->connection()->{$method}(...$parameters);
    }
}


