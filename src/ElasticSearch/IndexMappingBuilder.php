<?php

declare(strict_types=1);

namespace App\ElasticSearch;

use RuntimeException;

/**
 * TODO: Generate the mapping dynamically from inferred column types
 *       instead of relying on a static JSON file, once TypeInferenceService
 *       covers enough types (DATE, BOOLEAN, etc.).
 * TODO: Support index aliases so zero-downtime reindexing is possible
 *       (create new index → reindex → swap alias → delete old).
 */
class IndexMappingBuilder
{
    public function __construct(
        private string $mappingDir
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function fromFile(string $mappingFile): array
    {
        $path = rtrim($this->mappingDir, '/') . '/' . $mappingFile;

        if (!file_exists($path)) {
            throw new RuntimeException(sprintf('Mapping file not found: %s', $path));
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException(sprintf('Could not read mapping file: %s', $path));
        }

        /** @var array<string, mixed>|null $mapping */
        $mapping = json_decode($content, associative: true);

        if ($mapping === null) {
            throw new RuntimeException(sprintf('Invalid JSON in mapping file: %s', $path));
        }

        return $mapping;
    }
}