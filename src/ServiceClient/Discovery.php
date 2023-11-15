<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace AMRCFactoryPlus\Utilities\ServiceClient;

use League\Uri\Uri;

# This class does static discovery (preconfigured) for now. It could be
# extended later to discover via the Directory.
class Discovery extends ServiceInterface
{
    /**
     * @throws ServiceClientException
     */
    public function serviceUrl(string $service): Uri
    {
        return Uri::createFromString($this->lookup($service));
    }

    /**
     * @throws ServiceClientException
     */
    public function lookup(string $service): string
    {
        return match ($service) {
            "auth" => $this->client->scheme . '://auth.' . $this->client->baseUrl,
            "cmdesc" => $this->client->scheme . '://cmdesc.' . $this->client->baseUrl,
            "configdb" => $this->client->scheme . '://configdb.' . $this->client->baseUrl,
            default => throw new ServiceClientException(
                sprintf("Unknown service for discovery: %s", $service)
            ),
        };
    }
}
