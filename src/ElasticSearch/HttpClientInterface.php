<?php

declare(strict_types=1);

namespace App\ElasticSearch;

interface HttpClientInterface
{
    /**
     * @param array<string, mixed> $body
     */
    public function request(string $method, string $url, array $body = []): string;
}