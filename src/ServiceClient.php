<?php
/*
 *  Factory+ / AMRC Connectivity Stack (ACS)
 *  Copyright 2023 AMRC
 */

namespace AMRCFactoryPlus;

use AMRCFactoryPlus\Exceptions\ServiceClientException;
use DateTime;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use League\Uri\Uri;
use Throwable;

class ServiceClient
{
    private $cache;
    public $logger;

    private ConfigDB $configdb;
    private ClusterManager $clusterManager;
    private Discovery $discovery;
    private HTTP $http;
    public string $baseUrl;
    public string $realm;
    public string $principal = 'sv1manager';
    public string $scheme = 'https';
    public string $keytabPath;

    public function __construct()
    {
        $this->configdb = new ConfigDB($this);
        $this->clusterManager = new ClusterManager($this);
        $this->discovery = new Discovery($this);
        $this->http = new HTTP($this);
    }

    public function getToken($service, $forceRefresh = false)
    {
        if (!extension_loaded('krb5')) {
            exit('KRB5 Extension not installed. Please install the KRB5 PHP extension to use this package.');
        }

        // Get the current TGT or ask for a new one
        $ccache = new \KRB5CCache;
        $ccache->initKeytab($this->principal . '@' . $this->realm, $this->keytabPath);

        // If the cache doesn't have a krb_token_<$service>_service then get one
        if (!$this->cache->has('krb_token_' . $service . '_service') || $forceRefresh) {
            $clientContext = (new \GSSAPIContext);
            $token = null;

            try {
                $clientContext->acquireCredentials($ccache);
            } catch (Throwable $e) {
                $this->logger->error($e->getMessage());
                throw new ServiceClientException($e->getMessage());
            }

            $targetService = 'HTTP/' . $service . '.' . $this->baseUrl . '@' . $this->realm;

            $clientContext->initSecContext(
                $targetService,
                null,
                0,
                null,
                $token
            );

            $base = $this->getDiscovery()->serviceUrl($service);

            $url = Uri::createFromBaseUri('/token', $base);

            $client = (new Client());

            // Make the HTTP POST request
            try {
                $response = $client->post($url->toString(), [
                    'headers' => [
                        'Authorization' => 'Negotiate ' . base64_encode($token),
                    ],
                ]);

                // Decode the response
                $returnToken = json_decode($response->getBody(), false, 512, JSON_THROW_ON_ERROR);
            } catch (GuzzleException $e) {
                // Handle or log the exception
                throw new Exception('Guzzle HTTP request failed: ' . $e->getMessage());
            }

            // Assuming $returnToken->expiry is in milliseconds
            $expiryTimestampInSeconds = (int)($returnToken->expiry / 1000);

            // Create a DateTime object from the UNIX timestamp (in seconds)
            $expiryDateTime = (new DateTime())->setTimestamp($expiryTimestampInSeconds);


            // Add to cache under krb_token_$service_service with lifetime
            $this->cache->put(
                'krb_token_' . $service . '_service',
                $returnToken->token,
                $expiryDateTime
            );
        }

        // Return token
        return $this->cache->get('krb_token_' . $service . '_service');
    }

    public function getConfigDB(): ConfigDB
    {
        return $this->configdb;
    }

    public function getClusterManager(): ClusterManager
    {
        return $this->clusterManager;
    }

    public function getDiscovery(): Discovery
    {
        return $this->discovery;
    }

    public function getHTTP(): HTTP
    {
        return $this->http;
    }

    public function setBaseUrl(string $baseUrl): ServiceClient
    {
        $this->baseUrl = $baseUrl;
        return $this;
    }

    public function setLogger($logger): ServiceClient
    {
        $this->logger = $logger;
        return $this;
    }

    public function setRealm(string $realm): ServiceClient
    {
        $this->realm = $realm;
        return $this;
    }

    public function setPrincipal(string $principal): ServiceClient
    {
        $this->principal = $principal;
        return $this;
    }

    /**
     * @param mixed $cache
     * @return ServiceClient
     */
    public function setCache($cache): ServiceClient
    {
        $this->cache = $cache;
        return $this;
    }

    public function setScheme(string $scheme): ServiceClient
    {
        $this->scheme = $scheme;
        return $this;
    }

    public function setKeytabPath(string $keytabPath): ServiceClient
    {
        $this->keytabPath = $keytabPath;
        return $this;
    }
}
