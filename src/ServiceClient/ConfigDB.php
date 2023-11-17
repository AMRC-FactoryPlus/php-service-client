<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace AMRCFactoryPlus\Utilities\ServiceClient;

use AMRCFactoryPlus\Utilities\ServiceClient\UUIDs\App;

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

    public function searchConfig(
        string $app,
        array $query,
        string $class = null,
        array $results = null
    ) {
        $url = is_null($class) ? sprintf("/v1/app/%s/search", $app) : sprintf(
            "/v1/app/%s/class/%s/search",
            $app,
            $class
        );

        $qs = [];
        foreach ($query as $k => $v) {
            $qs[$k] = json_encode($v);
        }
        if (!is_null($results)) {
            foreach ($results as $k => $v) {
                $qs["@" . $k] = $v;
            }
        }

        $res = $this->fetch(type: "get", url: $url, query: $qs);
        if (!$res->ok()) {
            $this->client->logger->debug(
                sprintf(
                    "ConfigDB search for %s failed: %u",
                    $app,
                    $res->status()
                )
            );
            return;
        }
        return $res->json();
    }
}
