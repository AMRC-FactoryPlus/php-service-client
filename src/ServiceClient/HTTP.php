<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace AMRCFactoryPlus\Utilities\ServiceClient;

use AMRCFactoryPlus\Utilities\ServiceClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use JsonException;
use League\Uri\QueryString;
use League\Uri\Uri;

class HTTP
{
    public ServiceClient $client;

    public function __construct(ServiceClient $client)
    {
        $this->client = $client;
    }

    public function fetch(string $type, string $service, string $url, $payload = null, array $query = null)
    {
        // Validate the request
        if (!in_array($type, ['get', 'post', 'put'])) {
            throw new ServiceClientException('Incorrect method passed to Fetch');
        }

        $base = $this->client->getDiscovery()->serviceUrl($service);

        $url = Uri::createFromBaseUri($url, $base);

        if (!is_null($query)) {
            # Convert from PHP assoc-array to the alist the library wants.
            $alist = array_map(
                fn($k) => [$k, $query[$k]],
                array_keys($query)
            );
            $url = $url->withQuery(QueryString::build($alist));
        }

        // Keep trying to make the request while we get an UnauthorisedException
        $response = null;
        $tries = 0;
        $refresh = false;
        while ($tries < 3) {
            try {
                return json_decode(
                    $this->do($type, $service, $url, $payload, $refresh)->getBody()->getContents(),
                    true
                );
            } catch (UnauthorisedException $e) {
                $tries++;
                // Force the next try to get a fresh token
                $refresh = true;
                $this->client->logger->debug('UnauthorisedException: ' . $e->getMessage());
                $this->client->logger->debug('Retrying request (' . $tries . ')');
            }
        }
        throw new ServiceClientException('Failed to get a valid token after 3 attempts');
    }

    /**
     * @throws GuzzleException
     * @throws ServiceClientException
     * @throws JsonException
     */
    public function do(
        string $type,
        string $service,
        string $url,
        $payload = null,
        $force = false
    ) {
        $client = new Client();
        // Get a valid token, either via the cache or by asking for a new one
        $token = $this->client->getToken($service, $force);
        $headers = ['Authorization' => 'Bearer ' . $token];

        $options = [
            'headers' => $headers,
            'json' => $payload
        ];

        try {
            // Try and make the original request
            return $client->request(strtoupper($type), $url, $options);
        } catch (ClientException $e) {
            // If we fail with a 401 then our token has likely expired and we need a new one, so repeat this request with
            // a force refresh
            if ($e->getCode() === 401) {
                throw new UnauthorisedException;
            } else {
                // If we have a different error then throw it up the stack
                throw new ServiceClientException(
                    'Guzzle HTTP request failed (' . $e->getCode() . '): ' . $e->getMessage(), $e->getCode()
                );
            }
        } catch (ServerException $e) {
            // If we fail with a server error then throw it up the stack
            throw new ServiceClientException(
                'Guzzle HTTP request failed (' . $e->getCode() . '): ' . $e->getMessage(), $e->getCode()
            );
        }
    }
}
