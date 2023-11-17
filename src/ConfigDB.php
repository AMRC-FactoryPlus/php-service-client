<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace AMRCFactoryPlus;

use AMRCFactoryPlus\Exceptions\ServiceClientException;
use AMRCFactoryPlus\UUIDs\App;

class ConfigDB extends ServiceInterface
{
    static string $serviceName = "configdb";

    public function createObject(string $class, string $uuid = null, string $name = null)
    {
        $payload = ["class" => $class];
        if (!is_null($uuid)) {
            $payload["uuid"] = $uuid;
        }

        $response = $this->fetch(
            type: 'post',
            url: '/v1/object',
            payload: $payload,
        );

        // Add an entry to the General Object Info app if a name is provided.
        if (!is_null($name)) {
            $this->putConfig(App::Info, $uuid, ["name" => $name]);
        }

        return $response;
    }

    public function getConfig(string $app, string $obj = null)
    {
        $url = sprintf("/v1/app/%s/object%s", $app, $obj ? "/$obj" : "");

        return $this->fetch(
            type: "get",
            url: $url,
        );
    }

    public function putConfig(string $app, string $obj, $payload)
    {
        try {
            return $this->fetch(
                type: 'put',
                url: sprintf("/v1/app/%s/object/%s", $app, $obj),
                payload: $payload,
            );
        } catch (ServiceClientException $e) {
            throw new ServiceClientException(
                sprintf(
                    "Failed to put ConfigDB entry for %s/%s: %u",
                    $app,
                    $obj,
                    $e->getCode()
                ), $e->getCode(),
            );
        }
    }

    public function deleteConfig(string $app, string $obj)
    {
        try {
            return $this->fetch(
                type: 'delete',
                url: sprintf("/v1/app/%s/object/%s", $app, $obj),
            );
        } catch (ServiceClientException $e) {
            throw new ServiceClientException(
                sprintf(
                    "Failed to delete ConfigDB entry for %s/%s: %u",
                    $app,
                    $obj,
                    $e->getCode()
                ), $e->getCode(),
            );
        }
    }
}
