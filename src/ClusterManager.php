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
}
