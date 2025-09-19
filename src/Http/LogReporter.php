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
        $this->endpoint = "https://webhook-test.com/49b976a98e30538f4e5bda7764cee7fd";
        $this->async    = $config['async'] ?? config('logtrail.async');
        $this->client   = new Client(['timeout' => 2]);
    }

    /**
     * @throws GuzzleException
     */
    public function send(array $payload, string $authorization): void
    {
        $options = [
            'headers' => [
                'Authorization'      => 'Bearer '.$authorization,
                'X-Signature'        => $payload['signature'],
                'Accept'             => 'application/json',
            ],
            'json' => $payload,
        ];

        if ($this->async) {
            $this->client->postAsync($this->endpoint, $options);
        } else {
            $this->client->post($this->endpoint, $options);
        }
    }
}
