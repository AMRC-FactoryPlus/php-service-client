<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace AMRCFactoryPlus;

abstract class ServiceInterface
{
    protected static string $serviceName;
    protected ServiceClient $client;

    public function __construct(ServiceClient $client)
    {
        $this->client = $client;
    }

    /**
     * Fetch data from the service.
     *
     * @param mixed ...$args Arguments required for fetching data.
     *
     * @return mixed The fetched data.
     */
    public function fetch(...$args): mixed
    {
        // This is a wrapper for ServiceClient::fetch() that sets the service name when called on the service's class.
        return $this->client->getHTTP()->fetch(...$args, service: static::$serviceName);
    }
}
