<?php

namespace App\Service;


use Symfony\Contracts\HttpClient\HttpClientInterface;

class AIPredictionService
{
    /** Default equipment-AI base URL; override via EQUIPMENT_AI_BASE_URL in any environment. */
    private const DEFAULT_BASE_URL = 'http://127.0.0.1:8001';

    private $client;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
    }

    public function predict($data)
    {
        $base = rtrim(
            (string) ($_ENV['EQUIPMENT_AI_BASE_URL'] ?? self::DEFAULT_BASE_URL),
            '/',
        );

        $response = $this->client->request('POST', $base . '/full-analysis', [
            'json' => $data
        ]);

        return $response->toArray();
    }
}