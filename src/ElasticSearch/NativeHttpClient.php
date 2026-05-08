<?php

declare(strict_types=1);

namespace App\ElasticSearch;

use RuntimeException;

class NativeHttpClient implements HttpClientInterface
{
    /**
     * @param array<string, mixed> $body
     */
    public function request(string $method, string $url, array $body = []): string
    {
        $json = json_encode($body);

        if ($json === false) {
            throw new RuntimeException('Failed to encode request body.');
        }

        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => "Content-Type: application/json\r\nContent-Length: " . strlen($json),
                'content'       => $json,
                'ignore_errors' => true,
            ],
        ]);

        $response = file_get_contents($url, false, $context);

        if ($response === false) {
            throw new RuntimeException(sprintf('ElasticSearch request failed: %s %s', $method, $url));
        }

        return $response;
    }
}