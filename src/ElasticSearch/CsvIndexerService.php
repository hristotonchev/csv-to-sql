<?php

declare(strict_types=1);

namespace App\ElasticSearch;

use RuntimeException;

/**
 * Indexes CSV rows into ElasticSearch via the REST API.
 *
 * TODO: Switch from single-doc indexing to the _bulk API for 100k+ row files.
 *       Bulk indexing in batches of 500-1000 documents cuts HTTP overhead dramatically.
 * TODO: Add retry logic with exponential backoff for transient ES failures.
 * TODO: Emit index events via Symfony Messenger so the SQL write and ES index
 *       happen in the same logical transaction (outbox pattern).
 * TODO: Add a --dry-run flag that validates documents against the mapping without indexing.
 */
class CsvIndexerService
{
    public function __construct(
        private readonly string $elasticsearchUrl,
        private readonly HttpClientInterface $httpClient,
    ) {}

    /**
     * Creates or updates the index with the given mapping.
     *
     * @param array<string, mixed> $mapping
     */
    public function createIndex(string $indexName, array $mapping): void
    {
        $this->request('PUT', $indexName, $mapping);
    }

    /**
     * @param array<int, array<string, string>> $rows
     */
    public function indexRows(string $indexName, array $rows): void
    {
        foreach ($rows as $id => $row) {
            $document = $this->normalizeRow($row, $id + 1);
            $this->request('PUT', "{$indexName}/_doc/{$document['id']}", $document);
        }
    }

    public function search(string $indexName, string $query, int $from = 0, int $size = 10): string
    {
        $body = [
            'from' => $from,
            'size' => $size,
            'query' => [
                'multi_match' => [
                    'query'     => $query,
                    'fields'    => ['name^2', 'grade'],
                    'fuzziness' => 'AUTO',
                ],
            ],
            // TODO: Expose sort field and direction as parameters.
            'sort' => [
                ['salary' => ['order' => 'desc']],
            ],
        ];

        return $this->request('GET', "{$indexName}/_search", $body);
    }

    /**
     * @param array<string, string> $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row, int $id): array
    {
        $normalized = ['id' => $id, 'indexed_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339)];

        foreach ($row as $key => $value) {
            $normalized[strtolower($key)] = $this->castValue($value);
        }

        return $normalized;
    }

    private function castValue(string $value): int|float|string
    {
        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }

        if (preg_match('/^-?\d+\.\d+$/', $value)) {
            return (float) $value;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(string $method, string $endpoint, array $body = []): string
    {
        $url = rtrim($this->elasticsearchUrl, '/') . '/' . ltrim($endpoint, '/');

        return $this->httpClient->request($method, $url, $body);
    }

}