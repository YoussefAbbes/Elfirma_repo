<?php

namespace App\Service;


use Symfony\Contracts\HttpClient\HttpClientInterface;

class AIPredictionService
{
    /** Default equipment-AI base URL; override via EQUIPMENT_AI_BASE_URL in any environment. */
    private const DEFAULT_BASE_URL = 'http://127.0.0.1:8001';

    private $client;
    private string $baseUrl;

    public function __construct(HttpClientInterface $client, string $baseUrl = '')
    {
        $this->client = $client;
        $this->baseUrl = rtrim($baseUrl !== '' ? $baseUrl : self::DEFAULT_BASE_URL, '/');
    }

    public function predict($data)
    {
        $response = $this->client->request('POST', $this->baseUrl . '/full-analysis', [
            'json' => $data
        ]);

        return $response->toArray();
    }
}