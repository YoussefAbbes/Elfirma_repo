<?php

namespace App\Service;

class FaceIdClient
{
    private string $host;
    private int $port;
    private string $pythonBin;
    private string $projectDir;
    private float $threshold;
    private string $apiToken;

    public function __construct(
        string $projectDir,
        string $pythonBin,
        string $host,
        int $port,
        float $threshold,
        string $apiToken = ''
    ) {
        $this->projectDir = $projectDir;
        $this->pythonBin = $pythonBin;
        $this->host = $host;
        $this->port = $port;
        $this->threshold = $threshold;
        $this->apiToken = $apiToken;
    }

    public function detect(string $imageBase64): array
    {
        return $this->request('detect', ['image' => $imageBase64]);
    }

    public function recognize(string $imageBase64): array
    {
        return $this->request('recognize', ['image' => $imageBase64]);
    }

    public function enroll(array $payload): array
    {
        return $this->request('enroll', $payload);
    }

    private function request(string $endpoint, array $data): array
    {
        try {
            $url = "http://{$this->host}:{$this->port}/{$endpoint}";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $headers = ['Content-Type: application/json'];
            if ($this->apiToken !== '') {
                $headers[] = 'X-Face-Id-Token: ' . $this->apiToken;
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                return ['ok' => false, 'error' => "Connection failed: {$curlError}. Make sure the Face ID service is running."];
            }

            if ($httpCode !== 200) {
                return ['ok' => false, 'error' => "Service returned HTTP {$httpCode}. Please ensure the Face ID service is properly configured."];
            }

            $decoded = json_decode($response, true);
            return $decoded ?? ['ok' => false, 'error' => 'Invalid response from service'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => "Error: " . $e->getMessage()];
        }
    }
}