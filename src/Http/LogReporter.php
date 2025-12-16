<?php

namespace Xetreon\LaravelLog\Http;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class LogReporter
{
    protected Client $client;
    protected string $endpoint;
    protected bool $async;

    public function __construct(array $config)
    {
        $this->endpoint = "https://api.logtrail.site/api/v1/log/ingest";
        $this->async    = $config['async'] ?? config('logtrail.async');
        $this->client   = new Client(['timeout' => 2]);
    }

    /**
     * @throws GuzzleException
     */
    public function send(array $payload, string $authorization): void
    {
        $payloadJson = json_encode($payload); // Convert array to JSON

        $compressedPayload = gzencode($payloadJson, 5); // Compress the JSON
        $options = [
            'headers' => [
                'Authorization'      => 'Bearer '.$authorization,
                'X-Signature'        => $payload['signature'],
                'Accept'        => 'application/json',
                'Content-Encoding' => 'gzip',
                'Content-Type'     => 'application/json',
            ],
            'body' => $compressedPayload,
        ];

        if ($this->async) {
            $promise = $this->client->postAsync($this->endpoint, $options);
            $promise->then(
                function ($response) {
                    echo $response->getStatusCode();
                    echo $response->getBody()->getContents();
                },
                function ($e) {
                    echo "Error: " . $e->getMessage();
                }
            )->wait();
        } else {
            $response = $this->client->post($this->endpoint, $options);
        }
    }
}