<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace AMRCFactoryPlus;

use AMRCFactoryPlus\Exceptions\ServiceClientException;
use AMRCFactoryPlus\UUIDs\App;

class ClusterManager extends ServiceInterface
{
    static string $serviceName = "clusters";

    public function getBootstrapScript(string $cluster)
    {
        return $this->fetch(
            type: "post",
            url: '/v1/cluster/'. $cluster .'/bootstrap-url',
        );
    }

    public function putSecret(string $cluster, string $namespace, string $name, string $key, $payload)
    {
        try {
            return $this->fetch(
                type: 'put',
                url: sprintf("/v1/cluster/%s/secret/%s/%s/%s", $cluster, $namespace, $name, $key),
                payload: $payload,
                contentType: 'application/octet-stream'
            );
        } catch (ServiceClientException $e) {
            throw new ServiceClientException(
                sprintf(
                    "Failed to put ConfigDB entry for %s/%s/%s/%s: %u",
                    $cluster,
                    $namespace,
                    $name,
                    $key,
                    $e->getCode()
                ), $e->getCode(),
            );
        }
    }
}
