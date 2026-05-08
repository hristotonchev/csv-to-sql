<?php

declare(strict_types=1);

namespace App\Tests\Unit\ElasticSearch;

use App\ElasticSearch\IndexMappingBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class IndexMappingBuilderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/es_mapping_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*') ?: []);
        rmdir($this->tempDir);
    }

    public function testLoadsMappingFromFile(): void
    {
        file_put_contents($this->tempDir . '/test.json', json_encode(['mappings' => ['properties' => []]]));

        $result = (new IndexMappingBuilder($this->tempDir))->fromFile('test.json');

        $this->assertArrayHasKey('mappings', $result);
    }

    public function testThrowsWhenFileNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Mapping file not found/');

        (new IndexMappingBuilder($this->tempDir))->fromFile('missing.json');
    }

    public function testThrowsOnInvalidJson(): void
    {
        file_put_contents($this->tempDir . '/bad.json', 'not-json');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid JSON/');

        (new IndexMappingBuilder($this->tempDir))->fromFile('bad.json');
    }
}