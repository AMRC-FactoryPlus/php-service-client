<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS) Manager component
 *  Copyright 2023 AMRC
 */

namespace AMRCFactoryPlus;

use AMRCFactoryPlus\Exceptions\ServiceClientException;
use AMRCFactoryPlus\Exceptions\UnauthorisedException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Response;
use JsonException;
use League\Uri\QueryString;
use League\Uri\Uri;
use Psr\Http\Message\ResponseInterface;

class HTTP
{
    public ServiceClient $client;

    public function __construct(ServiceClient $client)
    {
        $this->client = $client;
    }

    public function fetch(
        string $type,
        string $service,
        string $url,
        $payload = null,
        array $query = null,
        $contentType = null
    ) {
        // Validate the request
        if (!in_array($type, ['get', 'post', 'put', 'delete'])) {
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
        $tries = 0;
        $refresh = false;
        while ($tries < 3) {
            try {
                return json_decode(
                    $this->do($type, $service, $url, $payload, $refresh, contentType: $contentType)->getBody()
                    ->getContents(),
                    true
                );
            } catch (UnauthorisedException $e) {
                $tries++;
                // Force the next try to get a fresh token
                $refresh = true;
                $this->client->logger->debug('UnauthorisedException: '.$e->getMessage());
                $this->client->logger->debug('Retrying request ('.$tries.')');
            }
        }
        throw new ServiceClientException('Failed to get a valid token after 3 attempts');
    }

    /**
     * @throws GuzzleException
     * @throws ServiceClientException
     * @throws JsonException|UnauthorisedException
     */
    public function do(
        string $type,
        string $service,
        string $url,
        $payload = null,
        $force = false,
        $contentType = null
    ): ResponseInterface {
        $client = new Client();
        // Get a valid token, either via the cache or by asking for a new one
        $token = $this->client->getToken($service, $force);
        $headers = ['Authorization' => 'Bearer '.$token];

        if ($contentType) {
            $headers['Content-Type'] = $contentType;
        }

        $options = [
            'headers' => $headers,
        ];

        // If the payload is JSON then use the `json` key, otherwise use the `body` key
        if (!is_null($payload)) {
            if (is_object($payload)) {
                $options['json'] = $payload;
            } else {
                $options['body'] = $payload;
            }
        }

        try {
            // Try and make the original request
            return $client->request(strtoupper($type), $url, $options);
        } catch (ClientException $e) {
            // If we fail with a 401 then our token has likely expired and we need a new one, so repeat this request with
            // a force refresh
            if ($e->getCode() === 401) {
                throw new UnauthorisedException;
            } // If we get a 404 then return null
            else {
                if ($e->getCode() === 404) {
                    // Create a ResponseInterface with a 404 status code and a null body
                    return new Response(404, [], null);
                } else {
                    // If we have a different error then throw it up the stack
                    throw new ServiceClientException(
                        'Guzzle HTTP request failed ('.$e->getCode().'): '.$e->getMessage(), $e->getCode()
                    );
                }
            }
        } catch (ServerException $e) {
            // If we fail with a server error then throw it up the stack
            throw new ServiceClientException(
                'Guzzle HTTP request failed ('.$e->getCode().'): '.$e->getMessage(), $e->getCode()
            );
        }
    }
}
